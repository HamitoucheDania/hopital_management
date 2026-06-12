<?php
/**
 * Page d'activation de compte patient.
 *
 * Cette page :
 * - reçoit un jeton d'activation (token) via l'URL,
 * - vérifie la validité du jeton dans la table PATIENT,
 * - active le compte (is_active, droits_actifs) si le jeton est valide,
 * - affiche un message de succès ou d'erreur.
 *
 * @package HospitCare
 */

// activation.php — activation de compte via lien reçu par mail

header('Content-Type: text/html; charset=UTF-8');
session_start();

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
 * Jeton d'activation reçu en paramètre d'URL.
 *
 * @var string
 */
$token = $_GET['token'] ?? '';

/**
 * Message affiché à l'utilisateur concernant l'état de l'activation.
 *
 * @var string
 */
$message = '';

if ($token !== '') {
    /**
     * Recherche du patient correspondant au jeton d'activation fourni,
     * uniquement si le compte n'est pas encore actif.
     *
     * @var PDOStatement $stmt
     */
    $stmt = $pdo->prepare('
        SELECT patient_id
        FROM PATIENT
        WHERE activation_token = :token
          AND is_active = FALSE
    ');
    $stmt->execute([':token' => $token]);

    /**
     * Résultat de la recherche du patient.
     *
     * @var array<string, mixed>|false $patient
     */
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        /**
         * Mise à jour du compte patient :
         * - activation du compte,
         * - suppression du jeton d'activation,
         * - activation des droits.
         *
         * @var PDOStatement $upd
         */
        $upd = $pdo->prepare('
            UPDATE PATIENT
            SET is_active = TRUE,
                activation_token = NULL,
                droits_actifs = TRUE
            WHERE patient_id = :id
        ');
        $upd->execute([':id' => $patient['patient_id']]);

        $message = 'Votre compte a été activé avec succès. Vous pouvez maintenant vous connecter.';
    } else {
        $message = 'Lien d’activation invalide ou compte déjà activé.';
    }
} else {
    $message = 'Lien d’activation invalide.';
}

/**
 * Titre de la page, utilisé dans le header.
 *
 * @var string
 */
$pageTitle = 'Activation du compte';
include __DIR__ . '/include/header.inc.php';
?>

<section class="connexion-section">
    <h1>Activation du compte</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <p style="margin-top: 1rem;">
        <a href="/connexion.php" class="btn-primary">Aller à la page de connexion</a>
    </p>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
