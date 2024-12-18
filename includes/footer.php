<?php
/**
 * footer.php
 *
 * Ce fichier contient le code HTML pour le pied de page du site PureOxy.
 * Il inclut des liens vers les pages importantes telles que "À propos", "Politique de confidentialité" et "Contact".
 *
 */

$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<footer>
    <div id="footer" class="container">
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
