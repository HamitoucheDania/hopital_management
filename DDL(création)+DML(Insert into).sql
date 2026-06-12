
------------------------------------------------------------Création (Partie DDL)---------------------------------------------------------
-- Création de la base de données
CREATE DATABASE hopital_management;
\c hopital_management;
-- Table PATIENT
CREATE TABLE PATIENT (
    patient_id SERIAL PRIMARY KEY,
    nss VARCHAR(15) NOT NULL UNIQUE,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(80) NOT NULL,
    date_naissance DATE NOT NULL,
    sexe CHAR(1) NOT NULL CHECK (sexe IN ('M', 'F')),
    droits_actifs BOOLEAN NOT NULL DEFAULT TRUE,
    password VARCHAR(255) NOT NULL,
    est_valide BOOLEAN NOT NULL DEFAULT FALSE,
    token_validation VARCHAR(100),
    email VARCHAR(100) NOT NULL UNIQUE,
);

-- Table CARTE_VITALE
CREATE TABLE CARTE_VITALE (
 carte_id SERIAL PRIMARY KEY,
 patient_id INT NOT NULL UNIQUE,
 numero_carte VARCHAR(20) NOT NULL UNIQUE,
 date_expiration DATE NOT NULL,
 statut VARCHAR(10) NOT NULL CHECK (statut IN ('ACTIVE', 'EXPIREE', 'BLOQUEE')),
 FOREIGN KEY (patient_id) REFERENCES PATIENT(patient_id) ON DELETE CASCADE
);
-- Table SERVICE
CREATE TABLE SERVICE (
 service_id SERIAL PRIMARY KEY,
 code VARCHAR(6) NOT NULL UNIQUE,
 libelle VARCHAR(100) NOT NULL
);
-- Table PERSONNEL
CREATE TABLE PERSONNEL (
 personnel_id SERIAL PRIMARY KEY,
 nom VARCHAR(80) NOT NULL,
 prenom VARCHAR(80) NOT NULL,
 type VARCHAR(20) NOT NULL CHECK (type IN ('MEDICAL', 'ADMINISTRATIF')),
 service_id INT NOT NULL,
 password VARCHAR(255) NOT NULL,
 FOREIGN KEY (service_id) REFERENCES SERVICE(service_id)
);
-- Table PERSONNEL_MEDICAL
CREATE TABLE PERSONNEL_MEDICAL (
 personnel_med_id INT PRIMARY KEY,
 categorie VARCHAR(20) NOT NULL CHECK (categorie IN ('MEDECIN', 'INFIRMIER')),
 specialite VARCHAR(80),
 FOREIGN KEY (personnel_med_id) REFERENCES PERSONNEL(personnel_id) ON DELETE
CASCADE
);
-- Table PERSONNEL_ADMINISTRATIF
CREATE TABLE PERSONNEL_ADMINISTRATIF (
 personnel_admin_id INT PRIMARY KEY,
 poste VARCHAR(50) NOT NULL CHECK (poste IN ('SECRETAIRE', 'ADMIN')),
 FOREIGN KEY (personnel_admin_id) REFERENCES PERSONNEL(personnel_id) ON DELETE
CASCADE
);
-- Table SEJOUR
CREATE TABLE SEJOUR (
 sejour_id SERIAL PRIMARY KEY,
 patient_id INT NOT NULL,
 personnel_med_id INT NOT NULL,
 service_id INT NOT NULL,
 date_debut TIMESTAMP NOT NULL,
 date_fin TIMESTAMP NULL,
 motif VARCHAR(150) NOT NULL,
 FOREIGN KEY (patient_id) REFERENCES PATIENT(patient_id),
 FOREIGN KEY (personnel_med_id) REFERENCES PERSONNEL_MEDICAL(personnel_med_id),
 FOREIGN KEY (service_id) REFERENCES SERVICE(service_id),
 CHECK (date_fin IS NULL OR date_fin >= date_debut)
);
-- Table ACTE_MEDICAL
CREATE TABLE ACTE_MEDICAL (
 acte_id SERIAL PRIMARY KEY,
 sejour_id INT NOT NULL,
 personnel_med_id INT NOT NULL,
 code_ccam VARCHAR(10) NOT NULL,
 date_acte TIMESTAMP NOT NULL,
 cout NUMERIC(10,2) NOT NULL CHECK (cout >= 0),
 FOREIGN KEY (sejour_id) REFERENCES SEJOUR(sejour_id),
 FOREIGN KEY (personnel_med_id) REFERENCES PERSONNEL_MEDICAL(personnel_med_id)
);
-- Table FACTURE
CREATE TABLE FACTURE (
 facture_id SERIAL PRIMARY KEY,
 sejour_id INT NOT NULL UNIQUE,
 personnel_admin_id INT,
 montant_total NUMERIC(10,2) NOT NULL CHECK (montant_total >= 0),
 statut VARCHAR(15) NOT NULL CHECK (statut IN ('EN_ATTENTE', 'PAYEE',
'REJETEE')),
 date_emission TIMESTAMP NOT NULL DEFAULT NOW(),
 FOREIGN KEY (sejour_id) REFERENCES SEJOUR(sejour_id),
 FOREIGN KEY (personnel_admin_id) REFERENCES
PERSONNEL_ADMINISTRATIF(personnel_admin_id)
);
-- Table TRAITEMENT
CREATE TABLE TRAITEMENT (
 traitement_id SERIAL PRIMARY KEY,
 patient_id INT NOT NULL,
 personnel_med_id INT NOT NULL,
 nom_medicament VARCHAR(30) NOT NULL,
 dosage VARCHAR(50) NOT NULL,
 date_debut DATE NOT NULL,
 date_fin DATE NULL,
 FOREIGN KEY (patient_id) REFERENCES PATIENT(patient_id),
 FOREIGN KEY (personnel_med_id) REFERENCES PERSONNEL_MEDICAL(personnel_med_id),
 CHECK (date_fin IS NULL OR date_fin >= date_debut)
);
-- Table ACCUEIL
CREATE TABLE ACCUEIL (
 accueil_id SERIAL PRIMARY KEY,
 accueil_code VARCHAR(20) UNIQUE,
 libelle VARCHAR(80)
);
-- Table SESSION
CREATE TABLE SESSION (
 session_id SERIAL PRIMARY KEY,
 patient_id INT NOT NULL,
 accueil_id INT NOT NULL,
 personnel_admin_id INT,
 statut VARCHAR(20) NOT NULL CHECK (statut IN ('OUVERTE', 'FERMEE')),
 date_passage TIMESTAMP NOT NULL DEFAULT NOW(),
 motif VARCHAR(140),
 FOREIGN KEY (patient_id) REFERENCES PATIENT(patient_id),
 FOREIGN KEY (accueil_id) REFERENCES ACCUEIL(accueil_id),
 FOREIGN KEY (personnel_admin_id) REFERENCES
PERSONNEL_ADMINISTRATIF(personnel_admin_id)
);
-- Table SCAN_CARTE
CREATE TABLE SCAN_CARTE (
 scan_id SERIAL PRIMARY KEY,
 session_id INT NOT NULL,
 carte_id INT NOT NULL,
 date_scan TIMESTAMP NOT NULL DEFAULT NOW(),
 statut_verification VARCHAR(20) NOT NULL CHECK (statut_verification IN
('SUCCESS', 'ERREUR', 'EN_COURS')),
 FOREIGN KEY (session_id) REFERENCES SESSION(session_id),
 FOREIGN KEY (carte_id) REFERENCES CARTE_VITALE(carte_id)
);
-- Table PATIENT_ACCUEIL (passe par relation N:N entre patient et accueil)
CREATE TABLE PATIENT_ACCUEIL (
 patient_id INT NOT NULL,
 accueil_id INT NOT NULL,
 date_passage TIMESTAMP NOT NULL,
 PRIMARY KEY (patient_id, accueil_id, date_passage),
 FOREIGN KEY (patient_id) REFERENCES PATIENT(patient_id),
 FOREIGN KEY (accueil_id) REFERENCES ACCUEIL(accueil_id)
);
-- Table SESSION_PERSONNEL_ADMIN (gère relation N:N entre personnel_administratif et session)
CREATE TABLE SESSION_PERSONNEL_ADMIN (
 session_id INT NOT NULL,
 personnel_admin_id INT NOT NULL,
 fonction VARCHAR(50) NOT NULL,
 PRIMARY KEY (session_id, personnel_admin_id),
 FOREIGN KEY (session_id) REFERENCES SESSION(session_id),
 FOREIGN KEY (personnel_admin_id) REFERENCES
PERSONNEL_ADMINISTRATIF(personnel_admin_id)
);

-------------------------------------------------------Insertion(Partie DML)---------------------------------------------------------------

-- Insertion des PATIENTS
INSERT INTO PATIENT (patient_id, nss, nom, prenom, date_naissance, sexe, droits_actifs, mot_de_passe, est_valide, token_validation, email) VALUES
(1, '123456789012345', 'Chemim', 'Missina', '1985-03-15', 'M', TRUE, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku', TRUE, 'TOKEN_PAT1', 'missina.chemim@example.com'),
(2, '234567890123456', 'Moussaoui', 'Imane', '1990-07-22', 'F', TRUE, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku', TRUE, 'TOKEN_PAT2', 'imane.moussaoui@example.com'),
(3, '345678901234567', 'Bernard', 'Paul', '1978-11-30', 'M', FALSE, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku', FALSE, 'TOKEN_PAT3', 'paul.bernard@example.com'),
(4, '456789012345678', 'Hamite', 'Emma', '2005-05-10', 'F', TRUE, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku', TRUE, 'TOKEN_PAT4', 'emma.hamite@example.com'),
(5, '567890123456789', 'Petit', 'Lucas', '1995-12-03', 'M', TRUE, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku', TRUE, 'TOKEN_PAT5', 'lucas.petit@example.com'),
-- Insertion des CARTES_VITALES
INSERT INTO CARTE_VITALE (carte_id, patient_id, numero_carte, date_expiration,
statut) VALUES
(101, 1, '0034567890123', '2026-12-31', 'ACTIVE'),
(102, 2, '0034567890124', '2025-06-30', 'ACTIVE'),
(103, 3, '0034567890125', '2024-01-15', 'EXPIREE'),
(104, 4, '0034567890126', '2027-03-20', 'ACTIVE'),
(105, 5, '0034567890127', '2026-08-10', 'BLOQUEE');
-- Insertion des SERVICES
INSERT INTO SERVICE (service_id, code, libelle) VALUES
(201, 'URG', 'Urgences'),
(202, 'CAR', 'Cardiologie'),
(203, 'CHI', 'Chirurgie'),
(204, 'PED', 'Pédiatrie'),
(205, 'RAD', 'Radiologie');
-- Insertion du PERSONNEL
INSERT INTO personnel (personnel_id, nom, prenom, type, service_id, mot_de_passe) VALUES
(301, 'Martin', 'Pierre', 'MEDICAL', 201, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(302, 'Bernard', 'Sophie', 'MEDICAL', 202, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(303, 'Dubois', 'Luc', 'MEDICAL', 203, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(304, 'Moreau', 'Marie', 'MEDICAL', 204, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(305, 'Petit', 'Julie', 'MEDICAL', 201, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(306, 'Laurent', 'Thomas', 'MEDICAL', 202, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(307, 'Simon', 'Laura', 'MEDICAL', 203, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(308, 'Michel', 'Alice', 'ADMINISTRATIF', 201, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(309, 'Lefebvre', 'Robert', 'ADMINISTRATIF', 201, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(310, 'Roux', 'Catherine', 'ADMINISTRATIF', 202, '$2b$12$gdwfdXCVWAoKJkZHKqX24OisukSmO1of3OuuLHdhZguzCnFsbv3ku'),
(8, 'Charnaud', 'Pauline', 'ADMINISTRATIF', NULL, '$2y$10$Uxd8G4bJxW1mzUdhJ/gQ0uiMoeE5MX0FzusT6FA1RQ29bbEhhKCua'),
(9, 'Leloup', 'Laurie', 'MEDICAL', 204, '$2y$10$BIupYrRMtr3xKq5ugo1Utu4sMnmdqyI3WQy8anOrUFupbRmvdHu5C');
-- Insertion PERSONNEL_MEDICAL
INSERT INTO PERSONNEL_MEDICAL (personnel_med_id, categorie, specialite) VALUES
(301, 'MEDECIN', 'Urgentiste'),
(302, 'MEDECIN', 'Cardiologue'),
(303, 'MEDECIN', 'Chirurgien'),
(304, 'MEDECIN', 'Pédiatre'),
(305, 'INFIRMIER', NULL),
(306, 'INFIRMIER', NULL),
(307, 'INFIRMIER', NULL);
-- Insertion PERSONNEL_ADMINISTRATIF
INSERT INTO PERSONNEL_ADMINISTRATIF (personnel_admin_id, poste) VALUES
(308, 'SECRETAIRE'),
(309, 'ADMIN'),
(310, 'SECRETAIRE');
-- Insertion des SEJOURS
INSERT INTO SEJOUR (sejour_id, patient_id, personnel_med_id, service_id,
date_debut, date_fin, motif) VALUES
(401, 1, 301, 201, '2024-01-10 14:30:00', '2024-01-12 10:00:00', 'Fracture bras
droit'),
(402, 2, 302, 202, '2024-01-15 09:15:00', NULL, 'Surveillance cardiaque'),
(403, 3, 303, 203, '2024-01-20 16:45:00', '2024-01-25 12:00:00', 'Appendicite
aiguë'),
(404, 4, 304, 204, '2024-01-22 11:20:00', NULL, 'Fièvre persistante'),
(405, 1, 302, 202, '2024-02-01 08:00:00', NULL, 'Consultation cardiologie');
-- Insertion des ACTES_MEDICAUX
INSERT INTO ACTE_MEDICAL (acte_id, sejour_id, personnel_med_id, code_ccam,
date_acte, cout) VALUES
(501, 401, 301, 'HBJD001', '2024-01-10 15:00:00', 150.00),
(502, 401, 305, 'ZFQP005', '2024-01-11 09:00:00', 75.50),
(503, 403, 303, 'ABCD123', '2024-01-21 10:30:00', 1200.00),
(504, 402, 302, 'EFGH456', '2024-01-16 14:00:00', 89.00),
(505, 404, 304, 'IJKL789', '2024-01-23 16:00:00', 65.00);
-- Insertion des FACTURES
INSERT INTO FACTURE (facture_id, sejour_id, personnel_admin_id, montant_total,
statut, date_emission) VALUES
(601, 401, 308, 225.50, 'PAYEE', '2024-01-13 09:00:00'),
(602, 403, 309, 1200.00, 'EN_ATTENTE', '2024-01-26 14:30:00'),
(603, 402, 308, 89.00, 'EN_ATTENTE', '2024-01-17 11:00:00');
-- Insertion des TRAITEMENTS
INSERT INTO TRAITEMENT (traitement_id, patient_id, personnel_med_id,
nom_medicament, dosage, date_debut, date_fin) VALUES
(701, 1, 301, 'Paracétamol', '1000mg 3x/jour', '2024-01-10', '2024-01-17'),
(702, 2, 302, 'Aspirine', '100mg 1x/jour', '2024-01-15', NULL),
(703, 3, 303, 'Antibiotique', '500mg 2x/jour', '2024-01-20', '2024-01-27'),
(704, 4, 304, 'Ibuprofène', '200mg 3x/jour', '2024-01-22', '2024-01-29'),
(705, 1, 302, 'Bêta-bloquant', '25mg 1x/jour', '2024-02-01', NULL);
-- Insertion des ACCUEILS
INSERT INTO ACCUEIL (accueil_id, accueil_code, libelle) VALUES
(801, 'ACC_URG', 'Accueil Urgences'),
(802, 'ACC_CAR', 'Accueil Cardiologie'),
(803, 'ACC_CHI', 'Accueil Chirurgie');
-- Insertion des SESSIONS 
INSERT INTO SESSION (session_id, patient_id, accueil_id, personnel_admin_id,
statut, date_passage, motif) VALUES
(901, 1, 801, 308, 'FERMEE', '2024-01-10 14:15:00', 'Admission urgences'),
(902, 2, 802, 310, 'OUVERTE', '2024-01-15 09:00:00', 'Consultation programmée'),
(903, 3, 801, 308, 'FERMEE', '2024-01-20 16:30:00', 'Douleurs abdominales'),
(904, 4, 803, 310, 'OUVERTE', '2024-01-22 11:10:00', 'Admission pédiatrie'),
(905, 5, 801, NULL, 'FERMEE', '2024-02-01 10:00:00', 'Visite de contrôle');
-- Insertion des SCANS_CARTE
INSERT INTO SCAN_CARTE (scan_id, session_id, carte_id, date_scan,
statut_verification) VALUES
(1001, 901, 101, '2024-01-10 14:15:30', 'SUCCESS'),
(1002, 902, 102, '2024-01-15 09:00:15', 'SUCCESS'),
(1003, 903, 103, '2024-01-20 16:30:45', 'ERREUR'),
(1004, 903, 103, '2024-01-20 16:31:20', 'SUCCESS'),
(1005, 904, 104, '2024-01-22 11:10:10', 'SUCCESS'),
(1006, 905, 105, '2024-02-01 10:00:05', 'ERREUR');
-- Insertion PATIENT_ACCUEIL (Passe par (relation N,N entre patient et accueil))
INSERT INTO PATIENT_ACCUEIL (patient_id, accueil_id, date_passage) VALUES
(1, 801, '2024-01-10 14:15:00'),
(2, 802, '2024-01-15 09:00:00'),
(3, 801, '2024-01-20 16:30:00'),
(4, 803, '2024-01-22 11:10:00'),
(5, 801, '2024-02-01 10:00:00');
-- Insertion SESSION_PERSONNEL_ADMIN ( Gère (relation N,N entre session et personnel admin))
INSERT INTO SESSION_PERSONNEL_ADMIN (session_id, personnel_admin_id, fonction)
VALUES
(901, 308, 'Saisie dossier'),
(902, 310, 'Validation'),
(903, 308, 'Clôture'),
(904, 310, 'Gestion accueil'),
(905, 309, 'Archivage');