<?php
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<header>
    <div class="container">
        <div id="logo">
            <h1><a href="<?php echo $baseUrl; ?>index.php">PureOxy</a></h1>
        </div>
        <nav>
            <ul>
                <li><a href="<?php echo $baseUrl; ?>index.php">Accueil</a></li>
                <li><a href="<?php echo $baseUrl; ?>carte.php">Carte interactive</a></li>
                <li><a href="<?php echo $baseUrl; ?>recherche.php">Recherche</a></li>
                <li><a href="<?php echo $baseUrl; ?>a-propos.php">À propos</a></li>
                <li><a href="<?php echo $baseUrl; ?>contact.php">Contact</a></li>
            </ul>
        </nav>
    </div>
</header>