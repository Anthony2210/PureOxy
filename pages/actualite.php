<?php
/**
 * Page des Actualités sur la Qualité de l'Air
 *
 * Cette page récupère et affiche les actualités concernant la qualité de l'air en France
 * en extrayant les données du site Atmo France. Les articles sont affichés dans une grille
 * avec des liens vers des détails supplémentaires.
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
    <title>PureOxy - Actualités sur la Qualité de l'Air</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page d'actualité -->
    <link rel="stylesheet" href="../styles/articles.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script></head>
<body>
<main>
    <div class="content-wrapper">
        <div class="container">
            <section>
                <h2>Actualités sur la qualité de l'air en France</h2>
                <div class="articles-grid">

                    <?php
                    /**
                     * Inclusion de la bibliothèque Simple HTML DOM pour l'analyse du HTML.
                     *
                     * Cette bibliothèque permet de parcourir et d'extraire des éléments spécifiques à partir du HTML récupéré.
                     */
                    include('../libs/simplehtmldom-master/simple_html_dom.php');

                    /**
                     * URL de base pour les actualités sur la qualité de l'air.
                     */
                    $base_url = "https://www.atmo-france.org/actualites";

                    /**
                     * Variables de contrôle pour la pagination des actualités.
                     */
                    $page = 0;
                    $max_pages = 5; // Limite de pages à charger
                    $articles_displayed = false;

                    /**
                     * Boucle pour parcourir les pages d'actualités jusqu'à la limite définie.
                     */
                    while ($page < $max_pages) {
                        $url = $base_url . "?page=" . $page;
                        $html = file_get_html($url);

                        if ($html) {
                            $found_articles = false;
                            /**
                             * Parcourir chaque article trouvé dans la page.
                             */
                            foreach ($html->find('article.node') as $article) {
                                $link = $article->find('a.all-block', 0)->href ?? '#';
                                $title = $article->find('span.field--name-title', 0)->plaintext ?? 'Titre non disponible';
                                $date = $article->find('div.c-card-infos', 0)->plaintext ?? 'Date non disponible';
                                $image = $article->find('img', 0)->src ?? '';

                                /**
                                 * Générer le lien vers la page de détail de l'article en encodant l'URL source.
                                 */
                                $detail_link = "detail_article.php?url=" . urlencode("https://www.atmo-france.org" . $link);

                                /**
                                 * Afficher l'article dans une carte avec image, titre, date et lien vers les détails.
                                 */
                                echo "<div class='article-card'>";
                                echo "<img src='https://www.atmo-france.org$image' alt='" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "'>";
                                echo "<div class='article-content'>";
                                echo "<h3><a href='" . htmlspecialchars($detail_link, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</a></h3>";
                                echo "<p>" . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . "</p>";
                                echo "<p><a href='" . htmlspecialchars($detail_link, ENT_QUOTES, 'UTF-8') . "'>Lire la suite</a></p>";
                                echo "</div>";
                                echo "</div>";

                                $found_articles = true;
                            }

                            if (!$found_articles) {
                                /**
                                 * Si aucun article n'est trouvé dans la page, arrêter la boucle.
                                 */
                                break;
                            }

                            $articles_displayed = true;
                            $page++;
                        } else {
                            /**
                             * Si la récupération du HTML échoue, arrêter la boucle.
                             */
                            break;
                        }
                    }

                    /**
                     * Afficher un message si aucun article n'a pu être chargé.
                     */
                    if (!$articles_displayed) {
                        echo "<p>Impossible de charger les actualités pour le moment.</p>";
                    }
                    ?>
                </div>
            </section>
        </div>
    </div>
</main>
<!-- Bouton "Revenir vers le haut" -->
<button id="backToTop">Revenir vers le haut</button>
<script src="../script/backtotop.js"></script>

<?php
/**
 * Inclusion du pied de page de la page.
 *
 * Le fichier footer.php contient le pied de page commun à toutes les pages du site, incluant
 * des liens utiles, des informations de contact et d'autres éléments récurrents.
 */
include '../includes/footer.php';
?>
</body>
</html>
