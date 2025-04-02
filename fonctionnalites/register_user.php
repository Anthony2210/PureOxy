<?php
/**
 * register_user.php
 *
 * Ce script gère l'inscription d'un nouvel utilisateur.
 * Il vérifie d'abord le jeton CSRF, puis valide et nettoie les données du formulaire.
 * Si les validations sont satisfaites, il vérifie l'unicité du nom d'utilisateur et de l'email,
 * puis insère les nouvelles données dans la table "users".
 * La réponse est renvoyée au format JSON.
 *
 * Références :
 * - ChatGPT pour la validation des données, la gestion du CSRF et la réponse JSON.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis le formulaire d'inscription sur la page compte.php.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */
session_start();
header('Content-Type: application/json');
require '../bd/bd.php';
$db = new Database();

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']);
    exit();
}

if (isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation des champs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Adresse email invalide.']);
        exit();
    } elseif ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas.']);
        exit();
    } elseif (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.']);
        exit();
    } elseif (!preg_match('/[A-Z]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins une lettre majuscule.']);
        exit();
    } elseif (!preg_match('/[a-z]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins une lettre minuscule.']);
        exit();
    } elseif (!preg_match('/[0-9]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins un chiffre.']);
        exit();
    } elseif (!preg_match('/[\W]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins un caractère spécial.']);
        exit();
    } else {
        // Vérifie l'unicité du nom d'utilisateur et de l'email
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce nom d\'utilisateur ou email est déjà pris.']);
                $stmt->close();
                exit();
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
            exit();
        }

        // Hachage du mot de passe et insertion dans la base
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $profile_picture = 'user.png';

        $stmt = $db->prepare("INSERT INTO users (username, email, password, profile_picture) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $profile_picture);
            if ($stmt->execute()) {
                // Met à jour la session avec l'identifiant de l'utilisateur inséré
                $_SESSION['id_users'] = $db->getConnection()->insert_id;
                $_SESSION['username'] = $username;
                echo json_encode(['success' => true, 'message' => 'Compte créé avec succès ! Vous allez être redirigé.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du compte.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
}
?>
