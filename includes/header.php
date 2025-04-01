<?php
/**
 * header.php
 *
 * En-tête du site PureOxy : logo, menu principal, chat.
 */

$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<header>
    <div id="header" class="container">
        <div id="logo">
            <a href="<?php echo $baseUrl; ?>index.php">
                <img src="<?php echo $baseUrl; ?>images/logo.png" alt="PureOxy Logo">
            </a>
        </div>
        <nav>
            <ul>
                <!-- Accueil -->
                <li>
                    <a href="<?php echo $baseUrl; ?>index.php">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                </li>

                <!-- Actualités -->
                <li>
                    <a href="<?php echo $baseUrl; ?>pages/actualite.php">
                        <i class="far fa-newspaper"></i> Actualités
                    </a>
                </li>

                <!-- Données (menu déroulant) -->
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

                <!-- Compte (ou nom utilisateur si connecté) -->
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
            <ul>
        </nav>
    </div>

    <!-- Chatbot -->
    <div id="chatbot-container">
        <!-- Texte visible en mode minimisé -->
        <span id="chatbot-toggle-text">Chat</span>

        <!-- Contenu de la chatbox (affiché en mode étendu) -->
        <div id="chatbot-content">
            <div id="chatbot-header">
                PureOxy Chatbot
                <button id="chatbot-close">✕</button>
            </div>
            <div id="chatbot-messages"></div>
            <input type="text" id="chatbot-input" placeholder="Pose-moi une question !">
        </div>
    </div>

    <!-- Lien vers le CSS du chatbot -->
    <link rel="stylesheet" href="../styles/chatbot.css">
    <!-- Font Awesome (icônes) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Script du chatbot -->
    <script src="../script/chatbot.js" defer></script>
</header>
