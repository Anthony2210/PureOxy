<?php
session_start();
require '../bd/bd.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT c.*, p.title FROM commentaire c LEFT JOIN pages p ON c.page = p.url WHERE c.user_id = ? ORDER BY c.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $comments = $stmt->get_result();

    if ($comments->num_rows > 0) {
        echo '<ul>';
        while ($comment = $comments->fetch_assoc()) {
            echo '<li>';
            echo '<p><strong>Sur la page :</strong> ' . htmlspecialchars($comment['page']) . ' le ' . $comment['created_at'] . '</p>';
            echo '<p>' . nl2br(htmlspecialchars($comment['content'])) . '</p>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Vous n\'avez pas encore posté de commentaires.</p>';
    }
} else {
    echo '<p>Vous devez être connecté pour voir vos commentaires.</p>';
}
?>
