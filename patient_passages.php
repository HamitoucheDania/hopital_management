<?php
/**
 * patient_passages.php — Page "Mes passages" (espace patient).
 *
 * Rôle de ce script :
 * - Vérifier que l’utilisateur connecté est un patient.
 * - Récupérer tous ses passages à l’accueil (table SESSION + ACCUEIL).
 * - Pour chaque passage, calculer des indicateurs :
 *      • nb_sejours      : nombre de séjours commencés après ce passage,
 *      • nb_traitements  : nombre de traitements débutés après ce passage,
 *      • nb_actes        : nombre d’actes médicaux réalisés après ce passage,
 *      • nb_factures     : nombre de factures émises après ce passage.
 * - Afficher l’historique des passages avec ces compteurs associés.
 *
 * Sécurité / Accès :
 * - Accès réservé aux utilisateurs avec le rôle `patient`.
 * - L’ID du patient est récupéré depuis la session ($_SESSION['user_id']).
 *
 * Variables principales :
 * @var int   $patientId  Identifiant du patient connecté.
 * @var PDO   $pdo        Connexion PDO à la base de données PostgreSQL.
 * @var array $passages   Liste des passages (SESSION) avec les compteurs associés.
 *
 * Tables principales utilisées :
 * - SESSION         : passages à l’accueil.
 * - ACCUEIL         : types / codes d’accueil.
 * - SEJOUR          : séjours hospitaliers.
 * - TRAITEMENT      : traitements médicamenteux.
 * - ACTE_MEDICAL    : actes médicaux réalisés.
 * - FACTURE         : factures générées après les séjours.
 *
 * @package HospitCare
 */

// patient_passages.php — Mes passages à l’accueil

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Accès uniquement patient
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

/*
 * On récupère tous les passages (SESSION) du patient
 * et, pour chaque passage, on calcule :
 * - nb_sejours : nombre de séjours dont la date_debut est >= date_passage
 * - nb_traitements : nombre de traitements dont la date_debut est >= date_passage
 * - nb_actes : nombre d'actes médicaux datés après ce passage
 * - nb_factures : nombre de factures émises après ce passage
 */

$sql = "
    SELECT 
        se.session_id,
        se.date_passage,
        se.statut,
        se.motif,
        a.accueil_code,
        a.libelle AS accueil_libelle,

        -- Séjours après ce passage
        (
            SELECT COUNT(*) FROM SEJOUR s
            WHERE s.patient_id = se.patient_id
              AND s.date_debut >= se.date_passage
        ) AS nb_sejours,

        -- Traitements après ce passage
        (
            SELECT COUNT(*) FROM TRAITEMENT t
            WHERE t.patient_id = se.patient_id
              AND t.date_debut >= se.date_passage
        ) AS nb_traitements,

        -- Actes médicaux après ce passage
        (
            SELECT COUNT(*) 
            FROM ACTE_MEDICAL am
            JOIN SEJOUR s2 ON s2.sejour_id = am.sejour_id
            WHERE s2.patient_id = se.patient_id
              AND am.date_acte >= se.date_passage
        ) AS nb_actes,

        -- Factures après ce passage
        (
            SELECT COUNT(*)
            FROM FACTURE f
            JOIN SEJOUR s3 ON s3.sejour_id = f.sejour_id
            WHERE s3.patient_id = se.patient_id
              AND f.date_emission >= se.date_passage
        ) AS nb_factures

    FROM SESSION se
    JOIN ACCUEIL a ON a.accueil_id = se.accueil_id
    WHERE se.patient_id = :id
    ORDER BY se.date_passage DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $patientId]);
$passages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Mes passages";
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Mes passages</h1>
            <p class="dashboard-subtitle">
                Historique de vos passages à l’accueil, avec les séjours, traitements, actes et factures associés.
            </p>
        </div>
    </section>

    <div class="card card-large">
        <?php if (empty($passages)): ?>
            <p class="card-info">Vous n’avez encore aucun passage enregistré.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Passage</th>
                        <th>Date / heure</th>
                        <th>Type d’accueil</th>
                        <th>Motif</th>
                        <th>Séjour</th>
                        <th>Traitements</th>
                        <th>Actes</th>
                        <th>Factures</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passages as $p): ?>
                        <?php
                        $nbSejours     = (int)$p['nb_sejours'];
                        $nbTraitements = (int)$p['nb_traitements'];
                        $nbActes       = (int)$p['nb_actes'];
                        $nbFactures    = (int)$p['nb_factures'];

                        $statut = $p['statut'];
                        $classStatut = 'encours';
                        if ($statut === 'TERMINEE') $classStatut = 'terminee';
                        if ($statut === 'ERREUR')   $classStatut = 'erreur';
                        ?>
                        <tr>
                            <td>#<?= (int)$p['session_id'] ?></td>

                            <td><?= htmlspecialchars($p['date_passage']) ?></td>

                            <td>
                                <span class="badge">
                                    <?= htmlspecialchars($p['accueil_code'] . ' - ' . $p['accueil_libelle']) ?>
                                </span>
                            </td>

                            <td style="white-space:normal;max-width:260px;">
                                <?= nl2br(htmlspecialchars($p['motif'])) ?>
                            </td>

                            <td>
                                <?php if ($nbSejours > 0): ?>
                                    <span class="status-pill pill-yes">
                                        OUI (<?= $nbSejours ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="status-pill pill-no">
                                        NON
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge badge-count">
                                    <?= $nbTraitements ?> traitement(s)
                                </span>
                            </td>

                            <td>
                                <span class="badge badge-count">
                                    <?= $nbActes ?> acte(s)
                                </span>
                            </td>

                            <td>
                                <span class="badge badge-count">
                                    <?= $nbFactures ?> facture(s)
                                </span>
                            </td>

                            <td>
                                <span class="status-pill <?= $classStatut ?>">
                                    <?= htmlspecialchars($statut) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
