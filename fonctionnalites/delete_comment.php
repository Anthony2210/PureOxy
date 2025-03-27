<?php
/**
 * delete_comment.php
 *
 * Ce script gère la suppression des commentaires par les utilisateurs. Il vérifie l'authentification de l'utilisateur,
 * valide le jeton CSRF, s'assure que le commentaire appartient à l'utilisateur, et décide de supprimer ou de
 * masquer le commentaire en fonction de la présence de réponses.
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
 * Vérifie si l'utilisateur est connecté.
 *
 * Si l'utilisateur n'est pas connecté, renvoie une réponse JSON d'erreur et termine le script.
 */
if (!isset($_SESSION['id_users'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour supprimer un commentaire.'
    ]);
    exit;
}

$id_users = $_SESSION['id_users'];

/**
 * Vérifie la validité du jeton CSRF.
 *
 * Si le jeton CSRF est invalide ou absent, renvoie une réponse JSON d'erreur et termine le script.
 */
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

/**
 * Fonction pour vérifier si un commentaire existe et appartient à l'utilisateur.
 *
 * @param mysqli $conn        Connexion à la base de données.
 * @param int    $comment_id  Identifiant du commentaire.
 * @param int    $id_users     Identifiant de l'utilisateur.
 *
 * @return bool Retourne true si le commentaire existe et appartient à l'utilisateur, sinon false.
 */
function isUserComment($conn, $comment_id, $id_users) {
    $stmt = $conn->prepare("SELECT id FROM commentaire WHERE id = ? AND id_users = ?");
    if (!$stmt) {
        // Journalisation de l'erreur côté serveur
        error_log("Erreur de préparation de la requête SQL dans isUserComment: " . $conn->error);
        return false;
    }
    $stmt->bind_param("ii", $comment_id, $id_users);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Fonction pour vérifier si un commentaire a des réponses.
 *
 * @param mysqli $conn        Connexion à la base de données.
 * @param int    $comment_id  Identifiant du commentaire.
 *
 * @return bool Retourne true si le commentaire a des réponses, sinon false.
 */
function hasReplies($conn, $comment_id) {
    $stmt = $conn->prepare("SELECT id FROM commentaire WHERE parent_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Erreur de préparation de la requête SQL dans hasReplies: " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasReplies = $result->num_rows > 0;
    $stmt->close();
    return $hasReplies;
}

/**
 * Fonction pour supprimer un commentaire.
 *
 * @param mysqli $conn        Connexion à la base de données.
 * @param int    $comment_id  Identifiant du commentaire.
 *
 * @return bool Retourne true si la suppression a réussi, sinon false.
 */
function deleteComment($conn, $comment_id) {
    $stmt = $conn->prepare("DELETE FROM commentaire WHERE id = ?");
    if (!$stmt) {
        error_log("Erreur de préparation de la requête SQL dans deleteComment: " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $comment_id);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Erreur lors de l'exécution de la requête SQL dans deleteComment: " . $stmt->error);
    }
    $stmt->close();
    return $success;
}

/**
 * Fonction pour masquer le contenu d'un commentaire.
 *
 * @param mysqli $conn        Connexion à la base de données.
 * @param int    $comment_id  Identifiant du commentaire.
 *
 * @return bool Retourne true si la mise à jour a réussi, sinon false.
 */
function hideComment($conn, $comment_id) {
    $stmt = $conn->prepare("UPDATE commentaire SET content = 'Message supprimé' WHERE id = ?");
    if (!$stmt) {
        error_log("Erreur de préparation de la requête SQL dans hideComment: " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $comment_id);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Erreur lors de l'exécution de la requête SQL dans hideComment: " . $stmt->error);
    }
    $stmt->close();
    return $success;
}

/**
 * Fonction principale pour traiter la suppression d'un commentaire.
 *
 * @param mysqli $conn        Connexion à la base de données.
 * @param int    $id_users     Identifiant de l'utilisateur.
 */
function processDeleteComment($conn, $id_users) {
    // Vérifie si les paramètres POST requis sont présents
    if (isset($_POST['action'], $_POST['comment_id']) && $_POST['action'] === 'delete_comment') {
        $comment_id = intval($_POST['comment_id']);

        // Validation de l'identifiant du commentaire
        if ($comment_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Identifiant de commentaire invalide.'
            ]);
            exit;
        }

        // Vérifie que le commentaire appartient à l'utilisateur
        if (!isUserComment($conn, $comment_id, $id_users)) {
            echo json_encode([
                'success' => false,
                'message' => 'Commentaire introuvable ou ne vous appartient pas.'
            ]);
            exit;
        }

        // Vérifie si le commentaire a des réponses
        if (hasReplies($conn, $comment_id)) {
            // Masquer le contenu du commentaire
            if (hideComment($conn, $comment_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Commentaire supprimé.',
                    'deleted' => false
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la suppression du commentaire.'
                ]);
                exit;
            }
        } else {
            // Supprimer le commentaire
            if (deleteComment($conn, $comment_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Commentaire supprimé.',
                    'deleted' => true
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la suppression du commentaire.'
                ]);
                exit;
            }
        }
    } else {
        // Paramètres POST invalides
        echo json_encode([
            'success' => false,
            'message' => 'Paramètres invalides.'
        ]);
        exit;
    }
}

// Traitement principal
processDeleteComment($conn, $id_users);
?>
