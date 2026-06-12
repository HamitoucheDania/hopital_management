<?php
/**
 * patient_sejours.php — Page "Mes séjours" (espace patient).
 *
 * Rôle du script :
 * - Vérifier que l'utilisateur connecté est un patient.
 * - Récupérer tous ses séjours hospitaliers depuis la base de données.
 * - Afficher chaque séjour avec :
 *      • service d’hospitalisation,
 *      • motif,
 *      • dates de début et de fin,
 *      • statut (EN COURS / TERMINÉ).
 *
 * Accès :
 * - Réservé au rôle `patient`.
 *
 * Variables principales :
 * @var int   $patientId  ID du patient connecté.
 * @var PDO   $pdo        Instance PDO connectée à PostgreSQL.
 * @var array $sejours    Liste des séjours du patient (SEJOUR + SERVICE).
 *
 * Affichage :
 * - Tableau listant les séjours du plus récent au plus ancien.
 * - Badge de service + statut dynamique basé sur la date de fin.
 *
 * @package HospitCare
 */

// patient_sejours.php

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'patient') {
    http_response_code(403);
    die('Accès réservé aux patients.');
}

$patientId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/secret/database.php';

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die('Erreur BD.');
}

$stmt = $pdo->prepare("
    SELECT s.sejour_id, s.date_debut, s.date_fin, s.motif,
           sv.libelle AS service_libelle
    FROM SEJOUR s
    LEFT JOIN SERVICE sv ON sv.service_id = s.service_id
    WHERE s.patient_id = :id
    ORDER BY s.date_debut DESC
");
$stmt->execute([':id' => $patientId]);
$sejours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Mes séjours";
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Mes séjours</h1>
            <p class="dashboard-subtitle">Historique de vos hospitalisations et séjours.</p>
        </div>
    </section>

    <div class="card card-large">
        <?php if (empty($sejours)): ?>
            <p class="card-info">Vous n’avez pas encore de séjour enregistré.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Séjour</th>
                        <th>Service</th>
                        <th>Motif</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sejours as $s): ?>
                        <?php
                        $enCours = empty($s['date_fin']) || (strtotime($s['date_fin']) >= time());
                        ?>
                        <tr>
                            <td>#<?= (int)$s['sejour_id'] ?></td>
                            <td>
                                <span class="badge">
                                    <?= htmlspecialchars($s['service_libelle'] ?? 'Non renseigné') ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($s['motif'])) ?></td>
                            <td><?= htmlspecialchars($s['date_debut']) ?></td>
                            <td><?= htmlspecialchars($s['date_fin'] ?? '—') ?></td>
                            <td>
                                <?php if ($enCours): ?>
                                    <span class="status-pill actif">EN COURS</span>
                                <?php else: ?>
                                    <span class="status-pill termine">TERMINE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
