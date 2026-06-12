import org.json.JSONObject;

public class ResponseHandler {

    // Méthode statique pour traiter et afficher une réponse JSON du serveur
    public static void traiterReponse(JSONObject response) {
        System.out.println("\n=== RÉPONSE SERVEUR ==="); 

        // Extraction des champs principaux de la réponse JSON
        String status = response.optString("status", "unknown"); // État de la réponse
        String message = response.optString("message", "");      // Message texte associé
        String code = response.optString("code", "");            // Code d'erreur

        // Différents cas selon le statut
        switch (status) {
            // Cas où la réponse est positive ou réussie
            case "success":
            case "nss_ok":
            case "droits_actifs":
            case "patient_created":
                System.out.println("[OK] " + message); // Affiche le message principal
                // Affiche tous les autres champs de la réponse
                for (String key : response.keySet()) {
                    if (!key.equals("status") && !key.equals("message") && !key.equals("code")) {
                        System.out.println("   " + key + ": " + response.get(key));
                    }
                }
                break;

            // Cas où le patient n'existe pas
            case "patient_not_found":
                System.out.println("[ATTENTION] " + message);
                break;

            // Cas d'erreur côté serveur ou communication
            case "error":
                System.out.println("[ERREUR] [" + code + "] : " + message);
                break;

            // Cas inattendu : affiche la réponse brute
            default:
                System.out.println("[?] Réponse brute: " + response.toString());
        }
    }
}
