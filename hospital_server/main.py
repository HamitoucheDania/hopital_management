import sys
from server import start_server
from config import CONFIG
from logs import log_info, log_error

if __name__ == "__main__":
    try:
        log_info("Initialisation du serveur...")

        # ────────────────────────────────────────────────
        # Paramétrage IP et PORT via arguments CLI
        # Usage :
        #   python main.py <PORT> <IP>
        # Exemples :
        #   python main.py  192.168.1.10 7100
        # Si rien n'est fourni => utilise la config par défaut.
        # ────────────────────────────────────────────────
        if len(sys.argv) > 1:
            new_ip = sys.argv[1]
            CONFIG._config["HOST"] = new_ip
            log_info(f"Adresse IP passée en argument : {new_ip}")
        # Port passé en argument
        if len(sys.argv) > 2:
            try:
                new_port = int(sys.argv[2])
                CONFIG._config["PORT"] = new_port
                log_info(f"Port passé en argument : {new_port}")
            except ValueError:
                log_error("Argument de port invalide. Utilisation du port par défaut.")

        start_server()

    except Exception as e:
        log_error(f"Erreur lors du lancement du serveur: {e}")
