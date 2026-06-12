<?php
/**
 * Page "Mes actes médicaux" — Espace patient.
 *
 * Rôle de ce script :
 * - Vérifier que l'utilisateur connecté est un patient.
 * - Récupérer tous les actes médicaux liés à ses séjours.
 * - Afficher ces actes sous forme de tableau : 
 *      • date de l’acte,
 *      • code CCAM,
 *      • coût,
 *      • séjour associé,
 *      • service concerné.
 *
 * Fonctionnement :
 * - Connexion à la base PostgreSQL via PDO.
 * - Requête SQL regroupant ACTE_MEDICAL, SEJOUR et SERVICE.
 * - Affichage dans une table HTML ou message si aucun acte.
 *
 * Accès :
 * - Uniquement aux patients connectés (contrôle via session).
 *
 * Variables principales :
 * @var int   $patientId   ID du patient connecté (depuis la session)
 * @var array $actes       Liste des actes médicaux trouvés
 *
 * @package HospitCare
 */

// patient_actes.php

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Vérification : accès patient uniquement
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'patient') {
    http_response_code(403);
    die('Accès réservé aux patients.');
}

$patientId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/secret/database.php';

// Connexion BD
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die('Erreur BD.');
}

// Récupération des actes médicaux du patient
$stmt = $pdo->prepare("
    SELECT 
        a.acte_id,
        a.date_acte,
        a.code_ccam,
        a.cout,
        s.sejour_id,
        s.date_debut,
        s.date_fin,
        sv.libelle AS service_libelle
    FROM ACTE_MEDICAL a
    JOIN SEJOUR s ON s.sejour_id = a.sejour_id
    LEFT JOIN SERVICE sv ON sv.service_id = s.service_id
    WHERE s.patient_id = :id
    ORDER BY a.date_acte DESC
");
$stmt->execute([':id' => $patientId]);
$actes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Mes actes médicaux";
include __DIR__ . '/include/header.inc.php';
?>
<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Mes actes médicaux</h1>
            <p class="dashboard-subtitle">
                Liste des actes réalisés lors de vos séjours.
            </p>
        </div>
    </section>

    <div class="card card-large">
        <?php if (empty($actes)): ?>
            <p class="card-info">Aucun acte médical enregistré à votre nom.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Acte</th>
                        <th>Séjour</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Code CCAM</th>
                        <th>Coût</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actes as $a): ?>
                        <tr>
                            <td>#<?= (int)$a['acte_id'] ?></td>
                            <td>#<?= (int)$a['sejour_id'] ?></td>
                            <td>
                                <span class="badge">
                                    <?= htmlspecialchars($a['service_libelle'] ?? 'Service inconnu') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($a['date_acte']) ?></td>
                            <td><?= htmlspecialchars($a['code_ccam']) ?></td>
                            <td><?= number_format((float)$a['cout'], 2, ',', ' ') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
