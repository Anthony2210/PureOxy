<?php
session_start();
require_once '../bd/bd.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_vote'])) {
    if (!isset($_SESSION['id_users'])) {
        echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour voter.']);
        exit;
    }
    $id_users = $_SESSION['id_users'];
    if (!isset($_POST['id_comm']) || empty($_POST['id_comm'])) {
        echo json_encode(['success' => false, 'message' => 'Commentaire non spécifié.']);
        exit;
    }
    $id_comm = (int) $_POST['id_comm'];
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
        $row = $result->fetch_assoc();
        if ($row['vote'] == $vote) {
            // Même vote : on retire le vote (toggle off)
            $stmtDel = $db->prepare("DELETE FROM likes WHERE id_likes = ?");
            $stmtDel->bind_param("i", $row['id_likes']);
            $stmtDel->execute();
            $stmtDel->close();
        } else {
            // Vote différent : mise à jour du vote
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

    // Calcul des compteurs de likes et dislikes
    $stmtLikes = $db->prepare("SELECT COUNT(*) as like_count FROM likes WHERE id_comm = ? AND vote = 1");
    $stmtLikes->bind_param("i", $id_comm);
    $stmtLikes->execute();
    $resultLikes = $stmtLikes->get_result();
    $rowLikes = $resultLikes->fetch_assoc();
    $like_count = $rowLikes && $rowLikes['like_count'] !== null ? $rowLikes['like_count'] : 0;
    $stmtLikes->close();

    $stmtDislikes = $db->prepare("SELECT COUNT(*) as dislike_count FROM likes WHERE id_comm = ? AND vote = -1");
    $stmtDislikes->bind_param("i", $id_comm);
    $stmtDislikes->execute();
    $resultDislikes = $stmtDislikes->get_result();
    $rowDislikes = $resultDislikes->fetch_assoc();
    $dislike_count = $rowDislikes && $rowDislikes['dislike_count'] !== null ? $rowDislikes['dislike_count'] : 0;
    $stmtDislikes->close();

    echo json_encode([
        'success' => true,
        'message' => 'Vote enregistré.',
        'like_count' => $like_count,
        'dislike_count' => $dislike_count
    ]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}
?>
