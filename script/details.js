/**
 * details.js
 *
 * Gère les interactions de la page details.php,
 * notamment l'ajout/retrait des favoris en AJAX.
 */
document.addEventListener('DOMContentLoaded', () => {
    const favIcon = document.getElementById('favoriteIcon');
    if (favIcon) {
        favIcon.addEventListener('click', () => {
            const cityId = favIcon.dataset.idville;
            toggleFavorite(cityId);
        });
    }
});

/**
 * Envoie une requête AJAX (fetch) à details.php
 * pour ajouter ou enlever la ville des favoris.
 *
 * @param {Number} cityId - ID de la ville dans la BD
 */
function toggleFavorite(cityId) {
    fetch('details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'toggleFavorite',
            idVille: cityId
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const favIcon = document.getElementById('favoriteIcon');
                // Mise à jour de l’icône
                if (data.isFavorite) {
                    favIcon.classList.add('is-favorite');
                } else {
                    favIcon.classList.remove('is-favorite');
                }
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur AJAX : ', error);
        });
}
