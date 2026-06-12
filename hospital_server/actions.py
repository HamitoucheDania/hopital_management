from database import get_connection
from logs import log_error, log_info, log_warn
import datetime
from decimal import Decimal
import re
import hashlib  


def safe_json(obj):
    """Sérialisation JSON sécurisée pour tous les types de données"""
    if isinstance(obj, (datetime.date, datetime.datetime)):
        return obj.isoformat()
    if isinstance(obj, Decimal):
        return float(obj)
    if isinstance(obj, bytes):
        return obj.decode('utf-8', errors='ignore')
    return str(obj)

def validate_nss(nss):
    """Validation stricte du Numéro de Sécurité Sociale"""
    if not isinstance(nss, str):
        return False
    # Format: 15 chiffres exactement
    return bool(re.fullmatch(r"\d{15}", nss))

#Fonction de validation des données patient Empêche les injections SQL en forçant les types corrects
def validate_patient_data(data):
    """Validation stricte des données patient"""
    errors = []
    
    # Validation nom
    if 'nom' in data:
        nom = data['nom']
        if not isinstance(nom, str):
            errors.append("Nom doit être une chaîne de caractères")
        elif len(nom) > 80:
            errors.append("Nom trop long (max 80 caractères)")
        elif not nom.strip():
            errors.append("Nom ne peut pas être vide")
    
    # Validation prénom
    if 'prenom' in data:
        prenom = data['prenom']
        if not isinstance(prenom, str):
            errors.append("Prénom doit être une chaîne de caractères")
        elif len(prenom) > 80:
            errors.append("Prénom trop long (max 80 caractères)")
        elif not prenom.strip():
            errors.append("Prénom ne peut pas être vide")
    
    # Validation date de naissance
    if 'date_naissance' in data:
        try:
            datetime.datetime.strptime(data['date_naissance'], '%Y-%m-%d')
            # Validation que la date n'est pas dans le futur
            birth_date = datetime.datetime.strptime(data['date_naissance'], '%Y-%m-%d').date()
            if birth_date > datetime.datetime.now().date():
                errors.append("Date de naissance ne peut pas être dans le futur")
        except ValueError:
            errors.append("Format date invalide (YYYY-MM-DD attendu)")
    
    # Validation sexe
    if 'sexe' in data:
        sexe = data['sexe']
        if sexe not in ['M', 'F']:
            errors.append("Sexe doit être 'M' ou 'F'")
    
    # Validation email 
    if 'email' in data and data['email']:
        email = data['email']
        if not isinstance(email, str):
            errors.append("Email doit être une chaîne de caractères")
        elif len(email) > 255:
            errors.append("Email trop long (max 255 caractères)")
        elif '@' not in email:
            errors.append("Format email invalide")
    
    return len(errors) == 0, errors


def handle_action(data):
    """Routeur principal avec traçabilité et gestion d'erreurs"""
    action = data.get("action")
    session_id = data.get("session_id", "N/A")
    
    log_info(f" Traitement action '{action}' (session: {session_id})")
    
  
    routes = {
        "verif_nss": verif_nss,
        "verif_droits": verif_droits,
        "get_infos": get_infos,
        "create_patient": create_patient,

        # --- REQUÊTES MÉTIER (Questions 4-10 Dans le rapport) ---
        "historique_actes": historique_actes,
        "traitements_en_cours": traitements_en_cours,
        "factures_en_attente": factures_en_attente,
        "chiffre_affaires_service": chiffre_affaires_service,
        "admissions_aujourdhui": admissions_aujourdhui,
        "medecins_urgence": medecins_urgence,
        "cartes_expiration": cartes_expiration,

        # === ACTIONS SPÉCIALES ===
        "health_check": health_check,
        "invalid_json": handle_invalid_json,
    }

    func = routes.get(action)
    if func:
        try:
            result = func(data)
            log_info(f" Action '{action}' traitée avec succès")
            return result
        except Exception as e:
            log_error(f" Erreur exécution action '{action}': {e}")
            return {
                "status": "error", 
                "code": "ACTION_EXECUTION_ERROR", 
                "message": f"Erreur lors du traitement de l'action '{action}'"
            }
    
    log_warn(f" Action inconnue '{action}' reçue")
    return {
        "status": "error", 
        "code": "UNKNOWN_ACTION", 
        "message": f"Action '{action}' non reconnue par le serveur"
    }


# JSON invalide
def handle_invalid_json(data):
    """Gère les requêtes JSON invalides"""
    return {
        "status": "error",
        "code": "INVALID_JSON", 
        "message": "Format JSON invalide ou incomplet"
    }



def verif_nss(data):
    """Vérifie uniquement si le patient existe """
    nss = data.get("nss")

    if not nss:
        return {
            "status": "error",
            "code": "MISSING_NSS",
            "message": "Numéro de Sécurité Sociale manquant"
        }

    if not validate_nss(nss):
        return {
            "status": "error",
            "code": "INVALID_NSS_FORMAT",
            "message": "Format NSS invalide (15 chiffres requis)"
        }

    conn = get_connection()
    if not conn:
        return {
            "status": "error",
            "code": "DB_UNAVAILABLE",
            "message": "Base de données temporairement indisponible"
        }

    try:
        cur = conn.cursor()

        # Vérification de l'existence du patient uniquement
        cur.execute("""
            SELECT patient_id, nom, prenom
            FROM PATIENT
            WHERE nss = %s
        """, (nss,))

        row = cur.fetchone()

        if not row:
            log_info(f" Patient non trouvé - NSS: {nss}")
            return {
                "status": "patient_not_found",
                "code": "PATIENT_NOT_FOUND",
                "message": "Aucun patient trouvé avec ce NSS"
            }

        patient_id, nom, prenom = row

        log_info(f" Patient trouvé - ID: {patient_id}, Nom: {nom} {prenom}")

        return {
            "status": "nss_ok",
            "patient_id": patient_id,
            "nom": nom,
            "prenom": prenom,
            "message": f"Patient {prenom} {nom} trouvé avec succès"
        }

    except Exception as e:
        log_error(f" Erreur vérification NSS: {e}")
        return {
            "status": "error",
            "code": "DB_ERROR",
            "message": f"Erreur lors de la vérification du NSS: {str(e)}"
        }
    finally:
        conn.close()



# Vérification des droits 
def verif_droits(data):
    patient_id = data.get("patient_id")
    
    if not patient_id:
        return {
            "status": "error",
            "code": "MISSING_PATIENT_ID", 
            "message": "ID patient manquant"
        }

    conn = get_connection()
    if not conn:
        return {
            "status": "error", 
            "code": "DB_UNAVAILABLE", 
            "message": "Base de données indisponible"
        }

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT p.droits_actifs, p.nom, p.prenom,
                   cv.statut as statut_carte,
                   cv.date_expiration
            FROM PATIENT p
            LEFT JOIN CARTE_VITALE cv ON p.patient_id = cv.patient_id
            WHERE p.patient_id = %s
        """, (patient_id,))
        
        row = cur.fetchone()
        
        if not row:
            return {
                "status": "error",
                "code": "PATIENT_NOT_FOUND", 
                "message": "Patient non trouvé"
            }
        
        droits_actifs, nom, prenom, statut_carte, date_expiration = row
        
        # Vérification  des droits
        if not droits_actifs:
            return {
                "status": "error", 
                "code": "DROITS_EXPIRES", 
                "message": "Droits expirés pour ce patient",
                "nom": nom,
                "prenom": prenom
            }
            
        # Vérification  de la carte
        if statut_carte == 'BLOQUEE':
            return {
                "status": "error",
                "code": "CARTE_BLOQUEE",
                "message": "Carte vitale bloquée",
                "nom": nom,
                "prenom": prenom
            }
            
        if date_expiration and date_expiration < datetime.datetime.now().date():
            return {
                "status": "error",
                "code": "CARTE_EXPIREE", 
                "message": "Carte vitale expirée",
                "nom": nom,
                "prenom": prenom,
                "date_expiration": date_expiration.isoformat()
            }
        
        return {
            "status": "droits_actifs", 
            "message": "Droits en cours de validité",
            "nom": nom,
            "prenom": prenom,
            "carte_active": statut_carte == 'ACTIVE',
            "date_expiration": date_expiration.isoformat() if date_expiration else None
        }
            
    except Exception as e:
        log_error(f" Erreur vérification droits: {e}")
        return {
            "status": "error", 
            "code": "DB_ERROR", 
            "message": "Erreur lors de la vérification des droits"
        }
    finally:
        conn.close()



def get_infos(data):
    patient_id = data.get("patient_id")
    
    if not patient_id:
        return {
            "status": "error",
            "code": "MISSING_PATIENT_ID", 
            "message": "ID patient manquant"
        }

    conn = get_connection()
    if not conn:
        return {
            "status": "error", 
            "code": "DB_UNAVAILABLE", 
            "message": "Base de données indisponible"
        }

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT p.*, cv.numero_carte, cv.date_expiration, cv.statut
            FROM PATIENT p
            LEFT JOIN CARTE_VITALE cv ON p.patient_id = cv.patient_id
            WHERE p.patient_id = %s
        """, (patient_id,))
        
        row = cur.fetchone()
        if not row:
            return {
                "status": "error",
                "code": "PATIENT_NOT_FOUND", 
                "message": "Patient non trouvé"
            }
        
        columns = [desc[0] for desc in cur.description]
        patient_data = {}
        
        for col_name, value in zip(columns, row):
            patient_data[col_name] = safe_json(value)
        
        log_info(f" Dossier patient {patient_id} récupéré avec succès")
        
        return {
            "status": "success", 
            "patient": patient_data
        }
        
    except Exception as e:
        log_error(f" Erreur récupération infos patient: {e}")
        return {
            "status": "error", 
            "code": "DB_ERROR", 
            "message": "Erreur lors de la récupération des informations patient"
        }
    finally:
        conn.close()



def create_patient(data):
    required_fields = ["nss", "nom", "prenom", "date_naissance", "sexe","password"]
    
    for field in required_fields:
        if field not in data:
            return {
                "status": "error",
                "code": "MISSING_FIELD", 
                "message": f"Champ obligatoire manquant : {field}"
            }
    
    #   Validation des données patient
    is_valid, errors = validate_patient_data(data)
    if not is_valid:
        return {
            "status": "error",
            "code": "INVALID_PATIENT_DATA",
            "message": "Données patient invalides",
            "validation_errors": errors
        }
    
    if not validate_nss(data["nss"]):
        return {
            "status": "error", 
            "code": "INVALID_NSS_FORMAT", 
            "message": "Format NSS invalide (15 chiffres requis)"
        }

    conn = get_connection()
    if not conn:
        return {
            "status": "error", 
            "code": "DB_UNAVAILABLE", 
            "message": "Base de données indisponible"
        }

    try:
        cur = conn.cursor()
        
        cur.execute("SELECT patient_id FROM PATIENT WHERE nss = %s", (data["nss"],))
        existing = cur.fetchone()
        if existing:
            return {
                "status": "error", 
                "code": "PATIENT_EXISTS", 
                "message": f"Patient existe déjà (ID: {existing[0]})"
            }
        
        password_hash = hashlib.sha256(data["password"].encode()).hexdigest()
        
        cur.execute("""
            INSERT INTO PATIENT (nss, nom, prenom, date_naissance, sexe, droits_actifs, password,email)
            VALUES (%s, %s, %s, %s, %s, TRUE, %s ,%s) 
            RETURNING patient_id
        """, (
            data["nss"], 
            data["nom"], 
            data["prenom"], 
            data["date_naissance"], 
            data["sexe"],
            password_hash,
            data.get("email", "") 
        ))
        
        new_patient_id = cur.fetchone()[0]
        conn.commit()
        
        log_info(f" NOUVEAU PATIENT CRÉÉ: ID {new_patient_id}, NSS {data['nss']}")
        
        return {
            "status": "patient_created",
            "patient_id": new_patient_id,
            "message": "Patient créé avec succès"
        }
        
    except Exception as e:
        error_msg = str(e)
        log_error(f" ERREUR CRÉATION PATIENT: {error_msg}")
        
        return {
            "status": "error", 
            "code": "DB_ERROR", 
            "message": f"Erreur création patient: {error_msg}"
        }
    finally:
        if conn:
            conn.close()


def creer_patient(data):
    return create_patient(data)



#QUESTIONS MÉTIER (7 questions)

def historique_actes(data):
    patient_id = data.get("patient_id")
    sejour_id = data.get("sejour_id")
    
    if not patient_id or not sejour_id:
        return {
            "status": "error",
            "code": "MISSING_IDS",
            "message": "ID patient ou ID séjour manquant"
        }

    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT am.acte_id, am.code_ccam, am.date_acte, p.nom, p.prenom, s.motif
            FROM ACTE_MEDICAL am
            JOIN SEJOUR s ON am.sejour_id = s.sejour_id
            JOIN PATIENT p ON s.patient_id = p.patient_id
            WHERE p.patient_id = %s AND s.sejour_id = %s
            ORDER BY am.date_acte DESC
        """, (patient_id, sejour_id))
        
        rows = cur.fetchall()
        columns = [desc[0] for desc in cur.description]
        
        actes = []
        for row in rows:
            acte_data = {}
            for col_name, value in zip(columns, row):
                acte_data[col_name] = safe_json(value)
            actes.append(acte_data)
        
        return {
            "status": "success",
            "count": len(actes),
            "actes": actes
        }
        
    except Exception as e:
        log_error(f" Erreur récupération historique actes: {e}")
        return {"status": "error", "message": "Erreur lors de la récupération de l'historique des actes"}
    finally:
        conn.close()

def traitements_en_cours(data):
    patient_id = data.get("patient_id")
    
    if not patient_id:
        return {
            "status": "error",
            "code": "MISSING_PATIENT_ID",
            "message": "ID patient manquant"
        }

    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT t.traitement_id, t.nom_medicament, t.dosage, t.date_debut, t.date_fin,
                   p.nom, p.prenom
            FROM TRAITEMENT t
            JOIN PATIENT p ON t.patient_id = p.patient_id
            WHERE p.patient_id = %s AND (t.date_fin IS NULL OR t.date_fin > CURRENT_DATE)
            ORDER BY t.date_debut DESC
        """, (patient_id,))
        
        rows = cur.fetchall()
        columns = [desc[0] for desc in cur.description]
        
        traitements = []
        for row in rows:
            traitement_data = {}
            for col_name, value in zip(columns, row):
                traitement_data[col_name] = safe_json(value)
            traitements.append(traitement_data)
        
        return {
            "status": "success",
            "count": len(traitements),
            "traitements": traitements
        }
        
    except Exception as e:
        log_error(f" Erreur récupération traitements: {e}")
        return {"status": "error", "message": "Erreur lors de la récupération des traitements"}
    finally:
        conn.close()

def factures_en_attente(data):
    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT f.facture_id, f.montant_total, f.date_emission, f.statut,
                   p.nom, p.prenom, s.motif, s.sejour_id
            FROM FACTURE f
            JOIN SEJOUR s ON f.sejour_id = s.sejour_id
            JOIN PATIENT p ON s.patient_id = p.patient_id
            WHERE f.statut = 'EN_ATTENTE'
            ORDER BY f.date_emission DESC
        """)
        
        rows = cur.fetchall()
        columns = [desc[0] for desc in cur.description]
        
        factures = []
        for row in rows:
            facture_data = {}
            for col_name, value in zip(columns, row):
                facture_data[col_name] = safe_json(value)
            factures.append(facture_data)
        
        return {
            "status": "success",
            "count": len(factures),
            "factures": factures
        }
        
    except Exception as e:
        log_error(f" Erreur récupération factures en attente: {e}")
        return {"status": "error", "message": "Erreur lors de la récupération des factures en attente"}
    finally:
        conn.close()

def chiffre_affaires_service(data):
    mois = data.get("mois", datetime.datetime.now().month)
    annee = data.get("annee", datetime.datetime.now().year)
    
    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT s.libelle as service, SUM(f.montant_total) as chiffre_affaires
            FROM FACTURE f
            JOIN SEJOUR se ON f.sejour_id = se.sejour_id
            JOIN SERVICE s ON se.service_id = s.service_id
            WHERE EXTRACT(MONTH FROM f.date_emission) = %s 
              AND EXTRACT(YEAR FROM f.date_emission) = %s
            GROUP BY s.service_id, s.libelle
            ORDER BY chiffre_affaires DESC
        """, (mois, annee))
        
        rows = cur.fetchall()
        
        ca_services = []
        total_ca = 0
        
        for row in rows:
            service, ca = row
            total_ca += float(ca) if ca else 0
            ca_services.append({
                "service": service,
                "chiffre_affaires": float(ca) if ca else 0.0
            })
        
        return {
            "status": "success",
            "mois": mois,
            "annee": annee,
            "total_chiffre_affaires": total_ca,
            "services": ca_services
        }
        
    except Exception as e:
        log_error(f" Erreur calcul chiffre d'affaires: {e}")
        return {"status": "error", "message": "Erreur lors du calcul du chiffre d'affaires par service"}
    finally:
        conn.close()

def admissions_aujourdhui(data):
    date_specifique = data.get("date")
    
    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        
        if date_specifique:
            cur.execute("SELECT COUNT(*) as admissions_count FROM SESSION WHERE DATE(date_passage) = %s", (date_specifique,))
        else:
            cur.execute("SELECT COUNT(*) as admissions_count FROM SESSION WHERE DATE(date_passage) = CURRENT_DATE")
        
        count = cur.fetchone()[0]
        
        return {
            "status": "success",
            "date": date_specifique or datetime.date.today().isoformat(),
            "admissions_count": count
        }
        
    except Exception as e:
        log_error(f" Erreur récupération admissions: {e}")
        return {"status": "error", "message": "Erreur lors de la récupération des statistiques d'admission"}
    finally:
        conn.close()

def medecins_urgence(data):
    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        cur.execute("""
            SELECT p.personnel_id, p.nom, p.prenom, pm.categorie, pm.specialite
            FROM PERSONNEL p
            JOIN PERSONNEL_MEDICAL pm ON p.personnel_id = pm.personnel_med_id 
            JOIN SERVICE s ON p.service_id = s.service_id
            WHERE s.code = 'URG' AND p.type = 'MEDICAL'
            ORDER BY p.nom, p.prenom
        """)
        
        rows = cur.fetchall()
        columns = [desc[0] for desc in cur.description]
        
        medecins = []
        for row in rows:
            medecin_data = {}
            for col_name, value in zip(columns, row):
                medecin_data[col_name] = safe_json(value)
            medecins.append(medecin_data)
        
        return {
            "status": "success",
            "count": len(medecins),
            "medecins": medecins
        }
        
    except Exception as e:
        log_error(f" Erreur récupération médecins urgence: {e}")
        return {"status": "error", "message": "Erreur lors de la récupération des médecins d'urgence"}
    finally:
        conn.close()

def cartes_expiration(data):
    annee = data.get("annee", datetime.datetime.now().year)
    mois = data.get("mois")
    
    conn = get_connection()
    if not conn:
        return {"status": "error", "code": "DB_UNAVAILABLE"}

    try:
        cur = conn.cursor()
        
        if mois:
            cur.execute("""
                SELECT p.patient_id, p.nom, p.prenom, cv.numero_carte, cv.date_expiration
                FROM CARTE_VITALE cv
                JOIN PATIENT p ON cv.patient_id = p.patient_id
                WHERE cv.Statut = 'ACTIVE'
                  AND EXTRACT(YEAR FROM cv.date_expiration) = %s
                  AND EXTRACT(MONTH FROM cv.date_expiration) = %s
                ORDER BY cv.date_expiration
            """, (annee, mois))
        else:
            cur.execute("""
                SELECT p.patient_id, p.nom, p.prenom, cv.numero_carte, cv.date_expiration
                FROM CARTE_VITALE cv
                JOIN PATIENT p ON cv.patient_id = p.patient_id
                WHERE cv.Statut = 'ACTIVE'
                  AND EXTRACT(YEAR FROM cv.date_expiration) = %s
                ORDER BY cv.date_expiration
            """, (annee,))
        
        rows = cur.fetchall()
        columns = [desc[0] for desc in cur.description]
        
        cartes = []
        for row in rows:
            carte_data = {}
            for col_name, value in zip(columns, row):
                carte_data[col_name] = safe_json(value)
            cartes.append(carte_data)
        
        return {
            "status": "success",
            "annee": annee,
            "mois": mois,
            "count": len(cartes),
            "cartes": cartes
        }
        
    except Exception as e:
        log_error(f" Erreur récupération cartes expiration: {e}")
        return {"status": "error", "message": "Erreur lors de la récupération des cartes à renouveler"}
    finally:
        conn.close()

def health_check(data):
    conn = get_connection()
    db_status = "OK" if conn else "ERROR"
    
    patient_count = None
    if conn:
        try:
            cur = conn.cursor()
            cur.execute("SELECT COUNT(*) FROM PATIENT")
            patient_count = cur.fetchone()[0]
            conn.close()
        except Exception as e:
            patient_count = None
            db_status = f"ERROR: {e}"
    else:
        patient_count = None
    
    return {
        "status": "success",
        "server": "RUNNING",
        "database": db_status,
        "patients_in_database": patient_count,
        "timestamp": datetime.datetime.now().isoformat()
    }