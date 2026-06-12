<?php
/**
 * Page d'inscription d'un membre du personnel (médical ou administratif).
 *
 * Cette page permet :
 * - de créer un compte pour un personnel MEDICAL ou ADMINISTRATIF,
 * - de charger la liste des services (pour les profils médicaux),
 * - de valider les champs saisis (mot de passe, type, service, etc.),
 * - d'insérer les données dans les tables :
 *      • PERSONNEL
 *      • PERSONNEL_MEDICAL ou PERSONNEL_ADMINISTRATIF selon le type
 * - de générer l'identifiant de connexion (1ère lettre du prénom + nom + personnel_id)
 *   identique au mécanisme utilisé dans connexion.php.
 *
 * Flux principal :
 * 1) Connexion à la base de données.
 * 2) Chargement des services pour le <select>.
 * 3) Si POST :
 *      - récupération et validation des champs,
 *      - transaction d'insertion dans les tables,
 *      - génération de l'identifiant de connexion,
 *      - message de succès ou erreurs.
 *
 * Variables importantes :
 * @var PDO        $pdo         Connexion à PostgreSQL
 * @var array      $errors      Liste des messages d’erreurs à afficher
 * @var string     $message     Message de succès détaillant l’ID et l’identifiant de connexion
 * @var bool       $success     Indique si la création s’est bien déroulée
 * @var array      $services    Liste des services (id, code, libellé) pour le formulaire
 *
 * @package HospitCare
 */

// inscription_personnel.php — création d’un compte personnel (médical ou administratif)

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/secret/database.php';

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}

$errors  = [];
$message = '';
$success = false;

// Charger la liste des services pour le <select>
$services = [];
try {
    $services = $pdo->query("
        SELECT service_id, code, libelle
        FROM SERVICE
        ORDER BY code
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Impossible de charger la liste des services.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom'] ?? '');
    $prenom     = trim($_POST['prenom'] ?? '');
    $type       = strtoupper(trim($_POST['type'] ?? ''));     // MEDICAL / ADMINISTRATIF
    $service_id = (int)($_POST['service_id'] ?? 0);
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password_confirm'] ?? '';

    // Champs pour médical
    $categorie  = strtoupper(trim($_POST['categorie'] ?? '')); // MEDECIN / INFIRMIER
    $specialite = trim($_POST['specialite'] ?? '');

    // Champs pour administratif
    $poste      = strtoupper(trim($_POST['poste'] ?? ''));     // SECRETAIRE / ADMIN

    // VALIDATIONS DE BASE
    if ($nom === '' || $prenom === '' || $type === '' || $password === '' || $password2 === '') {
        $errors[] = 'Tous les champs obligatoires doivent être remplis.';
    }

    if ($password !== $password2) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }

    // Règles spécifiques selon le type
    if ($type === 'MEDICAL') {
        // service obligatoire seulement pour le médical
        if ($service_id <= 0) {
            $errors[] = "Le service est obligatoire pour le personnel médical.";
        }

        // Vérifier que le service existe
        if ($service_id > 0) {
            $stmt = $pdo->prepare("SELECT 1 FROM SERVICE WHERE service_id = :id");
            $stmt->execute([':id' => $service_id]);
            if (!$stmt->fetchColumn()) {
                $errors[] = "Le service choisi n'existe pas.";
            }
        }

        if ($categorie === '' || $specialite === '') {
            $errors[] = 'Pour un personnel médical, la catégorie et la spécialité sont obligatoires.';
        }
        if (!in_array($categorie, ['MEDECIN', 'INFIRMIER'], true)) {
            $errors[] = "La catégorie doit être 'MEDECIN' ou 'INFIRMIER'.";
        }

    } elseif ($type === 'ADMINISTRATIF') {
        // pour l’administratif, PAS de service obligatoire
        if ($poste === '') {
            $errors[] = 'Pour un personnel administratif, le poste est obligatoire.';
        }
        if (!in_array($poste, ['SECRETAIRE', 'ADMIN'], true)) {
            $errors[] = "Le poste doit être 'SECRETAIRE' ou 'ADMIN'.";
        }
        // on ignorera service_id (sera forcé à NULL plus bas)

    } else {
        $errors[] = 'Type de personnel invalide.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // 1) Insert dans PERSONNEL (avec password)
            $stmt = $pdo->prepare("
                INSERT INTO PERSONNEL (nom, prenom, type, service_id, password)
                VALUES (:nom, :prenom, :type, :service_id, :password)
                RETURNING personnel_id
            ");
            $stmt->execute([
                ':nom'        => $nom,
                ':prenom'     => $prenom,
                ':type'       => $type,
                ':service_id' => ($type === 'MEDICAL') ? $service_id : null,
                ':password'   => $passwordHash,
            ]);
            $personnel_id = $stmt->fetchColumn();

            // 2) Insert dans la table spécialisée
            if ($type === 'MEDICAL') {
                $stmt2 = $pdo->prepare("
                    INSERT INTO PERSONNEL_MEDICAL (personnel_med_id, categorie, specialite)
                    VALUES (:id, :categorie, :specialite)
                ");
                $stmt2->execute([
                    ':id'         => $personnel_id,
                    ':categorie'  => $categorie,
                    ':specialite' => $specialite,
                ]);
            } else { // ADMINISTRATIF
                $stmt2 = $pdo->prepare("
                    INSERT INTO PERSONNEL_ADMINISTRATIF (personnel_admin_id, poste)
                    VALUES (:id, :poste)
                ");
                $stmt2->execute([
                    ':id'   => $personnel_id,
                    ':poste'=> $poste,
                ]);
            }

            $pdo->commit();

            // 3) Générer l’identifiant de connexion (comme dans connexion.php)
            $identifiant = strtolower(mb_substr($prenom, 0, 1) . $nom . $personnel_id);

            $success = true;
            $message = "Compte personnel créé avec succès.<br>
                        ID interne : {$personnel_id}<br>
                        Identifiant de connexion : <strong>{$identifiant}</strong>";

            // vider les champs du formulaire
            $nom = $prenom = $categorie = $specialite = $poste = '';
            $type = '';
            $service_id = 0;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erreur lors de la création du compte personnel : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Inscription personnel';
include __DIR__ . '/include/header.inc.php';
?>

<section class="connexion-section">
    <h1>Inscription personnel</h1>
    <p class="dashboard-subtitle">
        Créez un compte pour un membre du personnel médical ou administratif.
    </p>

    <?php if ($success && empty($errors)): ?>
        <div class="alert-success">
            <?= $message ?>
        </div>
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
            <label for="nom">Nom *</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($nom ?? '') ?>">
        </div>

        <div class="connexion-fields">
            <label for="prenom">Prénom *</label>
            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($prenom ?? '') ?>">
        </div>

        <div class="connexion-fields">
            <label for="type">Type de personnel *</label>
            <select name="type" id="type">
                <option value="">-- Sélectionner --</option>
                <option value="MEDICAL" <?= (isset($type) && $type === 'MEDICAL') ? 'selected' : '' ?>>Médical</option>
                <option value="ADMINISTRATIF" <?= (isset($type) && $type === 'ADMINISTRATIF') ? 'selected' : '' ?>>Administratif</option>
            </select>
        </div>

        <div class="connexion-fields">
            <label for="service_id">Service (obligatoire uniquement pour le médical)</label>
            <select id="service_id" name="service_id">
                <option value="0">-- Sélectionner un service --</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?= (int)$s['service_id'] ?>"
                        <?= (isset($service_id) && (int)$service_id === (int)$s['service_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['code'] . ' - ' . $s['libelle']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="connexion-fields">
            <label for="password">Mot de passe *</label>
            <input type="password" id="password" name="password">
        </div>

        <div class="connexion-fields">
            <label for="password_confirm">Confirmer le mot de passe *</label>
            <input type="password" id="password_confirm" name="password_confirm">
        </div>

        <fieldset class="connexion-fields">
            <legend>Si personnel médical</legend>
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
                <option value="">-- Sélectionner --</option>
                <option value="MEDECIN" <?= (isset($categorie) && $categorie === 'MEDECIN') ? 'selected' : '' ?>>Médecin</option>
                <option value="INFIRMIER" <?= (isset($categorie) && $categorie === 'INFIRMIER') ? 'selected' : '' ?>>Infirmier</option>
            </select>

            <label for="specialite">Spécialité</label>
            <input type="text" id="specialite" name="specialite"
                   placeholder="CARDIOLOGIE, RADIOLOGIE, ..."
                   value="<?= htmlspecialchars($specialite ?? '') ?>">
        </fieldset>

        <fieldset class="connexion-fields">
            <legend>Si personnel administratif</legend>
            <label for="poste">Poste</label>
            <select id="poste" name="poste">
                <option value="">-- Sélectionner --</option>
                <option value="SECRETAIRE" <?= (isset($poste) && $poste === 'SECRETAIRE') ? 'selected' : '' ?>>Secrétaire</option>
                <option value="ADMIN" <?= (isset($poste) && $poste === 'ADMIN') ? 'selected' : '' ?>>Admin</option>
            </select>
        </fieldset>

        <button type="submit" class="btn-primary">Créer le compte personnel</button>
    </form>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
