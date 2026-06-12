<?php
/**
 * qui_sommes_nous.php — Page de présentation de l’équipe HospitCare
 *
 * Rôle :
 * - Affiche une page publique présentant :
 *   • le projet HospitCare dans le cadre universitaire,
 *   • les membres de l’équipe étudiante,
 *   • les objectifs pédagogiques du projet.
 *
 * Accès :
 * - Page totalement publique (aucune connexion requise).
 *
 * Variables :
 * @var string $pageTitle  Titre de la page utilisé par le header.
 *
 * Structure :
 * - Section hero d’introduction.
 * - Carte de présentation du projet.
 * - Carte présentant les membres de l’équipe.
 * - Carte présentant les objectifs du projet.
 *
 * @package HospitCare
 */

// qui_sommes_nous.php — Page "Qui sommes-nous ?"

header('Content-Type: text/html; charset=UTF-8');
session_start();

$pageTitle = 'Qui sommes-nous ?';
include __DIR__ . '/include/header.inc.php';
?>
<section class="team-hero">
    <h1>Qui sommes-nous ?</h1>
    <p>Découvrez l’équipe étudiante derrière le projet HospitCare.</p>
</section>

<div class="team-wrapper">

    <!-- Présentation du projet -->
    <div class="team-card">
        <h2>Le projet HospitCare</h2>
        <p>
            HospitCare est un projet universitaire réalisé dans le cadre de la 
            <strong>SAE Base de Données</strong> à 
            <strong>CY Cergy Paris Université</strong>.
        </p>

        <p>Il relie trois domaines de notre formation :</p>

        <ul class="team-list">
            <li><strong>Réseaux</strong> — communication client/serveur & échanges sécurisés</li>
            <li><strong>Base de données</strong> — modélisation SQL, contraintes, intégrité</li>
            <li><strong>Développement Web</strong> — interfaces patients & personnel</li>
        </ul>
    </div>

    <!-- L’équipe -->
    <div class="team-card">
        <h2>L’équipe</h2>
        <p>
            Nous sommes trois étudiantes en Informatique à 
            <strong>CY Cergy Paris Université</strong>, passionnées par le développement et la conception de systèmes de gestion.
        </p>

        <div class="team-members">
            <div class="member-card">
                <div class="member-name">MOUSSAOUI Imane</div>
                <div class="member-role">Développement & Base de données</div>
            </div>
            <div class="member-card">
                <div class="member-name">CHEMIM Massiva</div>
                <div class="member-role">Modélisation & Interface</div>
            </div>
            <div class="member-card">
                <div class="member-name">HAMITOUCHE Dania</div>
                <div class="member-role">Réseaux & Architecture</div>
            </div>
        </div>
    </div>

    <!-- Objectif -->
    <div class="team-card">
        <h2>Notre objectif</h2>
        <p>
            Proposer une application claire, moderne et fonctionnelle illustrant :
        </p>

        <ul class="team-list">
            <li>la gestion d’un environnement médical réel,</li>
            <li>l’importance de la cohérence des données,</li>
            <li>l’intégration réseau–BD–web dans un même projet,</li>
            <li>le suivi des patients et du personnel via une interface ergonomique.</li>
        </ul>

        <p style="margin-top:1rem; font-size:0.9rem; opacity:0.7;">
            Ce projet est académique et n’a pas vocation à être utilisé dans un cadre hospitalier réel.
        </p>
    </div>

</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
