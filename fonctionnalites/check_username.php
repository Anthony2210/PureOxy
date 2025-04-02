<?php
/**
 * check_username.php
 *
 * Ce script vérifie si un nom d'utilisateur est déjà utilisé dans la table "users".
 * Il reçoit en POST un paramètre "username", effectue une requête préparée et renvoie
 * une réponse JSON indiquant si le nom d'utilisateur existe déjà (true) ou non (false).
 *
 * Références :
 * - ChatGPT pour la structuration et la gestion de la réponse JSON.
 *
 * Utilisation :
 * - Ce script est appelé en AJAX depuis le formulaire d'inscription pour valider l'unicité
 *   du nom d'utilisateur en temps réel.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

header('Content-Type: application/json');
require '../bd/bd.php';
$db = new Database();

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        // Renvoie true si le nom d'utilisateur est déjà utilisé, sinon false
        echo json_encode(['exists' => $count > 0]);
        $stmt->close();
    } else {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false]);
}
?>
