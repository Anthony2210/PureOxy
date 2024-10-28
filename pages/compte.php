<?php
session_start();
require '../bd/bd.php'; // Connexion à la base de données

// Gestion de l'inscription
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password === $confirm_password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hashed_password]);
        echo "Compte créé avec succès !";
    } else {
        echo "Les mots de passe ne correspondent pas.";
    }
}

// Gestion de la connexion
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    } else {
        echo "Nom d'utilisateur ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Compte - PureOxy</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">

</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="compte-container">
    <h1>L’espace Compte</h1>
    <p>Rejoignez-nous en vous inscrivant, ou connectez-vous si vous avez un compte</p>

    <div class="compte-forms">
        <!-- Formulaire de connexion -->
        <form class="compte-form" method="POST">
            <h2>Connexion à votre compte</h2>
            <input type="text" name="username" placeholder="Entrez le nom du compte" required>
            <input type="password" name="password" placeholder="Entrez le mot de passe" required>
            <button type="submit" name="login">Se connecter</button>
        </form>

        <!-- Formulaire d'inscription -->
        <form class="compte-form" method="POST">
            <h2>Création d'un nouveau compte</h2>
            <input type="text" name="username" placeholder="Entrez le nom du compte" required>
            <input type="password" name="password" placeholder="Entrez le mot de passe" required>
            <input type="password" name="confirm_password" placeholder="Entrez à nouveau le mot de passe" required>
            <button type="submit" name="register">S'inscrire</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
