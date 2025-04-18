/**
 * favoris.js
 *
 * Ce script gère les animations visuelles et les interactions liées aux actions sur les villes favorites
 * dans l'espace compte. Il effectue notamment :
 *  - L'animation de particules autour du bouton favori lors d'un clic.
 *  - La gestion AJAX de l'ajout et de la suppression de villes favorites.
 *  - L'affichage de messages d'information à l'utilisateur.
 *
 * Références :
 * - ChatGPT pour la structuration et la documentation.
 *
 * Utilisation :
 * - Ce script s'exécute sur la page "compte.php" et "details.php", via des appels AJAX,
 *   pour ajouter ou supprimer des villes favorites.
 *
 * Fichier placé dans le dossier script.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Animation de particules autour du bouton favorite lors d'un clic
    const favButton = document.querySelector('.favorite-icon');
    if (favButton) {
        favButton.addEventListener('click', function(e) {
            for (let i = 0; i < 10; i++) {
                const particle = document.createElement('span');
                particle.classList.add('particle');
                // Détermine une direction et une distance aléatoires
                const angle = Math.random() * 2 * Math.PI;
                const distance = Math.random() * 30 + 10; // distance entre 10 et 40px
                const x = Math.cos(angle) * distance;
                const y = Math.sin(angle) * distance;
                // Affecte les positions via des variables CSS personnalisées
                particle.style.setProperty('--x', x + 'px');
                particle.style.setProperty('--y', y + 'px');
                favButton.appendChild(particle);
                // Supprime la particule après 800ms pour libérer le DOM
                setTimeout(() => {
                    particle.remove();
                }, 800);
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Vérifie l'existence d'un conteneur de messages, sinon le crée
    let messageContainer = document.getElementById('message-container');
    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.id = 'message-container';
        document.body.appendChild(messageContainer);
    }
    /**
     * Affiche un message temporaire dans le conteneur.
     *
     * @param {string} message Le message à afficher.
     * @param {string} type "success" ou "error" pour le style.
     */
    function displayMessage(message, type) {
        type = type.trim().toLowerCase();
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(type === 'success' ? 'success-message' : 'error-message');
        messageDiv.textContent = message;
        messageContainer.appendChild(messageDiv);
        // Le message disparaît après 5 secondes
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    // Gestion de l'ajout d'une ville favorite via le formulaire
    const addFavoriteForm = document.getElementById('favorite-city-form');
    if (addFavoriteForm) {
        addFavoriteForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(addFavoriteForm);
            formData.append('ajax', '1');
            formData.append('add_favorite_city', '');
            fetch('compte.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.text())
                .then(dataText => {
                    try {
                        const data = JSON.parse(dataText);
                        if (data.success) {
                            // Si l'ajout réussit, ajoute la nouvelle ville favorite à la liste
                            const favoriteCitiesList = document.querySelector('.favorite-cities-list');
                            if (favoriteCitiesList) {
                                const li = document.createElement('li');
                                li.innerHTML = `
                                <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(data.city_name)}" class="favorite-link">
                                    ${data.city_name}
                                </a>
                                <form method="post" class="delete-city-form">
                                    <input type="hidden" name="city_name" value="${data.city_name}">
                                    <button type="submit" name="delete_favorite_city"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            `;
                                favoriteCitiesList.appendChild(li);
                                addFavoriteForm.reset();
                                attachDeleteEvent(li.querySelector('.delete-city-form'));
                            }
                            displayMessage(data.message, 'success');
                        } else {
                            displayMessage(data.message, 'error');
                        }
                    } catch (e) {
                        console.error('Erreur lors du parsing JSON (ajout de favoris):', e);
                        displayMessage("La réponse du serveur n'est pas au format JSON attendu.", 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de l\'ajout de la ville favorite:', error);
                    displayMessage('Une erreur s\'est produite.', 'error');
                });
        });
    }
    /**
     * Attache un événement de suppression à un formulaire de suppression de ville favorite.
     *
     * @param {HTMLFormElement} form Le formulaire de suppression.
     */
    function attachDeleteEvent(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('delete_favorite_city', '');
            fetch('compte.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.text())
                .then(dataText => {
                    try {
                        const data = JSON.parse(dataText);
                        if (data.success) {
                            // Supprime l'élément du DOM si la suppression est réussie
                            form.parentElement.remove();
                            displayMessage(data.message, 'success');
                        } else {
                            displayMessage(data.message, 'error');
                        }
                    } catch (e) {
                        console.error('Erreur lors du parsing JSON (suppression de favoris):', e);
                        displayMessage("La réponse du serveur n'est pas au format JSON attendu.", 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la suppression de la ville favorite:', error);
                    displayMessage('Une erreur s\'est produite.', 'error');
                });
        });
    }
    // Attache l'événement de suppression aux formulaires existants
    const deleteForms = document.querySelectorAll('.delete-city-form');
    deleteForms.forEach(form => {
        attachDeleteEvent(form);
    });

    // Gestion d'une autre interaction sur le formulaire de favoris (si présent)
    const favoriteForm = document.getElementById('favorite-form');
    if (favoriteForm) {
        const favoriteActionInput = document.getElementById('favorite_action');
        const favoriteButton = favoriteForm.querySelector('.favorite-icon');
        favoriteButton.addEventListener('click', function() {
            const action = favoriteButton.getAttribute('data-action');
            favoriteActionInput.value = action;
        });
        favoriteForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(favoriteForm);
            formData.append('ajax', '1');
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
            })
                .then(response => response.text())
                .then(dataText => {
                    try {
                        const data = JSON.parse(dataText);
                        if (data.success) {
                            const icon = favoriteForm.querySelector('.favorite-icon i');
                            if (data.action === 'added') {
                                icon.classList.remove('far');
                                icon.classList.add('fas');
                                favoriteButton.setAttribute('data-action', 'remove_favorite');
                            } else if (data.action === 'removed') {
                                icon.classList.remove('fas');
                                icon.classList.add('far');
                                favoriteButton.setAttribute('data-action', 'add_favorite');
                            }
                            displayMessage(data.message, 'success');
                        } else {
                            displayMessage(data.message, 'error');
                        }
                    } catch (e) {
                        console.error('Erreur lors du parsing JSON (favoris depuis details.php):', e);
                        displayMessage("La réponse du serveur n'est pas au format JSON attendu.", 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la requête fetch (favoris depuis details.php):', error);
                    displayMessage('Une erreur s\'est produite.', 'error');
                });
        });
    }
});

// Animation complémentaire : effet de particules sur le bouton favorite
document.addEventListener('DOMContentLoaded', function() {
    const favButton = document.querySelector('.favorite-icon');
    if (favButton) {
        favButton.addEventListener('click', function(e) {
            // Crée 10 particules pour l'effet visuel
            for (let i = 0; i < 10; i++) {
                const particle = document.createElement('span');
                particle.classList.add('particle');
                // Positionnement aléatoire autour du bouton
                particle.style.left = (Math.random() * 40 - 20) + 'px';
                particle.style.top = (Math.random() * 40 - 20) + 'px';
                favButton.appendChild(particle);
                // Suppression après l'animation (1 seconde)
                setTimeout(() => {
                    particle.remove();
                }, 1000);
            }
        });
    }
});
