<?php
/**
 * check_email.php
 *
 * Ce script vérifie si une adresse email est déjà utilisée dans la table "users".
 * Il renvoie une réponse JSON indiquant si l'email existe déjà.
 *
 * Références :
 * - ChatGPT pour la structuration de la requête et la gestion de la réponse JSON.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis le formulaire d'inscription afin de vérifier l'unicité de l'email.
 * 
 * Fichier placé dans le dossier fonctionnalites.
 */

header('Content-Type: application/json');
require '../bd/bd.php';
$db = new Database();

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        // Renvoie true si l'email existe déjà, false sinon
        echo json_encode(['exists' => $count > 0]);
        $stmt->close();
    } else {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false]);
}
?>
