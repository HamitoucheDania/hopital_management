# config.py
import os
from typing import Dict, Any

class HospitalConfig:
    """Configuration  avec valeurs par défaut et validation"""
    
    DEFAULTS: Dict[str, Any] = {
    "HOST": "127.0.0.1",
    "PORT": 5000,
    "BUFFER_SIZE": 4096,
    "SOCKET_TIMEOUT": 30,
    "MAX_PAYLOAD_SIZE": 16 * 1024,  
    "MAX_CLIENTS": 50,
    "DB_HOST": "postgresql-gestion-hospitaliere.alwaysdata.net",
    "DB_PORT": 5432,
    "DB_NAME": "gestion-hospitaliere_db",
    "DB_USER": "gestion-hospitaliere",
    "DB_PASSWORD": "SaeBD25",
    "DB_CONNECT_TIMEOUT": 5,
    "LOG_LEVEL": "INFO",
    "HEARTBEAT_TIMEOUT": 10,          
    "MAX_CLIENT_INACTIVITY": 30,
}
    
    def __init__(self):
        self._config = self._load_config()
    
    def _load_config(self) -> Dict[str, Any]:
        """Chargement de la configuration avec validation"""
        config = {}
        
        for key, default in self.DEFAULTS.items():
            env_key = f"HOSP_{key}"
            value = os.getenv(env_key, default)
            
            # Conversion des types
            if isinstance(default, int):
                try:
                    value = int(value)
                except (ValueError, TypeError):
                    value = default
            elif isinstance(default, bool):
                if isinstance(value, str):
                    value = value.lower() in ('true', '1', 'yes', 'on')
            
            config[key] = value
        
        return config
    
    def __getitem__(self, key: str) -> Any:
        return self._config[key]
    
    def get(self, key: str, default: Any = None) -> Any:
        return self._config.get(key, default)
    
    def validate(self) -> bool:
        """Validation de la configuration"""
        if not (1024 <= self._config["PORT"] <= 65535):
            raise ValueError("Port doit être entre 1024 et 65535")
        
        if self._config["MAX_PAYLOAD_SIZE"] > 10 * 1024 * 1024:  # 10MB max
            raise ValueError("Payload maximum trop élevé")
        
        return True

# Instance globale de configuration
CONFIG = HospitalConfig()

# Validation au chargement
try:
    CONFIG.validate()
    print(" Configuration validée avec succès")
except ValueError as e:
    print(f" Erreur configuration: {e}")
    raise