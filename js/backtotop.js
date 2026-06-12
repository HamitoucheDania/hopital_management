/**
 * backtotop.js — Gestion du bouton "Retour en haut"
 *
 * Rôle :
 * ------
 * Ce script gère l’affichage et le comportement du bouton flottant
 * permettant de remonter en haut de la page.
 *
 * Fonctionnalités :
 * ------------------
 * 1. Le script attend le chargement complet du DOM (DOMContentLoaded).
 * 2. Récupère l’élément #backToTop.
 * 3. Affiche le bouton si l'utilisateur a défilé de plus de 200px :
 *        - Ajoute la classe CSS "show"
 *        - La retire si l’on remonte au-dessus de 200px
 * 4. Au clic sur le bouton :
 *        - Scroll vers le haut de la page
 *        - Défilement fluide (smooth)
 *
 * Éléments utilisés :
 * -------------------
 *  - window.scrollY : position verticale actuelle
 *  - window.addEventListener("scroll") : surveille le défilement
 *  - Element.classList.add/remove : contrôle de visibilité du bouton
 *  - window.scrollTo({ top:0, behavior:"smooth" }) : remonte en haut
 *
 * Dépendances :
 * -------------
 *  - Nécessite un élément HTML ayant l’ID : #backToTop
 *  - Dépend d’une classe CSS "show" appliquée pour afficher le bouton
 */

document.addEventListener("DOMContentLoaded", function () {

    const btn = document.getElementById("backToTop");

    // Afficher le bouton après 200px
    window.addEventListener("scroll", () => {
        if (window.scrollY > 200) {
            btn.classList.add("show");
        } else {
            btn.classList.remove("show");
        }
    });

    // Scroll vers le haut
    btn.addEventListener("click", () => {
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });

});
