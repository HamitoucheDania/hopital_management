<?php
/**
 * traitements.php — Liste des traitements (côté personnel)
 *
 * Rôle :
 * -------
 * Cette page permet au personnel hospitalier de :
 *  - Consulter tous les traitements enregistrés dans la base.
 *  - Filtrer les traitements par :
 *        • patient
 *        • date de début
 *        • date de fin
 *  - Visualiser pour chaque traitement :
 *        • le patient concerné
 *        • le médicament
 *        • le dosage
 *        • les dates début / fin
 *        • le statut (ACTIF / TERMINÉ)
 *
 * Sécurité :
 * ----------
 * Accès strict aux utilisateurs connectés ayant :
 *      $_SESSION['user_role'] === 'personnel'
 * Sinon → HTTP 403 et fin d’exécution.
 *
 * Fonctionnement :
 * ----------------
 * 1. Récupération des filtres éventuels envoyés par GET :
 *      - patient_id (int)
 *      - date_from (Y-m-d)
 *      - date_to (Y-m-d)
 *
 * 2. Conversion des dates en format SQL :
 *      - date_from → Y-m-d 00:00:00
 *      - date_to   → Y-m-d 23:59:59
 *
 * 3. Récupération des patients (pour le <select> du filtre).
 *
 * 4. Requête principale sur TRAITEMENT + PATIENT :
 *      • Filtrage dynamique en fonction des paramètres
 *      • Tri par date_debut DESC
 *
 * 5. Calcul du statut :
 *      ACTIF    → date_fin NULL ou ≥ aujourd’hui
 *      TERMINÉ  → date_fin < aujourd’hui
 *
 * Tables utilisées :
 * ------------------
 *  - PATIENT      (patient_id, nom, prenom, …)
 *  - TRAITEMENT   (traitement_id, patient_id, nom_medicament, dosage, date_debut, date_fin, …)
 *
 * Variables principales :
 * -----------------------
 *  @var int      $patientId     Filtre sur l'identifiant patient (ou 0 = tous).
 *  @var string   $dateFrom      Début période (ou vide).
 *  @var string   $dateTo        Fin période (ou vide).
 *  @var array    $patientsFilter Liste des patients pour le filtre.
 *  @var array    $traitements    Résultat final des traitements filtrés.
 *
 * Interfaces :
 * ------------
 *  - Formulaire de filtres (patient + dates)
 *  - Tableau listant chaque traitement
 *  - LIEN vers la fiche patient : /patient_fiche.php?id=XX
 *
 * Ce fichier ne modifie jamais la base : uniquement lecture.
 */
 
// traitements.php — liste des traitements

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

/* ========= Requête traitements ========= */

$sql = "
    SELECT 
        t.traitement_id,
        t.nom_medicament,
        t.dosage,
        t.date_debut,
        t.date_fin,
        p.patient_id,
        p.nom,
        p.prenom
    FROM TRAITEMENT t
    JOIN PATIENT p ON p.patient_id = t.patient_id
    WHERE 1=1
";
$params = [];

if ($patientId > 0) {
    $sql .= " AND t.patient_id = :pid";
    $params[':pid'] = $patientId;
}
if ($dfSql !== null) {
    $sql .= " AND t.date_debut >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sql .= " AND t.date_debut <= :dt";
    $params[':dt'] = $dtSql;
}

$sql .= " ORDER BY t.date_debut DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$traitements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Traitements";
include __DIR__ . "/include/header.inc.php";
?>

<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Traitements</h1>
            <p class="dashboard-subtitle">
                Liste des traitements prescrits aux patients.
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
                <a href="traitements.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card card-large">
        <?php if (empty($traitements)): ?>
            <p class="card-info">Aucun traitement trouvé avec ces filtres.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                <tr>
                    <th>Patient</th>
                    <th>Médicament</th>
                    <th>Dosage</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($traitements as $t): ?>
                    <?php
                    $enCours = ($t['date_fin'] === null || $t['date_fin'] === '')
                        || (strtotime($t['date_fin']) >= strtotime(date('Y-m-d')));
                    ?>
                    <tr>
                        <td>
                            <a href="/patient_fiche.php?id=<?= (int)$t['patient_id'] ?>" class="link-patient">
                                <?= htmlspecialchars($t['prenom'] . ' ' . $t['nom']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($t['nom_medicament']) ?></td>
                        <td><?= htmlspecialchars($t['dosage']) ?></td>
                        <td><?= htmlspecialchars($t['date_debut']) ?></td>
                        <td><?= htmlspecialchars($t['date_fin'] ?? '—') ?></td>
                        <td>
                            <?php if ($enCours): ?>
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

<?php include __DIR__ . "/include/footer.inc.php"; ?>
