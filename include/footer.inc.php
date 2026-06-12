<?php
/**
 * Footer global du site.
 *
 * Affiche :
 * - le logo et le nom de l'application,
 * - la dernière date de visite (si autorisée par les cookies non essentiels),
 * - les crédits du site et les droits d’auteur,
 * - les liens vers les pages "Contact" et "Qui sommes-nous ?",
 * - le bouton de retour en haut de page,
 * - les scripts JS du thème et du bouton back-to-top.
 *
 * Variables utilisées :
 * @var string|null $lastVisit       Dernière date enregistrée (cookie optionnel)
 * @var bool        $allowNonEssential Indique si l’utilisateur accepte les cookies non essentiels
 *
 * @package HospitCare
 */
?>

</main>

<footer class="app-footer">

    <!-- GAUCHE : Logo + nom -->
    <div class="footer-left">
        <img src="/pictures/logo.png" alt="Logo HospitCare" class="footer-logo">
        <span class="footer-brand">HospitCare</span>
    </div>

    <!-- CENTRE : dernière visite -->
    <div class="footer-center">
        <?php if ($lastVisit && $allowNonEssential): ?>
            <p class="footer-lastvisit">
                Dernière visite : <strong><?= htmlspecialchars($lastVisit) ?></strong>
            </p>
        <?php endif; ?>

        <p class="footer-credits">
            — Fait par <strong>MOUSSAOUI Imane</strong>, <strong>CHEMIM Massiva</strong> &
            <strong>HAMITOUCHE Dania</strong>. Tous droits réservés. —
        </p>

        <p class="footer-copy">
            &copy; <?= date('Y') ?> HospitCare — Gestion hospitalière
        </p>
    </div>

    <!-- DROITE : liens -->
    <div class="footer-right">
        <a href="/contact.php" class="footer-link">Contact</a>
        <a href="/quisommesnous.php" class="footer-link">Qui Sommes Nous ?</a>
    </div>

</footer>

<button id="backToTop" class="back-to-top">▲</button>

<script src="/js/theme.js"></script>
<script src="/js/backtotop.js"></script>

</body>
</html>
