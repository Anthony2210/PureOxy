<?php
/**
 * register_user.php
 *
 * Ce script gère l'inscription des nouveaux utilisateurs sur le site PureOxy.
 * Il effectue des validations côté serveur, protège contre les attaques CSRF,
 * vérifie l'unicité du nom d'utilisateur et de l'email, et enregistre le nouvel utilisateur dans la base de données.
 *
 */
session_start();
header('Content-Type: application/json');
require '../bd/bd.php';

/**
 * Vérification du jeton CSRF pour protéger contre les attaques de type Cross-Site Request Forgery.
 * Si le jeton n'est pas présent ou invalide, renvoie une réponse d'erreur et arrête l'exécution.
 */
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']);
    exit();
}

/**
 * Vérifie si les paramètres 'username', 'email', 'password' et 'confirm_password' sont présents dans la requête POST.
 * Si oui, procède au traitement de l'inscription.
 */
if (isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password'])) {
    // Récupération et nettoyage des données envoyées via POST
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    /**
     * Validation côté serveur des données d'inscription.
     * Vérifie que tous les champs sont remplis et que les données respectent les critères définis.
     */
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
        /**
         * Vérifie si le nom d'utilisateur ou l'email est déjà pris dans la base de données.
         * Utilisation de requêtes préparées pour prévenir les injections SQL.
         */
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        if ($stmt) {
            // Liaison des paramètres 'username' et 'email' à la requête préparée
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();

            // Si le nom d'utilisateur ou l'email existe déjà, renvoie une erreur
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce nom d\'utilisateur ou email est déjà pris.']);
                $stmt->close();
                exit();
            }
            // Ferme la requête préparée
            $stmt->close();
        } else {
            // En cas d'échec de la préparation de la requête, renvoie une erreur de base de données
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
            exit();
        }

        /**
         * Hashage sécurisé du mot de passe avant son enregistrement dans la base de données.
         * Utilisation de l'algorithme de hashage par défaut recommandé par PHP.
         */
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Définition d'une photo de profil par défaut pour le nouvel utilisateur
        $profile_picture = 'user.png';

        /**
         * Insertion du nouvel utilisateur dans la base de données.
         * Utilisation de requêtes préparées pour sécuriser l'insertion.
         */
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, profile_picture) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            // Liaison des paramètres à la requête préparée
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $profile_picture);

            // Exécution de la requête d'insertion
            if ($stmt->execute()) {
                /**
                 * Connexion automatique de l'utilisateur après une inscription réussie.
                 * Stocke l'ID utilisateur et le nom d'utilisateur dans la session.
                 */
                $_SESSION['id_users'] = $conn->insert_id;
                $_SESSION['username'] = $username;

                echo json_encode(['success' => true, 'message' => 'Compte créé avec succès ! Vous allez être redirigé.']);
            } else {
                // En cas d'échec de l'exécution de la requête d'insertion
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du compte.']);
            }
            // Ferme la requête préparée
            $stmt->close();
        } else {
            // En cas d'échec de la préparation de la requête d'insertion
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
        }
    }
} else {
    // Si les données requises ne sont pas présentes dans la requête POST
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
}
?>