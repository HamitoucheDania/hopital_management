<?php
/**
 * Fichier d'en-tête global (header.inc.php) — HospitCare
 *
 * Ce fichier est inclus en haut de chaque page du site.  
 * Il gère :
 *
 * 1) **La session utilisateur**
 *    - Récupération du nom, rôle et type (patient / personnel / médical / administratif).
 *
 * 2) **La gestion des cookies**
 *    - Consentement cookies (acceptation / refus via paramètres GET)
 *    - Thème clair / sombre (cookie `style`)
 *    - Dernière visite (cookie `last_visit` si cookies non essentiels acceptés)
 *
 * 3) **La configuration du thème**
 *    - Chargement du CSS clair par défaut
 *    - Ajout du CSS sombre si sélectionné
 *
 * 4) **L’initialisation du titre de page**
 *
 * 5) **L’affichage de l’en-tête graphique**
 *    - Logo / nom de l’application
 *    - Navigation dynamique selon le rôle utilisateur :
 *         - non connecté
 *         - patient
 *         - personnel médical
 *         - personnel administratif
 *    - Bouton pour basculer le thème
 *    - Informations utilisateur + bouton de déconnexion
 *
 * 6) **La bannière de consentement cookies**
 *    - Affichée uniquement si aucun choix n’a été exprimé
 *
 * @package HospitCare
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ========= UTILISATEUR ========= */
$userName      = $_SESSION['user_name'] ?? "Utilisateur";
$userRole      = $_SESSION['user_role'] ?? null;
$personnelType = $_SESSION['personnel_type'] ?? null;

/* ========= COOKIES / STYLE ========= */

$cookieConsent      = $_COOKIE['cookie_consent'] ?? null;
$allowNonEssential  = ($cookieConsent === 'accepted');

/**
 * Gestion des actions "Accepter" ou "Refuser" les cookies non essentiels.
 * Redirige ensuite vers la même page sans les paramètres GET.
 */
if (isset($_GET['accept_cookies'])) {
    setcookie('cookie_consent', 'accepted', [
        'expires'  => time() + 365 * 24 * 60 * 60,
        'path'     => '/',
        'secure'   => false,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $redirect = strtok($_SERVER['REQUEST_URI'], '?') ?: '/index.php';
    header('Location: ' . $redirect);
    exit;
}

if (isset($_GET['refuse_cookies'])) {
    setcookie('cookie_consent', 'refused', [
        'expires'  => time() + 365 * 24 * 60 * 60,
        'path'     => '/',
        'secure'   => false,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $redirect = strtok($_SERVER['REQUEST_URI'], '?') ?: '/index.php';
    header('Location: ' . $redirect);
    exit;
}

/* ========= STYLE ========= */

$style = $_COOKIE['style'] ?? 'clair';

/**
 * Basculer le thème clair → sombre ou sombre → clair.
 * Enregistre le choix dans un cookie uniquement si l'utilisateur autorise.
 */
if (isset($_POST['toggle_style'])) {
    $newStyle = ($style === 'clair') ? 'sombre' : 'clair';
    $style    = $newStyle;

    if ($allowNonEssential) {
        setcookie('style', $newStyle, [
            'expires'  => time() + 5 * 24 * 60 * 60,
            'path'     => '/',
            'secure'   => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    $redirect = $_SERVER['REQUEST_URI'] ?? '/index.php';
    header('Location: ' . $redirect);
    exit;
}

/* ========= DERNIÈRE VISITE ========= */

$lastVisit = $_COOKIE['last_visit'] ?? null;

/**
 * Enregistre la date/heure de dernière visite dans un cookie
 * uniquement si les cookies non essentiels sont acceptés.
 */
if ($allowNonEssential) {
    $currentVisit = date('d/m/Y H:i:s');
    setcookie('last_visit', $currentVisit, [
        'expires'  => time() + 365 * 24 * 60 * 60,
        'path'     => '/',
        'secure'   => false,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

/* ========= TITRE ========= */
$pageTitle = $pageTitle ?? 'Gestion Hospitalière';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="/pictures/logo.png">

    <!-- Thèmes -->
    <link rel="stylesheet" href="/css/clair.css?v=1">
    <?php if ($style === 'sombre'): ?>
        <link rel="stylesheet" href="/css/sombre.css?v=1">
    <?php endif; ?>

    <script src="/include/script.js" defer></script>
</head>
<body class="layout <?= htmlspecialchars($style, ENT_QUOTES) ?>">
<header class="app-header">

    <div class="header-left">
        <div class="logo">
            <a href="/index.php" class="brand">
                <img src="/pictures/logo.png" alt="Logo" class="logo-img">
            </a>

            <div class="logo-text">
                <span class="logo-title">HospitCare</span>
                <span class="logo-subtitle">Gestion hospitalière</span>
            </div>
        </div>

        <nav class="main-nav">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <!-- Non connecté -->
                <a href="/index.php" class="nav-link">Accueil</a>

            <?php elseif ($userRole === 'patient'): ?>
                <!-- Navigation patient -->
                <a href="/patient_passages.php" class="nav-link">Mes passages</a>
                <a href="/patient_sejours.php" class="nav-link">Mes séjours</a>
                <a href="/patient_actes.php" class="nav-link">Mes actes médicaux</a>
                <a href="/patient_traitements.php" class="nav-link">Mes traitements</a>
                <a href="/patient_factures.php" class="nav-link">Mes factures</a>

            <?php elseif ($userRole === 'personnel'): ?>
                <!-- Navigation personnel -->
                <a href="/patients_historique.php" class="nav-link">Historique patients</a>
                <a href="/sejours.php" class="nav-link">Séjours</a>
                <a href="/actes.php" class="nav-link">Actes médicaux</a>
                <a href="/traitements.php" class="nav-link">Traitements</a>
                <a href="/patients_presents.php" class="nav-link">Patients présents</a>

                <?php if ($personnelType === 'ADMINISTRATIF'): ?>
                    <a href="/admin_facturation.php" class="nav-link">Factures</a>
                <?php endif; ?>

            <?php endif; ?>
        </nav>
    </div>

    <div class="header-right">

        <form method="post" class="theme-toggle-form" style="margin-right:8px;">
            <button type="submit" name="toggle_style" title="Basculer le thème">
                <?= ($style === 'clair') ? '🌙' : '☀️' ?>
            </button>
        </form>

        <?php if (isset($_SESSION['user_id'], $_SESSION['user_role'])): ?>
            <div class="user-info">
                <div class="user-initials">
                    <?= strtoupper(substr($userName, 0, 1)) ?>
                </div>
                <div class="user-text">
                    <span class="user-name"><?= htmlspecialchars($userName) ?></span>
                    <span class="user-role"><?= htmlspecialchars($userRole) ?></span>
                </div>
            </div>

            <a href="/deconnexion.php" class="logout-link">Déconnexion</a>
        <?php else: ?>
            <a href="/connexion.php" class="btn-primary" style="padding:8px 16px; font-size:0.9rem;">
                Connexion
            </a>
        <?php endif; ?>
    </div>

</header>

<?php if ($cookieConsent === null): ?>
    <div class="cookie-banner">
        <p>
            <span class="cookie-banner-title">Préférences de cookies</span>
            Nous utilisons des cookies pour mémoriser votre thème (clair/sombre) et votre dernière visite.
            Vous pouvez accepter ou refuser les cookies non essentiels à tout moment.
        </p>
        <div>
            <a href="?accept_cookies=1" class="cookie-btn">Accepter</a>
            <a href="?refuse_cookies=1" class="cookie-btn secondary">Refuser</a>
        </div>
    </div>
<?php endif; ?>
<main class="app-main">
