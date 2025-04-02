<?php
/**
 * header.php
 *
 * En-tête du site PureOxy.
 * Ce fichier gère l'affichage du logo, du menu principal, et du composant chatbot.
 * Il construit dynamiquement l'URL de base pour générer correctement les liens.
 *
 * Références :
 * - ChatGPT pour la structuration et la documentation du code.
 *
 * Utilisation :
 * - Inclure ce fichier dans les pages du site pour afficher l'en-tête commun.
 *
 * Fichier placé dans le dossier includes.
 */

// Construction de l'URL de base à partir du schéma et de l'hôte de la requête actuelle
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<header>
    <div id="header" class="container">
        <!-- Logo du site avec lien vers la page d'accueil -->
        <div id="logo">
            <a href="<?php echo $baseUrl; ?>index.php">
                <img src="<?php echo $baseUrl; ?>images/logo.png" alt="PureOxy Logo">
            </a>
        </div>
        <!-- Menu de navigation principal -->
        <nav>
            <ul>
                <!-- Lien vers la page d'accueil -->
                <li>
                    <a href="<?php echo $baseUrl; ?>index.php">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                </li>

                <!-- Lien vers la page des actualités -->
                <li>
                    <a href="<?php echo $baseUrl; ?>pages/actualite.php">
                        <i class="far fa-newspaper"></i> Actualités
                    </a>
                </li>

                <!-- Menu déroulant pour accéder aux pages de données -->
                <li class="dropdown">
                    <a href="javascript:void(0)">
                        <i class="fas fa-database"></i> Données <i class="fas fa-caret-down"></i>
                    </a>
                    <ul class="dropdown-content">
                        <li>
                            <a href="<?php echo $baseUrl; ?>pages/carte.php">
                                <i class="fas fa-map"></i> Carte
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>pages/recherche.php">
                                <i class="fas fa-search"></i> Recherche
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>pages/classement.php">
                                <i class="fas fa-chart-bar"></i> Classement
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Affichage du menu Compte ou du nom d'utilisateur s'il est connecté -->
                <?php if (isset($_SESSION['username'])): ?>
                    <li class="dropdown">
                        <a href="javascript:void(0)">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-caret-down"></i>
                        </a>
                        <ul class="dropdown-content">
                            <li>
                                <a href="<?php echo $baseUrl; ?>pages/compte.php">
                                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo $baseUrl; ?>fonctionnalites/deconnecter.php" style="color: red;">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo $baseUrl; ?>pages/compte.php">
                            <i class="fas fa-user"></i> Mon compte
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Composant Chatbot -->
    <div id="chatbot-container">
        <!-- Texte visible en mode minimisé -->
        <span id="chatbot-toggle-text">Chat</span>

        <!-- Contenu complet du chatbot en mode étendu -->
        <div id="chatbot-content">
            <div id="chatbot-header">
                PureOxy Chatbot
                <button id="chatbot-close">✕</button>
            </div>
            <div id="chatbot-messages"></div>
            <input type="text" id="chatbot-input" placeholder="Pose-moi une question !">
        </div>
    </div>

    <!-- Inclusion des styles et scripts pour le chatbot et les icônes -->
    <link rel="stylesheet" href="../styles/chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="../script/chatbot.js" defer></script>
</header>
