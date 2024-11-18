<?php
require '../bd/bd.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Générer un jeton CSRF si non déjà défini
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Récupérer le nom de la page depuis les paramètres POST
if (isset($_POST['page'])) {
    $page = $_POST['page'];
} else {
    echo json_encode(['success' => false, 'message' => 'Page non spécifiée.']);
    exit;
}

// Traitement du formulaire d'ajout de commentaire
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    // Valider le jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Erreur : Jeton CSRF invalide.']);
        exit;
    }

    // Vérifier si l'utilisateur est connecté
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $content = trim($_POST['content']);

        // Vérifier si parent_id est défini et non vide
        if (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') {
            $parent_id = intval($_POST['parent_id']);
        } else {
            $parent_id = null;
        }

        // Limiter la longueur du commentaire
        if (strlen($content) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Le commentaire est trop long (maximum 1000 caractères).']);
            exit;
        }

        // Vérifier que le contenu n'est pas vide
        if (!empty($content)) {
            // Préparer la requête pour insérer le commentaire
            if ($parent_id === null) {
                $stmt = $conn->prepare("INSERT INTO commentaire (user_id, page, content, parent_id) VALUES (?, ?, ?, NULL)");
                $stmt->bind_param("iss", $user_id, $page, $content);
            } else {
                $stmt = $conn->prepare("INSERT INTO commentaire (user_id, page, content, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $user_id, $page, $content, $parent_id);
            }

            if ($stmt->execute()) {
                $comment_id = $stmt->insert_id;
                $created_at = date('Y-m-d H:i:s');
                $username = $_SESSION['username'];

                // Préparer le HTML du nouveau commentaire
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
                // Bouton de suppression si l'utilisateur est l'auteur
                echo '<button class="delete-comment-button" data-comment-id="' . htmlspecialchars($comment_id, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-trash-alt"></i> Supprimer</button>';
                echo '</div>'; // Fermer comment-actions
                echo '</div>'; // Fermer comment-content
                $comment_html = ob_get_clean();

                echo json_encode([
                    'success' => true,
                    'message' => 'Commentaire ajouté avec succès.',
                    'comment_id' => $comment_id,
                    'comment_html' => $comment_html
                ]);
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