// Afficher/Masquer le bouton selon la position de défilement
window.onscroll = function() {
    const button = document.getElementById("backToTop");
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        button.style.display = "block";
    } else {
        button.style.display = "none";
    }
};

// Remonter en haut de la page lors du clic sur le bouton
document.getElementById("backToTop").addEventListener("click", function() {
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
});

