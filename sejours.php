<?php
/**
 * sejours.php — Liste et filtrage des séjours hospitaliers (côté personnel)
 *
 * Rôles :
 * - Personnel médical (dans un service) → accès uniquement aux séjours de son service.
 * - Personnel administratif (sans service) → accès à tous les séjours.
 */

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

/* ========= Connexion BD ========= */

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die('Erreur BD.');
}

/* ========= Détermination du type de personnel =========
 * - Si le personnel a un service_id → personnel médical (filtré par service)
 * - Si service_id NULL → personnel administratif (accès à tout)
 */

$personnelId = (int) $_SESSION['user_id'];
$serviceId   = null;

$sqlService = "SELECT service_id FROM PERSONNEL WHERE personnel_id = :pid";
$stmtService = $pdo->prepare($sqlService);
$stmtService->execute([':pid' => $personnelId]);
$serviceId = $stmtService->fetchColumn();

if ($serviceId !== null) {
    $serviceId = (int)$serviceId; 
}

/* ========= Récupération des filtres ========= */

$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$patientId = (int) ($_GET['patient_id'] ?? 0);

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

if ($serviceId !== null) {
    // Personnel médical → patients de son service
    $patientsFilterSql = "
        SELECT DISTINCT p.patient_id, p.nom, p.prenom
        FROM PATIENT p
        JOIN SEJOUR s ON s.patient_id = p.patient_id
        WHERE s.service_id = :srv
        ORDER BY p.nom, p.prenom
    ";
    $patientsStmt = $pdo->prepare($patientsFilterSql);
    $patientsStmt->execute([':srv' => $serviceId]);
    $patientsFilter = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Personnel administratif → tous les patients
    $patientsFilter = $pdo->query("
        SELECT patient_id, nom, prenom
        FROM PATIENT
        ORDER BY nom, prenom
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "
    SELECT 
        s.sejour_id,
        s.date_debut,
        s.date_fin,
        s.motif,
        p.patient_id,
        p.nom,
        p.prenom,
        sv.libelle AS service_libelle
    FROM SEJOUR s
    JOIN PATIENT p ON p.patient_id = s.patient_id
    LEFT JOIN SERVICE sv ON sv.service_id = s.service_id
    WHERE 1=1
";

$params = [];

if ($serviceId !== null) {
    // Personnel médical → seulement son service
    $sql .= " AND s.service_id = :srv";
    $params[':srv'] = $serviceId;
}

// Filtre par patient
if ($patientId > 0) {
    $sql .= " AND s.patient_id = :pid";
    $params[':pid'] = $patientId;
}

// Filtre par dates
if ($dfSql !== null) {
    $sql .= " AND s.date_debut >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sql .= " AND s.date_debut <= :dt";
    $params[':dt'] = $dtSql;
}

$sql .= " ORDER BY s.date_debut DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sejours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Séjours";
include __DIR__ . "/include/header.inc.php";
?>
<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Séjours</h1>
            <p class="dashboard-subtitle">
                Historique des séjours hospitaliers
                <?= $serviceId !== null ? "des patients de votre service." : "de tous les patients." ?>
            </p>
        </div>
    </section>

    <div class="card card-large filter-card">
        <h2>Filtrer</h2>
        <form method="get" class="filter-bar">
            <div class="field">
                <label for="patient_id">Patient</label>
                <select name="patient_id" id="patient_id">
                    <option value="">
                        <?= $serviceId !== null ? "Tous les patients du service" : "Tous les patients" ?>
                    </option>
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
                <a href="sejours.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card card-large">
        <?php if (empty($sejours)): ?>
            <p class="card-info">Aucun séjour trouvé avec ces filtres.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                <tr>
                    <th>Patient</th>
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
                        <td>
                            <a href="/patient_fiche.php?id=<?= (int)$s['patient_id'] ?>" class="link-patient">
                                <?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?>
                            </a>
                        </td>
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

<?php include __DIR__ . "/include/footer.inc.php"; ?>
