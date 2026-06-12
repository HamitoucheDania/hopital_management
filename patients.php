<?php
/**
 * patients.php — Liste complète des patients enregistrés.
 *
 * Rôle du script :
 * - Vérifier que l’utilisateur est bien un membre du personnel.
 * - Récupérer l’ensemble des patients dans la base de données.
 * - Afficher leur fiche synthétique dans un tableau (accès à la fiche détaillée via lien).
 * - Afficher des informations importantes :
 *      • identité (nom, prénom),
 *      • NSS,
 *      • date de naissance,
 *      • sexe,
 *      • statut du compte (actif / inactif),
 *      • droits actifs ou non.
 * - Proposer un bouton d’ajout d’un nouveau patient (`ajouter_patient.php`).
 *
 * Sécurité :
 * - Accès réservé au rôle `personnel`.
 *
 * Variables principales :
 * @var PDO   $pdo       Connexion PDO PostgreSQL.
 * @var array $patients  Liste des patients.
 *
 * Table utilisée :
 * - PATIENT
 *
 * @package HospitCare
 */

// patients.php — liste des patients

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'personnel'
) {
    http_response_code(403);
    die("Accès réservé au personnel.");
}

require_once __DIR__ . '/secret/database.php';

// Connexion BD
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die('Erreur BD.');
}

// Récupérer les patients
$sql = "
    SELECT patient_id, nss, nom, prenom, date_naissance, sexe,
           droits_actifs, is_active, email
    FROM PATIENT
    ORDER BY nom, prenom
";
$patients = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Patients";
include __DIR__ . "/include/header.inc.php";
?>

<section class="dashboard-page">
    <section class="dashboard-header">
        <div>
            <h1>Patients</h1>
            <p class="dashboard-subtitle">
                Liste des patients enregistrés dans l’établissement.
            </p>
        </div>
        <div class="dashboard-actions">
            <a href="/ajouter_patient.php" class="btn-primary">+ Nouveau patient</a>
        </div>
    </section>

    <section class="card card-large">
        <?php if (empty($patients)): ?>
            <p class="card-info">Aucun patient enregistré.</p>
        <?php else: ?>
            <table class="table-basic">
                <thead>
                <tr>
                    <th>Patient</th>
                    <th>NSS</th>
                    <th>Date de naissance</th>
                    <th>Sexe</th>
                    <th>Compte</th>
                    <th>Droits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td>
                            <div class="patient-name">
                                <a href="/patient_fiche.php?id=<?= (int)$p['patient_id'] ?>" class="link-patient">
                                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                </a>
                            </div>
                            <div class="patient-nss">
                                <?= htmlspecialchars($p['email']) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($p['nss']) ?></td>
                        <td><?= htmlspecialchars($p['date_naissance']) ?></td>
                        <td><?= htmlspecialchars($p['sexe']) ?></td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="status-pill actif">ACTIF</span>
                            <?php else: ?>
                                <span class="status-pill inactif">INACTIF</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['droits_actifs']): ?>
                                <span class="badge">Droits actifs</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fee2e2;color:#b91c1c;">
                                    Droits inactifs
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</section>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
