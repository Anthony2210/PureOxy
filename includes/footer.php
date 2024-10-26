<?php
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<footer>
    <div class="container">
        <p>© 2024 PureOxy. Tous droits réservés.</p>
        <nav>
            <ul>
                <li><a href="<?php echo $baseUrl; ?>pages/a-propos.php">À propos</a></li>
                <li><a href="<?php echo $baseUrl; ?>pages/politique-confidentialite.php">Politique de confidentialité</a></li>
                <li><a href="<?php echo $baseUrl; ?>pages/contact.php">Contact</a></li>
            </ul>
        </nav>
    </div>
</footer>