<?php
/**
 * Page d'accueil administratif.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur est un membre du personnel administratif,
 * - de sélectionner un patient et un type d’accueil,
 * - de saisir les informations de carte vitale,
 * - de créer ou mettre à jour la carte vitale du patient,
 * - d’enregistrer une SESSION de passage patient,
 * - de créer un enregistrement SCAN_CARTE avec le statut de vérification.
 *
 * @package HospitCare
 */

// admin_accueil.php — Gestion des passages patients (accueil administratif)

header('Content-Type: text/html; charset=UTF-8');
session_start();

/**
 * Vérification d'accès :
 * - utilisateur connecté,
 * - rôle "personnel",
 * - type de personnel "ADMINISTRATIF".
 *
 * En cas d'échec, renvoie un code 403 et arrête le script.
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
 * @var int|string
 */
$personnel_admin_id = $_SESSION['user_id'];

require_once __DIR__ . '/secret/database.php';

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
    die('Erreur BD.');
}

/**
 * Tableau des erreurs de validation du formulaire.
 *
 * @var string[]
 */
$errors  = [];

/**
 * Message de succès ou d'information après traitement.
 *
 * @var string
 */
$message = "";

/**
 * Liste des patients pour le sélecteur.
 *
 * @var array<int, array<string, mixed>>
 */
$patients = $pdo->query("
    SELECT patient_id, nom, prenom, nss 
    FROM PATIENT 
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Liste des types d'accueil pour le sélecteur.
 *
 * @var array<int, array<string, mixed>>
 */
$accueils = $pdo->query("
    SELECT accueil_id, accueil_code, libelle 
    FROM ACCUEIL 
    ORDER BY libelle
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Champs du formulaire (initialisation).
 *
 * @var int|string $patient_id
 * @var int|string $accueil_id
 * @var string     $motif
 * @var string     $num_cv
 * @var string     $date_exp
 */
$patient_id = $accueil_id = $motif = $num_cv = $date_exp = "";

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * Récupération des données envoyées par le formulaire.
     *
     * @var int    $patient_id
     * @var int    $accueil_id
     * @var string $motif
     * @var string $num_cv
     * @var string $date_exp
     */
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $accueil_id = (int)($_POST['accueil_id'] ?? 0);
    $motif      = trim($_POST['motif'] ?? '');
    $num_cv     = trim($_POST['numero_carte'] ?? '');
    $date_exp   = trim($_POST['date_expiration'] ?? '');

    // Validations
    if ($patient_id <= 0) $errors[] = "Sélectionner un patient.";
    if ($accueil_id <= 0) $errors[] = "Sélectionner un type d’accueil.";
    if ($motif === '')    $errors[] = "Motif obligatoire.";
    if ($num_cv === '')   $errors[] = "Numéro de carte vitale obligatoire.";
    if ($date_exp === '') $errors[] = "Date d’expiration obligatoire.";

    /**
     * Date d'expiration normalisée au format Y-m-d (ou null si invalide).
     *
     * @var string|null $date_exp_norm
     */
    $date_exp_norm = null;
    if ($date_exp !== "") {
        $ts = strtotime($date_exp);
        if ($ts === false) {
            $errors[] = "Date d’expiration invalide.";
        } else {
            $date_exp_norm = date("Y-m-d", $ts);
        }
    }

    if (empty($errors)) {

        /**
         * 1) Vérifier si le patient possède déjà une entrée dans CARTE_VITALE.
         *
         * @var PDOStatement $stmt
         * @var array<string, mixed>|false $carte
         */
        $stmt = $pdo->prepare("SELECT carte_id FROM CARTE_VITALE WHERE patient_id = :pid");
        $stmt->execute([':pid' => $patient_id]);
        $carte = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * Identifiant de la carte vitale (existante ou nouvellement créée).
         *
         * @var int|string $carte_id
         */
        if ($carte) {
            // UPDATE carte existante
            $carte_id = $carte['carte_id'];
            $stmt = $pdo->prepare("
                UPDATE CARTE_VITALE
                SET numero_carte = :num, date_expiration = :exp
                WHERE carte_id = :id
            ");
            $stmt->execute([
                ':num' => $num_cv,
                ':exp' => $date_exp_norm,
                ':id'  => $carte_id
            ]);
        } else {
            // Nouvelle carte vitale
            $stmt = $pdo->prepare("
                INSERT INTO CARTE_VITALE (numero_carte, date_expiration, statut, patient_id)
                VALUES (:num, :exp, 'ACTIVE', :pid)
                RETURNING carte_id
            ");
            $stmt->execute([
                ':num' => $num_cv,
                ':exp' => $date_exp_norm,
                ':pid' => $patient_id
            ]);
            $carte_id = $stmt->fetchColumn();
        }

        /**
         * Statut de vérification de la carte vitale pour la table SCAN_CARTE.
         *
         * Valeurs possibles :
         * - 'EN_COURS' par défaut,
         * - 'SUCCESS' si la carte est encore valable,
         * - 'ERREUR' si la carte est expirée.
         *
         * @var string $statut_verif
         */
        $statut_verif = 'EN_COURS';
        if ($date_exp_norm !== null) {
            if (strtotime($date_exp_norm) >= strtotime(date('Y-m-d'))) {
                $statut_verif = 'SUCCESS';
            } else {
                $statut_verif = 'ERREUR';
            }
        }

        /**
         * 2) Création d'une SESSION de passage patient.
         *
         * Colonne statut : 'EN_COURS' à la création.
         *
         * @var PDOStatement $stmt
         * @var int|string   $session_id
         */
        $stmt = $pdo->prepare("
            INSERT INTO SESSION (patient_id, accueil_id, personnel_admin_id, date_passage, statut, motif)
            VALUES (:pid, :accueil, :admin, NOW(), 'EN_COURS', :motif)
            RETURNING session_id
        ");

        $stmt->execute([
            ':pid'     => $patient_id,
            ':accueil' => $accueil_id,
            ':admin'   => $personnel_admin_id,
            ':motif'   => $motif
        ]);

        $session_id = $stmt->fetchColumn();

        /**
         * 3) Enregistrement du SCAN_CARTE associé à la session et à la carte vitale.
         *
         * @var PDOStatement $stmtScan
         */
        $stmtScan = $pdo->prepare("
            INSERT INTO SCAN_CARTE (session_id, carte_id, statut_verification)
            VALUES (:session_id, :carte_id, :statut)
        ");
        $stmtScan->execute([
            ':session_id' => $session_id,
            ':carte_id'   => $carte_id,
            ':statut'     => $statut_verif
        ]);

        $message = "Passage enregistré avec succès. Session #{$session_id}";
        
        // Reset champs du formulaire
        $patient_id = $accueil_id = $motif = $num_cv = $date_exp = "";
    }
}

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = "Accueil administratif";
include __DIR__ . "/include/header.inc.php";
?>

<div class="dashboard-page">
    <section class="connexion-section">
        <h1>Accueil administratif</h1>
        <p class="dashboard-subtitle">
            Enregistrement d’un passage patient et vérification de la carte vitale.
        </p>

        <?php if ($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="connexion-form">

            <div class="connexion-fields">
                <label for="patient_id">Patient</label>
                <select name="patient_id" required>
                    <option value="">-- Choisir un patient --</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['patient_id']; ?>" <?= ($p['patient_id'] == $patient_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nom'] . " " . $p['prenom'] . " (" . $p['nss'] . ")") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="accueil_id">Type d’accueil</label>
                <select name="accueil_id" required>
                    <option value="">-- Choisir accueil --</option>
                    <?php foreach ($accueils as $a): ?>
                        <option value="<?= $a['accueil_id']; ?>" <?= ($a['accueil_id'] == $accueil_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['accueil_code'] . " - " . $a['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="motif">Motif du passage</label>
                <textarea name="motif" rows="3" required><?= htmlspecialchars($motif) ?></textarea>
            </div>

            <h3>Carte vitale</h3>

            <div class="connexion-fields">
                <label for="numero_carte">Numéro carte vitale</label>
                <input type="text" name="numero_carte" value="<?= htmlspecialchars($num_cv) ?>" required>
            </div>

            <div class="connexion-fields">
                <label for="date_expiration">Date d’expiration</label>
                <input type="date" name="date_expiration" value="<?= htmlspecialchars($date_exp) ?>" required>
            </div>

            <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                <button type="submit" class="btn-primary">Enregistrer le passage</button>
                <a href="/ajouter_patient.php" class="btn-secondary">+ Nouveau patient</a>
            </div>

        </form>
    </section>
</div>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
