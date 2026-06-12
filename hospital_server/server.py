# Importation des modules nécessaires
import socket  # Pour la communication réseau TCP
import threading  # Pour gérer les connexions simultanées
import json  # Pour parser et formater les données JSON
import select  # Pour la gestion des sockets asynchrones
import time  # Pour les timeouts et mesures de performance
import sys  # Pour la gestion des arguments et arrêt du programme
from datetime import datetime  # Pour les horodatages
from config import CONFIG  # Configuration du serveur
from logs import log_info, log_warn, log_error  # Système de logging
from actions import handle_action  # Gestionnaire des actions métier

class HospitalServer:
    """Serveur TCP pour la gestion hospitalière avec protocole JSON """
    
    def __init__(self):
        """Initialisation du serveur avec état et statistiques"""
        self.running = False  # État du serveur (en cours d'exécution ou non)
        self.server_socket = None  # Socket principale d'écoute
        self.active_clients = {}  # Dictionnaire des clients connectés
        self.stats = {
            "connections_total": 0,  # Nombre total de connexions
            "requests_processed": 0,  # Nombre de requêtes traitées
            "errors_count": 0  # Nombre d'erreurs rencontrées
        }
        self.start_time = datetime.now()  # Heure de démarrage pour calculer l'uptime

    def _format_response(self, status, action=None, data=None, message=None, error_code=None):
        """
        Formate une réponse JSON standardisée pour le client
        
        Args:
            status: Statut de la réponse (success, error, etc.)
            action: Action qui a été traitée
            data: Données supplémentaires à inclure
            message: Message descriptif
            error_code: Code d'erreur en cas d'échec
            
        Returns:
            dict: Réponse formatée selon le protocole
        """
        # Structure de base de toutes les réponses
        response = {
            "status": status,
            "timestamp": datetime.now().isoformat(),  # Horodatage ISO
            "server_id": "HOSPITAL_SERVER_v1.0"  # Identifiant du serveur
        }

        
        if action:
            response["action"] = action  # Action traitée

        if data:
            response.update(data)  # Fusion des données supplémentaires

        if message:
            response["message"] = message  # Message descriptif

        if error_code:
            response["code"] = error_code  # Code d'erreur 

        return response

    def _handle_special_cases(self, raw_data):
        """
        Gère les cas spéciaux des clients netcat qui n'envoient pas du JSON pur
        
        Args:
            raw_data: Données brutes reçues du client
            
        Returns:
            dict or None: Données interprétées ou None si invalides
        """
        try:
            # Vérification de la taille maximale du payload
            if len(raw_data) > CONFIG["MAX_PAYLOAD_SIZE"]:
                log_warn(f"Payload trop volumineux rejeté: {len(raw_data)} bytes")
                return None

            # Décodage des données avec gestion d'erreur
            decoded = raw_data.decode('utf-8', errors='ignore').strip()

         
            if decoded.startswith(('GET ', 'POST ', 'PUT ', 'DELETE ', 'HEAD ')):
                log_warn(f"Requête HTTP rejetée: {decoded[:100]}")
                return {
                    "action": "health_check",
                    "message": "Protocol HTTP non supporté - Utilisez JSON",
                    "protocol_error": "HTTP_NOT_SUPPORTED"
                }

            # Tentative d'analyse JSON - MODIFICATION ICI
            if decoded.startswith('{'):
                try:
                    return json.loads(decoded)  # JSON valide
                except json.JSONDecodeError as e:
                    # JSON INCOMPLET - Rejet explicite
                    log_warn(f"JSON incomplet/invalide reçu: {decoded[:100]}")
                    return {
                        "action": "invalid_json",
                        "message": f"JSON invalide: {str(e)}",
                        "original_data": decoded,
                        "json_error": True
                    }

            
            if decoded and not decoded.isspace():
                # Troncature des messages trop longs pour les logs
                if len(decoded) > 500:
                    decoded = decoded[:500] + "...[TRONQUÉ]"
                log_info(f"Commande texte reçue: {decoded[:100]}")
                return {
                    "action": "health_check",
                    "message": f"Commande reçue: {decoded}",
                    "original_data": decoded
                }

        except Exception as e:
            log_error(f"Erreur traitement netcat: {e}")

        return None  # Aucune donnée valide trouvée

    def _validate_protocol(self, data):
        """
        Valide la conformité des données au protocole applicatif - VERSION RENFORCÉE
        """
        # Vérification du type de données
        if not isinstance(data, dict):
            log_warn("Données non-dictionnaire reçues - Rejet")
            return False, "INVALID_PROTOCOL"

        # Vérification de la taille des données
        data_size = len(str(data).encode('utf-8'))
        if data_size > CONFIG["MAX_PAYLOAD_SIZE"]:
            log_warn(f"Données trop volumineuses rejetées: {data_size} bytes")
            return False, "PAYLOAD_TOO_LARGE"

        #  Validation stricte des types de champs
        type_validations = {
            "patient_id": (int, str),
            "sejour_id": (int,),
            "nss": (str,),
            "nom": (str,),
            "prenom": (str,),
            "mois": (int,),
            "annee": (int,),
            "session_id": (str,)
        }
        
        for field, expected_types in type_validations.items():
            if field in data and not isinstance(data[field], expected_types):
                log_warn(f"Type invalide pour {field}: {type(data[field])} au lieu de {expected_types}")
                return False, f"INVALID_TYPE_{field.upper()}"

        #  Validation des longueurs de champs
        string_fields = ["nss", "nom", "prenom", "session_id"]
        for field in string_fields:
            if field in data and len(str(data[field])) > 255:
                log_warn(f"Champ {field} trop long: {len(str(data[field]))} caractères")
                return False, f"FIELD_TOO_LONG_{field.upper()}"

        # Liste  des actions autorisées 
        allowed_actions = {
            "verif_nss", "verif_droits", "create_patient", "get_infos", "health_check",
            "historique_actes", "traitements_en_cours", "factures_en_attente", 
            "chiffre_affaires_service", "admissions_aujourdhui", "medecins_urgence", 
            "cartes_expiration", "invalid_json"  
        }

        # Vérification de l'action demandée
        action = data.get("action")
        if action not in allowed_actions:
            log_warn(f"Action non autorisée reçue: {action}")
            return False, "UNKNOWN_ACTION"

        # Validation de chaque champ des données
        for key, value in data.items():
            # Vérification de la longueur des chaînes
            if isinstance(value, str) and len(value) > 1000:
                log_warn(f"Champ {key} trop long rejeté: {len(value)} caractères")
                return False, "FIELD_TOO_LONG"
            # Détection de données binaires suspectes
            if isinstance(value, (bytes, bytearray)):
                log_warn(f"Données binaires suspectes dans {key}")
                return False, "BINARY_DATA_NOT_ALLOWED"

        # Actions sans validation de champs spécifiques
        if action in ["health_check", "invalid_json"]: 
            return True, "OK"

        # Validation des champs obligatoires par action
        if action == "verif_nss" and not data.get("nss"):
            return False, "MISSING_NSS"

        if action in ["verif_droits", "get_infos"] and not data.get("patient_id"):
            return False, "MISSING_PATIENT_ID"

        if action in ["create_patient"] and not data.get("nss"):
            return False, "MISSING_NSS"

        return True, "OK"  # Toutes les validations passées

    def _recv_until_delimiter(self, conn, delimiters=[b'\n', b'\r\n']):
        """
        Réception des données jusqu'à un délimiteur avec gestion de timeout
        
        Args:
            conn: Socket client
            delimiters: Liste des délimiteurs de fin de message
            
        Returns:
            bytes or None: Données reçues ou None si connexion fermée
        """
        buffer = bytearray()  # Buffer de réception
        conn.settimeout(CONFIG["SOCKET_TIMEOUT"])  # Timeout de réception
        start_time = time.time()  # Début de la réception

        try:
            while True:
                # Vérification du timeout d'inactivité
                elapsed = time.time() - start_time
                if elapsed > CONFIG.get("MAX_CLIENT_INACTIVITY", 30):
                    log_warn(f"Client trop lent: {elapsed:.1f}s sans réponse")
                    raise socket.timeout(f"Client trop lent - {elapsed:.1f}s")
                
                
                chunk = conn.recv(256)
                if not chunk:  # Connexion fermée par le client
                    break

                buffer.extend(chunk)  # Ajout au buffer

                # Protection contre le débordement de buffer
                if len(buffer) > CONFIG["MAX_PAYLOAD_SIZE"]:
                    log_error(f"Débordement buffer: {len(buffer)} bytes")
                    raise ValueError("PAYLOAD_TOO_LARGE")

                # Recherche des délimiteurs de fin de message
                for delimiter in delimiters:
                    if delimiter in buffer:
                        delimiter_pos = buffer.find(delimiter)
                        request_time = time.time() - start_time
                        # Log des requêtes lentes
                        if request_time > 5:
                            log_warn(f"Client lent: {request_time:.2f}s pour requête")
                        return bytes(buffer[:delimiter_pos])  # Retour des données sans délimiteur

                
                if b'\r' in buffer:
                    delimiter_pos = buffer.find(b'\r')
                    return bytes(buffer[:delimiter_pos])

            return bytes(buffer) if buffer else None  # Retour des données restantes

        except socket.timeout:
            log_warn("Timeout réception - Client trop lent")
            raise
        except ConnectionResetError:
            log_warn("Client déconnecté brutalement")
            raise
        except Exception as e:
            log_error(f"Erreur réception: {e}")
            raise

    def _send_response(self, conn, response):
        """
        Envoie une réponse JSON au client avec gestion d'erreurs
        
        Args:
            conn: Socket client
            response: Données à envoyer
            
        Returns:
            bool: Succès de l'envoi
        """
        try:
            # Sérialisation JSON avec gestion des types spéciaux
            response_json = json.dumps(response, ensure_ascii=False, default=str)
            full_response = response_json.encode() + b"\n"  # Ajout du délimiteur de fin
            
            # Vérification de la taille de la réponse
            if len(full_response) > CONFIG["MAX_PAYLOAD_SIZE"]:
                log_error("Réponse trop volumineuse")
                # Création d'une réponse d'erreur alternative
                error_response = self._format_response(
                    "error", 
                    message="Erreur interne: réponse trop volumineuse",
                    error_code="RESPONSE_TOO_LARGE"
                )
                full_response = json.dumps(error_response).encode() + b"\n"
            
            
            conn.sendall(full_response)
            return True
            
        except BrokenPipeError:
            log_warn("Client déconnecté pendant l'envoi")
            return False
        except Exception as e:
            log_error(f"Erreur envoi réponse: {e}")
            return False

    def handle_client(self, conn, addr):
        """
        Gère une connexion client complète : réception, traitement, réponse
        
        Args:
            conn: Socket client
            addr: Adresse du client (ip, port)
        """
        # Identifiant unique du client
        client_id = f"{addr[0]}:{addr[1]}"
        
        # Mise à jour des statistiques
        self.stats["connections_total"] += 1

        log_info(f"Client connecté: {client_id}")

        # Enregistrement du client actif
        self.active_clients[client_id] = {
            "conn": conn,
            "addr": addr,
            "connect_time": datetime.now(),
            "last_activity": datetime.now()
        }

        try:
            # Réception des données du client
            raw_data = self._recv_until_delimiter(conn)
            if not raw_data:
                log_warn(f"Client {client_id} fermé sans données")
                return

            # Mise à jour de l'activité du client
            self.active_clients[client_id]["last_activity"] = datetime.now()
            log_info(f"Données reçues de {client_id}: {raw_data[:200]}")

            try:
                # Tentative de décodage JSON
                decoded_data = raw_data.decode('utf-8').strip()
                request_data = json.loads(decoded_data)
                self.stats["requests_processed"] += 1
            except json.JSONDecodeError as e:
                log_warn(f"JSON invalide de {client_id}: {e}")
                # Gestion des cas spéciaux 
                request_data = self._handle_special_cases(raw_data)
                if not request_data:
                    # Réponse d'erreur JSON invalide
                    response = self._format_response("error", message="JSON invalide", error_code="INVALID_JSON")
                    self._send_response(conn, response)
                    return

            # Extraction et validation de l'action
            action = request_data.get("action", "unknown")
            is_valid, error_code = self._validate_protocol(request_data)

            if not is_valid:
                log_warn(f"Violation protocole de {client_id}: {error_code}")
                # Réponse d'erreur de protocole
                response = self._format_response(
                    "error", 
                    action=action,
                    message=f"Violation du protocole: {error_code}", 
                    error_code=error_code
                )
                self._send_response(conn, response)
                return

            # GESTION SPÉCIALE JSON INVALIDE 
            if action == "invalid_json":
                response = self._format_response(
                    "error", 
                    message="Format JSON invalide ou incomplet",
                    error_code="INVALID_JSON"
                )
                self._send_response(conn, response)
                return

            log_info(f"Action '{action}' de {client_id}")

    
            start_process = time.time()
            response_data = handle_action(request_data)
            process_time = time.time() - start_process
            
            # Alertes de performance
            if process_time > 2.0:
                log_warn(f"Traitement lent: {process_time:.2f}s pour '{action}'")

            # Détermination du statut de réponse
            status_from_action = response_data.get("status", "unknown")
            valid_statuses = ["success", "nss_ok", "droits_actifs", "patient_created", "patient_not_found"]

            if status_from_action in valid_statuses:
                # Réponse de succès
                response = self._format_response(
                    status_from_action,
                    action=action,
                    data=response_data,
                    message=response_data.get("message")
                )
            else:
                # Réponse d'erreur 
                response = self._format_response(
                    "error",
                    action=action,
                    message=response_data.get("message"),
                    error_code=response_data.get("code")
                )

            # Envoi de la réponse finale
            if self._send_response(conn, response):
                log_info(f"Réponse envoyée à {client_id} ({process_time:.3f}s)")
            else:
                log_warn(f"Échec envoi réponse à {client_id}")

        except socket.timeout:
            # Gestion des clients trop lents
            log_warn(f"Timeout client {client_id} - Trop lent")
            self.stats["errors_count"] += 1
            try:
                timeout_response = self._format_response(
                    "error", 
                    message="Timeout - Trop lent à répondre", 
                    error_code="REQUEST_TIMEOUT"
                )
                self._send_response(conn, timeout_response)
            except:
                pass  
        except ValueError as e:
            # Gestion des débordements de buffer
            if "PAYLOAD_TOO_LARGE" in str(e):
                log_error(f"Débordement buffer client {client_id}")
                self.stats["errors_count"] += 1
                try:
                    error_response = self._format_response(
                        "error", 
                        message="Données trop volumineuses", 
                        error_code="PAYLOAD_TOO_LARGE"
                    )
                    self._send_response(conn, error_response)
                except:
                    pass
        except Exception as e:
            # Gestion des erreurs générales
            log_error(f"Erreur client {client_id}: {e}")
            self.stats["errors_count"] += 1
            try:
                error_response = self._format_response(
                    "error", 
                    message="Erreur interne", 
                    error_code="INTERNAL_ERROR"
                )
                self._send_response(conn, error_response)
            except:
                pass
        finally:
            # Nettoyage des ressources
            if client_id in self.active_clients:
                del self.active_clients[client_id]
            try:
                conn.close()  # Fermeture de la connexion
            except:
                pass
            log_info(f"Client déconnecté: {client_id}")

    def start(self):
        """Démarre le serveur et met en écoute sur le port configuré"""
        log_info("Démarrage du serveur hospitalier...")
        
        try:
            # Création de la socket TCP
            self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
       
            # Configuration de l'exclusivité du port sur Windows
            if hasattr(socket, 'SO_EXCLUSIVEADDRUSE'):
                self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_EXCLUSIVEADDRUSE, 1)

            try:
                # Liaison de la socket à l'adresse et port
                self.server_socket.bind((CONFIG["HOST"], CONFIG["PORT"]))
                log_info(f"Socket liée sur {CONFIG['HOST']}:{CONFIG['PORT']}")
            except OSError as e:
                # Gestion des erreurs de liaison
                if e.errno == 10048 or "Address already in use" in str(e):
                    error_msg = f"Port {CONFIG['PORT']} déjà utilisé"
                    log_error(error_msg)
                    raise Exception(error_msg)
                elif "Cannot assign requested address" in str(e):
                    error_msg = f"Adresse {CONFIG['HOST']} invalide"
                    log_error(error_msg)
                    raise Exception(error_msg)
                else:
                    log_error(f"Erreur bind: {e}")
                    raise

            
            self.server_socket.listen(10)
            self.server_socket.settimeout(1.0)  # Timeout pour l'acceptation

            log_info(f"Serveur en écoute sur {CONFIG['HOST']}:{CONFIG['PORT']}")
            log_info(f"Configuration: timeout {CONFIG['SOCKET_TIMEOUT']}s, buffer {CONFIG['MAX_PAYLOAD_SIZE']} bytes")
            
            self.running = True  # Marque le serveur comme actif
            self._accept_connections()  # Démarre la boucle d'acceptation

        except Exception as e:
            log_error(f"Erreur démarrage: {e}")
            self.stop()  # Arrêt propre en cas d'erreur
            sys.exit(1)

    def _accept_connections(self):
        """Boucle principale d'acceptation des connexions clients"""
        while self.running:
            try:
                # Surveillance de la socket avec timeout 1s
                ready = select.select([self.server_socket], [], [], 1.0)
                if ready[0]:  # Nouvelle connexion en attente
                    conn, addr = self.server_socket.accept()
                    
                    # Lancement d'un thread pour gérer le client
                    threading.Thread(target=self.handle_client, args=(conn, addr), daemon=True).start()

            except socket.timeout:
                continue  # Timeout normal on continue la boucle
            except Exception as e:
                if self.running:  # Log seulement si le serveur est actif
                    log_error(f"Erreur acceptation: {e}")

    def stop(self):
        """Arrêt propre du serveur avec libération des ressources"""
        log_info("Arrêt du serveur en cours...")
        self.running = False  # Signal d'arrêt

        # Fermeture de toutes les connexions clients actives
        for cid, info in list(self.active_clients.items()):
            try:
                info["conn"].close()
            except:
                pass  

        # Fermeture de la socket principale
        if self.server_socket:
            try:
                self.server_socket.close()
                log_info("Socket fermée")
            except:
                pass

        # Affichage des statistiques finales
        uptime = datetime.now() - self.start_time
        log_info(f"Statistiques finales:")
        log_info(f"   Connexions: {self.stats['connections_total']}")
        log_info(f"   Requêtes: {self.stats['requests_processed']}")
        log_info(f"   Erreurs: {self.stats['errors_count']}")
        log_info(f"   Uptime: {uptime}")

def start_server():
    """Point d'entrée principal du serveur avec gestion des signaux"""
    server = HospitalServer()
    
    def graceful_shutdown(signum, frame):
        """Gestionnaire d'arrêt propre pour les signaux système"""
        print("\n" + "="*50)
        print("Arrêt du serveur hospitalier")
        print("="*50)
        log_info("Arrêt demandé par l'utilisateur...")
        server.stop()
        print("Serveur arrêté avec succès")
        print("="*50)
        sys.exit(0)
    
    try:
        # Enregistrement des gestionnaires de signaux pour un arrêt propre
        import signal
        signal.signal(signal.SIGINT, graceful_shutdown)   # Ctrl+C
        signal.signal(signal.SIGTERM, graceful_shutdown)  # kill command
    except ImportError:
        pass  
    
    try:
        server.start()  # Démarrage du serveur
    except KeyboardInterrupt:
        graceful_shutdown(None, None)  # Arrêt par Ctrl+C
    except Exception as e:
        error_msg = str(e)
        log_error(f"ERREUR: {error_msg}")
        
        # Messages d'erreur utilisateur selon le type d'erreur
        if "déjà utilisé" in error_msg or "Address already in use" in error_msg:
            print(f"\n ERREUR: Le port {CONFIG['PORT']} est déjà utilisé")
            print(" Solutions possibles:")
            print("   - Attendez que l'autre serveur se termine")
            print("   - Utilisez un port différent avec: $env:HOSP_PORT='NouveauPort'")
            print("   - Trouvez et terminez le processus utilisant le port")
        elif "Cannot assign requested address" in error_msg:
            print(f"\n ERREUR: Adresse {CONFIG['HOST']} invalide")
            print(" Utilisez: $env:HOSP_HOST='localhost' ou '127.0.0.1'")
        else:
            print(f"\n ERREUR: {error_msg}")
        
        sys.exit(1)

if __name__ == "__main__":
    start_server()  # Point d'entrée du programme