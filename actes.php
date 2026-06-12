<?php
/**
 * Page de liste des actes médicaux.
 *
 * Cette page :
 * - vérifie que l'utilisateur connecté est un membre du personnel,
 * - applique des filtres (patient, date de début, date de fin),
 * - récupère les actes médicaux correspondants depuis la base PostgreSQL,
 * - affiche les résultats dans un tableau.
 *
 * @package HospitCare
 */

// actes.php — liste des actes médicaux

header('Content-Type: text/html; charset=UTF-8');
session_start();

/**
 * Vérification d'accès :
 * - nécessite une session active,
 * - l'utilisateur doit avoir le rôle "personnel".
 */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'personnel') {
    http_response_code(403);
    die('Accès réservé au personnel.');
}

/**
 * Type de personnel connecté (ex. MEDICAL, ADMIN, etc.).
 *
 * @var string|null
 */
$personnelType = $_SESSION['personnel_type'] ?? null;

/**
 * Identifiant du personnel connecté.
 *
 * @var int
 */
$personnelId   = (int)($_SESSION['user_id'] ?? 0);

require_once __DIR__ . '/secret/database.php';

/**
 * Connexion PDO à la base PostgreSQL.
 *
 * @var PDO $pdo
 */
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die('Erreur BD.');
}

/* ========= Filtres ========= */

/**
 * Date de début saisie dans le filtre (format Y-m-d).
 *
 * @var string
 */
$dateFrom   = trim($_GET['date_from'] ?? '');

/**
 * Date de fin saisie dans le filtre (format Y-m-d).
 *
 * @var string
 */
$dateTo     = trim($_GET['date_to'] ?? '');

/**
 * Identifiant du patient sélectionné dans le filtre.
 *
 * @var int
 */
$patientId  = (int)($_GET['patient_id'] ?? 0);

/**
 * Date de début convertie pour la requête SQL (Y-m-d H:i:s) ou null.
 *
 * @var string|null
 */
$dfSql = $dtSql = null;

if ($dateFrom !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dt) $dfSql = $dt->format('Y-m-d 00:00:00');
}
if ($dateTo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dt) $dtSql = $dt->format('Y-m-d 23:59:59');
}

/**
 * Liste des patients utilisés pour le sélecteur de filtre.
 *
 * @var array<int, array<string, mixed>>
 */
$patientsFilter = $pdo->query("
    SELECT patient_id, nom, prenom
    FROM PATIENT
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

/* ========= Requête actes ========= */

/**
 * Construction de la requête SQL pour les actes médicaux.
 *
 * - Si le personnel est de type MEDICAL : limite aux actes réalisés par lui.
 * - Sinon : renvoie les actes récents pour tous les patients.
 *
 * @var string $sql
 * @var array<string, mixed> $params
 */
if ($personnelType === 'MEDICAL') {
    $sql = "
        SELECT 
            a.acte_id, a.date_acte, a.code_ccam, a.cout,
            s.sejour_id,
            p.patient_id, p.nom, p.prenom
        FROM ACTE_MEDICAL a
        JOIN SEJOUR s ON s.sejour_id = a.sejour_id
        JOIN PATIENT p ON p.patient_id = s.patient_id
        WHERE a.personnel_med_id = :pid
    ";
    $params = [':pid' => $personnelId];
} else {
    $sql = "
        SELECT 
            a.acte_id, a.date_acte, a.code_ccam, a.cout,
            s.sejour_id,
            p.patient_id, p.nom, p.prenom
        FROM ACTE_MEDICAL a
        JOIN SEJOUR s ON s.sejour_id = a.sejour_id
        JOIN PATIENT p ON p.patient_id = s.patient_id
        WHERE 1=1
    ";
    $params = [];
}

if ($patientId > 0) {
    $sql .= " AND p.patient_id = :pidFilter";
    $params[':pidFilter'] = $patientId;
}
if ($dfSql !== null) {
    $sql .= " AND a.date_acte >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sql .= " AND a.date_acte <= :dt";
    $params[':dt'] = $dtSql;
}

$sql .= " ORDER BY a.date_acte DESC LIMIT 200";

/**
 * Préparation et exécution de la requête récupérant les actes médicaux.
 *
 * @var PDOStatement $stmt
 * @var array<int, array<string, mixed>> $actes
 */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$actes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = "Actes médicaux";
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Actes médicaux</h1>
            <p class="dashboard-subtitle">
                <?= ($personnelType === 'MEDICAL') 
                    ? "Actes réalisés par vous." 
                    : "Liste des actes médicaux récents." ?>
            </p>
        </div>
    </section>

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
                <a href="actes.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card card-large">
        <?php if (empty($actes)): ?>
            <p class="card-info">Aucun acte trouvé avec ces filtres.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Acte</th>
                        <th>Patient</th>
                        <th>Séjour</th>
                        <th>Date</th>
                        <th>Code CCAM</th>
                        <th>Coût</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actes as $a): ?>
                        <tr>
                            <td>#<?= (int)$a['acte_id'] ?></td>
                            <td>
                                <a href="/patient_fiche.php?id=<?= (int)$a['patient_id'] ?>" class="link-patient">
                                    <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge-sejour">
                                    Séjour #<?= (int)$a['sejour_id'] ?>
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
