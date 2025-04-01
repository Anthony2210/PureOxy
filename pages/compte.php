<?php
/**
 * Compte.php
 *
 * Gestion de la session utilisateur, connexion, inscription,
 * gestion des villes favorites et historique des recherches.
 */

session_start();
ob_start();

// Génération d'un jeton CSRF si non déjà défini.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require '../bd/bd.php';
$db = new Database();

/**
 * Gestion de la connexion de l'utilisateur.
 */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $login_error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            // Vérification du mot de passe et connexion
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['id_users'] = $user['id_users'];
                $_SESSION['username'] = $user['username'];
                header("Location: ../index.php");
                exit;
            } else {
                $login_error = "Nom d'utilisateur ou mot de passe incorrect.";
            }
        } catch (Exception $e) {
            $login_error = "Erreur lors de la connexion : " . $e->getMessage();
        }
    }
}

/**
 * Gestion de l'inscription de l'utilisateur.
 */
if (isset($_POST['register'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $register_error = "Jeton CSRF invalide.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

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
            try {
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $register_error = "Ce nom d'utilisateur ou email est déjà pris.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $profile_picture = 'user.png';

                    $stmt = $db->prepare("INSERT INTO users (username, email, password, profile_picture) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $profile_picture);

                    if ($stmt->execute()) {
                        $register_success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
                    } else {
                        $register_error = "Erreur lors de l'insertion : " . $stmt->error;
                    }
                }
            } catch (Exception $e) {
                $register_error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

/**
 * Récupération des données pour l'utilisateur connecté.
 */
if (isset($_SESSION['id_users'])) {
    $id_users = $_SESSION['id_users'];

    // Récupérer les 7 dernières recherches
    $stmt = $db->prepare("
        SELECT dv.ville AS search_query, sh.search_date
        FROM search_history sh
        JOIN donnees_villes dv ON sh.id_ville = dv.id_ville
        WHERE sh.id_users = ?
        ORDER BY sh.search_date DESC
        LIMIT 7
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id_users);
        $stmt->execute();
        $result = $stmt->get_result();
        $search_history = $result->fetch_all(MYSQLI_ASSOC);

        // Suppression des recherches plus anciennes
        $stmt = $db->prepare("
            DELETE FROM search_history
            WHERE id_users = ?
              AND search_date NOT IN (
                  SELECT search_date
                  FROM (
                      SELECT search_date
                      FROM search_history
                      WHERE id_users = ?
                      ORDER BY search_date DESC
                      LIMIT 7
                  ) AS sub
              )
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $id_users, $id_users);
            $stmt->execute();
        }
    }

    // Récupérer les villes favorites
    $stmt = $db->prepare("
        SELECT dv.ville AS city_name
        FROM favorite_cities fc
        JOIN donnees_villes dv ON fc.id_ville = dv.id_ville
        WHERE fc.id_users = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id_users);
        $stmt->execute();
        $cities_result = $stmt->get_result();
        $favorite_cities = $cities_result->fetch_all(MYSQLI_ASSOC);
    }

    // Récupérer les détails de l'utilisateur
    $stmt = $db->prepare("SELECT * FROM users WHERE id_users = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_users);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user = $user_result->fetch_assoc();
    }

    // Récupérer l'historique des recherches (10 dernières)
    $stmt = $db->prepare("
        SELECT dv.ville AS search_query, sh.search_date
        FROM search_history sh
        JOIN donnees_villes dv ON sh.id_ville = dv.id_ville
        WHERE sh.id_users = ?
        ORDER BY sh.search_date DESC
        LIMIT 10
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id_users);
        $stmt->execute();
        $history_result = $stmt->get_result();
        $search_history = $history_result->fetch_all(MYSQLI_ASSOC);
    }
}

/**
 * Ajout d'une ville favorite pour l'utilisateur connecté.
 */
if (isset($_POST['add_favorite_city']) && isset($_SESSION['id_users'])) {
    $city_name = trim($_POST['city_name']);
    $id_users = $_SESSION['id_users'];

    if (!empty($city_name)) {
        $stmt = $db->prepare("SELECT id_ville FROM donnees_villes WHERE ville = ? LIMIT 1");
        if (!$stmt) {
            $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
        } else {
            $stmt->bind_param("s", $city_name);
            $stmt->execute();
            $city_exists_result = $stmt->get_result();
            $city_row = $city_exists_result->fetch_assoc();

            if ($city_row) {
                $idVille = $city_row['id_ville'];
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE id_users = ? AND id_ville = ?");
                if (!$stmt) {
                    $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
                } else {
                    $stmt->bind_param("ii", $id_users, $idVille);
                    $stmt->execute();
                    $city_favorite_result = $stmt->get_result();
                    $city_favorite_row = $city_favorite_result->fetch_assoc();

                    if ($city_favorite_row['count'] == 0) {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE id_users = ?");
                        if (!$stmt) {
                            $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
                        } else {
                            $stmt->bind_param("i", $id_users);
                            $stmt->execute();
                            $count_result = $stmt->get_result();
                            $count_row = $count_result->fetch_assoc();

                            if ($count_row['count'] < 5) {
                                $stmt = $db->prepare("INSERT INTO favorite_cities (id_users, id_ville) VALUES (?, ?)");
                                if (!$stmt) {
                                    $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
                                } else {
                                    $stmt->bind_param("ii", $id_users, $idVille);
                                    if ($stmt->execute()) {
                                        $response = ['success' => true, 'message' => "Ville favorite ajoutée avec succès.", 'city_name' => $city_name];
                                    } else {
                                        $response = ['success' => false, 'message' => "Erreur lors de l'ajout: " . $stmt->error];
                                    }
                                }
                            } else {
                                $response = ['success' => false, 'message' => "Vous avez atteint le nombre maximum de 5 villes favorites."];
                            }
                        }
                    } else {
                        $response = ['success' => false, 'message' => "Cette ville est déjà dans vos favoris."];
                    }
                }
            } else {
                $response = ['success' => false, 'message' => "La ville sélectionnée n'est pas valide."];
            }
        }
    } else {
        $response = ['success' => false, 'message' => "Veuillez sélectionner une ville valide."];
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        ini_set('display_errors', 0);
        error_reporting(0);
        if (ob_get_length()) { ob_end_clean(); }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        if ($response['success']) {
            $success_message_favorite = $response['message'];
        } else {
            $error_message_favorite = $response['message'];
        }
    }
}

/**
 * Suppression d'une recherche individuelle dans l'historique de l'utilisateur.
 */
if (isset($_POST['delete_search']) && isset($_SESSION['id_users'])) {
    $search_query = $_POST['search_query'];
    $id_users = $_SESSION['id_users'];

    $stmt = $db->prepare("
        DELETE FROM search_history
        WHERE id_users = ?
          AND id_ville = (
              SELECT id_ville
              FROM donnees_villes
              WHERE ville = ?
              LIMIT 1
          )
    ");
    if (!$stmt) {
        $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
    } else {
        $stmt->bind_param("is", $id_users, $search_query);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => "Recherche supprimée avec succès.", 'search_query' => $search_query];
        } else {
            $response = ['success' => false, 'message' => "Erreur lors de la suppression: " . $stmt->error];
        }
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        header("Location: compte.php");
        exit;
    }
}

/**
 * Efface l'historique des recherches de l'utilisateur.
 */
if (isset($_POST['clear_history']) && isset($_SESSION['id_users'])) {
    $id_users = $_SESSION['id_users'];
    $stmt = $db->prepare("DELETE FROM search_history WHERE id_users = ?");
    if (!$stmt) {
        $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
    } else {
        $stmt->bind_param("i", $id_users);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => "Historique effacé avec succès."];
        } else {
            $response = ['success' => false, 'message' => "Erreur lors de l'effacement: " . $stmt->error];
        }
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        header("Location: compte.php");
        exit;
    }
}
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        // Calcul du nombre de semaines et jours restants
        $w = floor($diff->d / 7);
        $d = $diff->d - $w * 7;

        $diffArray = [
            'y' => $diff->y,
            'm' => $diff->m,
            'w' => $w,
            'd' => $d,
            'h' => $diff->h,
            'i' => $diff->i,
            's' => $diff->s
        ];

        $string = [
            'y' => 'an',
            'm' => 'mois',
            'w' => 'semaine',
            'd' => 'jour',
            'h' => 'h',
            'i' => 'min',
            's' => 'sec'
        ];
        foreach ($string as $k => &$v) {
            if ($diffArray[$k]) {
                $v = $diffArray[$k] . ' ' . $v . ($diffArray[$k] > 1 && $k !== 'h' ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

/**
 * Suppression d'une ville favorite de l'utilisateur.
 */
if (isset($_POST['delete_favorite_city']) && isset($_SESSION['id_users'])) {
    $city_name = $_POST['city_name'];
    $id_users = $_SESSION['id_users'];

    $stmt = $db->prepare("
        DELETE FROM favorite_cities
        WHERE id_users = ?
          AND id_ville = (
              SELECT id_ville
              FROM donnees_villes
              WHERE ville = ?
              LIMIT 1
          )
    ");
    if (!$stmt) {
        $response = ['success' => false, 'message' => "Préparation échouée: " . $db->getConnection()->error];
    } else {
        $stmt->bind_param("is", $id_users, $city_name);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => "Ville favorite supprimée avec succès.", 'city_name' => $city_name];
        } else {
            $response = ['success' => false, 'message' => "Erreur lors de la suppression: " . $stmt->error];
        }
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
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
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/recherche.css">
    <link rel="stylesheet" href="../styles/compte.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <link rel="stylesheet" href="../styles/messages.css">
    <script src="../script/erreur_formulaire.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div id="message-container">
    <?php
    if (isset($login_error)) {
        echo '<div class="error-message">' . htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (isset($register_error)) {
        echo '<div class="error-message">' . htmlspecialchars($register_error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (isset($register_success)) {
        echo '<div class="success-message">' . htmlspecialchars($register_success, ENT_QUOTES, 'UTF-8') . '</div>';
    }
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
    <?php if (isset($_SESSION['id_users'])): ?>
        <div class="dashboard">
            <div class="profile-card">
                <div class="profile-avatar">
                    <img src="../images/user.png" alt="Photo de profil">
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p>Membre depuis <?= isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Date inconnue' ?></p>
                    <a href="../fonctionnalites/deconnecter.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Se déconnecter</a>
                </div>
            </div>
            <div class="dashboard-content">
                <div class="favorite-cities-section">
                    <h3><i class="fas fa-city"></i> Vos villes favorites</h3>
                    <?php if (!empty($favorite_cities)): ?>
                        <ul class="favorite-cities-list">
                            <?php foreach ($favorite_cities as $city): ?>
                                <li>
                                    <a href="../fonctionnalites/details.php?ville=<?= urlencode($city['city_name']) ?>" class="favorite-link">
                                        <?= htmlspecialchars($city['city_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <form method="post" class="delete-city-form">
                                        <input type="hidden" name="city_name" value="<?= htmlspecialchars($city['city_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="delete_favorite_city"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Vous n'avez pas encore ajouté de villes favorites.</p>
                    <?php endif; ?>

                    <form method="post" class="favorite-city-form" id="favorite-city-form">
                        <input type="text" id="favorite-city-input" placeholder="Entrez le nom d'une ville" autocomplete="off" required>
                        <input type="hidden" name="city_name" id="city_name_hidden">
                        <ul id="suggestions-list"></ul>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" name="add_favorite_city" id="add-favorite-button" disabled><i class="fas fa-plus"></i> Ajouter</button>
                    </form>
                </div>
                <div class="history-section">
                    <h3><i class="fas fa-history"></i>Historique des dernières recherches</h3>
                    <?php if (!empty($search_history)): ?>
                        <ul class="history-list">
                            <?php foreach ($search_history as $search): ?>
                                <li>
                                    <a href="../fonctionnalites/details.php?ville=<?= urlencode($search['search_query']) ?>" class="search-query">
                                        <i class="fas fa-search"></i> <?= htmlspecialchars($search['search_query'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span class="search-date"><?= date('d/m/Y H:i', strtotime($search['search_date'])) ?></span>
                                    <form method="post" class="delete-search-form">
                                        <input type="hidden" name="search_query" value="<?= htmlspecialchars($search['search_query'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="delete_search"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="post" class="clear-history-form" id="clear-history-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" name="clear_history" class="clear-history-button"><i class="fas fa-trash-alt"></i> Effacer l'historique</button>
                        </form>
                    <?php else: ?>
                        <p>Vous n'avez pas encore effectué de recherches.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="user-comments-section">
            <h3><i class="fa-solid fa-comment"></i> Mes derniers commentaires</h3>
            <?php
            // Récupération des 5 derniers commentaires postés par l'utilisateur connecté
            $stmtComments = $db->prepare("SELECT c.*, dv.ville AS ville 
            FROM commentaires c 
            JOIN donnees_villes dv ON c.id_ville = dv.id_ville 
            WHERE c.id_users = ? 
            ORDER BY c.created_at DESC 
            LIMIT 5");
            $stmtComments->bind_param("i", $id_users);
            $stmtComments->execute();
            $resultComments = $stmtComments->get_result();
            $user_comments = $resultComments->fetch_all(MYSQLI_ASSOC);
            $stmtComments->close();

            if (!empty($user_comments)) {
                echo "<ul class='user-comments-list'>";
                foreach ($user_comments as $comment) {
                    echo "<li>";
                    // Lien vers la page détails pour la ville concernée
                    echo "<a href='../fonctionnalites/details.php?ville=" . urlencode($comment['ville']) . "'>" . htmlspecialchars($comment['ville']) . "</a>";
                    // Affichage du contenu du commentaire
                    echo "<div class='comment-content'>" . htmlspecialchars($comment['content']) . "</div>";
                    // Affichage de la date au format relatif
                    echo "<div class='comment-date'><i class='fa-solid fa-clock'></i> " . time_elapsed_string($comment['created_at']) . "</div>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>Vous n'avez encore posté aucun commentaire.</p>";
            }
            ?>
        </div>
    <?php else: ?>
        <div class="compte-tabs">
            <button class="compte-tab-link active" onclick="openTab(event, 'connexion')">
                <i class="fas fa-sign-in-alt"></i> Connexion
            </button>
            <button class="compte-tab-link" onclick="openTab(event, 'inscription')">
                <i class="fas fa-user-plus"></i> Inscription
            </button>
        </div>

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

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="register" class="btn-register" disabled>S'inscrire</button>
            </form>
        </div>

    <?php endif; ?>
</div>


<main></main>
<?php include '../includes/footer.php'; ?>

<!-- Inclusion des scripts externes -->
<script src="../script/suggestions.js"></script>
<script src="../script/compte.js"></script>
<script src="../script/favorites.js"></script>
<script src="../script/searchHistory.js"></script>
</body>
</html>
