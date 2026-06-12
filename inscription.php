<?php
/**
 * Page d'inscription d'un patient avec activation par e-mail.
 *
 * Rôles de ce script :
 * - Afficher un formulaire d'inscription pour les patients.
 * - Valider les données saisies :
 *      • champs obligatoires (nom, prénom, NSS, date de naissance, sexe, email, mot de passe),
 *      • format de l'e-mail,
 *      • format du NSS (exactement 15 chiffres),
 *      • cohérence de la date de naissance (entre 1925-01-01 et aujourd'hui),
 *      • valeur du sexe (M/F),
 *      • complexité et confirmation du mot de passe,
 *      • vérification du captcha,
 *      • unicité NSS / e-mail dans la table PATIENT.
 * - Créer le compte patient avec :
 *      • mot de passe hashé,
 *      • token d'activation généré aléatoirement,
 *      • compte inactif tant que non validé via le lien d'activation.
 * - Envoyer un e-mail contenant le lien d’activation via PHPMailer.
 * - Afficher les messages de succès ou les erreurs de validation.
 *
 * Technologies utilisées :
 * - PDO pour la connexion PostgreSQL,
 * - PHPMailer pour l'envoi d'e-mails (SMTP),
 * - Session PHP pour la gestion du captcha.
 *
 * Variables principales :
 * @var PDO     $pdo         Connexion à la base de données
 * @var string  $mail_host   Hôte SMTP (défini dans secret/database.php)
 * @var string  $mail_username Identifiant SMTP
 * @var string  $mail_password Mot de passe SMTP
 * @var int     $mail_port   Port SMTP
 * @var string  $mail_from   Adresse d’expéditeur
 * @var string  $mail_from_name Nom d’expéditeur
 * @var string  $site_base_url URL de base du site pour construire le lien d’activation
 * @var array   $errors      Liste des messages d’erreur à afficher
 * @var bool    $success     Indique si l'inscription et l’envoi de mail ont réussi
 *
 * @package HospitCare
 */

// inscription.php — création de compte patient avec activation par mail

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Connexion PDO
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}

$errors  = [];
$success = false;

// Génération du captcha si pas encore généré
if (empty($_SESSION['captcha_text'])) {
    $_SESSION['captcha_text'] = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 7);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $nss       = trim($_POST['nss'] ?? '');
    $dateNaiss = trim($_POST['date_naissance'] ?? '');
    $sexe      = $_POST['sexe'] ?? '';
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';
    $captcha   = trim($_POST['captcha'] ?? '');

    // VALIDATIONS DE BASE
    if ($nom === '' || $prenom === '' || $nss === '' || $dateNaiss === '' || $sexe === '' || $email === '' || $password === '' || $password2 === '') {
        $errors[] = 'Tous les champs sont obligatoires.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse e-mail invalide.';
    }

    // NSS : exactement 15 chiffres (NIR)
    if ($nss !== '' && !preg_match('/^\d{15}$/', $nss)) {
        $errors[] = 'Le numéro de sécurité sociale doit contenir exactement 15 chiffres.';
    }

    // Date de naissance : entre 1925-01-01 et aujourd’hui
    if ($dateNaiss !== '') {
        $ts = strtotime($dateNaiss);
        if ($ts === false) {
            $errors[] = 'Date de naissance invalide.';
        } else {
            $minDate = strtotime('1925-01-01');
            $maxDate = strtotime(date('Y-m-d'));
            if ($ts < $minDate || $ts > $maxDate) {
                $errors[] = "La date de naissance doit être comprise entre 1925-01-01 et aujourd'hui.";
            }
        }
    }

    // Sexe M / F
    if ($sexe !== '' && !in_array($sexe, ['M', 'F'], true)) {
        $errors[] = 'Le sexe doit être M ou F.';
    }

    // Mots de passe identiques
    if ($password !== $password2) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // Mot de passe fort :
    // - au moins 6 caractères
    // - 1 minuscule
    // - 1 majuscule
    // - 1 chiffre
    // - 1 caractère spécial
    if ($password !== '' && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{6,}$/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères, dont une minuscule, une majuscule, un chiffre et un caractère spécial.";
    }

    // Captcha
    if ($captcha === '' || $captcha !== ($_SESSION['captcha_text'] ?? '')) {
        $errors[] = 'Captcha incorrect.';
    }

    // Vérifier NSS / email uniques
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT 1 FROM PATIENT WHERE nss = :nss OR email = :email');
        $stmt->execute([':nss' => $nss, ':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Un compte existe déjà avec ce NSS ou cet e-mail.';
        }
    }

    // Si OK → insertion + envoi mail
    if (empty($errors)) {
        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare('
            INSERT INTO PATIENT (
                nss,
                nom,
                prenom,
                date_naissance,
                sexe,
                droits_actifs,
                email,
                password,
                activation_token,
                is_active
            )
            VALUES (
                :nss,
                :nom,
                :prenom,
                :date_naissance,
                :sexe,
                TRUE,              -- droits_actifs par défaut
                :email,
                :password,
                :token,
                FALSE              -- compte non actif tant que non validé
            )
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
            ':token'          => $token,
        ]);

        $patientId = $stmt->fetchColumn();

        // Envoi de l’e-mail d’activation
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $mail_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $mail_username;
            $mail->Password   = $mail_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $mail_port;

            $mail->setFrom($mail_from, $mail_from_name);
            $mail->addAddress($email, $prenom . ' ' . $nom);

            $mail->isHTML(true);
            $mail->Subject = 'Activation de votre compte HospitCare';

            $activationLink = rtrim($site_base_url, '/') . '/activation.php?token=' . urlencode($token);

            $mail->Body = '
                <p>Bonjour ' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) . ',</p>
                <p>Merci de votre inscription sur HospitCare.</p>
                <p>Pour activer votre compte, cliquez sur le lien suivant :</p>
                <p><a href="' . $activationLink . '">' . $activationLink . '</a></p>
                <p>Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer ce message.</p>
            ';

            $mail->AltBody = "Bonjour,\n\nMerci de votre inscription sur HospitCare.\nActivez votre compte via ce lien : " . $activationLink;

            $mail->send();
            $success = true;

            // Regénérer un captcha pour la prochaine fois
            $_SESSION['captcha_text'] = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 7);

        } catch (Exception $e) {
            $errors[] = "Inscription réalisée mais l'e-mail d'activation n'a pas pu être envoyé.";
        }
    }
}

$pageTitle = 'Inscription patient';
include __DIR__ . '/include/header.inc.php';
?>

<section class="connexion-section">
    <h1>Inscription patient</h1>
    <p class="dashboard-subtitle">
        Créez votre compte patient pour accéder à vos informations hospitalières.
    </p>

    <?php if ($success && empty($errors)): ?>
        <div class="alert-success">
            Votre compte a été créé. Un e-mail d’activation vous a été envoyé.
        </div>
    <?php else: ?>

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
                <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($nom ?? '') ?>">
            </div>

            <div class="connexion-fields">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($prenom ?? '') ?>">
            </div>

            <div class="connexion-fields">
                <label for="nss">Numéro de sécurité sociale</label>
                <input type="text" id="nss" name="nss" value="<?= htmlspecialchars($nss ?? '') ?>" maxlength="15">
                <small>15 chiffres, sans espaces.</small>
            </div>

            <div class="connexion-fields">
                <label for="date_naissance">Date de naissance</label>
                <input type="date" id="date_naissance" name="date_naissance" value="<?= htmlspecialchars($dateNaiss ?? '') ?>">
            </div>

            <div class="connexion-fields">
                <label>Sexe</label>
                <select name="sexe">
                    <option value="">-- Sélectionner --</option>
                    <option value="M" <?= (isset($sexe) && $sexe === 'M') ? 'selected' : '' ?>>Masculin</option>
                    <option value="F" <?= (isset($sexe) && $sexe === 'F') ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>

            <div class="connexion-fields">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>">
            </div>

            <div class="connexion-fields">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password">
                <small>
                    Min. 6 caractères, avec au moins : 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial.
                </small>
            </div>

            <div class="connexion-fields">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm">
            </div>

            <div class="connexion-fields">
                <label>Captcha</label>
                <div class="captcha-box">
                    <span class="captcha-text"><?= htmlspecialchars($_SESSION['captcha_text'] ?? '') ?></span>
                </div>
                <input type="text" name="captcha" placeholder="Recopiez le code ci-dessus">
            </div>

            <button type="submit" class="btn-primary">Créer mon compte</button>

            <p class="connexion-link">
                Déjà un compte ? <a href="/connexion.php">Se connecter</a>
            </p>
        </form>

    <?php endif; ?>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
