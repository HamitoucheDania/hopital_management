/**
 * theme.js — Gestion du thème clair/sombre (mode jour/nuit)
 *
 * Rôle général :
 * ----------------
 * Ce script permet à l’utilisateur de basculer dynamiquement entre :
 *   - le thème clair  → /css/clair.css
 *   - le thème sombre → /css/sombre.css
 *
 * Le choix est mémorisé dans le navigateur via localStorage,
 * afin que le thème préféré soit réappliqué automatiquement lors
 * des prochaines visites.
 *
 *
 * Fonctionnalités principales :
 * -----------------------------
 *
 * 1) Détection des éléments :
 *    - #themeToggle      : bouton permettant de changer le thème
 *    - #themeStylesheet  : <link> qui charge le fichier CSS actif
 *
 *    Si l'un des deux est introuvable, le script ne fait rien.
 *
 *
 * 2) Fonction setTheme(theme)
 *    - Met à jour l'attribut href du <link>
 *    - Change le symbole du bouton :
 *          🌙 = thème clair (on peut passer en nuit)
 *          ☀️ = thème nuit  (on peut repasser en clair)
 *    - Stocke la valeur "clair" ou "nuit" dans localStorage
 *
 *
 * 3) Initialisation automatique
 *    - Lit le thème mémorisé dans localStorage
 *    - Si aucun thème n’a été mémorisé → applique le thème clair
 *
 *
 * 4) Alternance du thème au clic
 *    - Lors d’un clic sur #themeToggle :
 *          clair → nuit
 *          nuit  → clair
 *
 *
 * Dépendances DOM :
 * ------------------
 * - <button id="themeToggle">
 * - <link id="themeStylesheet" rel="stylesheet">
 *
 * Paramètres : aucun
 * Retour : aucun
 */

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('themeToggle');
    const link = document.getElementById('themeStylesheet');

    // Si un des éléments n'existe pas, on ne fait rien
    if (!btn || !link) {
        return;
    }

    function setTheme(theme) {
        if (theme === 'nuit') {
            link.setAttribute('href', '/css/sombre.css?v=1');
            btn.textContent = '☀️';
        } else {
            // par défaut on repasse en clair
            link.setAttribute('href', '/css/clair.css?v=1');
            btn.textContent = '🌙';
            theme = 'clair';
        }

        // mémoriser le thème dans le navigateur
        window.localStorage.setItem('theme', theme);
    }

    // Thème initial : celui mémorisé ou clair
    const saved = window.localStorage.getItem('theme') || 'clair';
    setTheme(saved);

    // Clic sur le bouton = on alterne
    btn.addEventListener('click', function () {
        const current = window.localStorage.getItem('theme') || 'clair';
        const next = current === 'clair' ? 'nuit' : 'clair';
        setTheme(next);
    });
});
