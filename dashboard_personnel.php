<?php
/**
 * Tableau de bord du personnel (médical + administratif).
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur connecté est bien un membre du personnel,
 * - de récupérer le type de personnel (ADMINISTRATIF / MEDICAL),
 * - de calculer plusieurs indicateurs globaux (patients, séjours, sessions, factures),
 * - d'afficher quelques statistiques de l'hôpital,
 * - de lister les derniers séjours enregistrés.
 *
 * @package HospitCare
 */

// dashboard_personnel.php — tableau de bord du personnel (médical + administratif)

session_start();

// Vérification que l'utilisateur est un personnel
/**
 * Contrôle d'accès :
 * - nécessite un utilisateur connecté,
 * - le rôle doit être "personnel".
 *
 * En cas d'échec : HTTP 403 + arrêt du script.
 */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'personnel') {
    http_response_code(403);
    die('Accès réservé au personnel.');
}

/**
 * Type de personnel (ADMINISTRATIF ou MEDICAL), récupéré depuis la session.
 *
 * @var string $personnelType
 */
$personnelType = $_SESSION['personnel_type'] ?? '';

require_once __DIR__ . '/secret/database.php';

// Connexion PDO
/**
 * Connexion à la base de données PostgreSQL via PDO.
 *
 * @var PDO $pdo
 */
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}

//Récupération des données statistiques

/**
 * Nombre total de patients enregistrés dans la table PATIENT.
 *
 * @var int|string $patientsCount
 */
$patientsCount = $pdo->query("SELECT COUNT(*) FROM PATIENT")->fetchColumn();

/**
 * Nombre de séjours en cours :
 * - date_fin NULL ou
 * - date_fin >= NOW()
 *
 * @var int|string $sejoursCount
 */
$sejoursCount = $pdo->query("
    SELECT COUNT(*) 
    FROM SEJOUR 
    WHERE date_fin IS NULL OR date_fin >= NOW()
")->fetchColumn();

/**
 * Nombre de sessions d’accueil en cours
 * (statut = 'EN_COURS' dans la table SESSION).
 *
 * @var int|string $sessionsCount
 */
$sessionsCount = $pdo->query("
    SELECT COUNT(*) 
    FROM SESSION 
    WHERE statut = 'EN_COURS'
")->fetchColumn();

/**
 * Nombre de factures en attente (statut = 'EN_ATTENTE').
 *
 * @var int|string $facturesCount
 */
$facturesCount = $pdo->query("
    SELECT COUNT(*) 
    FROM FACTURE 
    WHERE statut = 'EN_ATTENTE'
")->fetchColumn();

/**
 * Derniers séjours créés, avec nom/prénom du patient associé.
 *
 * @var array<int, array<string,mixed>> $derniersSejours
 */
$derniersSejours = $pdo->query("
    SELECT s.sejour_id, s.date_debut, s.date_fin, s.motif,
           p.nom, p.prenom
    FROM SEJOUR s
    JOIN PATIENT p ON p.patient_id = s.patient_id
    ORDER BY s.date_debut DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string $pageTitle
 */
$pageTitle = 'Tableau de bord personnel';
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">

    <section class="dashboard-header">
        <div>
            <h1>Tableau de bord du personnel</h1>
            <p class="dashboard-subtitle">
                Vue globale de l’activité de l’hôpital.
            </p>

            <?php if ($personnelType === 'ADMINISTRATIF'): ?>
                <div class="role-banner badge-admin">
                    <span class="role-banner-dot"></span>
                    <span>Profil&nbsp;: Personnel administratif</span>
                </div>
            <?php elseif ($personnelType === 'MEDICAL'): ?>
                <div class="role-banner">
                    <span class="role-banner-dot"></span>
                    <span>Profil&nbsp;: Personnel médical</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-actions">
            <?php if ($personnelType === 'ADMINISTRATIF'): ?>
                <a href="/admin_accueil.php" class="btn-primary">Enregistrer un passage</a>
            <?php endif; ?>
        </div>
    </section>

    <div class="dashboard-grid">
        <article class="card card-stat stat-patients">
            <h2>Patients</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Patients enregistrés</span>
            </div>
            <p class="card-number"><?= (int)$patientsCount ?></p>
            <p class="card-info">Comptes patients présents dans le système.</p>
        </article>

        <article class="card card-stat stat-sejours">
            <h2>Séjours en cours</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Hospitalisations actives</span>
            </div>
            <p class="card-number"><?= (int)$sejoursCount ?></p>
            <p class="card-info">Patients actuellement hospitalisés.</p>
        </article>

        <article class="card card-stat stat-passages">
            <h2>Passages en cours</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Accueil / admissions</span>
            </div>
            <p class="card-number"><?= (int)$sessionsCount ?></p>
            <p class="card-info">Patients en cours de prise en charge à l’accueil.</p>
        </article>

        <article class="card card-stat stat-factures">
            <h2>Factures en attente</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>À traiter en facturation</span>
            </div>
            <p class="card-number"><?= (int)$facturesCount ?></p>
            <p class="card-info">Factures non encore réglées ou validées.</p>
        </article>
    </div>

    <section class="dashboard-bottom">
        <div class="card card-large">
            <h2>Derniers séjours</h2>

            <?php if (empty($derniersSejours)): ?>
                <p class="card-info">Aucun séjour récent.</p>
            <?php else: ?>
                <ul class="list-simple">
                    <?php foreach ($derniersSejours as $s): ?>
                        <li>
                            <strong><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?></strong>
                            <span class="stay-tag">Séjour #<?= (int)$s['sejour_id'] ?></span>
                            <div class="stay-dates">
                                <?= htmlspecialchars($s['date_debut']) ?> → <?= htmlspecialchars($s['date_fin'] ?? '—') ?>
                            </div>
                            <div class="stay-motif">
                                <?= nl2br(htmlspecialchars($s['motif'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
