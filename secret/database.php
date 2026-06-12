<?php
/**
 * Configuration principale du site web HospitCare.
 *
 * Ce fichier contient :
 * - les informations de connexion PostgreSQL,
 * - la configuration SMTP pour l'envoi d'e-mails,
 * - l'import des classes PHPMailer.
 *
 * @package HospitCare
 */

// ======= CONNEXION À LA BASE DE DONNÉES =======

/**
 * Hôte du serveur PostgreSQL.
 * @var string
 */
$host = 'postgresql-gestion-hospitaliere.alwaysdata.net';

/**
 * Nom de la base de données PostgreSQL.
 * @var string
 */
$dbname = 'gestion-hospitaliere_db';

/**
 * Nom d'utilisateur pour la connexion à la base.
 * @var string
 */
$username = 'gestion-hospitaliere';

/**
 * Mot de passe pour la connexion PostgreSQL.
 * @var string
 */
$password = 'SaeBD25';

/**
 * Port utilisé pour PostgreSQL.
 * @var string
 */
$port = '5432';

// ======= CONFIGURATION SMTP (AlwaysData) =======

/**
 * Hôte SMTP pour l'envoi des e-mails.
 * @var string
 */
$mail_host = 'smtp-gestion-hospitaliere.alwaysdata.net';

/**
 * Port SMTP (généralement 587 pour TLS).
 * @var int
 */
$mail_port = 587;

/**
 * Identifiant du compte SMTP.
 * @var string
 */
$mail_username = 'gestion-hospitaliere@alwaysdata.net';

/**
 * Mot de passe du compte SMTP.
 * @var string
 */
$mail_password = 'SaeBD25';

/**
 * Adresse e-mail utilisée comme expéditeur.
 * @var string
 */
$mail_from = 'gestion-hospitaliere@alwaysdata.net';

/**
 * Nom associé à l'adresse expéditeur.
 * @var string
 */
$mail_from_name = 'HospitCare';

/**
 * URL racine du site web.
 * @var string
 */
$site_base_url = 'https://gestion-hospitaliere.alwaysdata.net';

// ======= IMPORT DE PHPMailer =======

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

?>
