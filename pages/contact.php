<?php
/**
 * contact.php
 *
 * Ce script gère l'affichage et le traitement du formulaire de contact.
 * Il récupère les informations de l'utilisateur connecté depuis la table "users",
 * valide les données du formulaire, et enregistre le message dans la table "messages_contact"
 * qui comporte les colonnes : nom, id_message, email, sujet, message, date_demande, id_users.
 */

session_start();
require_once('../bd/bd.php');

// Instanciation de la classe Database
$db = new Database();

// Initialisation des variables du formulaire
$nom = '';
$email = '';
$sujet = '';
$message = '';

/**
 * Récupère les informations de l'utilisateur connecté depuis la table "users".
 *
 * @param Database $db       Instance de Database pour préparer les requêtes.
 * @param int      $id_users ID de l'utilisateur.
 */
function getUserInfo($db, $id_users) {
    global $nom, $email;
    try {
        // Récupération depuis la table "users"
        $stmt = $db->prepare("SELECT username, email FROM users WHERE id_users = ?");
        $stmt->bind_param("i", $id_users);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $nom = $row['username'];
            $email = $row['email'] ?? '';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

// Si l'utilisateur est connecté, on récupère ses informations
if (isset($_SESSION['id_users'])) {
    $id_users = $_SESSION['id_users'];
    getUserInfo($db, $id_users);
}

/**
 * Valide les données du formulaire de contact.
 *
 * @param array $data Données du formulaire.
 * @return array Tableau des erreurs de validation.
 */
function validateFormData($data) {
    $errors = [];
    if (empty($data['nom'])) {
        $errors[] = "Le nom est requis.";
    }
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }
    if (empty($data['sujet'])) {
        $errors[] = "L'objet du message est requis.";
    } elseif (strlen($data['sujet']) > 100) {
        $errors[] = "L'objet ne doit pas dépasser 100 caractères.";
    }
    if (empty($data['message'])) {
        $errors[] = "Le message est requis.";
    } elseif (strlen($data['message']) > 500) {
        $errors[] = "Le message ne doit pas dépasser 500 caractères.";
    }
    return $errors;
}

/**
 * Insère le message de contact dans la table "messages_contact".
 *
 * Les colonnes insérées sont : id_users, nom, email, sujet, message, date_demande.
 *
 * @param Database $db       Instance de Database.
 * @param int|null $id_users ID de l'utilisateur (peut être NULL).
 * @param string   $nom     Nom de l'utilisateur.
 * @param string   $email   Email de l'utilisateur.
 * @param string   $sujet   Sujet du message.
 * @param string   $message Contenu du message.
 * @return bool True en cas de succès, False sinon.
 */
function insertContactMessage($db, $id_users, $nom, $email, $sujet, $message) {
    try {
        // Date de la demande
        $date_demande = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO messages_contact (id_users, nom, email, sujet, message, date_demande) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $id_users, $nom, $email, $sujet, $message, $date_demande);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

// Traitement du formulaire lors d'une requête POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pour un utilisateur non connecté, récupérer les valeurs depuis le POST.
    // Pour un utilisateur connecté, on utilise déjà $nom et $email récupérés.
    if (!isset($_SESSION['id_users'])) {
        $nom   = trim($_POST['nom']);
        $email = trim($_POST['email']);
    }
    $sujet   = trim($_POST['sujet']);
    $message = trim($_POST['message']);
    $id_users = $_SESSION['id_users'] ?? NULL;

    // Constitution d'un tableau de données pour la validation
    $data = [
        'nom'     => $nom,
        'email'   => $email,
        'sujet'   => $sujet,
        'message' => $message
    ];
    $errors = validateFormData($data);

    if (empty($errors)) {
        if (insertContactMessage($db, $id_users, $nom, $email, $sujet, $message)) {
            $success_message = "Votre message a été envoyé avec succès.";
            // Pour un utilisateur non connecté, réinitialiser nom et email
            if (!isset($_SESSION['id_users'])) {
                $nom = $email = '';
            }
            $sujet = $message = '';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactez-nous</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Feuilles de style -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/contact.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Script de validation -->
    <script src="../script/erreur_formulaire.js"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>
<main>
    <section id="contact">
        <h2>Contactez-nous</h2>
        <?php if (isset($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php elseif (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <!-- Champ Nom -->
            <div class="input-group">
                <label for="nom">Votre nom</label>
                <?php if (isset($_SESSION['id_users'])): ?>
                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($nom); ?>" readonly style="background-color: #e9ecef;">
                <?php else: ?>
                    <input type="text" id="nom" name="nom" placeholder="Votre nom" value="<?php echo htmlspecialchars($nom); ?>" required>
                <?php endif; ?>
            </div>
            <!-- Champ Email -->
            <div class="input-group">
                <label for="email">Votre email</label>
                <?php if (isset($_SESSION['id_users'])): ?>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly style="background-color: #e9ecef;">
                <?php else: ?>
                    <input type="email" id="email" name="email" placeholder="Votre email" value="<?php echo htmlspecialchars($email); ?>" required>
                <?php endif; ?>
            </div>
            <!-- Champ Sujet -->
            <div class="input-group">
                <label for="sujet">Objet du message</label>
                <input type="text" id="sujet" name="sujet" placeholder="Objet du message" value="<?php echo htmlspecialchars($sujet); ?>" required>
            </div>
            <!-- Champ Message -->
            <div class="input-group">
                <label for="message">Votre message (500 caractères max)</label>
                <textarea id="message" name="message" placeholder="Votre message (500 caractères max)" maxlength="500" required><?php echo htmlspecialchars($message); ?></textarea>
            </div>
            <button id="envoyer" type="submit">Envoyer</button>
        </form>
    </section>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
