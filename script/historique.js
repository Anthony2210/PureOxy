/**
 * historique.js
 *
 * Ce script gère l'affichage et la mise à jour dynamique de l'historique des recherches sur la page compte.php.
 * Il permet de supprimer individuellement une recherche ou d'effacer l'historique complet via AJAX.
 *
 * Références :
 * - ChatGPT pour la gestion des requêtes AJAX et le parsing JSON.
 *
 * Utilisation :
 * - Ce script est chargé sur la page compte.php pour améliorer l'expérience utilisateur en lui donnant accès à son historique.
 *
 * Fichier placé dans le dossier script.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Affiche un message temporaire
    function displayMessage(message, type) {
        type = type.trim().toLowerCase();
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(type === 'success' ? 'success-message' : 'error-message');
        messageDiv.textContent = message;
        const messageContainer = document.getElementById('message-container');
        messageContainer.appendChild(messageDiv);
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    // Met à jour l'affichage de l'historique
    function updateHistoryUI() {
        const historyList = document.querySelector('.history-list');
        if (!historyList || historyList.children.length === 0) {
            const clearHistoryForm = document.getElementById('clear-history-form');
            if (clearHistoryForm) {
                clearHistoryForm.style.display = 'none';
            }
            const historySection = document.querySelector('.history-section');
            if (historySection && !historySection.querySelector('.no-searches-message')) {
                const p = document.createElement('p');
                p.classList.add('no-searches-message');
                p.textContent = "Vous n'avez pas encore effectué de recherches.";
                historySection.appendChild(p);
            }
        }
    }

    // Attache l'événement de suppression d'une recherche individuelle
    function attachDeleteSearchEvent(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('delete_search', '');
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
                            form.parentElement.remove();
                            displayMessage(data.message, 'success');
                            updateHistoryUI();
                        } else {
                            displayMessage(data.message, 'error');
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
    }

    // Attache les événements aux formulaires existants
    const deleteSearchForms = document.querySelectorAll('.delete-search-form');
    deleteSearchForms.forEach(form => {
        attachDeleteSearchEvent(form);
    });

    // Gestion du bouton "Effacer l'historique"
    const clearHistoryForm = document.getElementById('clear-history-form');
    if (clearHistoryForm) {
        clearHistoryForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(clearHistoryForm);
            formData.append('ajax', '1');
            formData.append('clear_history', '');
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
                            const historyList = document.querySelector('.history-list');
                            if (historyList) {
                                historyList.innerHTML = '';
                            }
                            displayMessage(data.message, 'success');
                            updateHistoryUI();
                        } else {
                            displayMessage(data.message, 'error');
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
});
