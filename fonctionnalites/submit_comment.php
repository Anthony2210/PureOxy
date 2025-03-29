<?php
require '../bd/bd.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (isset($_POST['page'])) {
    $page = $_POST['page'];
} else {
    echo json_encode(['success' => false, 'message' => 'Page non spécifiée.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Erreur : Jeton CSRF invalide.']);
        exit;
    }
    if (isset($_SESSION['id_users'])) {
        $id_users = $_SESSION['id_users'];
        $content = trim($_POST['content']);
        if (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') {
            $parent_id = intval($_POST['parent_id']);
        } else {
            $parent_id = null;
        }
        if (strlen($content) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Le commentaire est trop long (maximum 1000 caractères).']);
            exit;
        }
        if (!empty($content)) {
            if ($parent_id === null) {
                $stmt = $conn->prepare("INSERT INTO commentaire (id_users, page, content, parent_id) VALUES (?, ?, ?, NULL)");
                $stmt->bind_param("iss", $id_users, $page, $content);
            } else {
                $stmt = $conn->prepare("INSERT INTO commentaire (id_users, page, content, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $id_users, $page, $content, $parent_id);
            }
            if ($stmt->execute()) {
                $comment_id = $stmt->insert_id;
                $created_at = date('Y-m-d H:i:s');
                $username = $_SESSION['username'];
                ob_start();
                echo '<div class="comment-avatar">' . htmlspecialchars(substr($username, 0, 1), ENT_QUOTES, 'UTF-8') . '</div>';
                echo '<div class="comment-content">';
                echo '<div class="comment-details">';
                $formatted_date = date('d F Y à H:i', strtotime($created_at));
                echo '<strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong> le ' . htmlspecialchars($formatted_date, ENT_QUOTES, 'UTF-8');
                echo '</div>';
                echo '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</p>';
                echo '<div class="comment-actions">';
                echo '<form method="post" class="like-form" data-comment-id="' . htmlspecialchars($comment_id, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit"><i class="fas fa-thumbs-up"></i> J\'aime (<span class="like-count">0</span>)</button>';
                echo '</form>';
                echo '<button class="reply-button" data-comment-id="' . htmlspecialchars($comment_id, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-reply"></i> Répondre</button>';
                echo '<button class="delete-comment-button" data-comment-id="' . htmlspecialchars($comment_id, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-trash-alt"></i> Supprimer</button>';
                echo '</div>';
                echo '</div>';
                $comment_html = ob_get_clean();
                echo json_encode(['success' => true, 'message' => 'Commentaire ajouté avec succès.', 'comment_id' => $comment_id, 'comment_html' => $comment_html]);
                exit;
            } else {
                error_log('Erreur de base de données : ' . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'Une erreur s\'est produite lors de l\'ajout du commentaire.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Le commentaire ne peut pas être vide.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour poster un commentaire.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}
?>
