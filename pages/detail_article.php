<?php
/**
 * Détail de l'Article
 *
 * Cette page affiche les détails d'un article spécifique en extrayant les données depuis le site source.
 * Elle limite la longueur du contenu affiché et fournit un lien vers l'article complet sur le site source.
 *
 * @package PureOxy
 * @subpackage Actualités
 * @version 1.0
 * @since 2024-04-27
 */

session_start();
ob_start();

/**
 * Inclusion de l'en-tête de la page.
 *
 * Le fichier header.php contient l'en-tête commun à toutes les pages du site, incluant le logo,
 * le menu de navigation et éventuellement d'autres éléments récurrents.
 */
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de l'Article</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page des articles de presses -->
    <link rel="stylesheet" href="../styles/actualites.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script></head>
<body>

<div class="detail-article-wrapper">
    <p>
        <a href="actualite.php" class="button back-button">← Retour aux actualités</a>
    </p>

    <?php
    /**
     * Inclusion de la bibliothèque Simple HTML DOM pour l'analyse du HTML.
     *
     * Cette bibliothèque permet de parcourir et d'extraire des éléments spécifiques à partir du HTML récupéré.
     */
    include('../libs/simplehtmldom-master/simple_html_dom.php');

    /**
     * Vérifier si l'URL de l'article est passée en paramètre GET.
     */
    if (isset($_GET['url'])) {
        $url = $_GET['url'];
        $html = file_get_html($url);

        if ($html) {
            /**
             * Extraction des informations de l'article.
             */
            $title = $html->find('h1', 0)->plaintext ?? 'Titre non disponible';
            $date = $html->find('p.text-muted', 0)->plaintext ?? 'Date non disponible';
            $image = $html->find('img.image-style-top-page-banner', 0)->src ?? '';

            /**
             * Limitation de la longueur du contenu affiché.
             */
            $char_limit = 3000;
            $current_length = 0;
            $content = '';

            /**
             * Parcourir chaque élément dans la section de contenu.
             */
            foreach ($html->find('div.field--name-field-text-content .field__item *') as $element) {
                // Ajouter le texte HTML de l'élément (y compris les balises) tant qu'on n'a pas atteint la limite
                $element_text = $element->outertext;
                $element_length = strlen(strip_tags($element_text));

                // Vérifier si l'ajout de cet élément dépasse la limite
                if ($current_length + $element_length > $char_limit) {
                    $remaining_chars = $char_limit - $current_length;
                    $element_text = mb_substr(strip_tags($element_text), 0, $remaining_chars) . '...';
                    $content .= "<p>" . htmlspecialchars($element_text, ENT_QUOTES, 'UTF-8') . "</p>";
                    break;
                }

                // Ajouter l'élément complet au contenu
                $content .= $element_text;
                $current_length += $element_length;
            }

            /**
             * Afficher les informations extraites.
             */
            echo "<h2>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>";
            echo "<p><em>" . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . "</em></p>";
            if ($image) {
                echo "<img src='https://www.atmo-france.org" . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "'>";
            }
            echo "<div class='detail-article-content'>" . $content . "</div>";
            echo "<p><a href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' target='_blank' class='button'>Lire l'article complet sur le site source</a></p>";
        } else {
            /**
             * Afficher un message si le contenu de l'article n'a pas pu être chargé.
             */
            echo "<p>Impossible de charger le contenu de l'article.</p>";
        }
    } else {
        /**
         * Afficher un message si aucun article n'est sélectionné.
         */
        echo "<p>Aucun article sélectionné.</p>";
    }
    ?>

</div>

<?php
/**
 * Inclusion du pied de page de la page.
 *
 * Le fichier footer.php contient le pied de page commun à toutes les pages du site, incluant
 * des liens utiles, des informations de contact et d'autres éléments récurrents.
 */
include '../includes/footer.php'; // Inclusion du footer
?>
</body>
</html>
