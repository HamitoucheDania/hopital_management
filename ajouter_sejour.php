<?php
/**
 * Page de création d'un séjour pour un patient.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur est un membre du personnel médical,
 * - de charger les listes de patients et de services,
 * - de saisir les informations d'un nouveau séjour (dates, service, motif),
 * - de valider les données (cohérence des dates, champs obligatoires),
 * - d'enregistrer le séjour dans la table SEJOUR.
 *
 * @package HospitCare
 */

// ajouter_sejour.php — création d’un séjour pour un patient

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Vérification : réservé au personnel médical
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

/**
 * Identifiant du personnel médical connecté.
 *
 * @var int
 */
$personnel_med_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/secret/database.php';

// Connexion PDO
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
 * Identifiant du patient éventuellement passé en GET ou POST.
 *
 * @var int $patient_id
 */
$patient_id = (int)($_GET['patient_id'] ?? ($_POST['patient_id'] ?? 0));

/**
 * Liste des messages d'erreur de validation.
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
 * Indique si l'enregistrement du séjour s'est déroulé avec succès.
 *
 * @var bool
 */
$success = false;

//Chargement des listes (patients, services)

/**
 * Liste des patients pour le sélecteur du formulaire.
 *
 * @var array<int, array<string, mixed>>
 */
$patients = $pdo->query("
    SELECT patient_id, nom, prenom, nss
    FROM PATIENT
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Liste des services hospitaliers pour le sélecteur du formulaire.
 *
 * @var array<int, array<string, mixed>>
 */
$services = $pdo->query("
    SELECT service_id, code, libelle
    FROM SERVICE
    ORDER BY code
")->fetchAll(PDO::FETCH_ASSOC);

// Valeurs par défaut du formulaire
/**
 * Valeurs des champs de formulaire (initialisation).
 *
 * @var int|string $patient_id
 * @var int|string $service_id
 * @var string     $motif
 * @var string     $date_debut
 * @var string     $date_fin
 */
$patient_id   = '';
$service_id   = '';
$motif        = '';
$date_debut   = '';
$date_fin     = '';

//Traitement du POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * Récupération des données envoyées par le formulaire.
     *
     * @var int    $patient_id
     * @var int    $service_id
     * @var string $motif
     * @var string $date_debut
     * @var string $date_fin
     */
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $service_id = (int)($_POST['service_id'] ?? 0);
    $motif      = trim($_POST['motif'] ?? '');
    $date_debut = trim($_POST['date_debut'] ?? '');
    $date_fin   = trim($_POST['date_fin'] ?? '');

    // Vérifications de base
    if ($patient_id <= 0) {
        $errors[] = "Veuillez sélectionner un patient.";
    }

    if ($service_id <= 0) {
        $errors[] = "Veuillez sélectionner un service.";
    }

    if ($motif === '') {
        $errors[] = "Le motif de séjour est obligatoire.";
    } elseif (mb_strlen($motif) > 150) {
        $errors[] = "Le motif ne doit pas dépasser 150 caractères.";
    }

    // Conversion des dates (formulaire en datetime-local → format SQL)
    /**
     * Dates converties au format SQL (Y-m-d H:i:s) ou null si non renseignées / invalides.
     *
     * @var string|null $dateDebutSql
     * @var string|null $dateFinSql
     */
    $dateDebutSql = null;
    $dateFinSql   = null;

    if ($date_debut === '') {
        $errors[] = "La date de début de séjour est obligatoire.";
    } else {
        // ex: 2025-11-29T13:20 → 2025-11-29 13:20:00
        $tsDebut = strtotime(str_replace('T', ' ', $date_debut));
        if ($tsDebut === false) {
            $errors[] = "Date/heure de début invalide.";
        } else {
            $dateDebutSql = date('Y-m-d H:i:s', $tsDebut);
        }
    }

    if ($date_fin !== '') {
        $tsFin = strtotime(str_replace('T', ' ', $date_fin));
        if ($tsFin === false) {
            $errors[] = "Date/heure de fin invalide.";
        } else {
            $dateFinSql = date('Y-m-d H:i:s', $tsFin);
        }
    }

    // Vérifier cohérence début/fin (en plus du CHECK SQL)
    if ($dateDebutSql && $dateFinSql && ($dateFinSql < $dateDebutSql)) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
    }

    if (empty($errors)) {
        try {
            /**
             * Préparation de la requête d'insertion dans SEJOUR.
             *
             * @var PDOStatement $stmt
             */
            $stmt = $pdo->prepare("
                INSERT INTO SEJOUR (patient_id, personnel_med_id, service_id, date_debut, date_fin, motif)
                VALUES (:patient_id, :personnel_med_id, :service_id, :date_debut, :date_fin, :motif)
            ");

            $stmt->execute([
                ':patient_id'       => $patient_id,
                ':personnel_med_id' => $personnel_med_id,   
                ':service_id'       => $service_id,
                ':date_debut'       => $dateDebutSql,
                ':date_fin'         => $dateFinSql,         
                ':motif'            => $motif,
            ]);

            $success  = true;
            $message  = "Séjour créé avec succès.";
            // Reset du formulaire
            $patient_id = $service_id = '';
            $motif = $date_debut = $date_fin = '';

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'enregistrement du séjour : " . $e->getMessage();
        }
    }
}

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = "Nouveau séjour";
include __DIR__ . "/include/header.inc.php";
?>

<div class="dashboard-page">
    <section class="connexion-section">
        <h1>Nouveau séjour</h1>
        <p class="dashboard-subtitle">
            Créez un nouveau séjour hospitalier pour un patient.
        </p>

        <?php if ($success && $message): ?>
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
                <label for="patient_id">Patient *</label>
                <select name="patient_id" id="patient_id" required>
                    <option value="">-- Sélectionner un patient --</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['patient_id'] ?>"
                            <?= ((int)$patient_id === (int)$p['patient_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom'] . ' (' . $p['nss'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="service_id">Service *</label>
                <select name="service_id" id="service_id" required>
                    <option value="">-- Sélectionner un service --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= (int)$s['service_id'] ?>"
                            <?= ((int)$service_id === (int)$s['service_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['code'] . ' - ' . $s['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="date_debut">Date/heure de début *</label>
                <input type="datetime-local" id="date_debut" name="date_debut"
                       value="<?= htmlspecialchars($date_debut) ?>">
            </div>

            <div class="connexion-fields">
                <label for="date_fin">Date/heure de fin (optionnel)</label>
                <input type="datetime-local" id="date_fin" name="date_fin"
                       value="<?= htmlspecialchars($date_fin) ?>">
            </div>

            <div class="connexion-fields">
                <label for="motif">Motif de séjour *</label>
                <textarea id="motif" name="motif" rows="3"><?= htmlspecialchars($motif) ?></textarea>
            </div>

            <button type="submit" class="btn-primary">Enregistrer le séjour</button>
        </form>
    </section>
</div>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
