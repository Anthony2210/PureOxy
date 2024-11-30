<?php
// check_username.php
header('Content-Type: application/json');
require '../bd/bd.php';

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();

        echo json_encode(['exists' => $count > 0]);
        $stmt->close();
    } else {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false]);
}
?>
