<?php
/**
 * vote_comment.php
 *
 * Ce script gère le vote (like/dislike) sur un commentaire.
 * Il reçoit une requête POST via AJAX et vérifie que l'utilisateur est connecté.
 * Ensuite, il traite le vote en insérant, mettant à jour ou supprimant un vote existant,
 * puis calcule et renvoie le nombre de likes et de dislikes pour le commentaire.
 *
 * Références :
 * - ChatGPT pour la structuration et la gestion des requêtes AJAX.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis "commentaires.js" lors du clic sur les boutons like/dislike.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

session_start();
require_once '../bd/bd.php';
$db = new Database();

// Vérification que la requête est une requête POST avec le paramètre "ajax_vote"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_vote'])) {
    // Vérification que l'utilisateur est connecté
    if (!isset($_SESSION['id_users'])) {
        echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour voter.']);
        exit;
    }
    $id_users = $_SESSION['id_users'];

    // Vérification que l'identifiant du commentaire est spécifié
    if (!isset($_POST['id_comm']) || empty($_POST['id_comm'])) {
        echo json_encode(['success' => false, 'message' => 'Commentaire non spécifié.']);
        exit;
    }
    $id_comm = (int) $_POST['id_comm'];

    // Vérification que le vote est valide (doit être "1" ou "-1")
    if (!isset($_POST['vote']) || !in_array($_POST['vote'], ['1', '-1'])) {
        echo json_encode(['success' => false, 'message' => 'Vote invalide.']);
        exit;
    }
    $vote = (int) $_POST['vote'];

    // Vérifier si l'utilisateur a déjà voté pour ce commentaire
    $stmt = $db->prepare("SELECT id_likes, vote FROM likes WHERE id_users = ? AND id_comm = ?");
    $stmt->bind_param("ii", $id_users, $id_comm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Si un vote existe déjà
        $row = $result->fetch_assoc();
        if ($row['vote'] == $vote) {
            // Si l'utilisateur effectue le même vote, le retirer (toggle off)
            $stmtDel = $db->prepare("DELETE FROM likes WHERE id_likes = ?");
            $stmtDel->bind_param("i", $row['id_likes']);
            $stmtDel->execute();
            $stmtDel->close();
        } else {
            // Si le vote est différent, mettre à jour le vote
            $stmtUpdate = $db->prepare("UPDATE likes SET vote = ? WHERE id_likes = ?");
            $stmtUpdate->bind_param("ii", $vote, $row['id_likes']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    } else {
        // Aucun vote existant, insertion d'un nouveau vote
        $stmtInsert = $db->prepare("INSERT INTO likes (id_users, id_comm, vote) VALUES (?, ?, ?)");
        $stmtInsert->bind_param("iii", $id_users, $id_comm, $vote);
        $stmtInsert->execute();
        $stmtInsert->close();
    }
    $stmt->close();

    // Calcul du nombre de likes pour le commentaire
    $stmtLikes = $db->prepare("SELECT COUNT(*) as like_count FROM likes WHERE id_comm = ? AND vote = 1");
    $stmtLikes->bind_param("i", $id_comm);
    $stmtLikes->execute();
    $resultLikes = $stmtLikes->get_result();
    $rowLikes = $resultLikes->fetch_assoc();
    $like_count = $rowLikes && $rowLikes['like_count'] !== null ? $rowLikes['like_count'] : 0;
    $stmtLikes->close();

    // Calcul du nombre de dislikes pour le commentaire
    $stmtDislikes = $db->prepare("SELECT COUNT(*) as dislike_count FROM likes WHERE id_comm = ? AND vote = -1");
    $stmtDislikes->bind_param("i", $id_comm);
    $stmtDislikes->execute();
    $resultDislikes = $stmtDislikes->get_result();
    $rowDislikes = $resultDislikes->fetch_assoc();
    $dislike_count = $rowDislikes && $rowDislikes['dislike_count'] !== null ? $rowDislikes['dislike_count'] : 0;
    $stmtDislikes->close();

    // Renvoi de la réponse JSON avec les compteurs de votes
    echo json_encode([
        'success' => true,
        'message' => 'Vote enregistré.',
        'like_count' => $like_count,
        'dislike_count' => $dislike_count
    ]);
    exit;
} else {
    // Requête invalide
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}
?>
