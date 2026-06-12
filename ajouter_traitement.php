<?php
/**
 * Page d'ajout d'un traitement médicamenteux.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur est un membre du personnel médical,
 * - de lister les patients disponibles,
 * - de saisir un traitement (médicament, dosage, dates),
 * - de valider les données (cohérence des dates, champs obligatoires),
 * - d'enregistrer le traitement dans la table TRAITEMENT.
 *
 * @package HospitCare
 */

// ajouter_traitement.php — ajout de traitement par le personnel médical

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Sécurité : personnel médical uniquement
/**
 * Contrôle d'accès :
 * - nécessite un utilisateur connecté,
 * - rôle "personnel",
 * - type de personnel "MEDICAL".
 *
 * En cas d'échec : réponse HTTP 403 et arrêt du script.
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
    die('Erreur de connexion à la base de données.');
}

/**
 * Identifiant du patient éventuellement passé via GET ou POST.
 *
 * @var int $patient_id
 */
$patient_id = (int)($_GET['patient_id'] ?? ($_POST['patient_id'] ?? 0));

/**
 * Tableau des messages d'erreur de validation.
 *
 * @var string[]
 */
$errors  = [];

/**
 * Message de succès ou d'information à afficher.
 *
 * @var string
 */
$message = '';

// Liste des patients pour le <select>
/**
 * Liste des patients (limité à 200) pour la sélection dans le formulaire.
 *
 * @var array<int, array<string, mixed>>
 */
$patients = $pdo->query("
    SELECT patient_id, nom, prenom, nss
    FROM PATIENT
    ORDER BY nom, prenom
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

// valeurs formulaire
/**
 * Valeurs actuelles des champs du formulaire (initialisation).
 *
 * @var int|string $patient_id
 * @var string     $nom_med
 * @var string     $dosage
 * @var string     $date_debut_raw
 * @var string     $date_fin_raw
 */
$patient_id     = '';
$nom_med        = '';
$dosage         = '';
$date_debut_raw = '';
$date_fin_raw   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /**
     * Récupération des données envoyées par le formulaire.
     *
     * @var int    $patient_id
     * @var string $nom_med
     * @var string $dosage
     * @var string $date_debut_raw
     * @var string $date_fin_raw
     * @var int    $personnel_med_id
     */
    $patient_id     = (int)($_POST['patient_id'] ?? 0);
    $nom_med        = trim($_POST['nom_medicament'] ?? '');
    $dosage         = trim($_POST['dosage'] ?? '');
    $date_debut_raw = trim($_POST['date_debut'] ?? '');
    $date_fin_raw   = trim($_POST['date_fin'] ?? '');
    $personnel_med_id = (int)$_SESSION['user_id'];

    // Vérifications de base
    if ($patient_id <= 0) {
        $errors[] = 'Veuillez sélectionner un patient.';
    }
    if ($nom_med === '') {
        $errors[] = 'Le nom du médicament est obligatoire.';
    }
    if ($dosage === '') {
        $errors[] = 'Le dosage est obligatoire.';
    }
    if ($date_debut_raw === '') {
        $errors[] = 'La date de début est obligatoire.';
    }

    // normalisation dates (type date → on garde Y-m-d)
    /**
     * Dates de début et de fin normalisées au format Y-m-d ou null.
     *
     * @var string|null $date_debut
     * @var string|null $date_fin
     */
    $date_debut = null;
    $date_fin   = null;

    if ($date_debut_raw !== '') {
        $ts = strtotime($date_debut_raw);
        if ($ts === false) {
            $errors[] = 'Date de début invalide.';
        } else {
            $date_debut = date('Y-m-d', $ts);
        }
    }
    if ($date_fin_raw !== '') {
        $ts = strtotime($date_fin_raw);
        if ($ts === false) {
            $errors[] = 'Date de fin invalide.';
        } else {
            $date_fin = date('Y-m-d', $ts);
        }
    }

    // Cohérence des dates
    if ($date_debut && $date_fin && strtotime($date_fin) < strtotime($date_debut)) {
        $errors[] = 'La date de fin doit être postérieure à la date de début.';
    }

    if (empty($errors)) {
        /**
         * Insertion d'un nouveau traitement pour le patient.
         *
         * @var PDOStatement $stmt
         */
        $stmt = $pdo->prepare('
            INSERT INTO TRAITEMENT
            (patient_id, personnel_med_id, nom_medicament, dosage, date_debut, date_fin)
            VALUES
            (:patient_id, :personnel_med_id, :nom_medicament, :dosage, :date_debut, :date_fin)
            RETURNING traitement_id
        ');

        $stmt->execute([
            ':patient_id'       => $patient_id,
            ':personnel_med_id' => $personnel_med_id,
            ':nom_medicament'   => $nom_med,
            ':dosage'           => $dosage,
            ':date_debut'       => $date_debut,
            ':date_fin'         => $date_fin,
        ]);

        /**
         * Identifiant du traitement nouvellement créé.
         *
         * @var int|string $traitementId
         */
        $traitementId = $stmt->fetchColumn();
        $message = "Traitement #{$traitementId} créé avec succès.";

        // reset form
        $patient_id = '';
        $nom_med = $dosage = $date_debut_raw = $date_fin_raw = '';
    }
}

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = 'Ajouter un traitement';
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">
    <section class="connexion-section">
        <h1>Ajouter un traitement</h1>
        <p class="dashboard-subtitle">
            Prescription médicamenteuse pour un patient.
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
                <label for="patient_id">Patient</label>
                <select name="patient_id" id="patient_id" required>
                    <option value="">-- Sélectionner un patient --</option>
                    <?php foreach ($patients as $p): ?>
                        <?php
                        /**
                         * Valeur et libellé pour une option de patient dans la liste déroulante.
                         *
                         * @var int    $val
                         * @var string $label
                         */
                        $val   = (int)$p['patient_id'];
                        $label = $p['nom'] . ' ' . $p['prenom'] . ' (NSS: ' . $p['nss'] . ')';
                        ?>
                        <option value="<?= $val ?>" <?= ($patient_id == $val ? 'selected' : '') ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="nom_medicament">Nom du médicament</label>
                <input type="text" id="nom_medicament" name="nom_medicament"
                       value="<?= htmlspecialchars($nom_med) ?>" required>
            </div>

            <div class="connexion-fields">
                <label for="dosage">Dosage</label>
                <input type="text" id="dosage" name="dosage"
                       placeholder="Ex : 1 comprimé matin et soir"
                       value="<?= htmlspecialchars($dosage) ?>" required>
            </div>

            <div class="connexion-fields">
                <label for="date_debut">Date de début</label>
                <input type="date" id="date_debut" name="date_debut"
                       value="<?= htmlspecialchars($date_debut_raw) ?>" required>
            </div>

            <div class="connexion-fields">
                <label for="date_fin">Date de fin (optionnelle)</label>
                <input type="date" id="date_fin" name="date_fin"
                       value="<?= htmlspecialchars($date_fin_raw) ?>">
            </div>

            <button type="submit" class="btn-primary">Enregistrer le traitement</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
