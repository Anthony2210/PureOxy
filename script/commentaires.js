/**
 * commentaires.js
 *
 * Ce script gère l'envoi des commentaires, les réponses et le vote (like/dislike)
 * sur la page de détails d'une ville. Les événements sont délégués afin que
 * les commentaires ajoutés dynamiquement soient immédiatement interactifs sans
 * rechargement de la page.
 *
 * Références :
 * - ChatGPT pour la structuration des événements, la gestion des requêtes AJAX et la documentation du code.
 *
 * Utilisation :
 * - Ce script est chargé sur la page "details.php" et s'exécute lorsque le DOM est entièrement chargé.
 *
 * Fichier placé dans le dossier script.
 */

document.addEventListener('DOMContentLoaded', function() {
    // === Soumission du nouveau commentaire ===
    const submitCommentBtn = document.getElementById('submit-comment');
    if (submitCommentBtn) {
        submitCommentBtn.addEventListener('click', function() {
            const textarea = document.getElementById('new-comment');
            const content = textarea.value.trim();
            if (!content) {
                alert('Veuillez écrire un commentaire.');
                return;
            }
            // Préparation des données à envoyer en AJAX
            const formData = new FormData();
            formData.append('ajax_comment', '1');
            formData.append('content', content);
            formData.append('id_ville', idVille); // idVille est défini dans details.php

            fetch('../fonctionnalites/submit_comment.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = '';
                        // Ajout du nouveau commentaire à la fin de la liste
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

    // === Délégation d'événements pour les actions sur les commentaires ===
    // Cette approche permet aux éléments ajoutés dynamiquement d'hériter des fonctionnalités.
    document.addEventListener('click', function(e) {
        // --- Bouton "Répondre" pour afficher/masquer le formulaire de réponse ---
        if (e.target.closest('.reply-button')) {
            const replyButton = e.target.closest('.reply-button');
            const parentId = replyButton.getAttribute('data-parent');
            const replyForm = document.querySelector(`.reply-form[data-parent-form="${parentId}"]`);
            if (replyForm) {
                // Bascule l'affichage du formulaire de réponse
                replyForm.style.display = (replyForm.style.display === 'none' || replyForm.style.display === '') ? 'block' : 'none';
            }
            return;
        }

        // --- Soumission d'une réponse ---
        if (e.target.closest('.submit-reply')) {
            e.preventDefault();
            const submitReplyButton = e.target.closest('.submit-reply');
            const parentId = submitReplyButton.getAttribute('data-parent');
            const replyForm = document.querySelector(`.reply-form[data-parent-form="${parentId}"]`);
            if (!replyForm) return;
            const textarea = replyForm.querySelector('textarea');
            const content = textarea.value.trim();
            if (!content) {
                alert('Veuillez écrire une réponse.');
                return;
            }
            const formData = new FormData();
            formData.append('ajax_comment', '1');
            formData.append('content', content);
            formData.append('id_ville', idVille);
            formData.append('parent_id', parentId);

            fetch('../fonctionnalites/submit_comment.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = '';
                        // Ajoute la réponse dans le conteneur des réponses du commentaire parent
                        const parentComment = document.querySelector(`.comment[data-id="${parentId}"]`);
                        let repliesContainer = parentComment.querySelector('.replies');
                        if (!repliesContainer) {
                            repliesContainer = document.createElement('div');
                            repliesContainer.className = 'replies';
                            parentComment.appendChild(repliesContainer);
                        }
                        if (data.comment_html) {
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
            return;
        }

        // --- Gestion des votes (like/dislike) ---
        if (e.target.closest('.like-button')) {
            e.preventDefault();
            const likeButton = e.target.closest('.like-button');
            const commentId = likeButton.getAttribute('data-id');
            const vote = likeButton.getAttribute('data-vote'); // "1" pour like
            voteComment(commentId, vote, likeButton);
            return;
        }
        if (e.target.closest('.dislike-button')) {
            e.preventDefault();
            const dislikeButton = e.target.closest('.dislike-button');
            const commentId = dislikeButton.getAttribute('data-id');
            const vote = dislikeButton.getAttribute('data-vote'); // "-1" pour dislike
            voteComment(commentId, vote, dislikeButton);
            return;
        }
    });

    // === Fonction d'envoi du vote en AJAX ===
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
                    const commentElement = document.querySelector(`.comment[data-id="${commentId}"]`);
                    if (commentElement) {
                        const likeCountElem = commentElement.querySelector('.like-count');
                        const dislikeCountElem = commentElement.querySelector('.dislike-count');
                        if (likeCountElem) {
                            likeCountElem.textContent = data.like_count;
                        }
                        if (dislikeCountElem) {
                            dislikeCountElem.textContent = data.dislike_count;
                        }
                        // Mise à jour du style des boutons en fonction du vote
                        if (vote === "1") {
                            if (clickedButton.classList.contains('voted-like')) {
                                clickedButton.classList.remove('voted-like');
                            } else {
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
