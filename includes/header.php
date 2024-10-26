<?php
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<header>
    <div class="container">
        <div id="logo">
            <a href="<?php echo $baseUrl; ?>index.php">
                <img src="<?php echo $baseUrl; ?>images/logo.png" alt="PureOxy Logo">
            </a>
        </div>
        <nav>
            <ul>
                <li><a href="<?php echo $baseUrl; ?>index.php">Accueil</a></li>
                <li><a href="<?php echo $baseUrl; ?>fonctionnalites/carte.php">Carte interactive</a></li>
                <li><a href="<?php echo $baseUrl; ?>fonctionnalites/recherche.php">Recherche</a></li>
                <li><a href="<?php echo $baseUrl; ?>pages/compte.php">Compte</a></li>
            </ul>
        </nav>
    </div>
</header>