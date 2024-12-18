<?php
/**
 * commentaires.php
 *
 * Ce code gère l'affichage, l'ajout, la suppression et l'interaction des commentaires sur une page spécifique.
 * Il inclut des fonctionnalités telles que les likes, les réponses et la protection contre les attaques CSRF.
 *
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
    public $replies = [];

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
function getComments($page, $parent_id = null) {
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
        $comment->replies = getComments($page, $comment->id);
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
    global $csrf_token, $conn;
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
        echo '</div>';
        echo '</div>';

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

<script>
    window.commentairesConfig = {
        csrfToken: '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>'
    };
</script>
<script src="../script/commentaires.js"></script>