## \# Hopital management 
### \# Système de Gestion Hospitalière

##### Contenu global du fichier : 
#### \# Hopital_management

###### | Fichier                 | Description                                                     |
###### |-------------------------|-----------------------------------------------------------------|
###### | `Hospital_server`       | Serveur principal PYTHON                                        |
###### | `Hopital_client`        | Client JAVA                                                     |
###### | `DDL.sql`+ `DML.sql`    | Requêtes SQL de création et d'insertion dans la base de données |
###### | `DML.sql`               | Requêtes SQL de sélection de la base de données                 |
###### | `site_web(code)`        | Tout le code du site web                                        |


##### \## Type de projet

###### Projet scolaire - projet BD \& réseau

##### 

##### \### Équipe de Développement

\- \*\*CHEMIM Massiva\*\*

\- \*\*MOUSSAOUI Imane\*\*

\- \*\*HAMITOUCHE Dania\*\*

##### 

##### \## Aperçu du Projet

##### 

###### Le Système de Gestion Hospitalière est une application de gestion destinée à faciliter le suivi et la gestion des patients, des séjours hospitaliers, ainsi que des opérations administratives et de facturation. Il permet une gestion complète et sécurisée des informations médicales et administratives au sein d’un établissement hospitalier.

##### 

##### \## Fonctionnalités Principales

##### 

##### \### Gestion des Patients

###### \- Création et enregistrement de nouveaux patients

###### \- Vérification du Numéro de Sécurité Sociale (NSS) au format 15 chiffres

###### \- Consultation du dossier patient complet

###### \- Gestion des droits et de la carte vitale

##### 

##### \### Suivi Médical

###### \- Gestion des séjours hospitaliers

###### \- Historique des actes médicaux par patient et par séjour

###### \- Suivi des traitements en cours

###### \- Coordination du personnel médical

##### 

##### \### Administration et Facturation

###### \- Gestion des factures et statuts de paiement

###### \- Calcul du chiffre d'affaires par service

###### \- Suivi des admissions quotidiennes

###### \- Gestion de la carte vitale (renouvellements, expiration)

##### 

##### \## Objectifs

###### \- \*\*Interface client riche en Java\*\* avec reconnexion automatique

###### \- \*\*Serveur Python robuste\*\* avec gestion multi-clients

###### \- \*\*Base de données PostgreSQL sécurisée\*\*

###### \- \*\*Protocole JSON personnalisé\*\* pour les communications

##### 

##### \## Stack Technologique

###### \- \*\*Client\*\* : Java 11+, Interface Console, Bibliothèque JSON

###### \- \*\*Serveur\*\* : Python 3.8+, Socket TCP, Multi-threading

###### \- \*\*Base de données\*\* : PostgreSQL 12+

###### \- \*\*Protocole\*\* : JSON personnalisé over TCP


##### 

##### \## Composants Principaux

##### 

##### \### Client Java (HospitalClient.java)

###### \- Gestion des connexions réseau avec timeouts

###### \- Reconnexion automatique avec backoff exponentiel

###### \- Validation des requêtes avant envoi

###### \- Logging détaillé

##### 

##### \### Serveur Python (server.py)

###### \- Gestion des connexions simultanées

###### \- Validation stricte du protocole

###### \- Timeouts et gestion d'erreurs

##### 

##### \### Base de Données (database.py)

###### \- Requêtes paramétrées pour la sécurité

###### \- Gestion des transactions

##### 

##### \## Structure des Fichiers

##### 

##### \### Côté Client (Java)

###### | Fichier                 | Description                                      |

###### |-------------------------|--------------------------------------------------|

###### | `HospitalClient.java`    | Client principal avec gestion réseau avancée     |

###### | `HospitalService.java`   | Couche métier pour les requêtes hospitalières    |

###### | `UIConsole.java`         | Interface utilisateur en ligne de commande       |

###### | `ResponseHandler.java`   | Traitement et affichage des réponses serveur     |

##### 

##### \### Côté Serveur (Python)

###### | Fichier                 | Description                                      |

###### |-------------------------|--------------------------------------------------|

###### | `server.py`             | Serveur TCP principal                            |

###### | `actions.py`            | Gestionnaire de toutes les actions métier        |

###### | `database.py`           | Abstraction d'accès à la base de données         |

###### | `config.py`             | Configuration centralisée du système             |

###### | `logs.py`               | Système de logging unifié                        |

###### | `main.py`               | Point d'entrée du serveur                        |

##### \### Site web (php) 

###### | Fichier                     | Description                                 |
###### | --------------------------- | ------------------------------------------- |
###### | `css/clair.css`             | Feuille de style thème clair                |
###### | `css/sombre.css`            | Feuille de style thème sombre               |
###### | `include/footer.inc.php`    | Footer commun du site                       |
###### | `include/header.inc.php`    | Header commun du site                       |
###### | `js/backtotop.js`           | Script pour bouton retour en haut           |
###### | `js/script.js`              | Scripts JS généraux                         |
###### | `js/theme.js`               | Gestion du changement de thème clair/sombre |
###### | `secret/database.php`       | Configuration de la base de données         |
###### | `actes.php`                 | Gestion des actes médicaux                  |
###### | `activation.php`            | Activation d’un compte utilisateur          |
###### | `admin_accueil.php`         | Tableau de bord de l’administrateur         |
###### | `admin_facturation.php`     | Gestion de la facturation                   |
###### | `ajouter_acte.php`          | Formulaire d’ajout d’un acte                |
###### | `ajouter_patient.php`       | Formulaire d’ajout d’un patient             |
###### | `ajouter_sejour.php`        | Formulaire d’ajout d’un séjour              |
###### | `ajouter_traitement.php`    | Formulaire d’ajout d’un traitement          |
###### | `connexion.php`             | Page de connexion                           |
###### | `deconnexion.php`           | Page de déconnexion                         |
###### | `inscription.php`           | Page d’inscription patient                  |
###### | `inscription_personnel.php` | Page d’inscription personnel                |
###### | `dashboard_patient.php`     | Tableau de bord du patient                  |
###### | `dashboard_personnel.php`   | Tableau de bord du personnel                |
###### | `factures.php`              | Gestion des factures                        |
###### | `patient_actes.php`         | Consultation des actes d’un patient         |
###### | `patient_factures.php`      | Consultation des factures d’un patient      |
###### | `patient_fiche.php`         | Fiche médicale d’un patient                 |
###### | `patient_passages.php`      | Historique des passages du patient          |
###### | `patient_sejours.php`       | Historique des séjours                      |
###### | `patients_historique.php`   | Historique complet des patients             |
###### | `patients.php`              | Liste des patients                          |
###### | `patients_presents.php`     | Liste des patients présents                 |
###### | `patient_traitements.php`   | Consultation des traitements d’un patient   |
###### | `pointer_depart.php`        | Gestion des départs des patients            |
###### | `quisommesnous.php`         | Page “Qui sommes-nous ?”                    |
###### | `sejours.php`               | Gestion des séjours                         |
###### | `traitements.php`           | Gestion des traitements                     |
###### | `composer.json`             | Fichier de configuration Composer           |
###### | `composer.lock`             | Fichier lock des dépendances Composer       |


##### \## Configuration

##### 

##### \### Configuration Serveur (config.py)

Exemple de configuration:

```python

DEFAULTS = {

&nbsp;   "HOST": "127.0.0.1",

&nbsp;   "PORT": 5000,

&nbsp;   "DB\_HOST": "postgresql-gestion-hospitaliere.alwaysdata.net",

&nbsp;   "DB\_USER": "gestion-hospitaliere",

&nbsp;   "DB\_PASSWORD": "SaeBD25",

&nbsp;   # ... autres paramètres

}



##### \###Lancer le Serveur avec Configuration Par Défaut

python main.py


##### \###Lancer le Serveur avec Paramètres Personnalisés

python main.py  192.168.1.10 7100 (exemple)


##### \###Démarrage du Client

###### \###Configuration Par Défaut (localhost:5000)

javac -cp ".;..\\json.jar" \*.java

java -cp ".;..\\json.jar" UIConsole

###### 

###### \###Avec Serveur Personnalisé

java -cp ".;..\\json.jar" UIConsole 192.168.1.10 7100


#### \## Licence

##### 

##### \### Conditions d'Utilisation



\*\*Type\*\* : Projet Scolaire



\*\*Droits\*\* :

\- Utilisation à des fins éducatives

\- Modification pour l'apprentissage

\- Distribution dans un contexte académique



\*\*Restrictions\*\* :

\- Utilisation commerciale sans autorisation

\- Modification de la paternité du code

\- Distribution sans mention des auteurs originaux

##### 

##### \### Mentions Légales

Ce logiciel est fourni "tel quel", sans garantie d'aucune sorte. Les auteurs ne peuvent être tenus responsables des dommages résultant de son utilisation. Destiné exclusivement à un usage éducatif et d'apprentissage.


##### \### Support et Contact

Pour toute question concernant ce projet scolaire, contacter les auteurs via les canaux académiques appropriés.


\*\*Documentation technique - Version 1.0 - Novembre 2025\*\*

