<?php
session_start();
ob_start();
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Actualités sur la Qualité de l'Air</title>
    <link rel="stylesheet" href="../styles/style.css"> <!-- CSS principal -->
    <link rel="stylesheet" href="../styles/includes.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet"> <!-- Police utilisée -->
</head>
<body>
<main>
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
</main>
<?php
include '../includes/footer.php';
?>
</body>
</html>

