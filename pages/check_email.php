<?php
// check_email.php
header('Content-Type: application/json');
require '../bd/bd.php';
if (isset($_POST['email'])) {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param('s', $email);
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
