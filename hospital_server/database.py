
import psycopg2  
from psycopg2.extras import RealDictCursor  # Pour récupérer les résultats sous forme de dictionnaires
from config import CONFIG  # Configuration de la base de données
from logs import log_error  # Système de logging pour les erreurs

def get_connection():
    """
    Établit une connexion à la base de données PostgreSQL
    
    Returns:
        connection or None: Objet de connexion PostgreSQL ou None en cas d'échec
    """
    try:
        # Création de la connexion avec les paramètres de configuration
        conn = psycopg2.connect(
            host=CONFIG["DB_HOST"],  # Hôte de la base de données
            port=CONFIG["DB_PORT"],  # Port PostgreSQL (par défaut 5432)
            database=CONFIG["DB_NAME"],  # Nom de la base de données
            user=CONFIG["DB_USER"],  # Nom d'utilisateur PostgreSQL
            password=CONFIG["DB_PASSWORD"],  # Mot de passe de l'utilisateur
            connect_timeout=CONFIG.get("DB_CONNECT_TIMEOUT", 5)  # Timeout de connexion en secondes
        )
        return conn  # Retourne l'objet connexion en cas de succès
    except Exception as e:
        # Log de l'erreur de connexion
        log_error(f"Erreur connexion BD: {e}")
        return None  # Retourne None en cas d'échec


def fetchone(query, params=()):
    """
    Exécute une requête SQL et retourne un seul résultat
    
    Args:
        query (str): Requête SQL à exécuter
        params (tuple): Paramètres pour la requête paramétrée
        
    Returns:
        tuple: (result, error) - Résultat de la requête et message d'erreur éventuel
    """
    # Obtention d'une connexion à la base
    conn = get_connection()
    if conn is None:
        # Retourne une erreur si la connexion a échoué
        return None, "DB_UNAVAILABLE"
    try:
        # Création d'un curseur pour exécuter la requête
        with conn.cursor() as cur:
            cur.execute(query, params)  # Exécution de la requête avec paramètres
            row = cur.fetchone()  # Récupération d'une seule ligne de résultat
            return row, None  # Retour du résultat sans erreur
    except Exception as e:
        # Log de l'erreur d'exécution
        log_error(f"Erreur DB fetchone: {e}")
        return None, str(e)  # Retour de l'erreur
    finally:
        # Fermeture garantie de la connexion dans tous les cas
        conn.close()

def fetchall(query, params=()):
    """
    Exécute une requête SQL et retourne tous les résultats
    
    Args:
        query (str): Requête SQL à exécuter
        params (tuple): Paramètres pour la requête paramétrée
        
    Returns:
        tuple: (results, error) - Liste de résultats et message d'erreur éventuel
    """
    # Obtention d'une connexion à la base
    conn = get_connection()
    if conn is None:
        # Retourne une erreur si la connexion a échoué
        return None, "DB_UNAVAILABLE"
    try:
        # Création d'un curseur pour exécuter la requête
        with conn.cursor() as cur:
            cur.execute(query, params)  # Exécution de la requête avec paramètres
            rows = cur.fetchall()  # Récupération de toutes les lignes de résultat
            
            # Récupération des noms de colonnes pour créer des dictionnaires
            desc = [d[0] for d in cur.description] if cur.description else []
            
            # Conversion des résultats en liste de dictionnaires
            # Chaque ligne devient un dict {nom_colonne: valeur}
            result = [dict(zip(desc, r)) for r in rows]
            return result, None  # Retour des résultats sans erreur
    except Exception as e:
        # Log de l'erreur d'exécution
        log_error(f"Erreur DB fetchall: {e}")
        return None, str(e)  # Retour de l'erreur
    finally:
        # Fermeture garantie de la connexion dans tous les cas
        conn.close()

def execute_returning(query, params=()):
    """
    Exécute une requête SQL avec RETURNING (INSERT/UPDATE) et retourne le résultat
    
    Args:
        query (str): Requête SQL avec clause RETURNING
        params (tuple): Paramètres pour la requête paramétrée
        
    Returns:
        tuple: (result, error) - Résultat du RETURNING et message d'erreur éventuel
    """
    # Obtention d'une connexion à la base
    conn = get_connection()
    if conn is None:
        # Retourne une erreur si la connexion a échoué
        return None, "DB_UNAVAILABLE"
    try:
        # Création d'un curseur pour exécuter la requête
        with conn.cursor() as cur:
            cur.execute(query, params)  # Exécution de la requête avec paramètres
            row = cur.fetchone()  # Récupération du résultat du RETURNING
            conn.commit()  # Validation de la transaction
            return row, None  # Retour du résultat sans erreur
    except Exception as e:
        # Log de l'erreur d'exécution
        log_error(f"Erreur DB execute_returning: {e}")
        return None, str(e)  # Retour de l'erreur
    finally:
        # Fermeture garantie de la connexion dans tous les cas
        conn.close()