/**
 * favoritesAndMessages.js
 *
 * Ce script gère les fonctionnalités liées aux villes favorites et aux messages de l'utilisateur.
 * Il permet d'ajouter et de supprimer des villes favorites via des requêtes AJAX,
 * d'afficher des messages de confirmation ou d'erreur, et de gérer les interactions sur la page des détails.
 */

document.addEventListener('DOMContentLoaded', function() {

    /**
     * Crée et ajoute un conteneur de messages dans le DOM.
     */
    const messageContainer = document.createElement('div');
    messageContainer.id = 'message-container';
    document.body.appendChild(messageContainer);

    /**
     * Affiche un message utilisateur de type 'success' ou 'error'.
     *
     * @param {string} message - Le message à afficher.
     * @param {string} type - Le type de message ('success' ou 'error').
     */
    function displayMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
        messageDiv.textContent = message;

        // Ajoute le message au conteneur
        messageContainer.appendChild(messageDiv);

        // Supprime le message après 5 secondes
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    /**
     * Gestion de l'ajout de villes favorites via le formulaire.
     */
    const addFavoriteForm = document.getElementById('favorite-city-form');

    if (addFavoriteForm) {
        addFavoriteForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche l'envoi traditionnel du formulaire

            const formData = new FormData(addFavoriteForm);
            formData.append('ajax', '1'); // Indique que la requête est AJAX
            formData.append('add_favorite_city', ''); // Paramètre supplémentaire pour l'action

            console.log('Données envoyées (ajout de favoris):', Array.from(formData.entries()));

            // Envoie la requête AJAX au serveur
            fetch('compte.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Indique que la requête est AJAX
                }
            })
                .then(response => {
                    console.log('Réponse brute (ajout de favoris):', response);
                    return response.text(); // Obtient la réponse en texte brut
                })
                .then(dataText => {
                    console.log('Contenu brut de la réponse (ajout de favoris):', dataText);
                    try {
                        const data = JSON.parse(dataText); // Tente de parser la réponse en JSON
                        console.log('Données reçues (ajout de favoris):', data);
                        if (data.success) {
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

                                addFavoriteForm.reset(); // Réinitialise le formulaire
                                attachDeleteEvent(li.querySelector('.delete-city-form')); // Attache l'événement de suppression au nouveau formulaire
                            }

                            displayMessage(data.message, 'success'); // Affiche un message de succès
                        } else {
                            displayMessage(data.message, 'error'); // Affiche un message d'erreur
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
     * Attache les événements de suppression aux formulaires existants.
     */
    const deleteForms = document.querySelectorAll('.delete-city-form');
    deleteForms.forEach(form => {
        attachDeleteEvent(form);
    });

    /**
     * Fonction pour attacher l'événement de suppression à un formulaire.
     *
     * @param {HTMLFormElement} form - Le formulaire de suppression à attacher.
     */
    function attachDeleteEvent(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche l'envoi traditionnel du formulaire

            const formData = new FormData(form);
            formData.append('ajax', '1'); // Indique que la requête est AJAX
            formData.append('delete_favorite_city', ''); // Paramètre supplémentaire pour l'action

            console.log('Suppression des données (favoris):', Array.from(formData.entries()));

            // Envoie la requête AJAX au serveur
            fetch('compte.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    console.log('Réponse brute pour suppression (favoris):', response);
                    return response.text(); // Obtient la réponse en texte brut
                })
                .then(dataText => {
                    console.log('Contenu brut de la réponse (suppression de favoris):', dataText);
                    try {
                        const data = JSON.parse(dataText); // Tente de parser la réponse en JSON
                        console.log('Réponse JSON pour suppression (favoris):', data);
                        if (data.success) {
                            form.parentElement.remove(); // Supprime l'élément parent (la ville favorite) du DOM
                            displayMessage(data.message, 'success'); // Affiche un message de succès
                        } else {
                            displayMessage(data.message, 'error'); // Affiche un message d'erreur
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

    /**
     * Gestion de l'ajout/suppression de favoris sur la page details.php.
     */
    const favoriteForm = document.getElementById('favorite-form');

    if (favoriteForm) {
        const favoriteActionInput = document.getElementById('favorite_action');
        const favoriteButton = favoriteForm.querySelector('.favorite-icon');

        // Ajoute un événement au clic sur l'icône de favori pour définir l'action appropriée
        favoriteButton.addEventListener('click', function() {
            const action = favoriteButton.getAttribute('data-action');
            favoriteActionInput.value = action;
        });

        // Ajoute un événement de soumission au formulaire de favori
        favoriteForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche l'envoi traditionnel du formulaire

            const formData = new FormData(favoriteForm);
            formData.append('ajax', '1'); // Indique que la requête est AJAX

            console.log('Données envoyées (favoris depuis details.php):', Array.from(formData.entries()));

            // Envoie la requête AJAX au serveur
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
            })
                .then(response => response.text())
                .then(dataText => {
                    console.log('Contenu brut de la réponse (favoris depuis details.php):', dataText);

                    try {
                        const data = JSON.parse(dataText); // Tente de parser la réponse en JSON
                        console.log('Données reçues (favoris depuis details.php):', data);
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
                            displayMessage(data.message, 'success'); // Affiche un message de succès
                        } else {
                            displayMessage(data.message, 'error'); // Affiche un message d'erreur
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

    /**
     * Attache les événements de suppression aux formulaires existants dans l'historique des recherches.
     */
    const deleteSearchForms = document.querySelectorAll('.delete-search-form');
    deleteSearchForms.forEach(form => {
        attachDeleteSearchEvent(form);
    });

    /**
     * Fonction pour attacher l'événement de suppression des recherches à un formulaire.
     *
     * @param {HTMLFormElement} form - Le formulaire de suppression de recherche à attacher.
     */
    function attachDeleteSearchEvent(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche l'envoi traditionnel du formulaire

            const formData = new FormData(form);
            formData.append('ajax', '1'); // Indique que la requête est AJAX
            formData.append('delete_search', ''); // Paramètre supplémentaire pour l'action

            console.log('Suppression des données (historique):', Array.from(formData.entries()));

            // Envoie la requête AJAX au serveur
            fetch('compte.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.text())
                .then(dataText => {
                    console.log('Contenu brut de la réponse (suppression historique):', dataText);
                    try {
                        const data = JSON.parse(dataText); // Tente de parser la réponse en JSON
                        console.log('Réponse JSON pour suppression (historique):', data);
                        if (data.success) {
                            form.parentElement.remove(); // Supprime l'élément parent (la recherche) du DOM
                            displayMessage(data.message, 'success'); // Affiche un message de succès
                        } else {
                            displayMessage(data.message, 'error'); // Affiche un message d'erreur
                        }
                    } catch (e) {
                        console.error('Erreur lors du parsing JSON (suppression historique):', e);
                        displayMessage("La réponse du serveur n'est pas au format JSON attendu.", 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la suppression de la recherche:', error);
                    displayMessage('Une erreur s\'est produite.', 'error');
                });
        });

        /**
         * Gestion de l'effacement total de l'historique des recherches.
         */
        const clearHistoryForm = document.getElementById('clear-history-form');

        if (clearHistoryForm) {
            clearHistoryForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Empêche l'envoi traditionnel du formulaire

                const formData = new FormData(clearHistoryForm);
                formData.append('ajax', '1'); // Indique que la requête est AJAX
                formData.append('clear_history', ''); // Paramètre supplémentaire pour l'action

                console.log('Effacement de l\'historique (AJAX):', Array.from(formData.entries()));

                // Envoie la requête AJAX au serveur
                fetch('compte.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.text())
                    .then(dataText => {
                        console.log('Contenu brut de la réponse (effacement historique):', dataText);
                        try {
                            const data = JSON.parse(dataText); // Tente de parser la réponse en JSON
                            console.log('Réponse JSON pour effacement historique:', data);
                            if (data.success) {
                                // Supprime tous les éléments de la liste d'historique
                                const historyList = document.querySelector('.history-list');
                                if (historyList) {
                                    historyList.innerHTML = '';
                                }
                                displayMessage(data.message, 'success'); // Affiche un message de succès
                            } else {
                                displayMessage(data.message, 'error'); // Affiche un message d'erreur
                            }
                        } catch (e) {
                            console.error('Erreur lors du parsing JSON (effacement historique):', e);
                            displayMessage("La réponse du serveur n'est pas au format JSON attendu.", 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de l\'effacement de l\'historique:', error);
                        displayMessage('Une erreur s\'est produite.', 'error');
                    });
            });
        }
    };

});
