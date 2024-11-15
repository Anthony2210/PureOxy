<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Actualités sur la Qualité de l'Air</title>
    <link rel="stylesheet" href="../styles/style.css"> <!-- CSS principal -->
    <link rel="stylesheet" href="../styles/includes.css"> <!-- Styles additionnels -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet"> <!-- Police utilisée -->
    
    <!-- CSS spécifique pour l'affichage des articles en grille -->
    <style>
        .articles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* Trois colonnes */
            gap: 20px; /* Espace entre les cartes */
            padding: 20px;
        }

        .article-card {
            background-color: #fff;
            border: 1px solid #6b8e23;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .article-card:hover {
            transform: translateY(-5px);
        }

        .article-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .article-content {
            padding: 15px;
        }

        .article-content h3 {
            font-size: 1.5em;
            color: #4c5f2d;
            margin-bottom: 10px;
        }

        .article-content p {
            color: #6b8e23;
            font-size: 1em;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?> <!-- Inclusion du header -->

<div class="content-wrapper">
    <div class="container">
        <section>
            <h2>Actualités sur la qualité de l'air en France</h2>
            <div class="articles-grid">
                <?php
                include('../libs/simplehtmldom-master/simple_html_dom.php');

                $base_url = "https://www.atmo-france.org/actualites";
                $page = 0;
                $max_pages = 5; // Limite de pages à charger
                $articles_displayed = false;

                while ($page < $max_pages) {
                    $url = $base_url . "?page=" . $page;
                    $html = file_get_html($url);

                    if ($html) {
                        $found_articles = false;
                        foreach ($html->find('article.node') as $article) {
                            $link = $article->find('a.all-block', 0)->href ?? '#';
                            $title = $article->find('span.field--name-title', 0)->plaintext ?? 'Titre non disponible';
                            $date = $article->find('div.c-card-infos', 0)->plaintext ?? 'Date non disponible';
                            $image = $article->find('img', 0)->src ?? '';

                            $detail_link = "detail_article.php?url=" . urlencode("https://www.atmo-france.org" . $link);

                            echo "<div class='article-card'>";
                            echo "<img src='https://www.atmo-france.org$image' alt='$title'>";
                            echo "<div class='article-content'>";
                            echo "<h3><a href='$detail_link'>$title</a></h3>";
                            echo "<p>$date</p>";
                            echo "<p><a href='$detail_link'>Lire la suite</a></p>";
                            echo "</div>";
                            echo "</div>";

                            $found_articles = true;
                        }

                        if (!$found_articles) {
                            break;
                        }

                        $articles_displayed = true;
                        $page++;
                    } else {
                        break;
                    }
                }

                if (!$articles_displayed) {
                    echo "<p>Impossible de charger les actualités pour le moment.</p>";
                }
                ?>
            </div>
        </section>
    </div>
</div>


</body>
</html>
