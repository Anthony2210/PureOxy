/**
 * erreur_formulaire.js
 *
 * Ce script vérifie, lors de la soumission d'un formulaire,
 * que le champ "ville" n'est pas vide. En cas d'absence de saisie,
 * il empêche l'envoi du formulaire, ajoute la classe "error" à l'input
 * et affiche une alerte.
 *
 * Fichier placé dans le dossier script.
 */

document.querySelector('form').addEventListener('submit', function(e) {
    // Récupération de l'input dont le nom est "ville"
    var ville = document.querySelector('input[name="ville"]');
    // Si la valeur de l'input est vide (après suppression des espaces)
    if (ville.value.trim() === '') {
        // Empêche la soumission du formulaire
        e.preventDefault();
        // Ajoute la classe "error" pour marquer l'input
        ville.classList.add('error');
        // Affiche une alerte à l'utilisateur
        alert('Veuillez entrer un nom de ville.');
    }
});
