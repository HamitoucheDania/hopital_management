
-- 1. Sélection simple de chaque table
SELECT * FROM SERVICE ;
SELECT * FROM PERSONNEL_MEDICAL;
SELECT * FROM PATIENT;
SELECT * FROM CARTE_VITALE;
SELECT * FROM SEJOUR;
SELECT * FROM TRAITEMENT;
SELECT * FROM ACTE_MEDICAL;
SELECT * FROM FACTURE;


-- IS NULL : séjours pas encore terminés
SELECT * FROM SEJOUR
WHERE date_sortie IS NULL;


-- IS NOT NULL : factures payées
SELECT * FROM FACTURE
WHERE date_paiement IS NOT NULL;


-- DISTINCT : toutes les spécialités du personnel
SELECT DISTINCT specialite FROM PERSONNEL_MEDICAL;


-- ORDER BY : patients triés par nom
SELECT * FROM PATIENT
ORDER BY nom ASC;


-- UNION : liste de tous les noms employés + patients
SELECT nom FROM PATIENT
UNION
SELECT nom FROM PERSONNEL_MEDICAL;


-- INTERSECT : personnes présentes et comme patients et personnel 
SELECT nom FROM PATIENT
INTERSECT
SELECT nom FROM PERSONNEL_MEDICAL;


--  EXCEPT : actes non facturés
SELECT sejour_id FROM ACTE_MEDICAL
EXCEPT
SELECT sejour_id FROM FACTURE;


-- MIN / MAX / SUM / COUNT / AVG
SELECT MIN(cout) AS min_acte, MAX(cout) AS max_acte, AVG(cout) AS moyenne FROM ACTE_MEDICAL;
SELECT SUM(montant) FROM FACTURE;
SELECT COUNT(*) FROM SEJOUR WHERE date_sortie IS NULL;


-- AS 
SELECT nom AS nom_patient, prenom AS prenom_patient FROM PATIENT;


-- EXISTS : patients ayant au moins un acte
SELECT nom, prenom FROM PATIENT p
WHERE EXISTS (
SELECT 1 FROM SEJOUR s
JOIN ACTE_MEDICAL a ON a.sejour_id = s.sejour_id
WHERE s.patient_id = p.patient_id
);

-- NATURAL JOIN 
SELECT * FROM SERVICE NATURAL JOIN PERSONNEL_MEDICAL;


-- équivalent NATURAL JOIN via produit cartésien + restriction
SELECT * FROM SERVICE s, PERSONNEL_MEDICAL pm
WHERE s.service_id = pm.service_id;


-- JOIN USING
SELECT nom, prenom, specialite FROM PERSONNEL_MEDICAL
JOIN SERVICE USING(service_id);


-- INNER JOIN ON
SELECT p.nom, p.prenom, s.date_entree, s.motif
FROM PATIENT p
INNER JOIN SEJOUR s ON p.patient_id = s.patient_id;


-- GROUP BY
SELECT patient_id, COUNT(*) AS nb_sejours
FROM SEJOUR
GROUP BY patient_id;


-- HAVING (patients ayant au moins 2 séjours)
SELECT patient_id, COUNT(*) AS nb_sejours
FROM SEJOUR
GROUP BY patient_id
HAVING COUNT(*) >= 2;