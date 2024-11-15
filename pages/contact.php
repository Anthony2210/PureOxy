<?php
session_start();
require_once('../bd/bd.php');

$nom = '';
$email = '';
$sujet = '';
$message = '';


if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Récupérer les informations de l'utilisateur
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $nom = $user_row['username'];
        $email = $user_row['email'] ?? '';
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $sujet = trim($_POST['sujet']);
    $message = trim($_POST['message']);
    $user_id = $_SESSION['user_id'] ?? NULL;

    // Validation des données
    $errors = [];

    if (empty($nom)) {
        $errors[] = "Le nom est requis.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }

    if (empty($sujet)) {
        $errors[] = "L'objet du message est requis.";
    } elseif (strlen($sujet) > 100) {
        $errors[] = "L'objet ne doit pas dépasser 100 caractères.";
    }

    if (empty($message)) {
        $errors[] = "Le message est requis.";
    } elseif (strlen($message) > 500) {
        $errors[] = "Le message ne doit pas dépasser 500 caractères.";
    }

    if (empty($errors)) {
        // Insérer la demande dans la base de données
        $stmt = $conn->prepare("INSERT INTO messages_contact (user_id, nom, email, sujet, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $nom, $email, $sujet, $message);
        if ($stmt->execute()) {
            $success_message = "Votre message a été envoyé avec succès.";
            // Réinitialiser les champs du formulaire
            $nom = '';
            $email = '';
            $sujet = '';
            $message = '';
        } else {
            $error_message = "Une erreur est survenue lors de l'envoi de votre message.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contactez-nous</title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/includes.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<section id="contact">
    <h2>Contactez-nous</h2>
    <?php if (isset($success_message)): ?>
        <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
    <?php elseif (isset($error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>
    <form method="post" action="">
        <div class="input-group">
            <input type="text" name="nom" placeholder="Votre nom" value="<?php echo htmlspecialchars($nom); ?>" required>
        </div>
        <div class="input-group">
            <input type="email" name="email" placeholder="Votre email" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        <div class="input-group">
            <input type="text" name="sujet" placeholder="Objet du message" value="<?php echo htmlspecialchars($sujet); ?>" required>
        </div>
        <div class="input-group">
            <textarea name="message" placeholder="Votre message (500 caractères max)" maxlength="500" required><?php echo htmlspecialchars($message); ?></textarea>
        </div>
        <button type="submit">Envoyer</button>
    </form>
</section>

<?php include '../includes/footer.php'; ?>
</body>
</html>
