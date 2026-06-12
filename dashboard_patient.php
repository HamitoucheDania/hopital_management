<?php
/**
 * Tableau de bord du patient connecté.
 *
 * Cette page permet :
 * - de vérifier que l'utilisateur connecté est bien un patient,
 * - de charger les informations principales du patient (NSS, identité, email, droits),
 * - de calculer quelques statistiques (nombre de séjours, séjours en cours, traitements actifs, factures en attente),
 * - d'afficher les informations de carte vitale,
 * - d'afficher les derniers séjours, traitements et factures.
 *
 * @package HospitCare
 */

// dashboard_patient.php — tableau de bord du patient connecté

header('Content-Type: text/html; charset=UTF-8');
session_start();

// Vérification : uniquement patient
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'patient') {
    http_response_code(403);
    die('Accès réservé aux patients.');
}

/**
 * Identifiant du patient connecté (récupéré depuis la session).
 *
 * @var int $patientId
 */
$patientId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/secret/database.php';

// Connexion PDO
/**
 * Connexion à la base de données PostgreSQL.
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

//Infos patient

/**
 * Récupération des informations de base du patient.
 *
 * @var PDOStatement $stmt
 * @var array<string,mixed>|false $patient
 */
$stmt = $pdo->prepare("
    SELECT patient_id, nss, nom, prenom, date_naissance, sexe, email, droits_actifs, is_active
    FROM PATIENT
    WHERE patient_id = :id
");
$stmt->execute([':id' => $patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    http_response_code(404);
    die('Patient introuvable.');
}


//Statistiques perso

/**
 * Nombre total de séjours du patient.
 *
 * @var int $nbSejours
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM SEJOUR WHERE patient_id = :id
");
$stmt->execute([':id' => $patientId]);
$nbSejours = (int)$stmt->fetchColumn();

/**
 * Nombre de séjours en cours (date_fin NULL ou >= NOW()).
 *
 * @var int $nbSejoursEnCours
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM SEJOUR 
    WHERE patient_id = :id
      AND (date_fin IS NULL OR date_fin >= NOW())
");
$stmt->execute([':id' => $patientId]);
$nbSejoursEnCours = (int)$stmt->fetchColumn();

/**
 * Nombre de traitements actifs (date_fin NULL ou >= CURRENT_DATE).
 *
 * @var int $nbTraitementsActifs
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM TRAITEMENT
    WHERE patient_id = :id
      AND (date_fin IS NULL OR date_fin >= CURRENT_DATE)
");
$stmt->execute([':id' => $patientId]);
$nbTraitementsActifs = (int)$stmt->fetchColumn();

/**
 * Nombre de factures en attente de paiement.
 *
 * @var int $nbFacturesAttente
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM FACTURE f
    JOIN SEJOUR s ON s.sejour_id = f.sejour_id
    WHERE s.patient_id = :id
      AND f.statut = 'EN_ATTENTE'
");
$stmt->execute([':id' => $patientId]);
$nbFacturesAttente = (int)$stmt->fetchColumn();

//Carte vitale

/**
 * Informations de la carte vitale du patient (si existante).
 *
 * @var array<string,mixed>|false $carteVitale
 */
$stmt = $pdo->prepare("
    SELECT carte_id, numero_carte, date_expiration, statut
    FROM CARTE_VITALE
    WHERE patient_id = :id
");
$stmt->execute([':id' => $patientId]);
$carteVitale = $stmt->fetch(PDO::FETCH_ASSOC);

//Derniers séjours, traitements, factures

/**
 * Derniers séjours du patient (limités à 5).
 *
 * @var array<int,array<string,mixed>> $derniersSejours
 */
$stmt = $pdo->prepare("
    SELECT s.sejour_id, s.date_debut, s.date_fin, s.motif,
           sv.libelle AS service_libelle
    FROM SEJOUR s
    LEFT JOIN SERVICE sv ON sv.service_id = s.service_id
    WHERE s.patient_id = :id
    ORDER BY s.date_debut DESC
    LIMIT 5
");
$stmt->execute([':id' => $patientId]);
$derniersSejours = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Derniers traitements du patient (limités à 5).
 *
 * @var array<int,array<string,mixed>> $derniersTraitements
 */
$stmt = $pdo->prepare("
    SELECT traitement_id, nom_medicament, dosage, date_debut, date_fin
    FROM TRAITEMENT
    WHERE patient_id = :id
    ORDER BY date_debut DESC
    LIMIT 5
");
$stmt->execute([':id' => $patientId]);
$derniersTraitements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Dernières factures du patient (limitées à 5).
 *
 * @var array<int,array<string,mixed>> $derniersFactures
 */
$stmt = $pdo->prepare("
    SELECT f.facture_id, f.montant_total, f.date_emission, f.statut,
           s.sejour_id
    FROM FACTURE f
    JOIN SEJOUR s ON s.sejour_id = f.sejour_id
    WHERE s.patient_id = :id
    ORDER BY f.date_emission DESC
    LIMIT 5
");
$stmt->execute([':id' => $patientId]);
$derniersFactures = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Titre de la page, utilisé par le header.
 *
 * @var string $pageTitle
 */
$pageTitle = 'Tableau de bord patient';
include __DIR__ . '/include/header.inc.php';
?>

<div class="dashboard-page">

    <section class="dashboard-header">
        <div>
            <h1>Bonjour <?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></h1>
            <p class="dashboard-subtitle">
                Vue d’ensemble de votre dossier hospitalier.
            </p>
            <div class="role-banner">
                <span class="role-banner-dot"></span>
                <span>Espace patient</span>
            </div>
        </div>
    </section>

    <section class="dashboard-grid">
        <article class="card card-stat stat-sejours">
            <h2>Mes séjours</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Historique de séjours</span>
            </div>
            <p class="card-number"><?= (int)$nbSejours ?></p>
            <p class="card-info">Dont <?= (int)$nbSejoursEnCours ?> séjour(s) en cours.</p>
        </article>

        <article class="card card-stat stat-traitements">
            <h2>Traitements</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Traitements actifs</span>
            </div>
            <p class="card-number"><?= (int)$nbTraitementsActifs ?></p>
            <p class="card-info">Traitements en cours aujourd’hui.</p>
        </article>

        <article class="card card-stat stat-factures">
            <h2>Factures</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Factures en attente</span>
            </div>
            <p class="card-number"><?= (int)$nbFacturesAttente ?></p>
            <p class="card-info">Factures non encore réglées.</p>
        </article>

        <article class="card card-stat stat-compte">
            <h2>Compte & droits</h2>
            <div class="card-stat-label">
                <span class="stat-dot"></span>
                <span>Statut du compte</span>
            </div>
            <p class="card-number" style="font-size:1.4rem;">
                <?= $patient['is_active'] ? 'Compte actif' : 'Compte inactif' ?>
            </p>
            <p class="card-info">
                Droits : 
                <?php if ($patient['droits_actifs']): ?>
                    <span class="pill-status ok">Droits actifs</span>
                <?php else: ?>
                    <span class="pill-status warn">Droits inactifs</span>
                <?php endif; ?>
            </p>
        </article>
    </section>

    <section class="dashboard-bottom">
        <div class="card card-large">
            <h2>Mes informations</h2>
            <p><strong>NSS :</strong> <?= htmlspecialchars($patient['nss']) ?></p>
            <p><strong>Date de naissance :</strong> <?= htmlspecialchars($patient['date_naissance']) ?></p>
            <p><strong>Sexe :</strong> <?= htmlspecialchars($patient['sexe']) ?></p>
            <p><strong>E-mail :</strong> <?= htmlspecialchars($patient['email']) ?></p>

            <h3 style="margin-top:1rem;">Carte vitale</h3>
            <?php if ($carteVitale): ?>
                <p>
                    <strong>Numéro :</strong> 
                    <?= htmlspecialchars($carteVitale['numero_carte']) ?>
                </p>
                <p>
                    <strong>Expiration :</strong> 
                    <?= htmlspecialchars($carteVitale['date_expiration']) ?>
                </p>
                <p>
                    <strong>Statut :</strong>
                    <?php
                    $statutCv = $carteVitale['statut'] ?? '';
                    $cvClass = ($statutCv === 'ACTIVE') ? 'ok' : 'warn';
                    ?>
                    <span class="pill-status <?= $cvClass ?>">
                        <?= htmlspecialchars($statutCv) ?>
                    </span>
                </p>
            <?php else: ?>
                <p class="card-info">
                    Aucune carte vitale enregistrée pour le moment.
                </p>
            <?php endif; ?>
        </div>

        <div class="card card-large">
            <h2>Derniers séjours</h2>
            <?php if (empty($derniersSejours)): ?>
                <p class="card-info">Vous n’avez pas encore de séjour enregistré.</p>
            <?php else: ?>
                <ul class="list-simple">
                    <?php foreach ($derniersSejours as $s): ?>
                        <li>
                            <strong>Séjour #<?= (int)$s['sejour_id'] ?></strong>
                            <?php if (!empty($s['service_libelle'])): ?>
                                <span class="stay-service">
                                    <?= htmlspecialchars($s['service_libelle']) ?>
                                </span>
                            <?php endif; ?>
                            <div class="stay-dates">
                                <?= htmlspecialchars($s['date_debut']) ?>
                                → <?= htmlspecialchars($s['date_fin'] ?? '—') ?>
                            </div>
                            <div class="stay-motif">
                                Motif : <?= nl2br(htmlspecialchars($s['motif'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="dashboard-bottom">
        <div class="card card-large">
            <h2>Derniers traitements</h2>
            <?php if (empty($derniersTraitements)): ?>
                <p class="card-info">Aucun traitement enregistré.</p>
            <?php else: ?>
                <ul class="list-simple">
                    <?php foreach ($derniersTraitements as $t): ?>
                        <li>
                            <strong><?= htmlspecialchars($t['nom_medicament']) ?></strong>
                            — <?= htmlspecialchars($t['dosage']) ?>
                            <div class="stay-dates">
                                Du <?= htmlspecialchars($t['date_debut']) ?>
                                <?php if ($t['date_fin']): ?>
                                    au <?= htmlspecialchars($t['date_fin']) ?>
                                <?php else: ?>
                                    <span class="pill-small">(en cours)</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card card-large">
            <h2>Dernières factures</h2>
            <?php if (empty($derniersFactures)): ?>
                <p class="card-info">Aucune facture enregistrée.</p>
            <?php else: ?>
                <ul class="list-simple">
                    <?php foreach ($derniersFactures as $f): ?>
                        <li>
                            <strong>Facture #<?= (int)$f['facture_id'] ?></strong>
                            — Séjour #<?= (int)$f['sejour_id'] ?>
                            <div class="stay-dates">
                                Montant : <?= number_format((float)$f['montant_total'], 2, ',', ' ') ?> €
                                <br>
                                Émise le <?= htmlspecialchars($f['date_emission']) ?> —
                                Statut : <?= htmlspecialchars($f['statut']) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="card-info" style="margin-top:0.6rem;">
                    <a href="/patient_factures.php">Voir toutes mes factures &rarr;</a>
                </p>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
