document.addEventListener('DOMContentLoaded', function() {
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
    const deleteSearchForms = document.querySelectorAll('.delete-search-form');
    deleteSearchForms.forEach(form => {
        attachDeleteSearchEvent(form);
    });
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
