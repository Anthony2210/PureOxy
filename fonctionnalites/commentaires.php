<?php
// commentaires.php
require '../bd/bd.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Déterminer la page actuelle pour identifier les commentaires
$page = basename($_SERVER['PHP_SELF']);
if (!empty($_SERVER['QUERY_STRING'])) {
    $page .= '?' . $_SERVER['QUERY_STRING'];
}

// Traitement du formulaire d'ajout de commentaire
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $content = trim($_POST['content']);
        // Si parent_id est défini (si c'est une réponse à un autre commentaire), on le récupère, sinon on le définit à NULL
        $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] != '' ? $_POST['parent_id'] : NULL;

        // Si le commentaire n'est pas vide
        if (!empty($content)) {
            // Si parent_id est défini (c'est une réponse), vérifier si le parent existe
            if ($parent_id) {
                // Vérifier que le commentaire parent existe
                $stmt_check_parent = $conn->prepare("SELECT id FROM commentaire WHERE id = ?");
                $stmt_check_parent->bind_param("i", $parent_id);
                $stmt_check_parent->execute();
                $result = $stmt_check_parent->get_result();

                // Si le parent n'existe pas
                if ($result->num_rows == 0) {
                    echo '<p>Erreur : le commentaire parent n\'existe pas.</p>';
                    exit; // Arrêter l'exécution si le parent n'existe pas
                }
            }

            // Insérer le commentaire (que ce soit un commentaire ou une réponse)
            $stmt = $conn->prepare("INSERT INTO commentaire (user_id, page, parent_id, content) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $user_id, $page, $parent_id, $content);
            $stmt->execute();

            // Optionnel : Message de confirmation
            echo '<p>Votre commentaire a été ajouté avec succès !</p>';
        } else {
            echo '<p>Le contenu du commentaire ne peut pas être vide.</p>';
        }
    } else {
        echo '<p>Vous devez être connecté pour poster un commentaire.</p>';
    }
}





// Traitement des likes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_comment'])) {
    if (isset($_SESSION['user_id'])) {
        $comment_id = $_POST['comment_id'];

        // Vérifier si l'utilisateur a déjà liké ce commentaire
        $stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            // Ajouter un like
            $stmt = $conn->prepare("UPDATE commentaire SET likes = likes + 1 WHERE id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();

            // Enregistrer le like dans la table 'likes' (à créer)
            $stmt = $conn->prepare("INSERT INTO likes (user_id, comment_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $_SESSION['user_id'], $comment_id);
            $stmt->execute();
        }
    }
}

// Récupérer les commentaires pour la page actuelle
$stmt = $conn->prepare("SELECT c.*, u.username FROM commentaire c JOIN users u ON c.user_id = u.id WHERE c.page = ? AND c.parent_id IS NULL ORDER BY c.created_at DESC");
$stmt->bind_param("s", $page);
$stmt->execute();
$comments = $stmt->get_result();

// Fonction récursive pour afficher les réponses
function display_replies($parent_id, $conn) {
    $stmt = $conn->prepare("SELECT c.*, u.username FROM commentaire c JOIN users u ON c.user_id = u.id WHERE c.parent_id = ? ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $replies = $stmt->get_result();

    if ($replies->num_rows > 0) {
        echo '<ul class="replies">';
        while ($reply = $replies->fetch_assoc()) {
            echo '<li>';
            echo '<p><strong>' . htmlspecialchars($reply['username']) . '</strong> le ' . $reply['created_at'] . '</p>';
            echo '<p>' . nl2br(htmlspecialchars($reply['content'])) . '</p>';
            echo '<div class="comment-actions">';
            echo '<form method="post" class="like-form">';
            echo '<input type="hidden" name="comment_id" value="' . $reply['id'] . '">';
            echo '<button type="submit" name="like_comment">J\'aime (' . $reply['likes'] . ')</button>';
            echo '</form>';
            if (isset($_SESSION['user_id'])) {
                echo '<button class="reply-button" data-comment-id="' . $reply['id'] . '">Répondre</button>';
            }
            echo '</div>';
            // Afficher les réponses des réponses
            display_replies($reply['id'], $conn);
            echo '</li>';
        }
        echo '</ul>';
    }
}

// Formulaire d'ajout de commentaire
?>
<div class="comment-section">
    <h3>Commentaires</h3>
    <?php if ($comments->num_rows > 0): ?>
        <ul class="comments">
            <?php while ($comment = $comments->fetch_assoc()): ?>
                <li>
                    <p><strong><?php echo htmlspecialchars($comment['username']); ?></strong> le <?php echo $comment['created_at']; ?></p>
                    <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                    <div class="comment-actions">
                        <form method="post" class="like-form">
                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                            <button type="submit" name="like_comment">J'aime (<?php echo $comment['likes']; ?>)</button>
                        </form>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="reply-button" data-comment-id="<?php echo $comment['id']; ?>">Répondre</button>
                        <?php endif; ?>
                    </div>
                    <?php
                    // Afficher les réponses
                    display_replies($comment['id'], $conn);
                    ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>Soyez le premier à commenter !</p>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="post" class="comment-form">
            <textarea name="content" placeholder="Votre commentaire..." required></textarea>
            <input type="hidden" name="parent_id" value="">
            <button type="submit" name="add_comment">Poster</button>
        </form>
    <?php else: ?>
        <p>Vous devez être connecté pour poster un commentaire.</p>
    <?php endif; ?>
</div>

<!-- Script pour gérer les réponses -->
<script>
    document.querySelectorAll('.reply-button').forEach(function(button) {
        button.addEventListener('click', function() {
            var commentId = this.getAttribute('data-comment-id');
            var form = document.querySelector('.comment-form');
            form.querySelector('input[name="parent_id"]').value = commentId;
            form.scrollIntoView({ behavior: 'smooth' });
            form.querySelector('textarea').focus();
        });
    });
</script>
