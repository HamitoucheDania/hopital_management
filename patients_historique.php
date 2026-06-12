<?php
/**
 * patients_historique.php — Liste et historique des patients (espace personnel).
 *
 * Rôle du script :
 * - Vérifier que l’utilisateur connecté est un membre du personnel.
 * - Charger la liste des patients de l’établissement avec plusieurs indicateurs :
 *      • premier_passage  : première date de passage (SESSION),
 *      • dernier_passage  : dernière date de passage,
 *      • nb_sejours       : nombre total de séjours (SEJOUR),
 *      • nb_traitements   : nombre total de traitements (TRAITEMENT),
 *      • nb_factures      : nombre total de factures liées (FACTURE + SEJOUR).
 * - Appliquer des filtres :
 *      • recherche texte sur nom / prénom / NSS,
 *      • plage de dates sur les passages à l’accueil.
 * - Permettre un tri dynamique sur plusieurs colonnes (nom, dernier passage, nb séjours, etc.).
 * - Afficher la liste des patients avec lien vers la fiche détaillée (patient_fiche.php).
 *
 * Sécurité / Accès :
 * - Accès réservé aux utilisateurs avec rôle `personnel`.
 *
 * Paramètres GET :
 * - q          : chaîne de recherche (nom, prénom, NSS).
 * - date_from  : date de début de filtre sur les passages (format Y-m-d).
 * - date_to    : date de fin de filtre sur les passages (format Y-m-d).
 * - sort       : clé de tri logique (nom, dernier_passage, nb_sejours, nb_traitements, nb_factures).
 * - dir        : direction du tri (ASC ou DESC).
 *
 * Variables principales :
 * @var PDO   $pdo        Connexion PDO à la base PostgreSQL.
 * @var array $patients   Liste des patients avec statistiques agrégées.
 * @var string $q         Terme de recherche actuel.
 * @var string $dateFrom  Date de début de filtre sur les passages.
 * @var string $dateTo    Date de fin de filtre sur les passages.
 * @var string $sort      Colonne logique utilisée pour le tri.
 * @var string $dir       Direction de tri (ASC/DESC).
 *
 * Fonctions :
 * - sortLink(string $label, string $col, string $currentSort, string $currentDir) : génère un lien de tri
 *   dans l’en-tête du tableau en conservant les filtres actuels.
 *
 * Tables principales utilisées :
 * - PATIENT
 * - SESSION
 * - SEJOUR
 * - TRAITEMENT
 * - FACTURE
 *
 * @package HospitCare
 */

// patients_historique.php — liste des patients + stats + filtres

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

/* ========= Filtres & tri ========= */

$q         = trim($_GET['q'] ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$sort      = $_GET['sort'] ?? 'nom';
$dir       = strtoupper($_GET['dir'] ?? 'ASC');

$allowedSort = [
    'nom'             => 'p.nom, p.prenom',
    'dernier_passage' => 'dernier_passage',
    'nb_sejours'      => 'nb_sejours',
    'nb_traitements'  => 'nb_traitements',
    'nb_factures'     => 'nb_factures'
];
$orderBy = $allowedSort[$sort] ?? $allowedSort['nom'];
$dir     = ($dir === 'DESC') ? 'DESC' : 'ASC';

/* ========= Construction de la requête =========
   Pour éviter les duplications, on utilise des sous-requêtes par patient.
*/

$sql = "
    SELECT
        p.patient_id,
        p.nom,
        p.prenom,
        p.nss,
        p.date_naissance,
        p.sexe,
        p.email,
        -- dates de premiers / derniers passages
        (SELECT MIN(s.date_passage)
           FROM SESSION s
          WHERE s.patient_id = p.patient_id) AS premier_passage,
        (SELECT MAX(s.date_passage)
           FROM SESSION s
          WHERE s.patient_id = p.patient_id) AS dernier_passage,
        -- nombre de séjours
        (SELECT COUNT(*)
           FROM SEJOUR se
          WHERE se.patient_id = p.patient_id) AS nb_sejours,
        -- nombre de traitements
        (SELECT COUNT(*)
           FROM TRAITEMENT t
          WHERE t.patient_id = p.patient_id) AS nb_traitements,
        -- nombre de factures
        (SELECT COUNT(*)
           FROM FACTURE f
           JOIN SEJOUR se2 ON se2.sejour_id = f.sejour_id
          WHERE se2.patient_id = p.patient_id) AS nb_factures
    FROM PATIENT p
    WHERE 1=1
";

$params = [];

// filtre recherche texte (nom/prenom/NSS)
if ($q !== '') {
    $sql .= " AND (
        p.nom ILIKE :q
        OR p.prenom ILIKE :q
        OR p.nss ILIKE :q
    )";
    $params[':q'] = '%' . $q . '%';
}

// filtre par dates de passage (SESSION)
if ($dateFrom !== '' && $dateTo !== '') {
    $sql .= " AND EXISTS (
        SELECT 1 FROM SESSION s2
        WHERE s2.patient_id = p.patient_id
          AND s2.date_passage BETWEEN :df AND :dt
    )";
    $params[':df'] = $dateFrom . ' 00:00:00';
    $params[':dt'] = $dateTo   . ' 23:59:59';
}

$sql .= " ORDER BY $orderBy $dir, p.nom, p.prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Historique patients";
include __DIR__ . "/include/header.inc.php";
?>
<div class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Historique des patients</h1>
            <p class="dashboard-subtitle">
                Tous les patients passés par l’établissement, avec leurs passages, séjours, traitements et factures.
            </p>
        </div>
    </section>

    <div class="card card-large">

        <form method="get" class="filters-row">
            <div class="field">
                <label>Recherche (nom, prénom ou NSS)</label>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="field">
                <label>Passages du</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="field">
                <label>au</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="field">
                <button type="submit">Filtrer</button>
                <?php if ($q || $dateFrom || $dateTo): ?>
                    <a href="patients_historique.php" class="reset-link">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($patients)): ?>
            <p class="card-info" style="margin-top:1rem;">
                Aucun patient ne correspond aux critères.
            </p>
        <?php else: ?>

            <?php
            // helper pour liens tri
            function sortLink(string $label, string $col, string $currentSort, string $currentDir): string {
                $dir = 'ASC';
                $arrow = '';
                if ($currentSort === $col) {
                    if ($currentDir === 'ASC') {
                        $dir = 'DESC';
                        $arrow = '▲';
                    } else {
                        $dir = 'ASC';
                        $arrow = '▼';
                    }
                }
                $qs = $_GET;
                $qs['sort'] = $col;
                $qs['dir']  = $dir;
                $href = 'patients_historique.php?' . http_build_query($qs);
                return '<a class="sortable" href="' . htmlspecialchars($href) . '">' .
                        htmlspecialchars($label) .
                        '<span class="order">' . htmlspecialchars($arrow) . '</span></a>';
            }
            ?>

            <table class="table-basic">
                <thead>
                <tr>
                    <th><?= sortLink('Patient', 'nom', $sort, $dir) ?></th>
                    <th>NSS</th>
                    <th><?= sortLink('Dernier passage', 'dernier_passage', $sort, $dir) ?></th>
                    <th><?= sortLink('Séjours', 'nb_sejours', $sort, $dir) ?></th>
                    <th><?= sortLink('Traitements', 'nb_traitements', $sort, $dir) ?></th>
                    <th><?= sortLink('Factures', 'nb_factures', $sort, $dir) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td>
                            <div class="patient-name">
                                <a href="/patient_fiche.php?id=<?= (int)$p['patient_id'] ?>">
                                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                </a>
                            </div>
                            <div class="patient-nss">
                                <?= htmlspecialchars($p['email']) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($p['nss']) ?></td>
                        <td>
                            <?php if ($p['dernier_passage']): ?>
                                <span class="pill">
                                    <?= htmlspecialchars($p['dernier_passage']) ?>
                                </span>
                            <?php else: ?>
                                <span class="pill">Jamais venu</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= (int)$p['nb_sejours'] ?></span></td>
                        <td><span class="badge"><?= (int)$p['nb_traitements'] ?></span></td>
                        <td><span class="badge"><?= (int)$p['nb_factures'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
