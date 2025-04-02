/**
 * backtotop.js
 *
 * Ce script gère l'affichage et le comportement du bouton "Revenir vers le haut" sur la page.
 * Le bouton apparaît lorsque l'utilisateur fait défiler la page vers le bas et permet de revenir
 * rapidement en haut de la page avec un défilement fluide.
 *
 * Fichier placé dans le dossier script.
 */

// Fonction appelée à chaque événement de défilement
window.onscroll = function() {
    const button = document.getElementById("backToTop");

    // Vérifie si l'utilisateur a fait défiler la page de plus de 100 pixels
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        button.style.display = "block"; // Affiche le bouton
    } else {
        button.style.display = "none"; // Masque le bouton
    }
};

// Ajoute un écouteur d'événement au clic sur le bouton "Revenir vers le haut"
document.getElementById("backToTop").addEventListener("click", function() {
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
});
