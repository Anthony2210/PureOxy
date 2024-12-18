/**
 * commentaires.js
 *
 * Gestion des interactions des commentaires (likes, réponses, suppressions) via AJAX.
 */

document.addEventListener('DOMContentLoaded', function() {
    /**
     * Affiche un message dans le conteneur des messages.
     *
     * @param {string} message Le message à afficher.
     * @param {string} type    Le type de message ('success' ou 'error').
     */
    function displayMessage(message, type) {
        var messageContainer = document.getElementById('message-container');
        var messageDiv = document.createElement('div');
        messageDiv.className = type === 'success' ? 'message success' : 'message error';
        messageDiv.textContent = message;
        messageContainer.appendChild(messageDiv);

        // Supprimer le message après 5 secondes
        setTimeout(function() {
            messageDiv.remove();
        }, 5000);
    }

    // Récupérer le jeton CSRF depuis la configuration globale
    const csrfToken = window.commentairesConfig.csrfToken;

    /**
     * Gestion des formulaires de like et unlike.
     */
    function handleLikeForms() {
        document.querySelectorAll('.like-form, .unlike-form').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                var formData = new FormData();
                var action = form.classList.contains('like-form') ? 'like' : 'unlike';
                var commentId = form.getAttribute('data-comment-id');
                formData.append('action', action);
                formData.append('comment_id', commentId);
                formData.append('csrf_token', csrfToken);

                fetch('like_comment.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            // Mettre à jour le nombre de likes
                            var likeCountSpan = form.querySelector('.like-count');
                            likeCountSpan.textContent = data.likes;

                            // Changer le formulaire de like/unlike
                            if (action === 'like') {
                                form.classList.remove('like-form');
                                form.classList.add('unlike-form');
                                form.querySelector('button').innerHTML = '<i class="fas fa-thumbs-down"></i> Je n\'aime plus (<span class="like-count">' + data.likes + '</span>)';
                            } else {
                                form.classList.remove('unlike-form');
                                form.classList.add('like-form');
                                form.querySelector('button').innerHTML = '<i class="fas fa-thumbs-up"></i> J\'aime (<span class="like-count">' + data.likes + '</span>)';
                            }
                            // Afficher un message de succès
                            var message = action === 'like' ? 'Vous avez aimé ce commentaire.' : 'Vous n\'aimez plus ce commentaire.';
                            displayMessage(message, 'success');
                        } else {
                            displayMessage(data.message, 'error');
                        }
                    })
                    .catch(function(error) {
                        console.error('Erreur:', error);
                        displayMessage('Une erreur s\'est produite lors de l\'action.', 'error');
                    });
            });
        });
    }

    handleLikeForms();

    /**
     * Gestion des boutons "Répondre".
     */
    function handleReplyButtons() {
        document.querySelectorAll('.reply-button').forEach(function(button) {
            button.addEventListener('click', function() {
                var commentId = this.getAttribute('data-comment-id');
                document.getElementById('parent_id').value = commentId;
                document.getElementById('comment-form').scrollIntoView({ behavior: 'smooth' });
            });
        });
    }

    handleReplyButtons();

    /**
     * Gestion de la soumission du formulaire de commentaire via AJAX.
     */
    document.getElementById('comment-form').addEventListener('submit', function(event) {
        event.preventDefault();

        var form = this;
        var formData = new FormData(form);
        formData.append('add_comment', '1'); // Indiquer que c'est une requête d'ajout de commentaire

        fetch('submit_comment.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Réinitialiser le formulaire
                    form.reset();
                    // Remettre le parent_id à vide
                    document.getElementById('parent_id').value = '';

                    // Créer le nouvel élément de commentaire
                    var newComment = document.createElement('li');
                    newComment.id = 'comment-' + data.comment_id;
                    newComment.innerHTML = data.comment_html;

                    // Vérifier si c'est une réponse ou un commentaire principal
                    var parentId = form.querySelector('#parent_id').value;
                    if (parentId) {
                        // C'est une réponse
                        var parentComment = document.getElementById('comment-' + parentId);
                        var repliesList = parentComment.querySelector('.replies');

                        if (!repliesList) {
                            repliesList = document.createElement('ul');
                            repliesList.classList.add('replies');
                            parentComment.appendChild(repliesList);
                        }
                        repliesList.appendChild(newComment);
                    } else {
                        // C'est un commentaire principal
                        var commentsList = document.querySelector('.comments');
                        if (!commentsList) {
                            // Si la liste n'existe pas encore, la créer
                            commentsList = document.createElement('ul');
                            commentsList.classList.add('comments');
                            document.querySelector('.comment-section').insertBefore(commentsList, form);
                        }
                        commentsList.insertBefore(newComment, commentsList.firstChild);
                    }
                    // Afficher un message de succès
                    displayMessage('Commentaire ajouté avec succès.', 'success');

                    // Réinitialiser les gestionnaires d'événements
                    handleLikeForms();
                    handleReplyButtons();
                    handleDeleteButtons();
                } else {
                    displayMessage(data.message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                displayMessage('Une erreur s\'est produite lors de l\'ajout du commentaire.', 'error');

            });
    });

    /**
     * Gestion de la suppression des commentaires via AJAX.
     */
    function handleDeleteButtons() {
        document.querySelectorAll('.delete-comment-button').forEach(function(button) {
            button.addEventListener('click', function() {
                var commentId = this.getAttribute('data-comment-id');
                if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
                    var formData = new FormData();
                    formData.append('action', 'delete_comment');
                    formData.append('comment_id', commentId);
                    formData.append('csrf_token', csrfToken);

                    fetch('delete_comment.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function(response) {
                            return response.json();
                        })
                        .then(function(data) {
                            if (data.success) {
                                var commentElement = document.getElementById('comment-' + commentId);
                                if (data.deleted) {
                                    // Supprimer le commentaire du DOM
                                    commentElement.remove();
                                } else {
                                    // Remplacer le contenu du commentaire par "Message supprimé"
                                    commentElement.querySelector('.comment-content p').textContent = 'Message supprimé';
                                    // Supprimer le bouton de suppression
                                    commentElement.querySelector('.delete-comment-button').remove();
                                }
                                // Afficher un message de succès
                                displayMessage('Commentaire supprimé avec succès.', 'success');
                            } else {
                                displayMessage(data.message, 'error');
                            }
                        })
                        .catch(function(error) {
                            console.error('Erreur:', error);
                            displayMessage('Une erreur s\'est produite lors de la suppression du commentaire.', 'error');

                        });
                }
            });
        });
    }

    handleDeleteButtons();

    /**
     * Gestion de l'ancre pour faire défiler jusqu'au commentaire spécifié.
     */
    var hash = window.location.hash;
    if (hash) {
        var element = document.querySelector(hash);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
