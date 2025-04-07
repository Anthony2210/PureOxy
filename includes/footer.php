<?php
/**
 * footer.php
 *
 * Pied de page du site PureOxy.
 * Ce fichier affiche les informations légales et fournit des liens vers les pages "À propos",
 * "Politique de confidentialité" et "Contact".
 *
 * Références :
 * - ChatGPT pour la structuration et la documentation du code.
 *
 * Utilisation :
 * - Inclure ce fichier dans les pages du site pour afficher le pied de page commun.
 *
 * Fichier placé dans le dossier includes.
 */

// Construction de l'URL de base à partir du schéma et de l'hôte de la requête actuelle
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>

<footer>
    <div id="footer" class="container">
        <!-- Informations de copyright -->
        <p>© 2024 PureOxy. Tous droits réservés.</p>
        <!-- Menu de navigation secondaire -->
        <nav>
            <ul>
                <li><a href="<?php echo $baseUrl; ?>pages/a-propos.php">À propos</a></li>
                <li><a href="<?php echo $baseUrl; ?>pages/politique_confidentialite.php">Politique de confidentialité</a></li>
                <li><a href="<?php echo $baseUrl; ?>pages/contact.php">Contact</a></li>
            </ul>
        </nav>
    </div>
</footer>
