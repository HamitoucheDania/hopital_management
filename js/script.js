/**
 * connexion.js — Gestion dynamique du formulaire de connexion
 *
 * Rôle général :
 * ----------------
 * Ce script gère deux fonctionnalités essentielles du formulaire de connexion :
 *
 * 1) L’affichage conditionnel des blocs selon le rôle sélectionné
 *    - Rôle "patient"  → affiche les champs spécifiques au patient (NSS)
 *    - Rôle "personnel" → affiche les champs dédiés au personnel (identifiant interne)
 *
 * 2) La gestion de l'affichage/masquage du mot de passe via un bouton "œil"
 *
 *
 * Fonctionnalité 1 : affichage dynamique selon le rôle
 * -----------------------------------------------------
 * Le script :
 *  - détecte tous les boutons radio <input name="role">
 *  - récupère les deux blocs :
 *          #bloc-patient      (NSS)
 *          #bloc-personnel    (identifiant interne)
 *  - définit une fonction updateVisibility() qui :
 *          -> lit le rôle sélectionné
 *          -> affiche le bloc correspondant
 *          -> masque l’autre bloc
 *  - déclenche updateVisibility() :
 *          -> au chargement de la page
 *          -> à chaque changement de sélection
 *
 *
 * Fonctionnalité 2 : œil de mot de passe
 * --------------------------------------
 * Le script :
 *  - détecte un bouton .toggle-password
 *  - lit l’attribut data-target contenant l’ID du <input>
 *  - bascule le type du champ :
 *          password → text   (affiche le mot de passe)
 *          text     → password (le masque)
 *  - change également l’emoji du bouton :
 *          👁 pour masquer le mot de passe
 *          🙈 pour l'afficher
 *
 *
 * Dépendances DOM :
 * ------------------
 * - input[name="role"]
 * - #bloc-patient
 * - #bloc-personnel
 * - .toggle-password (optionnel)
 */

document.addEventListener('DOMContentLoaded', function () {
    const radios = document.querySelectorAll('input[name="role"]');
    const blocPatient = document.getElementById('bloc-patient');
    const blocPersonnel = document.getElementById('bloc-personnel');

    /**
     * Met à jour l'affichage des sections selon le rôle sélectionné.
     */
    function updateVisibility() {
        const role = document.querySelector('input[name="role"]:checked').value;

        if (role === 'patient') {
            blocPatient.style.display = 'block';
            blocPersonnel.style.display = 'none';
        } else {
            blocPatient.style.display = 'none';
            blocPersonnel.style.display = 'block';
        }
    }

    // Déclenchement lors du changement de sélection
    radios.forEach(r => r.addEventListener('change', updateVisibility));

    // Initialisation à l'ouverture de la page
    updateVisibility();
});


// Gestion de l’œil mot de passe
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.querySelector('.toggle-password');
    if (!toggleBtn) return;

    toggleBtn.addEventListener('click', function () {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;

        if (input.type === 'password') {
            input.type = 'text';
            this.textContent = '🙈';
        } else {
            input.type = 'password';
            this.textContent = '👁';
        }
    });
});
