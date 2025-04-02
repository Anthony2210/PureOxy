/**
 * commentaires.js
 *
 * Ce script gère l'envoi des commentaires, les réponses et le vote (like/dislike)
 * sur la page de détails d'une ville.
 *
 * Références :
 * - ChatGPT pour la structuration des événements, la gestion des requêtes AJAX et la documentation du code.
 *
 * Utilisation :
 * - Ce script est chargé sur la page "details.php" et s'exécute lorsque le DOM est entièrement chargé.
 *
 * Fichier placé dans le dossier script.
 */

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // === Soumission du nouveau commentaire ===
    // Récupération du bouton de soumission du commentaire
    const submitCommentBtn = document.getElementById('submit-comment');
    if (submitCommentBtn) {
        // Ajout d'un écouteur d'événement "click" pour envoyer le commentaire
        submitCommentBtn.addEventListener('click', function() {
            // Récupération de la zone de texte et du contenu du commentaire
            const textarea = document.getElementById('new-comment');
            const content = textarea.value.trim();
            if (!content) {
                alert('Veuillez écrire un commentaire.');
                return;
            }
            // Création d'un objet FormData pour envoyer les données en AJAX
            const formData = new FormData();
            formData.append('ajax_comment', '1');
            formData.append('content', content);
            formData.append('id_ville', idVille); // idVille est défini dans details.php

            // Envoi de la requête AJAX pour soumettre le commentaire
            fetch('../fonctionnalites/submit_comment.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        textarea.value = '';
                        // Ajout du nouveau commentaire à la fin de la liste, si du HTML est retourné
                        const commentList = document.getElementById('comments-list');
                        if (data.comment_html) {
                            commentList.insertAdjacentHTML('beforeend', data.comment_html);
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors de l\'envoi du commentaire.');
                });
        });
    }

    // === Gestion du bouton "Répondre" pour afficher/masquer le formulaire de réponse ===
    // Pour chaque bouton "Répondre", ajouter un écouteur d'événement pour basculer l'affichage du formulaire de réponse
    document.querySelectorAll('.reply-button').forEach(function(button) {
        button.addEventListener('click', function() {
            const parentId = this.getAttribute('data-parent');
            const replyForm = document.querySelector(`.reply-form[data-parent-form="${parentId}"]`);
            if (replyForm) {
                replyForm.style.display = (replyForm.style.display === 'none' ? 'block' : 'none');
            }
        });
    });

    // === Soumission d'une réponse ===
    // Pour chaque bouton de soumission de réponse, ajouter un écouteur d'événement "click"
    document.querySelectorAll('.submit-reply').forEach(function(button) {
        button.addEventListener('click', function() {
            const parentId = this.getAttribute('data-parent');
            const replyForm = document.querySelector(`.reply-form[data-parent-form="${parentId}"]`);
            const textarea = replyForm.querySelector('textarea');
            const content = textarea.value.trim();
            if (!content) {
                alert('Veuillez écrire une réponse.');
                return;
            }
            // Création d'un objet FormData pour envoyer la réponse en AJAX
            const formData = new FormData();
            formData.append('ajax_comment', '1');
            formData.append('content', content);
            formData.append('id_ville', idVille);
            formData.append('parent_id', parentId);

            // Envoi de la requête AJAX pour soumettre la réponse
            fetch('../fonctionnalites/submit_comment.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        textarea.value = '';
                        // Ajout de la réponse dans la section des réponses du commentaire parent
                        const parentComment = document.querySelector(`.comment[data-id="${parentId}"]`);
                        let repliesContainer = parentComment.querySelector('.replies');
                        if(!repliesContainer){
                            repliesContainer = document.createElement('div');
                            repliesContainer.className = 'replies';
                            parentComment.appendChild(repliesContainer);
                        }
                        if(data.comment_html) {
                            repliesContainer.insertAdjacentHTML('beforeend', data.comment_html);
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors de l\'envoi de la réponse.');
                });
        });
    });

    // === Gestion des boutons like/dislike ===
    // Pour chaque bouton "like", ajouter un écouteur d'événement pour gérer le vote
    document.querySelectorAll('.like-button').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const commentId = this.getAttribute('data-id');
            const vote = this.getAttribute('data-vote'); // "1" pour like
            voteComment(commentId, vote, this);
        });
    });

    // Pour chaque bouton "dislike", ajouter un écouteur d'événement pour gérer le vote
    document.querySelectorAll('.dislike-button').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const commentId = this.getAttribute('data-id');
            const vote = this.getAttribute('data-vote'); // "-1" pour dislike
            voteComment(commentId, vote, this);
        });
    });

    // Fonction pour envoyer le vote (like ou dislike) en AJAX
    function voteComment(commentId, vote, clickedButton) {
        const formData = new FormData();
        formData.append('ajax_vote', '1');
        formData.append('id_comm', commentId);
        formData.append('vote', vote);

        fetch('../fonctionnalites/vote_comment.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mise à jour des compteurs de votes affichés
                    const commentElement = document.querySelector('.comment[data-id="'+commentId+'"]');
                    if (commentElement) {
                        commentElement.querySelector('.like-count').textContent = data.like_count;
                        commentElement.querySelector('.dislike-count').textContent = data.dislike_count;
                        // Mise à jour du style des boutons en fonction du vote
                        if (vote === "1") {
                            if (clickedButton.classList.contains('voted-like')) {
                                // Si déjà voté, retirer la classe
                                clickedButton.classList.remove('voted-like');
                            } else {
                                // Ajouter la classe au bouton like et retirer la classe dislike du bouton opposé
                                clickedButton.classList.add('voted-like');
                                const dislikeBtn = commentElement.querySelector('.dislike-button');
                                if (dislikeBtn) {
                                    dislikeBtn.classList.remove('voted-dislike');
                                }
                            }
                        } else if (vote === "-1") {
                            if (clickedButton.classList.contains('voted-dislike')) {
                                clickedButton.classList.remove('voted-dislike');
                            } else {
                                clickedButton.classList.add('voted-dislike');
                                const likeBtn = commentElement.querySelector('.like-button');
                                if (likeBtn) {
                                    likeBtn.classList.remove('voted-like');
                                }
                            }
                        }
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur lors du vote.');
            });
    }
});
