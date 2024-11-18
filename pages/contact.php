<?php
/**
 * Contact.php
 *
 * Ce script gère l'affichage et le traitement du formulaire de contact.
 * Il récupère les informations de l'utilisateur connecté, valide les données du formulaire,
 * et enregistre le message de contact dans la base de données.
 *
 * @package Contact
 */

session_start();
require_once('../bd/bd.php');

/**
 * Variables initiales pour le formulaire de contact.
 *
 * @var string $nom     Nom de l'utilisateur
 * @var string $email   Email de l'utilisateur
 * @var string $sujet   Sujet du message
 * @var string $message Contenu du message
 */
$nom = '';
$email = '';
$sujet = '';
$message = '';

/**
 * Récupère les informations de l'utilisateur connecté à partir de la session.
 *
 * @param mysqli $conn     Connexion à la base de données
 * @param int    $user_id  ID de l'utilisateur
 *
 * @return void
 */
function getUserInfo($conn, $user_id) {
    global $nom, $email;

    // Préparer la requête pour récupérer le nom d'utilisateur et l'email
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();

    // Si l'utilisateur est trouvé, assigner les valeurs aux variables
    if ($user_row = $user_result->fetch_assoc()) {
        $nom = $user_row['username'];
        $email = $user_row['email'] ?? '';
    }
}

// Vérifier si l'utilisateur est connecté et récupérer ses informations
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    getUserInfo($conn, $user_id);
}

/**
 * Valide les données du formulaire de contact.
 *
 * @param array $data Les données du formulaire à valider
 *
 * @return array Tableau contenant les erreurs de validation, vide si aucune erreur
 */
function validateFormData($data) {
    $errors = [];

    // Validation du nom
    if (empty($data['nom'])) {
        $errors[] = "Le nom est requis.";
    }

    // Validation de l'email
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }

    // Validation du sujet
    if (empty($data['sujet'])) {
        $errors[] = "L'objet du message est requis.";
    } elseif (strlen($data['sujet']) > 100) {
        $errors[] = "L'objet ne doit pas dépasser 100 caractères.";
    }

    // Validation du message
    if (empty($data['message'])) {
        $errors[] = "Le message est requis.";
    } elseif (strlen($data['message']) > 500) {
        $errors[] = "Le message ne doit pas dépasser 500 caractères.";
    }

    return $errors;
}

/**
 * Insère le message de contact dans la base de données.
 *
 * @param mysqli $conn    Connexion à la base de données
 * @param int    $user_id ID de l'utilisateur (peut être NULL)
 * @param string $nom     Nom de l'utilisateur
 * @param string $email   Email de l'utilisateur
 * @param string $sujet   Sujet du message
 * @param string $message Contenu du message
 *
 * @return bool True si l'insertion est réussie, False sinon
 */
function insertContactMessage($conn, $user_id, $nom, $email, $sujet, $message) {
    $stmt = $conn->prepare("INSERT INTO messages_contact (user_id, nom, email, sujet, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $nom, $email, $sujet, $message);
    return $stmt->execute();
}

// Traitement du formulaire lors d'une requête POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $sujet = trim($_POST['sujet']);
    $message = trim($_POST['message']);
    $user_id = $_SESSION['user_id'] ?? NULL;

    // Valider les données du formulaire
    $errors = validateFormData($_POST);

    if (empty($errors)) {
        // Insérer le message de contact dans la base de données
        if (insertContactMessage($conn, $user_id, $nom, $email, $sujet, $message)) {
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
        // Concaténer les messages d'erreur
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
            <label for="nom">Votre nom</label>
            <input type="text" id="nom" name="nom" placeholder="Votre nom" value="<?php echo htmlspecialchars($nom); ?>" required>
        </div>
        <div class="input-group">
            <label for="email">Votre email</label>
            <input type="email" id="email" name="email" placeholder="Votre email" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        <div class="input-group">
            <label for="sujet">Objet du message</label>
            <input type="text" id="sujet" name="sujet" placeholder="Objet du message" value="<?php echo htmlspecialchars($sujet); ?>" required>
        </div>
        <div class="input-group">
            <label for="message">Votre message (500 caractères max)</label>
            <textarea id="message" name="message" placeholder="Votre message (500 caractères max)" maxlength="500" required><?php echo htmlspecialchars($message); ?></textarea>
        </div>
        <button type="submit">Envoyer</button>
    </form>
</section>

<?php include '../includes/footer.php'; ?>
</body>
</html>
