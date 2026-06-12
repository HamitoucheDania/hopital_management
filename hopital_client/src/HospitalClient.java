import java.io.*;
import java.net.*;
import java.util.logging.*;
import java.time.LocalDateTime;
import org.json.JSONObject;

public class HospitalClient {

    // Logger pour afficher des informations de debug et erreurs
    private static final Logger logger = Logger.getLogger(HospitalClient.class.getName());

    // Hôte et port par défaut du serveur
    private final String serverHost;
    private final int serverPort;

    // Timeout de connexion et de lecture 
    private static final int CONNECTION_TIMEOUT = 10000;
    private static final int READ_TIMEOUT = 30000;

    // Bloc statique pour configurer le logger à la création de la classe
    static {
        try {
            ConsoleHandler handler = new ConsoleHandler();
            handler.setFormatter(new SimpleFormatter() {
                // Format  pour les logs : date, niveau, message
                private static final String FORMAT = "[%1$tY-%1$tm-%1$td %1$tH:%1$tM:%1$tS] [%2$-7s] %3$s %n";

                @Override
                public synchronized String format(LogRecord lr) {
                    return String.format(FORMAT,
                            new java.util.Date(lr.getMillis()),
                            lr.getLevel().getLocalizedName(),
                            lr.getMessage());
                }
            });
            logger.addHandler(handler);
            logger.setUseParentHandlers(false); // Désactive le logger par défaut
            logger.setLevel(Level.ALL); // Affiche tous les niveaux de logs
        } catch (Exception e) {
            System.err.println("Erreur configuration logging: " + e.getMessage());
        }
    }

    // Constructeur par défaut : connexion à localhost sur le port 5000
    public HospitalClient() {
        this("127.0.0.1", 5000);
    }

    // Constructeur permettant de définir un hôte et un port 
    public HospitalClient(String host, int port) {
        this.serverHost = host;
        this.serverPort = port;
    }

    // Méthode pour envoyer une requête JSON au serveur et récupérer la réponse JSON
    public JSONObject sendRequest(JSONObject request) {
        Socket socket = null;
        PrintWriter out = null;
        BufferedReader in = null;

        try {
            logger.info("Tentative de connexion au serveur " + serverHost + ":" + serverPort);

            // Création du socket avec timeout de connexion
            socket = new Socket();
            socket.connect(new InetSocketAddress(serverHost, serverPort), CONNECTION_TIMEOUT);
            socket.setSoTimeout(READ_TIMEOUT); // Timeout lecture serveur

            // Création des flux d'entrée/sortie pour le socket
            out = new PrintWriter(new OutputStreamWriter(socket.getOutputStream(), "UTF-8"), true);
            in = new BufferedReader(new InputStreamReader(socket.getInputStream(), "UTF-8"));

            logger.info("Connexion établie avec succès");

            // Validation de la requête avant envoi
            if (!validateRequest(request)) {
                return createErrorResponse("INVALID_REQUEST", "Requête JSON invalide");
            }

            String requestStr = request.toString();
            logger.info("Envoi requête: " + requestStr);

            // Envoi de la requête au serveur
            out.print(requestStr + "\n");
            out.flush();

            // Lecture de la réponse serveur
            String responseLine = in.readLine();
            if (responseLine == null) {
                logger.warning("Serveur a fermé la connexion");
                return createErrorResponse("CONNECTION_CLOSED", "Connexion fermée par le serveur");
            }

            logger.info("Réponse reçue: " + responseLine);
            return new JSONObject(responseLine);

        } catch (UnknownHostException e) {
            //  Gestion DNS non résolu
            logger.severe("Hôte inconnu - Vérifiez l'adresse IP/nom de domaine: " + e.getMessage());
            return createErrorResponse("UNKNOWN_HOST", "Hôte inaccessible: " + e.getMessage());
        } catch (SocketTimeoutException e) {
            logger.severe("Timeout - Serveur ne répond pas");
            return createErrorResponse("TIMEOUT", "Serveur ne répond pas dans le délai imparti");
        } catch (ConnectException e) {
            logger.severe("Connexion refusée - Port serveur non ouvert");
            return createErrorResponse("CONNECTION_REFUSED", "Port serveur non ouvert");
        } catch (IOException e) {
            logger.severe("Erreur d'E/S: " + e.getMessage());
            return createErrorResponse("IO_ERROR", "Erreur de communication: " + e.getMessage());
        } catch (Exception e) {
            logger.severe("Erreur inattendue: " + e.getMessage());
            return createErrorResponse("UNEXPECTED_ERROR", "Erreur inattendue: " + e.getMessage());
        } finally {
            try {
                // Fermeture des flux et du socket
                if (out != null) out.close();
                if (in != null) in.close();
                if (socket != null) socket.close();
                logger.info("Connexion fermée");
            } catch (IOException e) {
                logger.warning("Erreur lors de la fermeture de la connexion: " + e.getMessage());
            }
        }
    }

    //  Méthode avec reconnexion automatique
    public JSONObject sendRequestWithRetry(JSONObject request, int maxRetries) {
        logger.info("Tentative de requête avec " + maxRetries + " reconnexions max");
        
        for (int attempt = 1; attempt <= maxRetries; attempt++) {
            JSONObject response = sendRequest(request);
            
            // Si la requête réussit OU si l'erreur n'est pas liée à la connexion, on retourne
            if (!"error".equals(response.optString("status")) || 
                !"CONNECTION_REFUSED".equals(response.optString("code"))) {
                return response;
            }
            
            // Attente exponentielle avant reconnexion
            if (attempt < maxRetries) {
                int waitTime = 1000 * attempt; // 1s, 2s, 3s...
                logger.warning("Tentative " + attempt + "/" + maxRetries + " échouée - Reconnexion dans " + waitTime + "ms");
                try { 
                    Thread.sleep(waitTime); 
                } catch (InterruptedException e) { 
                    Thread.currentThread().interrupt();
                    break; 
                }
            }
        }
        
        logger.severe("Échec après " + maxRetries + " tentatives de reconnexion");
        return createErrorResponse("MAX_RETRIES_EXCEEDED", "Impossible de se connecter après " + maxRetries + " tentatives");
    }

    // Validation de la requête JSON avant envoi
    private boolean validateRequest(JSONObject request) {
        try {
            // Vérifie la présence de l'action
            if (!request.has("action")) {
                logger.warning("Requête sans action");
                return false;
            }

            // Vérifie le NSS si présent
            if (request.has("nss")) {
                String nss = request.getString("nss");
                if (!isValidNSS(nss)) {
                    logger.warning("NSS invalide: " + nss);
                    return false;
                }
            }

            // Vérifie le patient_id si présent
            if (request.has("patient_id")) {
                Object patientId = request.get("patient_id");
                if (!(patientId instanceof Integer) && !(patientId instanceof String)) {
                    logger.warning("patient_id invalide");
                    return false;
                }
            }

            return true;

        } catch (Exception e) {
            logger.warning("Erreur validation requête: " + e.getMessage());
            return false;
        }
    }

    // Vérification  d'un NSS : 15 chiffres
    private boolean isValidNSS(String nss) {
        return nss != null && nss.matches("\\d{15}");
    }

    // Création d'une réponse d'erreur JSON 
    public JSONObject createErrorResponse(String code, String message) {
        JSONObject error = new JSONObject();
        error.put("status", "error");
        error.put("code", code);
        error.put("message", message);
        error.put("timestamp", LocalDateTime.now().toString());
        return error;
    }

    // Génération d'un identifiant de session aléatoire
    public String generateSessionId() {
        return "SESS_" + System.currentTimeMillis() + "_" + (int)(Math.random() * 1000);
    }

    // Vérification de l'état du serveur
    public JSONObject healthCheck() {
        JSONObject req = new JSONObject();
        req.put("action", "health_check");
        return sendRequest(req);
    }
}