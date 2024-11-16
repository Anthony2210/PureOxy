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
    <title>Détail de l'Article</title>
    <link rel="stylesheet" href="../styles/style.css"> <!-- Lien vers votre fichier CSS principal -->
    <link rel="stylesheet" href="../styles/includes.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet"> <!-- Lien vers la police League Spartan -->


</head>
<body>

<div class="detail-article-wrapper">
    <p>
        <a href="actualite.php" class="button back-button">← Retour aux actualités</a>
    </p>

    <?php include('../libs/simplehtmldom-master/simple_html_dom.php');

    if (isset($_GET['url'])) {
    $url = $_GET['url'];
    $html = file_get_html($url);

    if ($html) {
    // Extraction des informations de l'article
    $title = $html->find('h1', 0)->plaintext ?? 'Titre non disponible';
    $date = $html->find('p.text-muted', 0)->plaintext ?? 'Date non disponible';
    $image = $html->find('img.image-style-top-page-banner', 0)->src ?? '';

    $char_limit = 3000;
    $current_length = 0;
    $content = '';

    // Parcourir chaque élément dans la section de contenu
    foreach ($html->find('div.field--name-field-text-content .field__item *') as $element) {
    // Ajouter le texte HTML de l'élément (y compris les balises) tant qu'on n'a pas atteint la limite
    $element_text = $element->outertext;
    $element_length = strlen(strip_tags($element_text));

    // Vérifier si l'ajout de cet élément dépasse la limite
    if ($current_length + $element_length > $char_limit) {
    $remaining_chars = $char_limit - $current_length;
    $element_text = mb_substr(strip_tags($element_text), 0, $remaining_chars) . '...';
    $content .= "<p>$element_text</p>";
    break;
    }

    // Ajouter l'élément complet au contenu
    $content .= $element_text;
    $current_length += $element_length;
    }

    // Afficher les informations extraites
    echo "<h2>$title</h2>";
    echo "<p><em>$date</em></p>";
    if ($image) {
    echo "<img src='https://www.atmo-france.org$image' alt='$title'>";
    }
    echo "<div class='detail-article-content'>$content</div>";
    echo "<p><a href='$url' target='_blank' class='button'>Lire l'article complet sur le site source</a></p>";
    } else {
    echo "<p>Impossible de charger le contenu de l'article.</p>";
    }
    } else {
    echo "<p>Aucun article sélectionné.</p>";
    }
    ?>


</div>

<?php include '../includes/footer.php'; ?> <!-- Inclusion du footer -->

</body>
</html>
