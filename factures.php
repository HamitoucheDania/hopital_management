<?php
/**
 * factures.php — Liste des factures côté personnel.
 *
 * Page réservée au personnel (médical ou administratif) permettant :
 * - de filtrer les factures par patient et par période (dates),
 * - d’afficher la liste complète des factures correspondantes,
 * - de visualiser les informations associées : montant, date, statut, séjour, patient.
 *
 * Sécurisation :
 * - L’accès est interdit si l’utilisateur n’est pas authentifié en tant que personnel.
 *
 * Fonctionnement général :
 * - Récupération des filtres GET (dates, patient),
 * - Construction dynamique de la requête SQL,
 * - Exécution et récupération des résultats,
 * - Affichage d’un tableau complet trié par date d’émission (desc).
 *
 * Variables principales :
 * @var PDO    $pdo              Connexion à la base de données
 * @var array  $patientsFilter   Liste des patients pour le <select>
 * @var array  $factures         Factures correspondant aux filtres
 * @var string $dateFrom         Date minimale (AAAA-MM-JJ)
 * @var string $dateTo           Date maximale (AAAA-MM-JJ)
 * @var int    $patientId        ID du patient filtré
 *
 * @package HospitCare
 */

// factures.php — liste des factures (côté personnel)

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'personnel'
) {
    http_response_code(403);
    die("Accès réservé au personnel.");
}

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

/* ========= Filtres ========= */

$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$patientId  = (int)($_GET['patient_id'] ?? 0);

$dfSql = $dtSql = null;

if ($dateFrom !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dt) {
        $dfSql = $dt->format('Y-m-d 00:00:00');
    }
}
if ($dateTo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dt) {
        $dtSql = $dt->format('Y-m-d 23:59:59');
    }
}

// Liste des patients pour le filtre
$patientsFilter = $pdo->query("
    SELECT patient_id, nom, prenom
    FROM PATIENT
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

/* ========= Requête factures ========= */

$sql = "
    SELECT 
        f.facture_id,
        f.montant_total,
        f.date_emission,
        f.statut,
        s.sejour_id,
        p.patient_id,
        p.nom,
        p.prenom
    FROM FACTURE f
    JOIN SEJOUR s ON s.sejour_id = f.sejour_id
    JOIN PATIENT p ON p.patient_id = s.patient_id
    WHERE 1=1
";
$params = [];

if ($patientId > 0) {
    $sql .= " AND p.patient_id = :pid";
    $params[':pid'] = $patientId;
}
if ($dfSql !== null) {
    $sql .= " AND f.date_emission >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sql .= " AND f.date_emission <= :dt";
    $params[':dt'] = $dtSql;
}

$sql .= " ORDER BY f.date_emission DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Factures";
include __DIR__ . "/include/header.inc.php";
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Factures</h1>
            <p class="dashboard-subtitle">
                Liste des factures liées aux séjours des patients.
            </p>
        </div>
    </section>

    <!-- Filtres -->
    <div class="card card-large filter-card">
        <h2>Filtrer</h2>
        <form method="get" class="filter-bar">
            <div class="field">
                <label for="patient_id">Patient</label>
                <select name="patient_id" id="patient_id">
                    <option value="">Tous les patients</option>
                    <?php foreach ($patientsFilter as $p): ?>
                        <option value="<?= (int)$p['patient_id'] ?>"
                            <?= ($patientId === (int)$p['patient_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                <a href="factures.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- Tableau -->
    <section class="card card-large">
        <?php if (empty($factures)): ?>
            <p class="card-info">Aucune facture trouvée avec ces filtres.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Patient</th>
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
                                <a href="/patient_fiche.php?id=<?= (int)$f['patient_id'] ?>" class="link-patient">
                                    <?= htmlspecialchars($f['prenom'] . ' ' . $f['nom']) ?>
                                </a>
                            </td>
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
    </section>
</div>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
