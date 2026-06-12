import java.util.Scanner;

public class UIConsole {

    public static void main(String[] args) {
        // Valeurs par défaut
        String serverHost = "127.0.0.1";
        int serverPort = 5000;

        // Vérifie si l'utilisateur a fourni IP et port en arguments
        if (args.length >= 1) {
            serverHost = args[0]; // 1er argument = IP
        }
        if (args.length >= 2) {
            try {
                serverPort = Integer.parseInt(args[1]); // 2e argument = port
            } catch (NumberFormatException e) {
                System.out.println("Port invalide fourni, utilisation du port par défaut: " + serverPort);
            }
        }

        System.out.println("Connexion au serveur " + serverHost + ":" + serverPort);

        // Création du client avec IP et port configurables
        HospitalClient client = new HospitalClient(serverHost, serverPort);
        HospitalService service = new HospitalService(client);
        Scanner scanner = new Scanner(System.in);

        System.out.println("=== CLIENT HOSPITALIER - GESTION ADMISSION ===");

        try {
            boolean running = true;
            while (running) { // Boucle principale du menu
                System.out.println("\n--- MENU PRINCIPAL ---");
                System.out.println("1. Vérifier NSS");
                System.out.println("2. Vérifier droits patient");
                System.out.println("3. Infos complètes patient");
                System.out.println("4. Créer nouveau patient");
                System.out.println("5. 7 Questions");
                System.out.println("0. Quitter");
                System.out.print("Choix: ");

                String choix = scanner.nextLine();

                switch (choix) {

                    case "1": // Vérification NSS
                        System.out.print("NSS à vérifier: ");
                        ResponseHandler.traiterReponse(service.verifNSS(scanner.nextLine()));
                        break;

                    case "2": // Vérification droits d’un patient
                        System.out.print("ID patient: ");
                        ResponseHandler.traiterReponse(service.verifDroits(
                                Integer.parseInt(scanner.nextLine())));
                        break;

                    case "3": // Informations  du patient
                        System.out.print("ID patient: ");
                        ResponseHandler.traiterReponse(service.getInfosPatient(
                                Integer.parseInt(scanner.nextLine())));
                        break;

                    case "4": // Création d’un nouveau patient
                        System.out.print("NSS: "); String nss = scanner.nextLine();
                        System.out.print("Nom: "); String nom = scanner.nextLine();
                        System.out.print("Prénom: "); String prenom = scanner.nextLine();
                        System.out.print("Date naissance (YYYY-MM-DD): "); String dn = scanner.nextLine();
                        System.out.print("Sexe (M/F): "); String sexe = scanner.nextLine();
                        ResponseHandler.traiterReponse(service.creerPatient(nss, nom, prenom, dn, sexe));
                        break;

                    case "5": // Accès au menu secondaire des requêtes au serveur (10 QUESTIONS)
                        menuRequetesMetier(service, scanner);
                        break;

                    case "0": // Quitter l’application
                        running = false;
                        break;

                    default: // Choix invalide
                        System.out.println("Choix invalide");
                }
            }

        } catch (Exception e) { // Gestion des exceptions critiques
            System.err.println("Erreur critique: " + e.getMessage());
            e.printStackTrace();
        } finally { // Fermeture du scanner et message de fin
            scanner.close();
            System.out.println("Client arrêté.");
        }
    }

    // Menu secondaire pour les requêtes au serveur (10 QUESTIONS)
    private static void menuRequetesMetier(HospitalService service, Scanner scanner) {

        boolean running = true;

        while (running) { // Boucle du menu secondaire

            System.out.println("\n--- REQUÊTES AU SERVEUR (7 QUESTIONS) ---");;
            System.out.println("1. Historique actes médicaux");
            System.out.println("2. Traitements en cours");
            System.out.println("3. Factures en attente");
            System.out.println("4. Chiffre d'affaires par service");
            System.out.println("5. Admissions aujourd'hui");
            System.out.println("6. Médecins aux urgences");
            System.out.println("7. Cartes à renouveler");
            System.out.println("0. Retour menu principal");
            System.out.print("Choix: ");

            String choix = scanner.nextLine();

            switch (choix) {

                case "1": // Historique des actes médicaux d’un patient pour un séjour
                    System.out.print("ID patient: ");
                    int id = Integer.parseInt(scanner.nextLine());
                    System.out.print("ID séjour: ");
                    int sejour = Integer.parseInt(scanner.nextLine());
                    ResponseHandler.traiterReponse(service.historiqueActes(id, sejour));
                    break;

                case "2": // Traitements en cours pour un patient
                    System.out.print("ID patient: ");
                    ResponseHandler.traiterReponse(service.traitementsEnCours(
                            Integer.parseInt(scanner.nextLine())));
                    break;

                case "3": // Factures en attente
                    ResponseHandler.traiterReponse(service.facturesEnAttente());
                    break;

                case "4": // Chiffre d’affaires par service pour un mois/année
                    System.out.print("Mois: ");
                    int mois = Integer.parseInt(scanner.nextLine());
                    System.out.print("Année: ");
                    int annee = Integer.parseInt(scanner.nextLine());
                    ResponseHandler.traiterReponse(service.chiffreAffairesService(mois, annee));
                    break;

                case "5": // Admissions pour une date donnée
                    System.out.print("Date : ");
                    String date = scanner.nextLine();
                    if (date.isEmpty()) date = null;
                    ResponseHandler.traiterReponse(service.admissionsAujourdhui(date));
                    break;

                case "6": // Médecins de garde aux urgences
                    ResponseHandler.traiterReponse(service.medecinsUrgence());
                    break;

                case "7": // Cartes d’assurance à renouveler
                    System.out.print("Année: ");
                    int a = Integer.parseInt(scanner.nextLine());
                    System.out.print("Mois: ");
                    String ms = scanner.nextLine();
                    Integer m = ms.isEmpty() ? null : Integer.parseInt(ms);
                    ResponseHandler.traiterReponse(service.cartesExpiration(a, m));
                    break;

                case "0": // Retour au menu principal
                    running = false;
                    break;

                default: // Choix invalide
                    System.out.println("Choix invalide");
            }
        }
    }
}
