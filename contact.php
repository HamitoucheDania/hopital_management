<?php
/**
 * Page statique : Contact.
 *
 * Cette page affiche les informations de contact du site :
 * - adresse e-mail de support,
 * - informations sur l'université,
 * - lien vers le site principal.
 *
 * Aucun traitement particulier ni accès restreint.
 *
 * @package HospitCare
 */

$pageTitle = "Contact";
include __DIR__ . "/include/header.inc.php";
?>

<section class="contact-wrapper">
    <h1>Contact</h1>
    <p>
        Vous souhaitez nous contacter concernant des questions techniques, votre compte patient,
        les données ou un problème sur le site ?  
        Retrouvez ci-dessous toutes les informations nécessaires.
    </p>

    <div class="contact-info">
        <p><span class="icon">📧</span> Email : <strong>contact@hospitcare.fr</strong></p>
        <p><span class="icon">🏫</span> Université : <strong>CY Cergy Paris Université</strong></p>
        <p><span class="icon">🌐</span> Site : <a href="https://gestion-hospitaliere.alwaysdata.net">HospitCare</a></p>
    </div>
</section>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
