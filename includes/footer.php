<?php
/**
 * footer.php
 *
 * Pied de page du site PureOxy.
 */

// Base URL dynamique depuis la racine du domaine (ex : https://pureoxy.rf.gd/)
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/';
?>

<footer>
    <div id="footer" class="container">
        <!-- Informations de copyright -->
        <p>© 2024 PureOxy. Tous droits réservés.</p>

        <!-- Menu de navigation secondaire -->
        <nav>
            <ul>
                <li><a href="<?= $baseUrl ?>pages/a-propos.php">À propos</a></li>
                <li><a href="<?= $baseUrl ?>pages/politique_confidentialite.php">Politique de confidentialité</a></li>
                <li><a href="<?= $baseUrl ?>pages/contact.php">Contact</a></li>
            </ul>
        </nav>
    </div>
</footer>
