<?php
/**
 * patients_presents.php — Liste des patients actuellement présents (sessions EN_COURS).
 *
 * Rôle du script :
 * - Vérifier que l’utilisateur connecté est un membre du personnel.
 * - Récupérer la liste des patients ayant une session d’accueil avec statut `EN_COURS`.
 * - Permettre le filtrage par :
 *      • patient (sélection dans un <select>),
 *      • date d’arrivée (intervalle [date_from, date_to]).
 * - Afficher un tableau récapitulatif :
 *      • identité du patient (nom, prénom, NSS),
 *      • type d’accueil (code + libellé),
 *      • motif du passage,
 *      • date/heure d’arrivée,
 *      • statut (présent),
 *      • action pour pointer le départ (formulaire vers pointer_depart.php).
 * - Afficher un éventuel message de confirmation après enregistrement d’un départ
 *   via le paramètre GET `depart` (ok / err).
 *
 * Accès :
 * - Réservé au rôle `personnel`.
 *
 * Paramètres GET :
 * - patient_id : filtre sur un patient précis.
 * - date_from  : date minimale d’arrivée (format Y-m-d).
 * - date_to    : date maximale d’arrivée (format Y-m-d).
 * - depart     : message flash après action de départ (ok / err).
 *
 * Variables principales :
 * @var PDO   $pdo               Connexion PDO PostgreSQL.
 * @var array $patientsFilter    Liste de tous les patients pour le filtre.
 * @var array $patientsPresents  Liste des sessions EN_COURS filtrées.
 * @var string $flash            Message d’information sur un départ (optionnel).
 * @var string $dateFrom         Début de plage de filtre (texte brut).
 * @var string $dateTo           Fin de plage de filtre (texte brut).
 * @var int    $patientId        Patient filtré (0 = tous).
 *
 * Tables utilisées :
 * - PATIENT
 * - SESSION
 * - ACCUEIL
 *
 * @package HospitCare
 */

// patients_presents.php — liste des patients ayant une session EN_COURS

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

// Récupérer message éventuel (après départ)
$flash = '';
if (isset($_GET['depart']) && $_GET['depart'] === 'ok') {
    $flash = "Le départ du patient a bien été enregistré.";
} elseif (isset($_GET['depart']) && $_GET['depart'] === 'err') {
    $flash = "Impossible d’enregistrer le départ (session introuvable).";
}

/* ========= Filtres ========= */

$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$patientId  = (int)($_GET['patient_id'] ?? 0);

$dfSql = $dtSql = null;

if ($dateFrom !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dt) $dfSql = $dt->format('Y-m-d 00:00:00');
}
if ($dateTo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dt) $dtSql = $dt->format('Y-m-d 23:59:59');
}

$patientsFilter = $pdo->query("
    SELECT patient_id, nom, prenom
    FROM PATIENT
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

/* ========= Récupérer les patients présents ========= */

$sql = "
    SELECT 
        se.session_id,
        se.date_passage,
        se.motif,
        p.patient_id,
        p.nom,
        p.prenom,
        p.nss,
        a.libelle AS accueil_libelle,
        a.accueil_code
    FROM SESSION se
    JOIN PATIENT p ON p.patient_id = se.patient_id
    JOIN ACCUEIL a ON a.accueil_id = se.accueil_id
    WHERE se.statut = 'EN_COURS'
";
$params = [];

if ($patientId > 0) {
    $sql .= " AND p.patient_id = :pid";
    $params[':pid'] = $patientId;
}
if ($dfSql !== null) {
    $sql .= " AND se.date_passage >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sql .= " AND se.date_passage <= :dt";
    $params[':dt'] = $dtSql;
}

$sql .= " ORDER BY se.date_passage DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patientsPresents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Patients présents";
include __DIR__ . "/include/header.inc.php";
?>
<div class="dashboard-page">

    <section class="dashboard-header">
        <div>
            <h1>Patients présents</h1>
            <p class="dashboard-subtitle">
                Patients ayant une session d’accueil <strong>EN_COURS</strong>.
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
                <label for="date_from">Arrivé après le</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="field">
                <label for="date_to">Arrivé avant le</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="field">
                <button type="submit" class="btn-apply">Appliquer</button>
                <a href="patients_presents.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card card-large">

        <?php if ($flash): ?>
            <div class="alert-success">
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($patientsPresents)): ?>
            <p class="card-info">Aucun patient présent actuellement avec ces filtres.</p>
        <?php else: ?>

            <table class="table-basic">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Type d’accueil</th>
                        <th>Motif</th>
                        <th>Arrivée</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($patientsPresents as $p): ?>
                    <tr>
                        <td>
                            <div class="patient-name">
                                <a href="/patient_fiche.php?id=<?= (int)$p['patient_id'] ?>" class="link-patient">
                                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                </a>
                            </div>
                            <div class="patient-nss">
                                NSS : <?= htmlspecialchars($p['nss']) ?>
                            </div>
                        </td>

                        <td>
                            <span class="badge">
                                <?= htmlspecialchars($p['accueil_code'] . ' · ' . $p['accueil_libelle']) ?>
                            </span>
                        </td>

                        <td><?= nl2br(htmlspecialchars($p['motif'])) ?></td>

                        <td><?= htmlspecialchars($p['date_passage']) ?></td>

                        <td>
                            <span class="status-pill ouverte">Présent</span>
                        </td>

                        <td class="actions-col">
                            <form method="post" action="/pointer_depart.php"
                                  onsubmit="return confirm('Confirmer le départ de ce patient ?');">
                                <input type="hidden" name="session_id" value="<?= (int)$p['session_id'] ?>">
                                <button class="btn-small btn-danger" type="submit">
                                    Patient parti
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
