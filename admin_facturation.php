<?php
/**
 * Page de facturation administrative.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur est un membre du personnel administratif,
 * - de générer des factures à partir des séjours non facturés,
 * - de calculer le montant total des actes médicaux d'un séjour,
 * - de marquer des factures comme payées,
 * - de filtrer et lister les dernières factures.
 *
 * @package HospitCare
 */

// admin_facturation.php — Génération et gestion des factures

header('Content-Type: text/html; charset=UTF-8');
session_start();

/**
 * Vérification d'accès :
 * - utilisateur connecté,
 * - rôle "personnel",
 * - type de personnel "ADMINISTRATIF".
 *
 * En cas d'échec : code HTTP 403 + arrêt du script.
 */
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'personnel' ||
    ($_SESSION['personnel_type'] ?? '') !== 'ADMINISTRATIF'
) {
    http_response_code(403);
    die('Accès réservé au personnel administratif.');
}

/**
 * Identifiant du personnel administratif connecté.
 *
 * @var int
 */
$personnel_admin_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/secret/database.php';

// Connexion BD
/**
 * Connexion PDO à la base de données PostgreSQL.
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

/**
 * Tableau des erreurs rencontrées lors du traitement du formulaire.
 *
 * @var string[]
 */
$errors  = [];

/**
 * Message de confirmation / information affiché en haut de page.
 *
 * @var string
 */
$message = '';

//TRAITEMENT POST (création / mise à jour factures)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Génération d'une nouvelle facture
    if (isset($_POST['sejour_id']) && ($_POST['action'] ?? '') === 'creer_facture') {

        /**
         * Identifiant du séjour pour lequel on souhaite générer une facture.
         *
         * @var int $sejour_id
         */
        $sejour_id = (int)$_POST['sejour_id'];

        if ($sejour_id <= 0) {
            $errors[] = "Séjour invalide.";
        } else {
            /**
             * Vérifier que le séjour existe et n'est pas déjà facturé.
             *
             * @var PDOStatement $stmt
             * @var array<string, mixed>|false $sejourExiste
             */
            $stmt = $pdo->prepare("
                SELECT s.sejour_id
                FROM SEJOUR s
                LEFT JOIN FACTURE f ON f.sejour_id = s.sejour_id
                WHERE s.sejour_id = :sid
                  AND f.facture_id IS NULL
            ");
            $stmt->execute([':sid' => $sejour_id]);
            $sejourExiste = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sejourExiste) {
                $errors[] = "Ce séjour est introuvable ou a déjà une facture.";
            } else {
                /**
                 * Calcul du montant total des actes médicaux pour ce séjour.
                 *
                 * @var float $total
                 */
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(cout), 0) AS total
                    FROM ACTE_MEDICAL
                    WHERE sejour_id = :sid
                ");
                $stmt->execute([':sid' => $sejour_id]);
                $total = (float)$stmt->fetchColumn();

                if ($total <= 0) {
                    $errors[] = "Aucun acte médical pour ce séjour, facture impossible.";
                } else {
                    /**
                     * Insertion d'une nouvelle facture en statut EN_ATTENTE.
                     *
                     * @var PDOStatement $stmt
                     * @var int|string   $factureId
                     */
                    $stmt = $pdo->prepare("
                        INSERT INTO FACTURE (sejour_id, personnel_admin_id, montant_total, statut)
                        VALUES (:sid, :admin, :total, 'EN_ATTENTE')
                        RETURNING facture_id
                    ");
                    $stmt->execute([
                        ':sid'   => $sejour_id,
                        ':admin' => $personnel_admin_id,
                        ':total' => $total,
                    ]);

                    $factureId = $stmt->fetchColumn();
                    $message   = "Facture #{$factureId} créée pour le séjour #{$sejour_id} (montant : " .
                                 number_format($total, 2, ',', ' ') . " €).";
                }
            }
        }
    }

    // Marquer une facture comme PAYÉE
    if (isset($_POST['facture_id']) && ($_POST['action'] ?? '') === 'marquer_payee') {
        /**
         * Identifiant de la facture à marquer comme payée.
         *
         * @var int $facture_id
         */
        $facture_id = (int)$_POST['facture_id'];
        if ($facture_id <= 0) {
            $errors[] = "Facture invalide.";
        } else {
            /**
             * Mise à jour du statut de la facture à PAYEE.
             *
             * @var PDOStatement $stmt
             */
            $stmt = $pdo->prepare("
                UPDATE FACTURE
                SET statut = 'PAYEE'
                WHERE facture_id = :fid
            ");
            $stmt->execute([':fid' => $facture_id]);
            $message = "Facture #{$facture_id} marquée comme PAYÉE.";
        }
    }
}

//FILTRES POUR LES DERNIÈRES FACTURES

/**
 * Date de début du filtre (format Y-m-d).
 *
 * @var string
 */
$dateFrom     = trim($_GET['date_from'] ?? '');

/**
 * Date de fin du filtre (format Y-m-d).
 *
 * @var string
 */
$dateTo       = trim($_GET['date_to'] ?? '');

/**
 * Identifiant du patient utilisé dans le filtre.
 *
 * @var int
 */
$patientId    = (int)($_GET['patient_id'] ?? 0);

/**
 * Statut de facture filtré (EN_ATTENTE, PAYEE, ANNULEE ou vide).
 *
 * @var string
 */
$statutFilter = trim($_GET['statut'] ?? '');

/**
 * Dates converties au format datetime SQL (Y-m-d H:i:s) ou null.
 *
 * @var string|null $dfSql
 * @var string|null $dtSql
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

// Liste pour le <select> patient
/**
 * Liste des patients utilisée pour le filtre des factures.
 *
 * @var array<int, array<string, mixed>>
 */
$patientsFilter = $pdo->query("
    SELECT patient_id, nom, prenom
    FROM PATIENT
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);


//SÉJOURS NON FACTURÉS + TOTAL ACTES

/**
 * Séjours non facturés avec total des actes, pour afficher
 * les séjours à facturer.
 *
 * @var array<int, array<string, mixed>>
 */
$sejoursNonFactures = $pdo->query("
    SELECT 
        s.sejour_id,
        s.date_debut,
        s.date_fin,
        s.motif,
        p.patient_id,
        p.nom,
        p.prenom,
        COALESCE(SUM(a.cout), 0) AS total_actes
    FROM SEJOUR s
    JOIN PATIENT p ON p.patient_id = s.patient_id
    LEFT JOIN FACTURE f ON f.sejour_id = s.sejour_id
    LEFT JOIN ACTE_MEDICAL a ON a.sejour_id = s.sejour_id
    WHERE f.facture_id IS NULL
    GROUP BY s.sejour_id, s.date_debut, s.date_fin, s.motif, p.patient_id, p.nom, p.prenom
    HAVING COALESCE(SUM(a.cout), 0) > 0
    ORDER BY s.date_debut DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);


//DERNIÈRES FACTURES AVEC FILTRES

/**
 * Requête de base pour la récupération des dernières factures,
 * avant application des filtres.
 *
 * @var string $sqlFact
 * @var array<string, mixed> $params
 */
$sqlFact = "
    SELECT 
        f.facture_id,
        f.date_emission,
        f.montant_total,
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
    $sqlFact .= " AND p.patient_id = :pid";
    $params[':pid'] = $patientId;
}
if ($dfSql !== null) {
    $sqlFact .= " AND f.date_emission >= :df";
    $params[':df'] = $dfSql;
}
if ($dtSql !== null) {
    $sqlFact .= " AND f.date_emission <= :dt";
    $params[':dt'] = $dtSql;
}
if ($statutFilter !== '') {
    $sqlFact .= " AND f.statut = :statut";
    $params[':statut'] = $statutFilter;
}

$sqlFact .= " ORDER BY f.date_emission DESC LIMIT 100";

/**
 * Exécution de la requête listant les dernières factures filtrées.
 *
 * @var PDOStatement $stmt
 * @var array<int, array<string, mixed>> $dernieresFactures
 */
$stmt = $pdo->prepare($sqlFact);
$stmt->execute($params);
$dernieresFactures = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = "Facturation";
include __DIR__ . "/include/header.inc.php";
?>

<div class="dashboard-page">

    <section class="dashboard-header">
        <div>
            <h1>Facturation</h1>
            <p class="dashboard-subtitle">
                Génération et suivi des factures à partir des séjours et des actes médicaux.
            </p>
        </div>
    </section>

    <div class="connexion-section">
        <?php if ($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- FILTRE SUR LES FACTURES -->
    <div class="card card-large filter-card">
        <h2>Filtrer les factures</h2>
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
                <label for="statut">Statut</label>
                <select name="statut" id="statut">
                    <option value="">Tous</option>
                    <option value="EN_ATTENTE" <?= ($statutFilter === 'EN_ATTENTE') ? 'selected' : '' ?>>En attente</option>
                    <option value="PAYEE" <?= ($statutFilter === 'PAYEE') ? 'selected' : '' ?>>Payée</option>
                    <option value="ANNULEE" <?= ($statutFilter === 'ANNULEE') ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            <div class="field">
                <button type="submit" class="btn-apply">Appliquer</button>
                <a href="admin_facturation.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <section class="dashboard-bottom">
        <!-- Séjours à facturer -->
        <div class="card card-large">
            <h2>Séjours à facturer</h2>

            <?php if (empty($sejoursNonFactures)): ?>
                <p class="card-info">Aucun séjour à facturer (soit déjà facturé, soit sans actes).</p>
            <?php else: ?>
                <table class="table-basic">
                    <thead>
                        <tr>
                            <th>Séjour</th>
                            <th>Patient</th>
                            <th>Dates</th>
                            <th>Motif</th>
                            <th>Total actes (€)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sejoursNonFactures as $s): ?>
                            <tr>
                                <td>
                                    <span class="badge-sejour">
                                        Séjour #<?= (int)$s['sejour_id'] ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/patient_fiche.php?id=<?= (int)$s['patient_id'] ?>" class="link-patient">
                                        <?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= htmlspecialchars($s['date_debut']) ?>
                                    → <?= htmlspecialchars($s['date_fin'] ?? '—') ?>
                                </td>
                                <td><?= htmlspecialchars($s['motif']) ?></td>
                                <td><?= number_format((float)$s['total_actes'], 2, ',', ' ') ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="sejour_id" value="<?= (int)$s['sejour_id'] ?>">
                                        <input type="hidden" name="action" value="creer_facture">
                                        <button type="submit" class="btn-primary btn-small">
                                            Générer facture
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Dernières factures -->
        <div class="card card-large">
            <h2>Dernières factures</h2>

            <?php if (empty($dernieresFactures)): ?>
                <p class="card-info">Aucune facture trouvée avec ces filtres.</p>
            <?php else: ?>
                <table class="table-basic">
                    <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Patient</th>
                            <th>Séjour</th>
                            <th>Montant</th>
                            <th>Date émission</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernieresFactures as $f): ?>
                            <?php
                            /**
                             * Classe CSS utilisée pour styliser le statut de facture.
                             *
                             * @var string $cls
                             */
                            $cls = 'autre';
                            if ($f['statut'] === 'EN_ATTENTE') $cls = 'attente';
                            if ($f['statut'] === 'PAYEE')      $cls = 'payee';
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
                                <td><?= number_format((float)$f['montant_total'], 2, ',', ' ') ?> €</td>
                                <td><?= htmlspecialchars($f['date_emission']) ?></td>
                                <td>
                                    <span class="status-pill <?= $cls ?>">
                                        <?= htmlspecialchars($f['statut']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($f['statut'] === 'EN_ATTENTE'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="facture_id" value="<?= (int)$f['facture_id'] ?>">
                                            <input type="hidden" name="action" value="marquer_payee">
                                            <button type="submit" class="btn-secondary btn-small">
                                                Marquer PAYÉE
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="status-pill autre">Aucune</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
