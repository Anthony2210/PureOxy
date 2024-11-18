<?php
require '../bd/bd.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour liker un commentaire.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Vérifier le jeton CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Erreur : Jeton CSRF invalide.']);
    exit;
}

if (isset($_POST['action']) && isset($_POST['comment_id'])) {
    $action = $_POST['action'];
    $comment_id = intval($_POST['comment_id']);

    if ($action == 'like') {
        // Vérifier si l'utilisateur a déjà liké ce commentaire
        $stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ?");
        $stmt->bind_param("ii", $user_id, $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            // Ajouter le like
            $stmt = $conn->prepare("INSERT INTO likes (user_id, comment_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $comment_id);
            if ($stmt->execute()) {
                // Mettre à jour le nombre de likes dans la table commentaire
                $stmt = $conn->prepare("UPDATE commentaire SET likes = likes + 1 WHERE id = ?");
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();

                // Récupérer le nouveau nombre de likes
                $stmt = $conn->prepare("SELECT likes FROM commentaire WHERE id = ?");
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comment = $result->fetch_assoc();
                $likes = $comment['likes'];

                echo json_encode(['success' => true, 'message' => 'Commentaire liké.', 'likes' => $likes]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Une erreur s\'est produite lors du like.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Vous avez déjà liké ce commentaire.']);
            exit;
        }
    } elseif ($action == 'unlike') {
        // Vérifier si l'utilisateur a déjà liké ce commentaire
        $stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ?");
        $stmt->bind_param("ii", $user_id, $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Supprimer le like
            $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND comment_id = ?");
            $stmt->bind_param("ii", $user_id, $comment_id);
            if ($stmt->execute()) {
                // Mettre à jour le nombre de likes dans la table commentaire
                $stmt = $conn->prepare("UPDATE commentaire SET likes = likes - 1 WHERE id = ?");
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();

                // Récupérer le nouveau nombre de likes
                $stmt = $conn->prepare("SELECT likes FROM commentaire WHERE id = ?");
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comment = $result->fetch_assoc();
                $likes = $comment['likes'];

                echo json_encode(['success' => true, 'message' => 'Like retiré.', 'likes' => $likes]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Une erreur s\'est produite lors du unlike.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas liké ce commentaire.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}
?>
