<?php
session_start();
require '../bd/bd.php'; // Connexion à la base de données

// Gestion de la connexion
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Préparation de la requête pour vérifier les informations de connexion
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Vérifie si l'utilisateur existe et si le mot de passe est correct
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: ../index.php"); // Redirige vers la page index.php
        exit;
    } else {
        $login_error = "Nom d'utilisateur ou mot de passe incorrect.";
    }
}
// Gestion de l'inscription
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifie que les mots de passe correspondent
    if ($password === $confirm_password) {
        // Vérifie si le nom d'utilisateur est déjà pris
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $register_error = "Ce nom d'utilisateur est déjà pris.";
        } else {
            // Hashage du mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Photo de profil par défaut
            $profile_picture = 'user.png';

            // Insertion du nouvel utilisateur dans la base de données
            $stmt = $conn->prepare("INSERT INTO users (username, password, profile_picture) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed_password, $profile_picture);

            if ($stmt->execute()) {
                $register_success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                $register_error = "Une erreur s'est produite lors de la création du compte.";
            }
        }
    } else {
        $register_error = "Les mots de passe ne correspondent pas.";
    }
}

// Vérifie si l'utilisateur est connecté pour afficher l'historique des recherches
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Récupère l'historique des recherches de l'utilisateur
    $stmt = $conn->prepare("SELECT search_query, search_date FROM search_history WHERE user_id = ? ORDER BY search_date DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $search_history = $result->fetch_all(MYSQLI_ASSOC);

    // Récupérer les villes favorites
    $stmt = $conn->prepare("SELECT city_name FROM favorite_cities WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cities_result = $stmt->get_result();
    $favorite_cities = $cities_result->fetch_all(MYSQLI_ASSOC);
}
// Effacer l'historique des recherches
if (isset($_POST['clear_history']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    // Rafraîchir la page pour mettre à jour l'affichage
    header("Location: compte.php");
    exit;
}

// Récupérer les informations de l'utilisateur pour afficher la date d'inscription
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Récupérer les détails de l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();

    // Récupérer l'historique des recherches
    $stmt = $conn->prepare("SELECT search_query, search_date FROM search_history WHERE user_id = ? ORDER BY search_date DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $history_result = $stmt->get_result();
    $search_history = $history_result->fetch_all(MYSQLI_ASSOC);
}
// Ajout d'une ville favorite
if (isset($_POST['add_favorite_city']) && isset($_SESSION['user_id'])) {
    $city_name = trim($_POST['city_name']);
    $user_id = $_SESSION['user_id'];

    // Vérifier que le nom de la ville n'est pas vide
    if (!empty($city_name)) {
        // Vérifier que la ville existe dans la base de données
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pollution_villes WHERE City = ?");
        $stmt->bind_param("s", $city_name);
        $stmt->execute();
        $city_exists_result = $stmt->get_result();
        $city_exists_row = $city_exists_result->fetch_assoc();

        if ($city_exists_row['count'] > 0) {
            // Vérifier que la ville n'est pas déjà dans les favoris de l'utilisateur
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ? AND city_name = ?");
            $stmt->bind_param("is", $user_id, $city_name);
            $stmt->execute();
            $city_favorite_result = $stmt->get_result();
            $city_favorite_row = $city_favorite_result->fetch_assoc();

            if ($city_favorite_row['count'] == 0) {
                // Vérifier le nombre actuel de villes favorites
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $count_result = $stmt->get_result();
                $count_row = $count_result->fetch_assoc();

                if ($count_row['count'] < 5) {
                    // Insérer la nouvelle ville favorite
                    $stmt = $conn->prepare("INSERT INTO favorite_cities (user_id, city_name) VALUES (?, ?)");
                    $stmt->bind_param("is", $user_id, $city_name);
                    if ($stmt->execute()) {
                        $success_message_favorite = "Ville favorite ajoutée avec succès.";
                    } else {
                        $error_message_favorite = "Une erreur s'est produite lors de l'ajout de la ville.";
                    }
                } else {
                    $error_message_favorite = "Vous avez atteint le nombre maximum de 5 villes favorites.";
                }
            } else {
                $error_message_favorite = "Cette ville est déjà dans vos favoris.";
            }
        } else {
            $error_message_favorite = "La ville sélectionnée n'est pas valide.";
        }
    } else {
        $error_message_favorite = "Veuillez sélectionner une ville valide.";
    }

    // Rafraîchir la page pour mettre à jour l'affichage
    header("Location: compte.php");
    exit;
}

// Suppression d'une ville favorite
if (isset($_POST['delete_favorite_city']) && isset($_SESSION['user_id'])) {
    $city_name = $_POST['city_name'];
    $user_id = $_SESSION['user_id'];

    // Supprimer la ville favorite
    $stmt = $conn->prepare("DELETE FROM favorite_cities WHERE user_id = ? AND city_name = ?");
    $stmt->bind_param("is", $user_id, $city_name);
    $stmt->execute();

    // Rafraîchir la page pour mettre à jour l'affichage
    header("Location: compte.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Compte - PureOxy</title>
    <!-- Lien Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/includes.css">

</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="compte-container">
    <h1>L’espace Compte</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Tableau de bord de l'utilisateur -->
        <div class="dashboard">

            <!-- Carte de Profil -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <img src="../images/user.png" alt="Photo de profil">
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($_SESSION['username']) ?></h2>
                    <!-- Afficher la date d'inscription si disponible -->
                    <p>Membre depuis <?= isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Date inconnue' ?></p>
                </div>
            </div>

            <!-- Section Villes Favorites -->
            <div class="favorite-cities-section">
                <h3><i class="fas fa-city"></i> Vos villes favorites</h3>
                <?php if (isset($error_message_favorite)): ?>
                    <p class="error-message"><?= htmlspecialchars($error_message_favorite) ?></p>
                <?php endif; ?>
                <?php if (isset($success_message_favorite)): ?>
                    <p class="success-message"><?= htmlspecialchars($success_message_favorite) ?></p>
                <?php endif; ?>


                <?php if (!empty($favorite_cities)): ?>
                    <ul class="favorite-cities-list">
                        <?php foreach ($favorite_cities as $city): ?>
                            <li>
                                <span><?= htmlspecialchars($city['city_name']) ?></span>
                                <!-- Formulaire pour supprimer la ville favorite -->
                                <form method="post" class="delete-city-form">
                                    <input type="hidden" name="city_name" value="<?= htmlspecialchars($city['city_name']) ?>">
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
                    <button type="submit" name="add_favorite_city" id="add-favorite-button" disabled><i class="fas fa-plus"></i> Ajouter</button>
                </form>


            </div>

            <!-- Section Historique des Recherches -->
            <div class="history-section">
                <h3><i class="fas fa-history"></i> Historique des dernières recherches</h3>
                <?php if (!empty($search_history)): ?>
                    <ul class="history-list">
                        <?php foreach ($search_history as $search): ?>
                            <li>
                                <a href="../fonctionnalites/details.php?ville=<?= urlencode($search['search_query']) ?>" class="search-query">
                                    <i class="fas fa-search"></i> <?= htmlspecialchars($search['search_query']) ?>
                                </a>
                                <span class="search-date"><?= date('d/m/Y H:i', strtotime($search['search_date'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <!-- Bouton pour effacer l'historique -->
                    <form method="post" class="clear-history-form">
                        <button type="submit" name="clear_history" class="clear-history-button"><i class="fas fa-trash-alt"></i> Effacer l'historique</button>
                    </form>
                <?php else: ?>
                    <p>Vous n'avez pas encore effectué de recherches.</p>
                <?php endif; ?>
            </div>

            <!-- Bouton de déconnexion -->
            <a href="deconnecter.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Se déconnecter</a>
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
                <?php if (isset($login_error)): ?>
                    <p class="error-message"><?= $login_error ?></p>
                <?php endif; ?>
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
            <form class="compte-form" method="POST">
                <h2>Création d'un nouveau compte</h2>
                <?php if (isset($register_success)): ?>
                    <p class="success-message"><?= $register_success ?></p>
                <?php elseif (isset($register_error)): ?>
                    <p class="error-message"><?= $register_error ?></p>
                <?php endif; ?>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Nom d'utilisateur" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" placeholder="Confirmez le mot de passe" required>
                </div>
                <button type="submit" name="register">S'inscrire</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<main>
</main>
<?php include '../includes/footer.php'; ?>

<!-- Script pour les onglets -->
<script>
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
    document.getElementById("connexion").style.display = "block";
</script>
<script>
    // Initialiser les suggestions pour le champ de ville favorite
    initializeSuggestions('favorite-city-input', 'suggestions-list', 'city_name_hidden', 'add-favorite-button');
</script>
<script>
    document.getElementById('favorite-city-form').addEventListener('submit', function(event) {
        const hiddenInput = document.getElementById('city_name_hidden');
        if (!hiddenInput.value) {
            event.preventDefault();
            alert("Veuillez sélectionner une ville valide dans les suggestions.");
        }
    });
</script>

</body>

</html>

