<?php
/**
 * Page d'ajout d'un acte médical.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur est un membre du personnel médical,
 * - de lister les séjours disponibles,
 * - de saisir les informations d'un acte médical (code CCAM, date, coût),
 * - d'enregistrer l'acte dans la table ACTE_MEDICAL.
 *
 * @package HospitCare
 */

// ajouter_acte.php — ajout d’un acte médical

header('Content-Type: text/html; charset=UTF-8');
session_start();

/**
 * Vérification d'accès :
 * - utilisateur connecté,
 * - rôle "personnel",
 * - type de personnel "MEDICAL".
 *
 * En cas d'échec : code HTTP 403 + arrêt du script.
 */
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'personnel' ||
    ($_SESSION['personnel_type'] ?? '') !== 'MEDICAL'
) {
    http_response_code(403);
    die('Accès réservé au personnel médical.');
}

require_once __DIR__ . '/secret/database.php';

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
 * Identifiant du patient éventuellement passé via GET ou POST.
 *
 * @var int $patient_id
 */
$patient_id = (int)($_GET['patient_id'] ?? ($_POST['patient_id'] ?? 0));

/**
 * Tableau des erreurs de validation ou d'exécution.
 *
 * @var string[]
 */
$errors  = [];

/**
 * Message de succès ou d'information affiché à l'utilisateur.
 *
 * @var string
 */
$message = '';

/**
 * Liste des séjours utilisés pour le <select>.
 * Chaque entrée contient les infos de séjour + nom/prénom du patient.
 *
 * @var array<int, array<string, mixed>>
 */
$sejours = $pdo->query("
    SELECT s.sejour_id, s.date_debut, s.date_fin, s.motif,
           p.nom, p.prenom
    FROM SEJOUR s
    JOIN PATIENT p ON p.patient_id = s.patient_id
    ORDER BY s.date_debut DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Champs du formulaire (valeurs courantes / par défaut).
 *
 * @var int|string $sejour_id
 * @var string     $code_ccam
 * @var string     $date_acte
 * @var string     $cout_raw
 */
$sejour_id   = '';
$code_ccam   = '';
$date_acte   = '';
$cout_raw    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * Récupération des données envoyées par le formulaire.
     *
     * @var int    $sejour_id
     * @var string $code_ccam
     * @var string $date_acte
     * @var string $cout_raw
     * @var int    $personnel_med_id
     */
    $sejour_id = (int)($_POST['sejour_id'] ?? 0);
    $code_ccam = trim($_POST['code_ccam'] ?? '');
    $date_acte = trim($_POST['date_acte'] ?? '');
    $cout_raw  = trim($_POST['cout'] ?? '');
    $personnel_med_id = (int)$_SESSION['user_id'];

    // Validations de base
    if ($sejour_id <= 0) {
        $errors[] = 'Veuillez sélectionner un séjour.';
    }
    if ($code_ccam === '') {
        $errors[] = 'Le code CCAM est obligatoire.';
    }
    if ($cout_raw === '' || !is_numeric($cout_raw)) {
        $errors[] = 'Le coût doit être un nombre.';
    }

    /**
     * Gestion de la date de l'acte :
     * - si vide : date/heure actuelle,
     * - sinon : conversion au format Y-m-d H:i:s.
     */
    if ($date_acte === '') {
        $date_acte = date('Y-m-d H:i:s');
    } else {
        $ts = strtotime($date_acte);
        if ($ts === false) {
            $errors[] = 'Date d’acte invalide.';
        } else {
            $date_acte = date('Y-m-d H:i:s', $ts);
        }
    }

    /**
     * Coût de l'acte converti en nombre flottant.
     *
     * @var float $cout
     */
    $cout = (float)$cout_raw;

    if (empty($errors)) {
        /**
         * Insertion d'un nouvel acte médical dans ACTE_MEDICAL.
         *
         * @var PDOStatement $stmt
         */
        $stmt = $pdo->prepare('
            INSERT INTO ACTE_MEDICAL
            (sejour_id, personnel_med_id, code_ccam, date_acte, cout)
            VALUES
            (:sejour_id, :personnel_med_id, :code_ccam, :date_acte, :cout)
            RETURNING acte_id
        ');

        $stmt->execute([
            ':sejour_id'       => $sejour_id,
            ':personnel_med_id'=> $personnel_med_id,
            ':code_ccam'       => $code_ccam,
            ':date_acte'       => $date_acte,
            ':cout'            => $cout,
        ]);

        /**
         * Identifiant de l'acte nouvellement créé.
         *
         * @var int|string $acteId
         */
        $acteId = $stmt->fetchColumn();
        $message = "Acte médical #{$acteId} créé avec succès.";

        // Réinitialisation des champs du formulaire
        $sejour_id = $code_ccam = $date_acte = $cout_raw = '';
    }
}

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = 'Ajouter un acte médical';
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">
    <section class="connexion-section">
        <h1>Ajouter un acte médical</h1>
        <p class="dashboard-subtitle">
            Enregistrement d’un acte pour un séjour patient.
        </p>

        <?php if ($message): ?>
            <div class="alert-success"><?= $message ?></div>
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

        <form method="post" class="connexion-form">
            <div class="connexion-fields">
                <label for="sejour_id">Séjour</label>
                <select name="sejour_id" id="sejour_id" required>
                    <option value="">-- Sélectionner un séjour --</option>
                    <?php foreach ($sejours as $s): ?>
                        <?php
                        /**
                         * Valeur et libellé d'une option de séjour.
                         *
                         * @var int    $val
                         * @var string $label
                         */
                        $val   = (int)$s['sejour_id'];
                        $label = '#' . $s['sejour_id'] . ' — ' .
                                 $s['prenom'] . ' ' . $s['nom'] .
                                 ' (' . $s['date_debut'] . ')';
                        ?>
                        <option value="<?= $val ?>" <?= ($sejour_id == $val ? 'selected' : '') ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="code_ccam">Code CCAM</label>
                <input type="text" id="code_ccam" name="code_ccam"
                       value="<?= htmlspecialchars($code_ccam) ?>" required>
            </div>

            <div class="connexion-fields">
                <label for="date_acte">Date de l’acte</label>
                <input type="datetime-local" id="date_acte" name="date_acte"
                       value="<?= htmlspecialchars(str_replace(' ', 'T', substr($date_acte, 0, 16))) ?>">
                <small>Laisser vide pour utiliser la date/heure actuelle.</small>
            </div>

            <div class="connexion-fields">
                <label for="cout">Coût (€)</label>
                <input type="number" step="0.01" id="cout" name="cout"
                       value="<?= htmlspecialchars($cout_raw) ?>" required>
            </div>

            <button type="submit" class="btn-primary">Enregistrer l’acte</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
