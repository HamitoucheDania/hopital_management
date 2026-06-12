<?php
/**
 * Page d'ajout d'un patient par le personnel.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur connecté est un membre du personnel,
 * - de saisir les informations d'un nouveau patient,
 * - de vérifier la validité des données et l'unicité NSS / e-mail,
 * - de créer le patient dans la table PATIENT avec un compte actif immédiatement.
 *
 * @package HospitCare
 */

// ajouter_patient.php — création de patient par le personnel

header('Content-Type: text/html; charset=UTF-8');
session_start();

// 1) Sécurité : accès réservé au personnel
/**
 * Vérification d'accès :
 * - nécessite une session active,
 * - le rôle utilisateur doit être "personnel".
 *
 * En cas d'échec : code HTTP 403 + arrêt du script.
 */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'personnel') {
    http_response_code(403);
    die('Accès réservé au personnel.');
}

require_once __DIR__ . '/secret/database.php';

// 2) Connexion PDO
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
 * Tableau des erreurs rencontrées lors de la validation ou de l'insertion.
 *
 * @var string[]
 */
$errors  = [];

/**
 * Message d'information ou de succès affiché à l'utilisateur.
 *
 * @var string
 */
$message = '';

/**
 * Champs du formulaire (valeurs initiales).
 *
 * @var string $nom
 * @var string $prenom
 * @var string $nss
 * @var string $dateNaiss
 * @var string $sexe
 * @var string $email
 */
$nom = $prenom = $nss = $dateNaiss = $sexe = $email = '';

// 3) Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * Récupération et nettoyage des données du formulaire.
     *
     * @var string $nom
     * @var string $prenom
     * @var string $nss
     * @var string $dateNaiss
     * @var string $sexe
     * @var string $email
     * @var string $password
     * @var string $password2
     */
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $nss       = trim($_POST['nss'] ?? '');
    $dateNaiss = trim($_POST['date_naissance'] ?? '');
    $sexe      = $_POST['sexe'] ?? '';
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    // VALIDATION
    if ($nom === '' || $prenom === '' || $nss === '' || $dateNaiss === '' || $sexe === '' || $email === '' || $password === '' || $password2 === '') {
        $errors[] = 'Tous les champs sont obligatoires.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse e-mail invalide.';
    }

    if ($password !== $password2) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }

    // Vérifier NSS / email uniques
    if (empty($errors)) {
        /**
         * Vérification d'unicité du NSS et de l'e-mail dans la table PATIENT.
         *
         * @var PDOStatement $stmt
         */
        $stmt = $pdo->prepare('SELECT 1 FROM PATIENT WHERE nss = :nss OR email = :email');
        $stmt->execute([':nss' => $nss, ':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Un compte existe déjà avec ce NSS ou cet e-mail.';
        }
    }

    // INSERT si tout est OK
    if (empty($errors)) {
        /**
         * Hachage sécurisé du mot de passe avant insertion.
         *
         * @var string $hash
         */
        $hash = password_hash($password, PASSWORD_DEFAULT);

        /**
         * Insertion d'un nouveau patient dans la table PATIENT.
         *
         * Le compte est créé actif immédiatement (droits_actifs = TRUE, is_active = TRUE,
         * activation_token = NULL).
         *
         * @var PDOStatement $stmt
         */
        $stmt = $pdo->prepare('
            INSERT INTO PATIENT
            (nss, nom, prenom, date_naissance, sexe,
             droits_actifs, email, password, activation_token, is_active)
            VALUES
            (:nss, :nom, :prenom, :date_naissance, :sexe,
             TRUE, :email, :password, NULL, TRUE)
            RETURNING patient_id
        ');

        $stmt->execute([
            ':nss'            => $nss,
            ':nom'            => $nom,
            ':prenom'         => $prenom,
            ':date_naissance' => $dateNaiss,
            ':sexe'           => $sexe,
            ':email'          => $email,
            ':password'       => $hash,
        ]);

        /**
         * Identifiant du patient nouvellement créé.
         *
         * @var int|string $patientId
         */
        $patientId = $stmt->fetchColumn();

        $message = "Patient créé avec succès (ID : {$patientId}).";
        // on peut vider les champs
        $nom = $prenom = $nss = $dateNaiss = $sexe = $email = '';
    }
}

/**
 * Titre de la page, utilisé dans le header.
 *
 * @var string
 */
$pageTitle = 'Ajout patient';
include __DIR__ . '/include/header.inc.php';
?>

<section class="connexion-section">
    <h1>Ajouter un patient</h1>
    <p class="dashboard-subtitle">
        Création de patient par le personnel (compte actif immédiatement).
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
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($nom) ?>">
        </div>

        <div class="connexion-fields">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($prenom) ?>">
        </div>

        <div class="connexion-fields">
            <label for="nss">Numéro de sécurité sociale</label>
            <input type="text" id="nss" name="nss" value="<?= htmlspecialchars($nss) ?>">
        </div>

        <div class="connexion-fields">
            <label for="date_naissance">Date de naissance</label>
            <input type="date" id="date_naissance" name="date_naissance" value="<?= htmlspecialchars($dateNaiss) ?>">
        </div>

        <div class="connexion-fields">
            <label>Sexe</label>
            <select name="sexe">
                <option value="">-- Sélectionner --</option>
                <option value="M" <?= ($sexe === 'M') ? 'selected' : '' ?>>Masculin</option>
                <option value="F" <?= ($sexe === 'F') ? 'selected' : '' ?>>Féminin</option>
            </select>
        </div>

        <div class="connexion-fields">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>">
        </div>

        <div class="connexion-fields">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password">
        </div>

        <div class="connexion-fields">
            <label for="password_confirm">Confirmer le mot de passe</label>
            <input type="password" id="password_confirm" name="password_confirm">
        </div>

        <button type="submit" class="btn-primary">Créer le patient</button>
    </form>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
