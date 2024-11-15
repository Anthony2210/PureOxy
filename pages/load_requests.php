<?php
session_start();
require_once('../bd/bd.php');

if (!isset($_SESSION['user_id'])) {
    echo '<p>Veuillez vous connecter pour voir vos demandes.</p>';
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT sujet, message, date_demande FROM messages_contact WHERE user_id = ? ORDER BY date_demande DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<ul>';
    while ($row = $result->fetch_assoc()) {
        echo '<li>';
        echo '<strong>Objet :</strong> ' . htmlspecialchars($row['sujet']) . '<br>';
        echo '<strong>Date :</strong> ' . htmlspecialchars($row['date_demande']) . '<br>';
        echo '<strong>Message :</strong> ' . nl2br(htmlspecialchars($row['message'])) . '<br>';
        echo '</li><hr>';
    }
    echo '</ul>';
} else {
    echo '<p>Vous n\'avez pas encore envoyé de demandes.</p>';
}
?>
