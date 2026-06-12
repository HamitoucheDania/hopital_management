<?php
/**
 * Page de connexion pour les patients et le personnel.
 *
 * Cette page permet :
 * - de gérer la connexion des patients via NSS + mot de passe,
 * - de gérer la connexion du personnel via identifiant généré + mot de passe,
 * - de vérifier le rôle et de stocker les informations en session,
 * - de rediriger vers l'accueil en cas de connexion réussie.
 *
 * @package HospitCare
 */

// connexion.php — connexion patient / personnel

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Si déjà connecté → retour à l'accueil / tableau de bord
/**
 * Si un utilisateur est déjà connecté (patient ou personnel),
 * il est redirigé vers la page d'accueil (index.php).
 */
if (isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/secret/database.php';

// Connexion PDO
/**
 * Connexion à la base PostgreSQL via PDO.
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
 * Message d'erreur affiché en cas d'échec de connexion.
 *
 * @var string
 */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /**
     * Type de connexion demandé : 'patient' ou 'personnel'.
     *
     * @var string $role
     */
    $role     = $_POST['role'] ?? '';

    /**
     * Mot de passe saisi (commun aux deux types de connexion).
     *
     * @var string $password
     */
    $password = $_POST['password'] ?? '';

    if ($role === 'patient') {

        /**
         * Numéro de sécurité sociale du patient (15 chiffres attendus).
         *
         * @var string $nss
         */
        $nss = trim($_POST['nss'] ?? '');

        if ($nss === '' || $password === '') {
            $error = 'Veuillez saisir votre NSS et votre mot de passe.';
        } elseif (!preg_match('/^\d{15}$/', $nss)) {
            $error = 'Le NSS doit contenir exactement 15 chiffres.';
        } else {
            /**
             * Récupération du patient par NSS.
             *
             * @var PDOStatement $stmt
             * @var array<string, mixed>|false $patient
             */
            $stmt = $pdo->prepare('
                SELECT patient_id, nom, prenom, password, is_active
                FROM PATIENT
                WHERE nss = :nss
            ');
            $stmt->execute([':nss' => $nss]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                $error = 'NSS ou mot de passe incorrect.';
            } elseif (!$patient['is_active']) {
                $error = 'Votre compte n’est pas encore activé. Vérifiez vos e-mails.';
            } elseif (!password_verify($password, $patient['password'])) {
                $error = 'NSS ou mot de passe incorrect.';
            } else {
                // Connexion réussie pour un patient
                $_SESSION['user_id']   = $patient['patient_id'];
                $_SESSION['user_name'] = $patient['prenom'] . ' ' . $patient['nom'];
                $_SESSION['user_role'] = 'patient';

                header('Location: index.php');
                exit();
            }
        }

    } elseif ($role === 'personnel') {

        /**
         * Identifiant du membre du personnel, construit comme :
         * 1ère lettre du prénom + nom + personnel_id (ex : imoussaoui308).
         *
         * @var string $identifiant
         */
        $identifiant = trim($_POST['identifiant'] ?? '');

        if ($identifiant === '' || $password === '') {
            $error = 'Veuillez saisir votre identifiant et votre mot de passe.';
        } else {
            // identifiant attendu : 1ère lettre du prénom + nom + personnel_id
            /**
             * Récupération du personnel par identifiant généré.
             *
             * @var PDOStatement $stmt
             * @var array<string, mixed>|false $perso
             */
            $stmt = $pdo->prepare("
                SELECT personnel_id, nom, prenom, type, password
                FROM PERSONNEL
                WHERE LOWER( (SUBSTRING(prenom FROM 1 FOR 1) || nom || personnel_id::text) ) = LOWER(:identifiant)
            ");
            $stmt->execute([':identifiant' => $identifiant]);
            $perso = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$perso) {
                $error = 'Identifiant ou mot de passe incorrect.';
            } elseif (!password_verify($password, $perso['password'])) {
                $error = 'Identifiant ou mot de passe incorrect.';
            } else {
                // Connexion réussie pour un membre du personnel
                $_SESSION['user_id']        = $perso['personnel_id'];
                $_SESSION['user_name']      = $perso['prenom'] . ' ' . $perso['nom'];
                $_SESSION['user_role']      = 'personnel';
                // MEDICAL / ADMINISTRATIF
                $_SESSION['personnel_type'] = $perso['type'];

                header('Location: index.php');
                exit();
            }
        }

    } else {
        $error = 'Veuillez sélectionner un type de connexion.';
    }
}

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string
 */
$pageTitle = 'Connexion';
include __DIR__ . '/include/header.inc.php';
?>

<section class="connexion-section">
    <h1>Connexion</h1>
    <p class="dashboard-subtitle">Connectez-vous en tant que patient ou membre du personnel.</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="connexion-form">
        <div class="connexion-role">
            <label>
                <input type="radio" name="role" value="patient" <?= (($_POST['role'] ?? '') === 'personnel') ? '' : 'checked' ?>>
                Patient
            </label>
            <label>
                <input type="radio" name="role" value="personnel" <?= (($_POST['role'] ?? '') === 'personnel') ? 'checked' : '' ?>>
                Personnel
            </label>
        </div>

        <div class="connexion-fields connexion-patient" id="bloc-patient">
            <label for="nss">Numéro de sécurité sociale</label>
            <input type="text" id="nss" name="nss" value="<?= htmlspecialchars($_POST['nss'] ?? '') ?>">
        </div>

        <div class="connexion-fields connexion-personnel" id="bloc-personnel" style="display:none;">
            <label for="identifiant">Identifiant personnel</label>
            <input type="text" id="identifiant" name="identifiant" value="<?= htmlspecialchars($_POST['identifiant'] ?? '') ?>">
            <small>Format : 1ère lettre du prénom + nom de famille + ID (ex : imoussaoui308)</small>
        </div>

        <div class="connexion-fields">
            <label for="password">Mot de passe</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password">
                <button
                    type="button"
                    class="toggle-password"
                    data-target="password"
                    aria-label="Afficher ou masquer le mot de passe"
                >
                    👁
                </button>
            </div>
        </div>

        <button type="submit" class="btn-primary">Se connecter</button>

        <p class="connexion-link">
            Pas encore inscrit ? <a href="/inscription.php">Créer un compte patient</a>
        </p>
    </form>
</section>

<script src="/js/script.js"></script>
<?php include __DIR__ . '/include/footer.inc.php'; ?>
