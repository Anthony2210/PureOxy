<?php
/**
 * Gestion des Likes et Unlikes pour les Commentaires - PureOxy
 *
 * ... [Description précédente]
 */

require '../bd/bd.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour liker un commentaire.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

if (
    !isset($_POST['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur : Jeton CSRF invalide.'
    ]);
    exit;
}

if (isset($_POST['action']) && isset($_POST['comment_id'])) {
    $action = $_POST['action'];
    $comment_id = intval($_POST['comment_id']);

    // Début de la transaction
    $conn->begin_transaction();

    try {
        if ($action == 'like') {
            // Vérifier si l'utilisateur a déjà liké ce commentaire
            $stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête.');
            }
            $stmt->bind_param("ii", $user_id, $comment_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $stmt->close();

                // Ajouter le like
                $stmt = $conn->prepare("INSERT INTO likes (user_id, comment_id) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête d\'insertion.');
                }
                $stmt->bind_param("ii", $user_id, $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de l\'insertion du like.');
                }
                $stmt->close();

                // Mettre à jour le nombre de likes dans la table commentaire
                $stmt = $conn->prepare("UPDATE commentaire SET likes = likes + 1 WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de mise à jour.');
                }
                $stmt->bind_param("i", $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de la mise à jour des likes.');
                }
                $stmt->close();

                // Récupérer le nouveau nombre de likes
                $stmt = $conn->prepare("SELECT likes FROM commentaire WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de récupération des likes.');
                }
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comment = $result->fetch_assoc();
                $likes = $comment['likes'];
                $stmt->close();

                // Commit de la transaction
                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Commentaire liké.',
                    'likes' => $likes
                ]);
                exit;
            } else {
                $stmt->close();
                // Rollback de la transaction
                $conn->rollback();

                echo json_encode([
                    'success' => false,
                    'message' => 'Vous avez déjà liké ce commentaire.'
                ]);
                exit;
            }

        } elseif ($action == 'unlike') {
            // Vérifier si l'utilisateur a déjà liké ce commentaire
            $stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête.');
            }
            $stmt->bind_param("ii", $user_id, $comment_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmt->close();

                // Supprimer le like
                $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND comment_id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de suppression.');
                }
                $stmt->bind_param("ii", $user_id, $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de la suppression du like.');
                }
                $stmt->close();

                // Mettre à jour le nombre de likes dans la table commentaire
                $stmt = $conn->prepare("UPDATE commentaire SET likes = likes - 1 WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de mise à jour.');
                }
                $stmt->bind_param("i", $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de la mise à jour des likes.');
                }
                $stmt->close();

                // Récupérer le nouveau nombre de likes
                $stmt = $conn->prepare("SELECT likes FROM commentaire WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de récupération des likes.');
                }
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comment = $result->fetch_assoc();
                $likes = $comment['likes'];
                $stmt->close();

                // Commit de la transaction
                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Like retiré.',
                    'likes' => $likes
                ]);
                exit;
            } else {
                $stmt->close();
                // Rollback de la transaction
                $conn->rollback();

                echo json_encode([
                    'success' => false,
                    'message' => 'Vous n\'avez pas liké ce commentaire.'
                ]);
                exit;
            }

        } else {
            // Rollback de la transaction pour une action non reconnue
            $conn->rollback();

            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue.'
            ]);
            exit;
        }

    } catch (Exception $e) {
        // Rollback de la transaction en cas d'erreur
        $conn->rollback();

        // Journalisation de l'erreur côté serveur
        error_log("Erreur dans like_comment.php : " . $e->getMessage());

        echo json_encode([
            'success' => false,
            'message' => 'Une erreur s\'est produite. Veuillez réessayer plus tard.'
        ]);
        exit;
    }

} else {
    /**
     * Si les paramètres POST requis ne sont pas présents, renvoie une réponse JSON d'erreur.
     */
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres invalides.'
    ]);
    exit;
}
?>
