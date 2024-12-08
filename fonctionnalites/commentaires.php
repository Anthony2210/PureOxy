<?php
/**
 * Gestion des Commentaires - PureOxy
 *
 * Ce script gère l'affichage, l'ajout, la suppression et l'interaction des commentaires sur une page spécifique.
 * Il inclut des fonctionnalités telles que les likes, les réponses et la protection contre les attaques CSRF.
 *
 * @package PureOxy
 */

require '../bd/bd.php';

/**
 * Démarre une session si aucune n'est active.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Génère un jeton CSRF si non déjà défini dans la session.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/**
 * Récupère le nom de la ville depuis les paramètres GET et définit la page courante.
 */
if (isset($_GET['ville'])) {
    $ville = $_GET['ville'];
    $page = 'details.php?ville=' . $ville;
} else {
    $page = basename($_SERVER['PHP_SELF']);
}

/**
 * Classe représentant un commentaire.
 */
class Commentaire {
    /**
     * @var int Identifiant du commentaire.
     */
    public $id;

    /**
     * @var int Identifiant de l'utilisateur qui a posté le commentaire.
     */
    public $user_id;

    /**
     * @var string Nom d'utilisateur de l'auteur du commentaire.
     */
    public $username;

    /**
     * @var string Contenu du commentaire.
     */
    public $content;

    /**
     * @var int Nombre de likes sur le commentaire.
     */
    public $likes;

    /**
     * @var string Date et heure de création du commentaire.
     */
    public $created_at;

    /**
     * @var array Tableau des réponses au commentaire.
     */
    public $replies = []; // Tableau des réponses

    /**
     * Constructeur de la classe Commentaire.
     *
     * @param array $data Données du commentaire provenant de la base de données.
     */
    public function __construct($data) {
        $this->id = $data['id'];
        $this->user_id = $data['user_id'];
        $this->username = $data['username'];
        $this->content = $data['content'];
        $this->likes = $data['likes'];
        $this->created_at = $data['created_at'];
    }

    /**
     * Ajoute une réponse au commentaire.
     *
     * @param Commentaire $reply Réponse à ajouter.
     */
    public function addReply($reply) {
        $this->replies[] = $reply;
    }
}

/**
 * Récupère les commentaires et leurs réponses depuis la base de données.
 *
 * @param string   $page       Page actuelle pour laquelle récupérer les commentaires.
 * @param int|null $parent_id Identifiant du commentaire parent. NULL pour les commentaires principaux.
 *
 * @return Commentaire[] Tableau d'objets Commentaire.
 */
function getComments($page, $parent_id = null) { // Paramètres réordonnés
    global $conn;
    $comments = [];

    $sql = "SELECT c.*, u.username 
            FROM commentaire c 
            INNER JOIN users u ON c.user_id = u.id 
            WHERE c.page = ? AND ";
    if (is_null($parent_id)) {
        $sql .= "c.parent_id IS NULL ";
    } else {
        $sql .= "c.parent_id = ? ";
    }
    $sql .= "ORDER BY c.created_at ASC";

    $stmt = $conn->prepare($sql);

    if (is_null($parent_id)) {
        $stmt->bind_param("s", $page);
    } else {
        $stmt->bind_param("si", $page, $parent_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $comment = new Commentaire($row);
        // Récursivement récupérer les réponses du commentaire
        $comment->replies = getComments($page, $comment->id); // Appel mis à jour
        $comments[] = $comment;
    }

    return $comments;
}

/**
 * Affiche les commentaires et leurs réponses de manière récursive.
 *
 * @param Commentaire[] $comments Tableau d'objets Commentaire à afficher.
 *
 * @return void
 */
function displayComments($comments) {
    global $csrf_token, $conn; // Ajoutez $conn ici
    foreach ($comments as $comment) {
        echo '<li id="comment-' . htmlspecialchars($comment->id, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="comment-avatar">' . htmlspecialchars(substr($comment->username, 0, 1), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<div class="comment-content">';
        echo '<div class="comment-details">';
        $formatted_date = date('d F Y à H:i', strtotime($comment->created_at));
        echo '<strong>' . htmlspecialchars($comment->username, ENT_QUOTES, 'UTF-8') . '</strong> le ' . htmlspecialchars($formatted_date, ENT_QUOTES, 'UTF-8');
        echo '</div>';
        echo '<p>' . nl2br(htmlspecialchars($comment->content, ENT_QUOTES, 'UTF-8')) . '</p>';
        echo '<div class="comment-actions">';
        // Formulaire de like
        if (isset($_SESSION['user_id'])) {
            // Vérifier si l'utilisateur a déjà liké ce commentaire
            $stmt_like = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ?");
            $stmt_like->bind_param("ii", $_SESSION['user_id'], $comment->id);
            $stmt_like->execute();
            $result_like = $stmt_like->get_result();

            if ($result_like->num_rows == 0) {
                // L'utilisateur n'a pas liké
                echo '<form method="post" class="like-form" data-comment-id="' . htmlspecialchars($comment->id, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit"><i class="fas fa-thumbs-up"></i> J\'aime (<span class="like-count">' . htmlspecialchars($comment->likes, ENT_QUOTES, 'UTF-8') . '</span>)</button>';
                echo '</form>';
            } else {
                // L'utilisateur a déjà liké
                echo '<form method="post" class="unlike-form" data-comment-id="' . htmlspecialchars($comment->id, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit"><i class="fas fa-thumbs-down"></i> Je n\'aime plus (<span class="like-count">' . htmlspecialchars($comment->likes, ENT_QUOTES, 'UTF-8') . '</span>)</button>';
                echo '</form>';
            }

            // Bouton pour répondre
            echo '<button class="reply-button" data-comment-id="' . htmlspecialchars($comment->id, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-reply"></i> Répondre</button>';

            // Bouton de suppression si l'utilisateur est l'auteur
            if ($comment->user_id == $_SESSION['user_id']) {
                echo '<button class="delete-comment-button" data-comment-id="' . htmlspecialchars($comment->id, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-trash-alt"></i> Supprimer</button>';
            }
        } else {
            echo '<p><a href="../pages/compte.php">Connectez-vous</a> pour interagir.</p>';
        }
        echo '</div>'; // Fermer comment-actions
        echo '</div>'; // Fermer comment-content

        // Afficher les réponses
        if (!empty($comment->replies)) {
            echo '<ul class="replies">';
            displayComments($comment->replies);
            echo '</ul>';
        }

        echo '</li>';
    }
}
?>

<!-- Partie HTML pour afficher les commentaires -->
<div class="comment-section">
    <h3>Commentaires</h3>

    <!-- Conteneur pour les messages -->
    <div id="message-container">
        <?php
        // Les messages d'erreur et de succès seront affichés ici
        ?>
    </div>
    <?php
    /**
     * Récupère et affiche les commentaires principaux pour la page courante.
     */
    // Récupérer les commentaires principaux
    $comments = getComments($page); // Appel mis à jour

    if (!empty($comments)) {
        echo '<ul class="comments">';
        displayComments($comments);
        echo '</ul>';
    } else {
        echo '<p>Soyez le premier à commenter cette page !</p>';
    }

    /**
     * Affiche le formulaire pour ajouter un nouveau commentaire si l'utilisateur est connecté.
     */
    if (isset($_SESSION['user_id'])) {
        echo '<form method="post" class="comment-form" id="comment-form">';
        echo '<textarea name="content" placeholder="Votre commentaire..." required></textarea>';
        echo '<input type="hidden" name="parent_id" id="parent_id" value="">';
        echo '<input type="hidden" name="page" value="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">';
        // Jeton CSRF
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button type="submit" name="add_comment">Poster</button>';
        echo '</form>';
    }
    ?>

</div>

<!-- JavaScript pour gérer les réponses, les likes et les suppressions via AJAX -->
<script>
    /**
     * @file commentaires.js
     * @description Gestion des interactions des commentaires (likes, réponses, suppressions) via AJAX.
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
                    formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>');

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
                        formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>');

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
</script>
