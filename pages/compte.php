<?php
/**
 * Compte.php
 *
 * Gestion de la session utilisateur, connexion, inscription,
 * gestion des villes favorites et historique des recherches.
 */

session_start();
ob_start();

/**
 * Génération d'un jeton CSRF si non déjà défini.
 *
 * @var string $csrf_token Jeton CSRF pour la protection contre les attaques CSRF.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require '../bd/bd.php';

/**
 * Gestion de la connexion de l'utilisateur.
 *
 * Vérifie les informations de connexion, authentifie l'utilisateur et redirige vers la page d'accueil.
 */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $login_error = "Veuillez remplir tous les champs.";
    } else {
        // Préparation de la requête pour vérifier les informations de connexion
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        if (!$stmt) {
            $login_error = "Préparation de la requête échouée: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            // Vérifie si l'utilisateur existe et si le mot de passe est correct
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: ../index.php");
                exit;
            } else {
                $login_error = "Nom d'utilisateur ou mot de passe incorrect.";
            }
        }
    }
}

/**
 * Gestion de l'inscription de l'utilisateur.
 *
 * Valide les données du formulaire d'inscription, crée un nouvel utilisateur et ajoute à la base de données.
 */
if (isset($_POST['register'])) {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $register_error = "Jeton CSRF invalide.";
    } else {
        // Récupérer et sécuriser les données du formulaire
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation des données
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $register_error = "Tous les champs sont requis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = "Adresse email invalide.";
        } elseif ($password !== $confirm_password) {
            $register_error = "Les mots de passe ne correspondent pas.";
        } elseif (strlen($password) < 8) {
            $register_error = "Le mot de passe doit contenir au moins 8 caractères.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $register_error = "Le mot de passe doit contenir au moins une lettre majuscule.";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $register_error = "Le mot de passe doit contenir au moins une lettre minuscule.";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $register_error = "Le mot de passe doit contenir au moins un chiffre.";
        } elseif (!preg_match('/[\W]/', $password)) {
            $register_error = "Le mot de passe doit contenir au moins un caractère spécial.";
        } else {
            // Vérifier si le nom d'utilisateur ou l'email est déjà pris
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            if (!$stmt) {
                $register_error = "Préparation de la requête échouée: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $register_error = "Ce nom d'utilisateur ou email est déjà pris.";
                } else {
                    // Hashage du mot de passe
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Photo de profil par défaut
                    $profile_picture = 'user.png';

                    // Insertion du nouvel utilisateur dans la base de données
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, profile_picture) VALUES (?, ?, ?, ?)");
                    if (!$stmt) {
                        $register_error = "Préparation de la requête d'insertion échouée: " . $conn->error;
                    } else {
                        $stmt->bind_param("ssss", $username, $email, $hashed_password, $profile_picture);

                        if ($stmt->execute()) {
                            $register_success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
                        } else {
                            $register_error = "Exécution de la requête d'insertion échouée: " . $stmt->error;
                        }
                    }
                }
            }
        }
    }
}

/**
 * Vérifie si l'utilisateur est connecté et récupère l'historique des recherches et les villes favorites.
 */
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Récupérer les 7 dernières recherches
    $stmt = $conn->prepare("SELECT search_query, search_date FROM search_history WHERE user_id = ? ORDER BY search_date DESC LIMIT 7");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $search_history = $result->fetch_all(MYSQLI_ASSOC);

        // Supprimer les recherches plus anciennes
        $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ? AND search_date NOT IN (SELECT search_date FROM (SELECT search_date FROM search_history WHERE user_id = ? ORDER BY search_date DESC LIMIT 7) AS sub)");
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
        }
    }

    // Récupérer les villes favorites
    $stmt = $conn->prepare("SELECT city_name FROM favorite_cities WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cities_result = $stmt->get_result();
        $favorite_cities = $cities_result->fetch_all(MYSQLI_ASSOC);
    }

    // Récupérer les détails de l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user = $user_result->fetch_assoc();
    }

    // Récupérer l'historique des recherches (10 dernières)
    $stmt = $conn->prepare("SELECT search_query, search_date FROM search_history WHERE user_id = ? ORDER BY search_date DESC LIMIT 10");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $history_result = $stmt->get_result();
        $search_history = $history_result->fetch_all(MYSQLI_ASSOC);
    }
}

/**
 * Ajout d'une ville favorite pour l'utilisateur connecté.
 *
 * @return void
 */
if (isset($_POST['add_favorite_city']) && isset($_SESSION['user_id'])) {
    $city_name = trim($_POST['city_name']);
    $user_id = $_SESSION['user_id'];

    // Vérifier que le nom de la ville n'est pas vide
    if (!empty($city_name)) {
        // Vérifier que la ville existe dans la base de données
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pollution_villes WHERE City = ?");
        if (!$stmt) {
            $response = [
                'success' => false,
                'message' => "Préparation de la requête échouée: " . $conn->error
            ];
        } else {
            $stmt->bind_param("s", $city_name);
            $stmt->execute();
            $city_exists_result = $stmt->get_result();
            $city_exists_row = $city_exists_result->fetch_assoc();

            if ($city_exists_row['count'] > 0) {
                // Vérifier que la ville n'est pas déjà dans les favoris de l'utilisateur
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ? AND city_name = ?");
                if (!$stmt) {
                    $response = [
                        'success' => false,
                        'message' => "Préparation de la requête échouée: " . $conn->error
                    ];
                } else {
                    $stmt->bind_param("is", $user_id, $city_name);
                    $stmt->execute();
                    $city_favorite_result = $stmt->get_result();
                    $city_favorite_row = $city_favorite_result->fetch_assoc();

                    if ($city_favorite_row['count'] == 0) {
                        // Vérifier le nombre actuel de villes favorites
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                        if (!$stmt) {
                            $response = [
                                'success' => false,
                                'message' => "Préparation de la requête échouée: " . $conn->error
                            ];
                        } else {
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $count_result = $stmt->get_result();
                            $count_row = $count_result->fetch_assoc();

                            if ($count_row['count'] < 5) {
                                // Insérer la nouvelle ville favorite
                                $stmt = $conn->prepare("INSERT INTO favorite_cities (user_id, city_name) VALUES (?, ?)");
                                if (!$stmt) {
                                    $response = [
                                        'success' => false,
                                        'message' => "Préparation de la requête échouée: " . $conn->error
                                    ];
                                } else {
                                    $stmt->bind_param("is", $user_id, $city_name);
                                    if ($stmt->execute()) {
                                        $response = [
                                            'success' => true,
                                            'message' => "Ville favorite ajoutée avec succès.",
                                            'city_name' => $city_name
                                        ];
                                    } else {
                                        $response = [
                                            'success' => false,
                                            'message' => "Une erreur s'est produite lors de l'ajout de la ville: " . $stmt->error
                                        ];
                                    }
                                }
                            } else {
                                $response = [
                                    'success' => false,
                                    'message' => "Vous avez atteint le nombre maximum de 5 villes favorites."
                                ];
                            }
                        }
                    } else {
                        $response = [
                            'success' => false,
                            'message' => "Cette ville est déjà dans vos favoris."
                        ];
                    }
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => "La ville sélectionnée n'est pas valide."
                ];
            }
        }
    } else {
        $response = [
            'success' => false,
            'message' => "Veuillez sélectionner une ville valide."
        ];
    }

    // Vérifier si la requête est une requête AJAX
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        ini_set('display_errors', 0);
        error_reporting(0);
        if (ob_get_length()) {
            ob_end_clean(); // Nettoyer et fermer le buffer de sortie
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    else {
        // Si ce n'est pas une requête AJAX, rediriger normalement
        if ($response['success']) {
            $success_message_favorite = $response['message'];
        } else {
            $error_message_favorite = $response['message'];
        }
    }
}

/**
 * Suppression d'une recherche individuelle dans l'historique de l'utilisateur.
 *
 * @return void
 */
if (isset($_POST['delete_search']) && isset($_SESSION['user_id'])) {
    $search_query = $_POST['search_query'];
    $user_id = $_SESSION['user_id'];

    // Supprimer la recherche
    $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ? AND search_query = ?");
    if (!$stmt) {
        $response = [
            'success' => false,
            'message' => "Préparation de la requête échouée: " . $conn->error
        ];
    } else {
        $stmt->bind_param("is", $user_id, $search_query);
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "Recherche supprimée avec succès.",
                'search_query' => $search_query
            ];
        } else {
            $response = [
                'success' => false,
                'message' => "Une erreur s'est produite lors de la suppression de la recherche: " . $stmt->error
            ];
        }
    }

    // Vérifier si la requête est une requête AJAX
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // Si ce n'est pas une requête AJAX, rediriger normalement
        header("Location: compte.php");
        exit;
    }
}

/**
 * Efface l'historique des recherches de l'utilisateur.
 *
 * @return void
 */
if (isset($_POST['clear_history']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ?");
    if (!$stmt) {
        $response = [
            'success' => false,
            'message' => "Préparation de la requête échouée: " . $conn->error
        ];
    } else {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "Historique effacé avec succès."
            ];
        } else {
            $response = [
                'success' => false,
                'message' => "Une erreur s'est produite lors de l'effacement de l'historique: " . $stmt->error
            ];
        }
    }

    // Vérifier si la requête est une requête AJAX
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // Si ce n'est pas une requête AJAX, rediriger normalement
        header("Location: compte.php");
        exit;
    }
}

/**
 * Suppression d'une ville favorite de l'utilisateur.
 *
 * @return void
 */
if (isset($_POST['delete_favorite_city']) && isset($_SESSION['user_id'])) {
    $city_name = $_POST['city_name'];
    $user_id = $_SESSION['user_id'];

    // Supprimer la ville favorite
    $stmt = $conn->prepare("DELETE FROM favorite_cities WHERE user_id = ? AND city_name = ?");
    if (!$stmt) {
        $response = [
            'success' => false,
            'message' => "Préparation de la requête échouée: " . $conn->error
        ];
    } else {
        $stmt->bind_param("is", $user_id, $city_name);
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "Ville favorite supprimée avec succès.",
                'city_name' => $city_name
            ];
        } else {
            $response = [
                'success' => false,
                'message' => "Une erreur s'est produite lors de la suppression de la ville: " . $stmt->error
            ];
        }
    }

    // Vérifier si la requête est une requête AJAX
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        if (ob_get_length()) {
            ob_clean(); // Nettoyer le buffer de sortie
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // Si ce n'est pas une requête AJAX, rediriger normalement
        header("Location: compte.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Compte - PureOxy</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Lien Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour les commentaires -->
    <link rel="stylesheet" href="../styles/commentaire.css">
    <!-- Styles pour les suggestions -->
    <link rel="stylesheet" href="../styles/recherche.css">
    <!-- Styles pour la page compte -->
    <link rel="stylesheet" href="../styles/compte.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Styles pour les Messages -->
    <link rel="stylesheet" href="../styles/messages.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script>
    <!-- Script de validation du formulaire avec jQuery et AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<!-- Conteneur pour les messages -->
<div id="message-container">
    <?php
    // Messages de connexion
    if (isset($login_error)) {
        echo '<div class="error-message">' . htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // Messages d'inscription
    if (isset($register_error)) {
        echo '<div class="error-message">' . htmlspecialchars($register_error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (isset($register_success)) {
        echo '<div class="success-message">' . htmlspecialchars($register_success, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // Messages pour les favoris
    if (isset($success_message_favorite)) {
        echo '<div class="success-message">' . htmlspecialchars($success_message_favorite, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (isset($error_message_favorite)) {
        echo '<div class="error-message">' . htmlspecialchars($error_message_favorite, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>
</div>


<div class="compte-container">
    <h2>L’espace Compte</h2>

    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Tableau de bord de l'utilisateur -->
        <div class="dashboard">
            <!-- Carte de Profil -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <img src="../images/user.png" alt="Photo de profil">
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p>Membre depuis <?= isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Date inconnue' ?></p>
                    <button id="view-comments-button">Voir vos commentaires</button>
                    <button id="view-requests-button">Demandes envoyées</button>
                    <!-- Bouton de déconnexion -->
                    <a href="deconnecter.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Se déconnecter</a>
                </div>
            </div>
            <!-- Contenu à droite : Favoris et Historique -->
            <div class="dashboard-content">
                <!-- Section Villes Favorites -->
                <div class="favorite-cities-section">
                    <h3><i class="fas fa-city"></i> Vos villes favorites</h3>
                    <!-- Contenu de la section Favoris -->
                   <?php if (!empty($favorite_cities)): ?>
                        <ul class="favorite-cities-list">
                            <?php foreach ($favorite_cities as $city): ?>
                                <li>
                                    <a href="../fonctionnalites/details.php?ville=<?= urlencode($city['city_name']) ?>" class="favorite-link">
                                        <?= htmlspecialchars($city['city_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <!-- Formulaire pour supprimer la ville favorite -->
                                    <form method="post" class="delete-city-form">
                                        <input type="hidden" name="city_name" value="<?= htmlspecialchars($city['city_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <!-- Jeton CSRF -->
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="delete_favorite_city"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Vous n'avez pas encore ajouté de villes favorites.</p>
                    <?php endif; ?>

                    <!-- Formulaire pour ajouter une nouvelle ville favorite -->
                    <form method="post" class="favorite-city-form" id="favorite-city-form">
                        <input type="text" id="favorite-city-input" placeholder="Entrez le nom d'une ville" autocomplete="off" required>
                        <!-- Champ caché pour stocker la ville sélectionnée -->
                        <input type="hidden" name="city_name" id="city_name_hidden">
                        <!-- Liste déroulante pour les suggestions -->
                        <ul id="suggestions-list"></ul>
                        <!-- Jeton CSRF -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" name="add_favorite_city" id="add-favorite-button" disabled><i class="fas fa-plus"></i> Ajouter</button>
                    </form>
                </div>
                <!-- Section Historique des Recherches -->
                <div class="history-section">
                    <h3><i class="fas fa-history"></i>Historique des dernières recherches</h3>
                    <!-- Contenu de la section Historique -->
                    <?php if (!empty($search_history)): ?>
                        <ul class="history-list">
                            <?php foreach ($search_history as $search): ?>
                                <li>
                                    <a href="../fonctionnalites/details.php?ville=<?= urlencode($search['search_query']) ?>" class="search-query">
                                        <i class="fas fa-search"></i> <?= htmlspecialchars($search['search_query'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span class="search-date"><?= date('d/m/Y H:i', strtotime($search['search_date'])) ?></span>
                                    <!-- Formulaire pour supprimer une recherche individuelle -->
                                    <form method="post" class="delete-search-form">
                                        <input type="hidden" name="search_query" value="<?= htmlspecialchars($search['search_query'], ENT_QUOTES, 'UTF-8') ?>">
                                        <!-- Jeton CSRF -->
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="delete_search"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <!-- Bouton pour effacer l'historique -->
                        <form method="post" class="clear-history-form" id="clear-history-form">
                            <!-- Jeton CSRF -->
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" name="clear_history" class="clear-history-button"><i class="fas fa-trash-alt"></i> Effacer l'historique</button>
                        </form>
                    <?php else: ?>
                        <p>Vous n'avez pas encore effectué de recherches.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Conteneur des onglets -->
        <div class="compte-tabs">
            <button class="compte-tab-link active" onclick="openTab(event, 'connexion')">
                <i class="fas fa-sign-in-alt"></i> Connexion
            </button>
            <button class="compte-tab-link" onclick="openTab(event, 'inscription')">
                <i class="fas fa-user-plus"></i> Inscription
            </button>
        </div>

        <!-- Formulaire de connexion -->
        <div id="connexion" class="compte-tab-content active">
            <form class="compte-form" method="POST">
                <h2>Connexion à votre compte</h2>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Nom d'utilisateur" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                </div>
                <button type="submit" name="login">Se connecter</button>
            </form>
        </div>

        <!-- Formulaire d'inscription -->
        <div id="inscription" class="compte-tab-content">
            <form class="compte-form" id="registration-form">
                <h2>Création d'un nouveau compte</h2>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" id="username" class="input-field" placeholder="Nom d'utilisateur" required>
                </div>
                <span class="error-message-inscription" id="username-error"></span>

                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="email" class="input-field" placeholder="Email" required>
                </div>
                <span class="error-message-inscription" id="email-error"></span>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" class="input-field" placeholder="Mot de passe" required>
                </div>
                <span class="error-message-inscription" id="password-error"></span>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" id="confirm_password" class="input-field" placeholder="Confirmez le mot de passe" required>
                </div>
                <span class="error-message-inscription" id="confirm-password-error"></span>

                <!-- Jeton CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="register" class="btn-register" disabled>S'inscrire</button>
            </form>
        </div>

    <?php endif; ?>
</div>
<!-- Fenêtre modale pour les demandes -->
<div id="requests-modal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Vos demandes</h2>
        <div id="requests-list">
            <!-- Les demandes seront chargées ici -->
        </div>
    </div>
</div>

<!-- Fenêtre modale pour les commentaires -->
<div id="comments-modal" class="modal">
    <div class="modal-content">
        <span class="close-button-comments">&times;</span>
        <h2>Vos commentaires</h2>
        <div id="user-comments-list">
            <!-- Les commentaires seront chargés ici -->
        </div>
    </div>
</div>

<main>
</main>
<?php include '../includes/footer.php'; ?>

<!-- Vos scripts JavaScript -->
<script src="../script/suggestions.js"></script>
<script src="../script/messagesAjax.js"></script>
<script>
    $(document).ready(function() {
        var validUsername = false;
        var validEmail = false;
        var validPassword = false;
        var validConfirmPassword = false;

        function checkFormValidity() {
            if (validUsername && validEmail && validPassword && validConfirmPassword) {
                $('.btn-register').prop('disabled', false);
            } else {
                $('.btn-register').prop('disabled', true);
            }
        }

        // Validation du nom d'utilisateur avec vérification AJAX
        $('#username').on('input', function() {
            var username = $(this).val();
            if (username.trim() === '') {
                $(this).removeClass('valid').addClass('invalid');
                $('#username-error').text('Le nom d\'utilisateur ne peut pas être vide.');
                validUsername = false;
                checkFormValidity();
            } else {
                // Vérifier si le nom d'utilisateur existe déjà via AJAX
                $.ajax({
                    url: 'check_username.php',
                    method: 'POST',
                    data: { username: username },
                    dataType: 'json',
                    success: function(response) {
                        if (response.exists) {
                            $('#username').removeClass('valid').addClass('invalid');
                            $('#username-error').text('Ce nom d\'utilisateur est déjà pris.');
                            validUsername = false;
                        } else {
                            $('#username').removeClass('invalid').addClass('valid');
                            $('#username-error').text('');
                            validUsername = true;
                        }
                        checkFormValidity();
                    },
                    error: function() {
                        $('#username').removeClass('valid').addClass('invalid');
                        $('#username-error').text('Erreur lors de la vérification du nom d\'utilisateur.');
                        validUsername = false;
                        checkFormValidity();
                    }
                });
            }
        });

        // Validation de l'email avec vérification AJAX
        $('#email').on('input', function() {
            var email = $(this).val();
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.trim() === '') {
                $(this).removeClass('valid').addClass('invalid');
                $('#email-error').text('L\'email ne peut pas être vide.');
                validEmail = false;
                checkFormValidity();
            } else if (!emailPattern.test(email)) {
                $(this).removeClass('valid').addClass('invalid');
                $('#email-error').text('Format d\'email invalide.');
                validEmail = false;
                checkFormValidity();
            } else {
                // Vérifier si l'email existe déjà via AJAX
                $.ajax({
                    url: 'check_email.php',
                    method: 'POST',
                    data: { email: email },
                    dataType: 'json',
                    success: function(response) {
                        if (response.exists) {
                            $('#email').removeClass('valid').addClass('invalid');
                            $('#email-error').text('Cet email est déjà utilisé.');
                            validEmail = false;
                        } else {
                            $('#email').removeClass('invalid').addClass('valid');
                            $('#email-error').text('');
                            validEmail = true;
                        }
                        checkFormValidity();
                    },
                    error: function() {
                        $('#email').removeClass('valid').addClass('invalid');
                        $('#email-error').text('Erreur lors de la vérification de l\'email.');
                        validEmail = false;
                        checkFormValidity();
                    }
                });
            }
        });

        // Validation du mot de passe
        $('#password').on('input', function() {
            var password = $(this).val();
            // Doit contenir au moins une lettre majuscule, une minuscule, un chiffre et un caractère spécial, et au moins 8 caractères
            var passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).{8,}$/;
            if (password.trim() === '') {
                $(this).removeClass('valid').addClass('invalid');
                $('#password-error').text('Le mot de passe ne peut pas être vide.');
                validPassword = false;
            } else if (!passwordPattern.test(password)) {
                $(this).removeClass('valid').addClass('invalid');
                $('#password-error').text('Le mot de passe doit contenir au moins 8 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.');
                validPassword = false;
            } else {
                $(this).removeClass('invalid').addClass('valid');
                $('#password-error').text('');
                validPassword = true;
            }

            // Vérifier la confirmation du mot de passe
            var confirmPassword = $('#confirm_password').val();
            if (confirmPassword !== '') {
                if (password !== confirmPassword) {
                    $('#confirm_password').removeClass('valid').addClass('invalid');
                    $('#confirm-password-error').text('Les mots de passe ne correspondent pas.');
                    validConfirmPassword = false;
                } else {
                    $('#confirm_password').removeClass('invalid').addClass('valid');
                    $('#confirm-password-error').text('');
                    validConfirmPassword = true;
                }
            }
            checkFormValidity();
        });

        // Validation de la confirmation du mot de passe
        $('#confirm_password').on('input', function() {
            var confirmPassword = $(this).val();
            var password = $('#password').val();
            if (confirmPassword.trim() === '') {
                $(this).removeClass('valid').addClass('invalid');
                $('#confirm-password-error').text('Veuillez confirmer votre mot de passe.');
                validConfirmPassword = false;
            } else if (password !== confirmPassword) {
                $(this).removeClass('valid').addClass('invalid');
                $('#confirm-password-error').text('Les mots de passe ne correspondent pas.');
                validConfirmPassword = false;
            } else {
                $(this).removeClass('invalid').addClass('valid');
                $('#confirm-password-error').text('');
                validConfirmPassword = true;
            }
            checkFormValidity();
        });

        // Soumission du formulaire via AJAX
        $('#registration-form').on('submit', function(e) {
            e.preventDefault();
            if (validUsername && validEmail && validPassword && validConfirmPassword) {
                // Récupérer les données du formulaire
                var formData = {
                    username: $('#username').val(),
                    email: $('#email').val(),
                    password: $('#password').val(),
                    confirm_password: $('#confirm_password').val(),
                    csrf_token: $('input[name="csrf_token"]').val()
                };
                // Envoyer les données via AJAX
                $.ajax({
                    url: 'register_user.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#registration-form')[0].reset();
                            $('.input-field').removeClass('valid');
                            $('.btn-register').prop('disabled', true);
                            $('#registration-form').hide();
                            $('#message-container').html('<div class="success-message">' + response.message + '</div>');
                            // Redirection après 1 seconde
                            setTimeout(function() {
                                window.location.href = '../index.php';
                            }, 1000);
                        } else {
                            $('#message-container').html('<div class="error-message">' + response.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#message-container').html('<div class="error-message">Erreur lors de la création du compte.</div>');
                    }
                });
            } else {
                $('#message-container').html('<div class="error-message">Veuillez remplir correctement tous les champs.</div>');
            }
        });
    });

    /**
     * Gestion de la fenêtre modale des commentaires.
     */
        // Modal des commentaires
    const commentsModal = document.getElementById("comments-modal");
    const commentsBtn = document.getElementById("view-comments-button");
    const closeCommentsBtn = document.querySelector(".close-button-comments");

    commentsBtn.addEventListener("click", () => {
        commentsModal.style.display = "block";
        loadUserComments();
    });

    closeCommentsBtn.addEventListener("click", () => {
        commentsModal.style.display = "none";
    });

    window.addEventListener("click", (event) => {
        if (event.target == commentsModal) {
            commentsModal.style.display = "none";
        }
    });

    /**
     * Charge les commentaires de l'utilisateur via une requête AJAX.
     */
    function loadUserComments() {
        fetch('load_user_comments.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('user-comments-list').innerHTML = data;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des commentaires:', error);
                document.getElementById('user-comments-list').innerHTML = '<p>Une erreur est survenue.</p>';
            });
    }

    /**
     * Fonction pour gérer l'ouverture des onglets (Connexion/Inscription).
     *
     * @param {Event} evt Événement de clic.
     * @param {string} tabName Nom de l'onglet à ouvrir.
     */
    // Script pour les onglets
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;

        // Récupérer tous les éléments avec la classe "compte-tab-content" et les cacher
        tabcontent = document.getElementsByClassName("compte-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }

        // Récupérer tous les éléments avec la classe "compte-tab-link" et enlever la classe "active"
        tablinks = document.getElementsByClassName("compte-tab-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        // Afficher le contenu de l'onglet courant et ajouter la classe "active" au bouton qui a été cliqué
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // Par défaut, afficher l'onglet de connexion
    var connexionTab = document.getElementById("connexion");
    if (connexionTab) {
        connexionTab.style.display = "block";
    }

    /**
     * Initialise les suggestions pour le champ de ville favorite.
     */
    // Initialiser les suggestions pour le champ de ville favorite
    initializeSuggestions('favorite-city-input', 'suggestions-list', 'city_name_hidden', 'add-favorite-button');

    document.getElementById('favorite-city-form').addEventListener('submit', function(event) {
        const hiddenInput = document.getElementById('city_name_hidden');
        if (!hiddenInput.value) {
            event.preventDefault();
            alert("Veuillez sélectionner une ville valide dans les suggestions.");
        }
    });

    /**
     * Gestion de la fenêtre modale des demandes.
     */
        // Obtenir les éléments
    var modal = document.getElementById("requests-modal");
    var btn = document.getElementById("view-requests-button");
    var span = document.getElementsByClassName("close-button")[0];

    // Quand l'utilisateur clique sur le bouton, ouvrir la modale
    btn.onclick = function() {
        modal.style.display = "block";
        loadRequests(); // Charger les demandes
    }

    // Quand l'utilisateur clique sur le x, fermer la modale
    span.onclick = function() {
        modal.style.display = "none";
    }

    // Quand l'utilisateur clique en dehors de la modale, la fermer
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    /**
     * Charge les demandes de l'utilisateur via une requête AJAX.
     */
    // Fonction pour charger les demandes via AJAX
    function loadRequests() {
        fetch('load_requests.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('requests-list').innerHTML = data;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des demandes:', error);
                document.getElementById('requests-list').innerHTML = '<p>Une erreur est survenue.</p>';
            });
    }
</script>
</body>
</html>