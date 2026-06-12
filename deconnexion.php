<?php
/**
 * Script de déconnexion (logout).
 *
 * Ce fichier :
 * - initialise la session si besoin,
 * - supprime toutes les variables de session,
 * - détruit complètement la session,
 * - redirige l'utilisateur vers la page d'accueil.
 *
 * Aucun affichage, aucun formulaire : c’est une action directe.
 *
 * @package HospitCare
 */

// deconnexion.php — déconnexion simple

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Suppression de toutes les variables de session
session_unset();

// Destruction de la session
session_destroy();

// Redirection vers la page d'accueil
header('Location: index.php');
exit();
?>
