import org.json.JSONObject;

public class HospitalService {

    // Référence vers le client qui gère la communication avec le serveur
    private final HospitalClient client;

    // Constructeur : injecte le client à utiliser pour envoyer les requêtes
    public HospitalService(HospitalClient client) {
        this.client = client;
    }

    // Vérifie la validité d'un NSS
    public JSONObject verifNSS(String nss) {
        JSONObject request = new JSONObject();
        request.put("action", "verif_nss"); // Action demandée au serveur
        request.put("nss", nss);            // NSS à vérifier
        request.put("session_id", client.generateSessionId()); // Identifiant de session
        return client.sendRequestWithRetry(request, 3); 
    }

    // Vérifie les droits d'un patient à partir de son ID
    public JSONObject verifDroits(int patientId) {
        JSONObject request = new JSONObject();
        request.put("action", "verif_droits");
        request.put("patient_id", patientId);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  
    }

    // Récupère les informations de base d'un patient
    public JSONObject getInfosPatient(int patientId) {
        JSONObject request = new JSONObject();
        request.put("action", "get_infos");
        request.put("patient_id", patientId);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  
    }

    // Crée un nouveau patient avec les informations fournies
    public JSONObject creerPatient(String nss, String nom, String prenom, String dateNaissance, String sexe) {
        JSONObject request = new JSONObject();
        request.put("action", "create_patient");
        request.put("nss", nss);
        request.put("nom", nom);
        request.put("prenom", prenom);
        request.put("date_naissance", dateNaissance);
        request.put("sexe", sexe);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);   
    }

    // Vérification de l'état  du serveur
    public JSONObject healthCheck() {
        JSONObject request = new JSONObject();
        request.put("action", "health_check");
        return client.sendRequest(request);  
    }


    // Récupère l'historique des actes pour un patient et un séjour donné
    public JSONObject historiqueActes(int patientId, int sejourId) {
        JSONObject request = new JSONObject();
        request.put("action", "historique_actes");
        request.put("patient_id", patientId);
        request.put("sejour_id", sejourId);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  // Utiliser la reconnexion automatique
    }

    // Récupère les traitements en cours d'un patient
    public JSONObject traitementsEnCours(int patientId) {
        JSONObject request = new JSONObject();
        request.put("action", "traitements_en_cours");
        request.put("patient_id", patientId);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  //  Utiliser la reconnexion automatique
    }

    // Récupère les factures en attente pour tous les patients
    public JSONObject facturesEnAttente() {
        JSONObject request = new JSONObject();
        request.put("action", "factures_en_attente");
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  // Utiliser la reconnexion automatique
    }

    // Récupère le chiffre d'affaires d'un service pour un mois et une année donnés
    public JSONObject chiffreAffairesService(int mois, int annee) {
        JSONObject request = new JSONObject();
        request.put("action", "chiffre_affaires_service");
        request.put("mois", mois);
        request.put("annee", annee);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  //  Utiliser la reconnexion automatique
    }

    // Récupère les admissions du jour donné 
    public JSONObject admissionsAujourdhui(String date) {
        JSONObject request = new JSONObject();
        request.put("action", "admissions_aujourdhui");
        if (date != null) request.put("date", date);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3);  //  Utiliser la reconnexion automatique
    }

    // Récupère la liste des médecins de garde (urgence)
    public JSONObject medecinsUrgence() {
        JSONObject request = new JSONObject();
        request.put("action", "medecins_urgence");
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3); // Utiliser la reconnexion automatique
    }

    // Récupère les cartes expirant pour une année donnée et éventuellement un mois spécifique
    public JSONObject cartesExpiration(int annee, Integer mois) {
        JSONObject request = new JSONObject();
        request.put("action", "cartes_expiration");
        request.put("annee", annee);
        if (mois != null) request.put("mois", mois);
        request.put("session_id", client.generateSessionId());
        return client.sendRequestWithRetry(request, 3); // Utiliser la reconnexion automatique
    }
}