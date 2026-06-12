###HospitCare - GestionHospitalière

###Description du projet
Le projet **HospitCare Gestion Hospitalière** est un site web permettant la gestion administrative d’un établissement hospitalier : patients, personnel, actes médicaux, séjours, traitements, factures, consultations et authentification.  
Ce projet a été réalisé dans le cadre de la *SAE / Base de Données*.

URL : https://gestion-hospitaliere.alwaysdata.net

###Technologies utilisées
- **HTML5 / CSS3 / JavaScript**
- **PHP 8**
- **PostgreSQL**
- **phpDocumentor**
- **AlwaysData** (hébergement)

###Structure du projet
/css  
 clair.css  
 sombre.css  

/include  
 footer.inc.php  
 header.inc.php  

/js  
 backtotop.js  
 script.js  
 theme.js  

/pictures  
 index1.png  
 index2.png  
 index3.png  
 logo.png  

/secret  
 .htaccess  
 database.php  

/doc  
 Documentation HTML générée par phpDocumentor  
 index.html  
 fichiers internes de documentation

/.phpdoc  
 Fichier de configuration généré automatiquement pour phpDocumentor

/racine (fichiers PHP principaux)  
 actes.php  
 activation.php  
 admin_accueil.php  
 admin_facturation.php  
 ajouter_acte.php  
 ajouter_patient.php  
 ajouter_sejour.php  
 ajouter_traitement.php  
 connexion.php  
 contact.php  
 dashboard_patient.php  
 dashboard_personnel.php  
 deconnexion.php  
 factures.php  
 index.php  
 inscription.php  
 inscription_personnel.php  
 patient_actes.php  
 patient_factures.php  
 patient_fiche.php  
 patient_passages.php  
 patient_sejours.php  
 patient_traitements.php  
 patients_historique.php  
 patients.php  
 pointer_depart.php  
 quissommesnous.php  
 sejours.php  
 traitements.php  

###Installation et configuration

###Cloner le projet

git clone <url-du-projet>
cd gestion-hospitaliere

###Configurer la base de données
Modifier le fichier /secret/database.php :

$host = "postgresql-gestion-hospitaliere.alwaysdata.net";
$dbname = "gestion-hospitaliere_db";
$user = "gestion-hospitaliere";
$pass = "votre_mot_de_passe";

###Importer la base PostgreSQL

psql -h postgresql-gestion-hospitaliere.alwaysdata.net -U gestion-hospitaliere gestion-hospitaliere_db


###Fonctionnalités principales

###Gestion des patients

Ajouter / modifier / supprimer un patient

Consultation de la fiche patient

Historique : actes, séjours, traitements, passages, factures

Recherche par NSS


###Gestion du personnel

Inscription du personnel

Connexion / gestion de compte

Gestion des rôles : médecin, infirmier, agent administratif


###Gestion administrative

Gestion des actes médicaux

Gestion des traitements

Gestion des séjours

Gestion des factures

Gestion des passages


###Consultations et services

Suivi du parcours patient

Visualisation des informations médicales

Gestion des décisions du personnel médical


###Authentification et sécurité

Connexion et déconnexion sécurisées

Gestion de session PHP

Protection via .htaccess

Accès différenciés :

Patient

Personnel

Administrateur


###Interface et thèmes
Le site propose deux thèmes CSS :

clair.css

sombre.css

Le changement de thème est géré via theme.js.
Le script backtotop.js améliore l’ergonomie de navigation.


###Documentation
La documentation du code a été générée automatiquement avec phpDocumentor.

Commande utilisée :

php phpDocumentor.phar -d . -t doc


La documentation est disponible dans :

/doc/index.html


###Tests et validation

Validation HTML et CSS

Vérification de la syntaxe PHP

Test des formulaires : connexion, inscription, ajout patient, ajout acte…

Vérification de l’accès aux tableaux de bord selon les rôles utilisateurs


###Auteurs
Projet développé dans le cadre de la matière SAE / Base de Données.
Développé par :

MOUSSAOUI Imane

HAMITOUCHE Dania

CHEMIM Massiva

###Licence
Projet académique 2025/2026 — diffusion limitée.
