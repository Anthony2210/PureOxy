<?php
require '../bd/bd.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour supprimer un commentaire.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Vérifier le jeton CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Erreur : Jeton CSRF invalide.']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'delete_comment' && isset($_POST['comment_id'])) {
    $comment_id = intval($_POST['comment_id']);

    // Vérifier que le commentaire appartient à l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM commentaire WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Vérifier s'il y a des réponses
        $stmt = $conn->prepare("SELECT * FROM commentaire WHERE parent_id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $replies = $stmt->get_result();

        if ($replies->num_rows > 0) {
            // Mettre à jour le contenu du commentaire pour indiquer qu'il est supprimé
            $stmt = $conn->prepare("UPDATE commentaire SET content = 'Message supprimé' WHERE id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            $deleted = false;
        } else {
            // Supprimer le commentaire
            $stmt = $conn->prepare("DELETE FROM commentaire WHERE id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            $deleted = true;
        }

        echo json_encode(['success' => true, 'message' => 'Commentaire supprimé.', 'deleted' => $deleted]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Commentaire introuvable ou ne vous appartient pas.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}
?>
