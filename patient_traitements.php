<?php
/**
 * patient_traitements.php — Page "Mes traitements" (espace patient)
 *
 * Rôle du script :
 * - Vérifier que l’utilisateur connecté est bien un patient.
 * - Récupérer depuis la base tous ses traitements (actifs et terminés).
 * - Déterminer automatiquement si un traitement est ACTIF :
 *        • date_fin NULL → actif
 *        • date_fin >= aujourd’hui → actif
 *        • sinon → terminé
 * - Afficher ces informations dans un tableau structuré.
 *
 * Accès :
 * - Réservé au rôle `patient`.
 *
 * Variables principales :
 * @var int   $patientId   ID du patient connecté
 * @var PDO   $pdo         Instance PDO reliée à PostgreSQL
 * @var array $traitements Liste des traitements du patient avec indicateur "actif"
 *
 * Affichage :
 * - Liste de tous les traitements du plus récent au plus ancien.
 * - Pastilles de statut : ACTIF / TERMINÉ.
 *
 * @package HospitCare
 */

// patient_traitements.php

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

// Traitements (actifs / passés)
$stmt = $pdo->prepare("
    SELECT traitement_id, nom_medicament, dosage, date_debut, date_fin,
           (date_fin IS NULL OR date_fin >= CURRENT_DATE) AS actif
    FROM TRAITEMENT
    WHERE patient_id = :id
    ORDER BY date_debut DESC
");
$stmt->execute([':id' => $patientId]);
$traitements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Mes traitements";
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Mes traitements</h1>
            <p class="dashboard-subtitle">Traitements en cours et passés.</p>
        </div>
    </section>

    <div class="card card-large">
        <?php if (empty($traitements)): ?>
            <p class="card-info">Aucun traitement enregistré.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Traitement</th>
                        <th>Médicament</th>
                        <th>Dosage</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($traitements as $t): ?>
                        <tr>
                            <td>#<?= (int)$t['traitement_id'] ?></td>
                            <td><?= htmlspecialchars($t['nom_medicament']) ?></td>
                            <td><?= htmlspecialchars($t['dosage']) ?></td>
                            <td><?= htmlspecialchars($t['date_debut']) ?></td>
                            <td><?= htmlspecialchars($t['date_fin'] ?? '—') ?></td>
                            <td>
                                <?php if ($t['actif']): ?>
                                    <span class="status-pill actif">ACTIF</span>
                                <?php else: ?>
                                    <span class="status-pill termine">TERMINÉ</span>
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
