<?php
/**
 * Page "Mes factures" — espace patient.
 *
 * Rôle de ce script :
 * - Vérifier que l'utilisateur connecté est un patient.
 * - Récupérer l'ensemble des factures liées aux séjours du patient.
 * - Permettre un filtrage simple par période (date de début / date de fin).
 * - Afficher les factures sous forme de tableau : séjour, date d'émission, montant, statut.
 *
 * Fonctionnement :
 * 1) Contrôle d'accès via la session (rôle = patient).
 * 2) Connexion à la base PostgreSQL via PDO.
 * 3) Construction de la requête SQL avec filtres optionnels sur les dates.
 * 4) Exécution de la requête et récupération des résultats dans $factures.
 * 5) Affichage du formulaire de filtre + tableau des factures.
 *
 * Variables principales :
 * @var int        $patientId  Identifiant du patient connecté (depuis la session)
 * @var PDO        $pdo        Connexion PDO à la base de données
 * @var string     $dateFrom   Date de début de filtrage (AAAA-MM-JJ) ou chaîne vide
 * @var string     $dateTo     Date de fin de filtrage (AAAA-MM-JJ) ou chaîne vide
 * @var string|null $dfSql     Date de début au format SQL (YYYY-mm-dd 00:00:00) ou null
 * @var string|null $dtSql     Date de fin au format SQL (YYYY-mm-dd 23:59:59) ou null
 * @var array<int,array<string,mixed>> $factures Liste des factures du patient
 *
 * @package HospitCare
 */

// patient_factures.php — liste des factures du patient connecté

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Vérification : uniquement patient
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'patient'
) {
    http_response_code(403);
    die("Accès réservé aux patients.");
}

/**
 * Identifiant du patient connecté (récupéré depuis la session).
 *
 * @var int $patientId
 */
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

/* ========= Filtres simples  ========= */

/**
 * Filtres de date transmis en GET : "date_from" et "date_to".
 *
 * @var string $dateFrom
 * @var string $dateTo
 * @var string|null $dfSql
 * @var string|null $dtSql
 */
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$dfSql = $dtSql = null;

if ($dateFrom !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dt) $dfSql = $dt->format('Y-m-d 00:00:00');
}
if ($dateTo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dt) $dtSql = $dt->format('Y-m-d 23:59:59');
}

/* ========= Requête factures du patient ========= */

/**
 * Construction de la requête SQL pour lister les factures du patient,
 * avec application des filtres de date si présents.
 *
 * @var string $sql
 * @var array<string,mixed> $params
 */
$sql = "
    SELECT 
        f.facture_id,
        f.montant_total,
        f.date_emission,
        f.statut,
        s.sejour_id
    FROM FACTURE f
    JOIN SEJOUR s ON s.sejour_id = f.sejour_id
    WHERE s.patient_id = :pid
";
$params = [':pid' => $patientId];

if ($dfSql !== null) {
    $sql .= " AND f.date_emission >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sql .= " AND f.date_emission <= :dt";
    $params[':dt'] = $dtSql;
}

$sql .= " ORDER BY f.date_emission DESC";

/**
 * Préparation et exécution de la requête,
 * récupération de la liste des factures dans $factures.
 *
 * @var PDOStatement $stmt
 * @var array<int,array<string,mixed>> $factures
 */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string $pageTitle
 */
$pageTitle = "Mes factures";
include __DIR__ . "/include/header.inc.php";
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Mes factures</h1>
            <p class="dashboard-subtitle">
                Historique de vos factures liées à vos séjours.
            </p>
        </div>
    </section>

    <!-- Filtre dates -->
    <div class="card card-large filter-card">
        <h2>Filtrer</h2>
        <form method="get" class="filter-bar">
            <div class="field">
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="field">
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="field">
                <button type="submit" class="btn-apply">Appliquer</button>
                <a href="patient_factures.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card card-large">
        <?php if (empty($factures)): ?>
            <p class="card-info">Aucune facture trouvée avec ces critères.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Séjour</th>
                        <th>Date émission</th>
                        <th>Montant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($factures as $f): ?>
                        <?php
                        $statut = $f['statut'];
                        $cls = 'autre';
                        if ($statut === 'EN_ATTENTE') $cls = 'attente';
                        if ($statut === 'PAYEE')      $cls = 'paye';
                        ?>
                        <tr>
                            <td>#<?= (int)$f['facture_id'] ?></td>
                            <td>
                                <span class="badge-sejour">
                                    Séjour #<?= (int)$f['sejour_id'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($f['date_emission']) ?></td>
                            <td><?= number_format((float)$f['montant_total'], 2, ',', ' ') ?> €</td>
                            <td>
                                <span class="status-pill <?= $cls ?>">
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

<?php include __DIR__ . "/include/footer.inc.php"; ?>
