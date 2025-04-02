<?php
/**
* actualite.php
*
* Ce fichier récupère les actualités sur la qualité de l'air en France depuis le site Atmo-France
* et les affiche sous forme de cartes dans une grille HTML.
*
* Références :
* - simple_html_dom pour le parsing du contenu HTML.
* - ChatGPT pour la structuration de la requête et la sécurisation de l'affichage.
*
* Utilisation :
* - Ce fichier est appelé directement via le navigateur pour afficher la liste d'articles.
*
* Fichier placé dans le dossier pages.
*/
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../libs/simplehtmldom-master/simple_html_dom.php';

$baseUrl = "https://www.atmo-france.org/actualites";
$page = 0;
$maxPages = 5;
$articlesFound = false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Actualités sur la Qualité de l'Air</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Feuilles de styles -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/actualites.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <script src="../script/erreur_formulaire.js"></script>
</head>
<body>
<main>
    <div class="content-wrapper">
        <div class="container">
            <section>
                <h2>Actualités sur la qualité de l'air en France</h2>
                <div class="articles-grid">
                    <?php
                    while ($page < $maxPages) {
                        $url = $baseUrl . "?page=" . $page;
                        $html = file_get_html($url);
                        if ($html) {
                            $articlesOnPage = $html->find('article.node');
                            if (empty($articlesOnPage)) {
                                break;
                            }
                            foreach ($articlesOnPage as $article) {
                                $linkElement = $article->find('a.all-block', 0);
                                $link = $linkElement ? $linkElement->href : '#';
                                $titleElement = $article->find('span.field--name-title', 0);
                                $title = $titleElement ? trim($titleElement->plaintext) : 'Titre non disponible';
                                $dateElement = $article->find('div.c-card-infos', 0);
                                $date = $dateElement ? trim($dateElement->plaintext) : 'Date non disponible';
                                $imageElement = $article->find('img', 0);
                                $image = $imageElement ? $imageElement->src : '';
                                $detailLink = "detail_article.php?url=" . urlencode("https://www.atmo-france.org" . $link);
                                ?>
                                <div class="article-card">
                                    <img src="https://www.atmo-france.org<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="article-content">
                                        <h3>
                                            <a href="<?= htmlspecialchars($detailLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </h3>
                                        <p><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p>
                                            <a href="<?= htmlspecialchars($detailLink, ENT_QUOTES, 'UTF-8'); ?>">Lire la suite</a>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            $articlesFound = true;
                            $page++;
                        } else {
                            break;
                        }
                    }
                    if (!$articlesFound) {
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
