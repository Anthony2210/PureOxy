<?php
/**
 * header.php
 *
 * Ce fichier contient le code HTML pour l'en-tête du site PureOxy.
 * Il inclut le logo, le menu de navigation principal et gère l'affichage conditionnel du nom de l'utilisateur si connecté.
 *
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
                <li><a href="<?php echo $baseUrl; ?>pages/actualite.php">Actualité</a></li>
                <li><a href="<?php echo $baseUrl; ?>fonctionnalites/carte.php">Carte interactive</a></li>
                <li><a href="<?php echo $baseUrl; ?>fonctionnalites/recherche.php">Recherche</a></li>

                <!-- Vérifie si l'utilisateur est connecté pour afficher son nom au lieu de "Compte" -->
                <?php if (isset($_SESSION['username'])): ?>
                    <li><a href="<?php echo $baseUrl; ?>pages/compte.php"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <?php else: ?>
                    <li><a href="<?php echo $baseUrl; ?>pages/compte.php">Compte</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
