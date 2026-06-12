<?php
/**
 * patient_fiche.php — Fiche détaillée d’un patient (côté personnel).
 *
 * Objectif :
 * - Afficher le dossier complet d’un patient pour un membre du personnel.
 * - Centraliser sur une même page :
 *      • informations générales du patient,
 *      • passages à l’accueil (SESSION),
 *      • séjours hospitaliers,
 *      • traitements,
 *      • actes médicaux,
 *      • factures.
 *
 * Sécurité / accès :
 * - Accès strictement réservé aux utilisateurs connectés avec le rôle `personnel`.
 * - Un paramètre GET `id` (patient_id) est obligatoire.
 *
 * Filtrage :
 * - Deux champs GET optionnels `date_from` et `date_to` permettent de filtrer
 *   l’ensemble des données sur une période :
 *      • Passages : filtre sur `SESSION.date_passage`
 *      • Séjours : seuls ceux qui chevauchent l’intervalle sont inclus
 *      • Traitements : clauses sur date_debut / date_fin (chevauchement)
 *      • Actes : filtre sur `ACTE_MEDICAL.date_acte`
 *      • Factures : filtre sur `FACTURE.date_emission`
 *
 * Sections principales :
 * - Infos patient (NSS, naissance, sexe, email, compte/droits actifs).
 * - Passages à l’accueil (SESSION + ACCUEIL).
 * - Séjours (SEJOUR + SERVICE) avec bouton "Nouveau séjour" pour le médical.
 * - Traitements (TRAITEMENT) avec statut ACTIF / TERMINÉ et bouton d’ajout.
 * - Actes médicaux (ACTE_MEDICAL + SEJOUR) avec bouton d’ajout pour le médical.
 * - Factures (FACTURE + SEJOUR).
 *
 * Variables clés :
 * @var int         $patientId      ID du patient affiché (provenant de GET)
 * @var string|null $personnelType  Type de personnel connecté (MEDICAL / ADMINISTRATIF)
 * @var string      $dateFrom       Date de début de filtre (AAAA-MM-JJ) ou vide
 * @var string      $dateTo         Date de fin de filtre (AAAA-MM-JJ) ou vide
 * @var string|null $dfSql          Date de début au format SQL (Y-m-d 00:00:00) ou null
 * @var string|null $dtSql          Date de fin au format SQL (Y-m-d 23:59:59) ou null
 * @var bool        $hasDateFilter  Indique si au moins un filtre de date est actif
 * @var array       $patient        Informations générales du patient (table PATIENT)
 * @var array       $passages       Liste des passages (SESSION + ACCUEIL)
 * @var array       $sejours        Liste des séjours (SEJOUR + SERVICE)
 * @var array       $traitements    Liste des traitements (TRAITEMENT)
 * @var array       $actes          Liste des actes médicaux (ACTE_MEDICAL + SEJOUR)
 * @var array       $factures       Liste des factures (FACTURE + SEJOUR)
 *
 * @package HospitCare
 */

// patient_fiche.php — fiche détaillée d’un patient

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'personnel'
) {
    http_response_code(403);
    die("Accès réservé au personnel.");
}

$personnelType = $_SESSION['personnel_type'] ?? null;

$patientId = (int)($_GET['id'] ?? 0);
if ($patientId <= 0) {
    http_response_code(400);
    die("Patient invalide.");
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

/* ========= FILTRE DATES GLOBAL ========= */

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

$dfSql = $dtSql = null;
$hasDateFilter = false;

if ($dateFrom !== '') {
    $dtObj = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dtObj) {
        $dfSql = $dtObj->format('Y-m-d 00:00:00');
        $hasDateFilter = true;
    }
}

if ($dateTo !== '') {
    $dtObj = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dtObj) {
        $dtSql = $dtObj->format('Y-m-d 23:59:59');
        $hasDateFilter = true;
    }
}

/* ========= Infos patient ========= */

$stmt = $pdo->prepare("
    SELECT patient_id, nss, nom, prenom, date_naissance, sexe, email,
           droits_actifs, is_active
    FROM PATIENT
    WHERE patient_id = :id
");
$stmt->execute([':id' => $patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    http_response_code(404);
    die("Patient introuvable.");
}

/* ========= Passages (SESSION) ========= */

$sqlPassages = "
    SELECT se.session_id, se.date_passage, se.statut, se.motif,
           a.accueil_code, a.libelle AS accueil_libelle
    FROM SESSION se
    JOIN ACCUEIL a ON a.accueil_id = se.accueil_id
    WHERE se.patient_id = :id
";

$paramsPassages = [':id' => $patientId];

if ($hasDateFilter) {
    if ($dfSql !== null) {
        $sqlPassages .= " AND se.date_passage >= :df";
        $paramsPassages[':df'] = $dfSql;
    }
    if ($dtSql !== null) {
        $sqlPassages .= " AND se.date_passage <= :dt";
        $paramsPassages[':dt'] = $dtSql;
    }
}
$sqlPassages .= " ORDER BY se.date_passage DESC";

$stmt = $pdo->prepare($sqlPassages);
$stmt->execute($paramsPassages);
$passages = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========= Séjours ========= */

$sqlSejours = "
    SELECT s.sejour_id, s.date_debut, s.date_fin, s.motif,
           sv.libelle AS service_libelle
    FROM SEJOUR s
    LEFT JOIN SERVICE sv ON sv.service_id = s.service_id
    WHERE s.patient_id = :id
";

$paramsSejours = [':id' => $patientId];

if ($hasDateFilter && $dfSql !== null && $dtSql !== null) {
    // Séjours qui chevauchent l’intervalle
    $sqlSejours .= "
        AND s.date_debut <= :dt
        AND (s.date_fin IS NULL OR s.date_fin >= :df)
    ";
    $paramsSejours[':df'] = $dfSql;
    $paramsSejours[':dt'] = $dtSql;
}

$sqlSejours .= " ORDER BY s.date_debut DESC";

$stmt = $pdo->prepare($sqlSejours);
$stmt->execute($paramsSejours);
$sejours = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========= Traitements ========= */

$sqlTraitements = "
    SELECT traitement_id, nom_medicament, dosage, date_debut, date_fin
    FROM TRAITEMENT
    WHERE patient_id = :id
";
$paramsTrai = [':id' => $patientId];

if ($hasDateFilter && $dfSql !== null && $dtSql !== null) {
    // Traitements qui chevauchent l’intervalle
    $sqlTraitements .= "
        AND date_debut <= :dt
        AND (date_fin IS NULL OR date_fin >= :df)
    ";
    $paramsTrai[':df'] = $dfSql;
    $paramsTrai[':dt'] = $dtSql;
}

$sqlTraitements .= " ORDER BY date_debut DESC";

$stmt = $pdo->prepare($sqlTraitements);
$stmt->execute($paramsTrai);
$traitements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========= Actes médicaux ========= */

$sqlActes = "
    SELECT am.acte_id, am.date_acte, am.code_ccam, am.cout,
           s.sejour_id
    FROM ACTE_MEDICAL am
    JOIN SEJOUR s ON s.sejour_id = am.sejour_id
    WHERE s.patient_id = :id
";
$paramsActes = [':id' => $patientId];

if ($hasDateFilter) {
    if ($dfSql !== null) {
        $sqlActes .= " AND am.date_acte >= :df";
        $paramsActes[':df'] = $dfSql;
    }
    if ($dtSql !== null) {
        $sqlActes .= " AND am.date_acte <= :dt";
        $paramsActes[':dt'] = $dtSql;
    }
}

$sqlActes .= " ORDER BY am.date_acte DESC";

$stmt = $pdo->prepare($sqlActes);
$stmt->execute($paramsActes);
$actes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========= Factures ========= */

$sqlFactures = "
    SELECT f.facture_id, f.montant_total, f.date_emission, f.statut,
           s.sejour_id
    FROM FACTURE f
    JOIN SEJOUR s ON s.sejour_id = f.sejour_id
    WHERE s.patient_id = :id
";
$paramsFact = [':id' => $patientId];

if ($hasDateFilter) {
    if ($dfSql !== null) {
        $sqlFactures .= " AND f.date_emission >= :df";
        $paramsFact[':df'] = $dfSql;
    }
    if ($dtSql !== null) {
        $sqlFactures .= " AND f.date_emission <= :dt";
        $paramsFact[':dt'] = $dtSql;
    }
}

$sqlFactures .= " ORDER BY f.date_emission DESC";

$stmt = $pdo->prepare($sqlFactures);
$stmt->execute($paramsFact);
$factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Fiche patient";
include __DIR__ . "/include/header.inc.php";
?>
<section class="dashboard-page">

    <section class="dashboard-header">
        <div>
            <h1><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></h1>
            <p class="dashboard-subtitle">
                Dossier complet du patient.
            </p>
        </div>
        <div class="dashboard-actions">
            <a href="/patients_historique.php" class="btn-secondary">&larr; Retour à la liste</a>
        </div>
    </section>

    <!-- Barre de filtre dates globale -->
    <section class="card filter-bar-card">
        <h2>Filtrer par période</h2>
        <form method="get" class="filter-bar">
            <input type="hidden" name="id" value="<?= (int)$patientId ?>">
            <div class="field">
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="field">
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="field">
                <button type="submit" class="btn-apply">Appliquer</button>
                <?php if ($hasDateFilter): ?>
                    <a href="patient_fiche.php?id=<?= (int)$patientId ?>" class="reset-link">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="patient-layout">

        <!-- COLONNE GAUCHE : infos générales -->
        <div class="card info-card">
            <h2>Informations générales</h2>
            <p><span class="info-label">NSS :</span> <?= htmlspecialchars($patient['nss']) ?></p>
            <p><span class="info-label">Date de naissance :</span> <?= htmlspecialchars($patient['date_naissance']) ?></p>
            <p><span class="info-label">Sexe :</span> <?= htmlspecialchars($patient['sexe']) ?></p>
            <p><span class="info-label">Email :</span> <?= htmlspecialchars($patient['email']) ?></p>

            <p>
                <span class="info-label">Compte :</span>
                <?php if ($patient['is_active']): ?>
                    <span class="status-pill ok">ACTIF</span>
                <?php else: ?>
                    <span class="status-pill warn">INACTIF</span>
                <?php endif; ?>
            </p>
            <p>
                <span class="info-label">Droits :</span>
                <?php if ($patient['droits_actifs']): ?>
                    <span class="badge">Droits actifs</span>
                <?php else: ?>
                    <span class="badge" style="background:#b91c1c33;color:#fecaca;">
                        Droits inactifs
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <!-- COLONNE DROITE : 5 sections -->
        <div class="patient-right-grid">

            <!-- Passages -->
            <div class="card">
                <h2>Passages à l’accueil</h2>
                <?php if (empty($passages)): ?>
                    <p class="card-info">Aucun passage enregistré sur la période.</p>
                <?php else: ?>
                    <div class="scroll-table">
                        <table class="table-basic">
                            <thead>
                            <tr>
                                <th>Date / heure</th>
                                <th>Type accueil</th>
                                <th>Motif</th>
                                <th>Statut</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($passages as $pa): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pa['date_passage']) ?></td>
                                    <td>
                                        <span class="badge">
                                            <?= htmlspecialchars($pa['accueil_code'] . ' - ' . $pa['accueil_libelle']) ?>
                                        </span>
                                    </td>
                                    <td style="white-space:normal;max-width:240px;">
                                        <?= nl2br(htmlspecialchars($pa['motif'])) ?>
                                    </td>
                                    <td><?= htmlspecialchars($pa['statut']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Séjours -->
            <div class="card">
                <h2>Séjours</h2>
                <?php if (empty($sejours)): ?>
                    <p class="card-info">Aucun séjour enregistré sur la période.</p>
                <?php else: ?>
                    <div class="scroll-table">
                        <table class="table-basic">
                            <thead>
                            <tr>
                                <th>Séjour</th>
                                <th>Service</th>
                                <th>Motif</th>
                                <th>Début</th>
                                <th>Fin</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sejours as $s): ?>
                                <tr>
                                    <td>#<?= (int)$s['sejour_id'] ?></td>
                                    <td><?= htmlspecialchars($s['service_libelle'] ?? 'Non renseigné') ?></td>
                                    <td style="white-space:normal;max-width:240px;">
                                        <?= nl2br(htmlspecialchars($s['motif'])) ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['date_debut']) ?></td>
                                    <td><?= htmlspecialchars($s['date_fin'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($personnelType === 'MEDICAL'): ?>
                    <div class="section-actions">
                        <a href="/ajouter_sejour.php?patient_id=<?= (int)$patientId ?>" class="btn-primary">
                            + Nouveau séjour
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Traitements -->
            <div class="card">
                <h2>Traitements</h2>
                <?php if (empty($traitements)): ?>
                    <p class="card-info">Aucun traitement enregistré sur la période.</p>
                <?php else: ?>
                    <div class="scroll-table">
                        <table class="table-basic">
                            <thead>
                            <tr>
                                <th>Médicament</th>
                                <th>Dosage</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Statut</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($traitements as $t): ?>
                                <?php
                                $enCours = ($t['date_fin'] === null || $t['date_fin'] === '')
                                    || (strtotime($t['date_fin']) >= strtotime(date('Y-m-d')));
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['nom_medicament']) ?></td>
                                    <td><?= htmlspecialchars($t['dosage']) ?></td>
                                    <td><?= htmlspecialchars($t['date_debut']) ?></td>
                                    <td><?= htmlspecialchars($t['date_fin'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($enCours): ?>
                                            <span class="status-pill ok">ACTIF</span>
                                        <?php else: ?>
                                            <span class="status-pill warn">TERMINÉ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($personnelType === 'MEDICAL'): ?>
                    <div class="section-actions">
                        <a href="/ajouter_traitement.php?patient_id=<?= (int)$patientId ?>" class="btn-primary">
                            + Nouveau traitement
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actes -->
            <div class="card">
                <h2>Actes médicaux</h2>
                <?php if (empty($actes)): ?>
                    <p class="card-info">Aucun acte médical enregistré sur la période.</p>
                <?php else: ?>
                    <div class="scroll-table">
                        <table class="table-basic">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Séjour</th>
                                <th>Code CCAM</th>
                                <th>Coût</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($actes as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['date_acte']) ?></td>
                                    <td>#<?= (int)$a['sejour_id'] ?></td>
                                    <td><?= htmlspecialchars($a['code_ccam']) ?></td>
                                    <td><?= number_format((float)$a['cout'], 2, ',', ' ') ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($personnelType === 'MEDICAL'): ?>
                    <div class="section-actions">
                        <a href="/ajouter_acte.php?patient_id=<?= (int)$patientId ?>" class="btn-primary">
                            + Nouvel acte médical
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Factures -->
            <div class="card">
                <h2>Factures</h2>
                <?php if (empty($factures)): ?>
                    <p class="card-info">Aucune facture enregistrée sur la période.</p>
                <?php else: ?>
                    <div class="scroll-table">
                        <table class="table-basic">
                            <thead>
                            <tr>
                                <th>Facture</th>
                                <th>Séjour</th>
                                <th>Date émission</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($factures as $f): ?>
                                <tr>
                                    <td>#<?= (int)$f['facture_id'] ?></td>
                                    <td>#<?= (int)$f['sejour_id'] ?></td>
                                    <td><?= htmlspecialchars($f['date_emission']) ?></td>
                                    <td><?= number_format((float)$f['montant_total'], 2, ',', ' ') ?> €</td>
                                    <td><?= htmlspecialchars($f['statut']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</section>

<?php include __DIR__ . "/include/footer.inc.php"; ?>
