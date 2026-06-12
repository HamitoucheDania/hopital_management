<?php
/**
 * index.php — Page d’accueil publique du site HospitCare.
 *
 * Comportement principal :
 * - Si l’utilisateur est connecté :
 *      • Patient  → redirection vers dashboard_patient.php
 *      • Personnel → redirection vers dashboard_personnel.php
 * - Sinon, affichage de la page d’accueil publique avec présentation du service.
 *
 * Affiche :
 * - un message de bienvenue,
 * - des images illustratives,
 * - un bouton pour se connecter,
 * - des sections de présentation : pourquoi choisir HospitCare, fonctionnalités, à propos.
 *
 * Variables utilisées :
 * @var string      $style       Thème actif (clair / sombre)
 * @var int|null    $userId      ID utilisateur en session
 * @var string|null $userRole    Rôle de l’utilisateur (patient / personnel)
 *
 * @package HospitCare
 */

// index.php — page d'accueil publique, redirection vers les dashboards si connecté

header('Content-Type: text/html; charset=UTF-8');
session_start();

$style = $_SESSION['style'] ?? 'clair';

$userId   = $_SESSION['user_id']   ?? null;
$userRole = $_SESSION['user_role'] ?? null;

// Si l'utilisateur est déjà connecté → redirection vers son tableau de bord
if ($userId !== null && $userRole === 'patient') {
    header('Location: /dashboard_patient.php');
    exit();
}

if ($userId !== null && $userRole === 'personnel') {
    header('Location: /dashboard_personnel.php');
    exit();
}

$pageTitle = 'Accueil';

include 'include/header.inc.php';
?>

<section class="welcome-section">
    <h1 class="welcome-title">Bienvenue sur HospitCare</h1>
    <p class="welcome-subtitle">
        Votre plateforme moderne pour une gestion hospitalière fluide, sécurisée et intuitive.
    </p>

    <div class="welcome-images">
        <img src="/pictures/index1.png" alt="Image 1" class="welcome-img">
        <img src="/pictures/index2.png" alt="Image 2" class="welcome-img">
        <img src="/pictures/index3.png" alt="Image 3" class="welcome-img">
    </div>

    <div class="welcome-buttons">
        <a href="/connexion.php" class="btn-primary">Se connecter</a>
    </div>
</section>

<section class="why-section">
    <h2>Pourquoi choisir HospitCare ?</h2>
    <div class="why-grid">

        <div class="why-card">
            <h3>Sécurité renforcée</h3>
            <p>Vos données sont protégées sur nos serveurs sécurisés. Encryptage, permissions avancées et confidentialité totale.</p>
        </div>

        <div class="why-card">
            <h3>Interface moderne</h3>
            <p>Un design clair, épuré et optimisé pour tous les appareils : ordinateur, tablette ou mobile.</p>
        </div>

        <div class="why-card">
            <h3>Gestion complète</h3>
            <p>Patients, séjours, actes médicaux, factures, sessions d’accueil… Tout est centralisé et facile d’accès.</p>
        </div>

    </div>
</section>

<section class="features-section">
    <h2>Fonctionnalités principales</h2>
    <ul class="features-list">
        <li>✔ Gestion administrative du patient</li>
        <li>✔ Suivi des séjours et actes médicaux</li>
        <li>✔ Gestion des factures et paiements</li>
        <li>✔ Historique complet des interactions</li>
        <li>✔ Système de rôles : patient, personnel, médecin, administration</li>
        <li>✔ Interface de connexion sécurisée</li>
    </ul>
</section>

<section class="about-section">
    <h2>À propos d’HospitCare</h2>
    <p>
        HospitCare est une solution développée afin de moderniser la gestion hospitalière.
        Elle facilite le travail du personnel soignant et administratif tout en permettant aux patients
        de retrouver clairement leurs informations essentielles.
    </p>
</section>

<?php include 'include/footer.inc.php'; ?>
