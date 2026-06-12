<?php
/**
 * pointer_depart.php — Clôture d’une session de passage (départ patient)
 *
 * Rôle du script :
 * - Vérifier que l’utilisateur est un membre **administratif** du personnel.
 * - Accepter uniquement les requêtes POST (sécurité).
 * - Récupérer l’identifiant d’une session d’accueil.
 * - Marquer la session comme **TERMINEE** uniquement si elle est encore `EN_COURS`.
 * - Rediriger vers patients_presents.php avec un message de succès ou d’erreur.
 *
 * Sécurité :
 * - Accès strictement réservé au personnel administratif (`personnel_type = ADMINISTRATIF`).
 * - Requêtes GET interdites → redirection automatique.
 *
 * Variables :
 * @var int $sessionId  Identifiant de la session à clôturer.
 * @var PDO $pdo        Connexion PDO à la base PostgreSQL.
 *
 * Base de données :
 * - Table : SESSION
 *   • session_id
 *   • statut (EN_COURS → TERMINEE)
 *
 * @package HospitCare
 */

// pointer_depart.php — pointer le départ d’un patient (clôturer la session)

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'personnel' ||
    ($_SESSION['personnel_type'] ?? '') !== 'ADMINISTRATIF'
) {
    http_response_code(403);
    die('Accès réservé au personnel administratif.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: patients_presents.php');
    exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);
if ($sessionId <= 0) {
    header('Location: patients_presents.php?depart=err');
    exit;
}

require_once __DIR__ . '/secret/database.php';

try {
    $dsn = 'pgsql:host=' . trim($host) . ';port=5432;dbname=' . $dbname;
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    header('Location: patients_presents.php?depart=err');
    exit;
}

// On ne clôture que si la session est encore EN_COURS
$stmt = $pdo->prepare("
    UPDATE SESSION
    SET statut = 'TERMINEE'
    WHERE session_id = :sid
      AND statut = 'EN_COURS'
");
$stmt->execute([':sid' => $sessionId]);

if ($stmt->rowCount() === 1) {
    header('Location: patients_presents.php?depart=ok');
} else {
    header('Location: patients_presents.php?depart=err');
}
exit;
