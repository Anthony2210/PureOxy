<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de l'Article</title>
    <link rel="stylesheet" href="../styles/style.css"> <!-- Lien vers votre fichier CSS principal -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet"> <!-- Lien vers la police League Spartan -->

    <!-- Styles spécifiques pour la page de détail d'article -->
    <style>
        /* Conteneur spécifique de l'article pour éviter les conflits avec les autres styles */
        .detail-article-wrapper {
            width: 100%;
            max-width: 1200px; /* Largeur similaire à celle des autres pages */
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Titre principal de l'article */
        .detail-article-wrapper h2 {
            font-size: 2em;
            color: #4c5f2d;
            margin-bottom: 20px; /* Espacement plus grand */
        }

        /* Image de l'article */
        .detail-article-wrapper img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        /* Contenu de l'article */
        .detail-article-content {
            font-size: 1.1em;
            color: #333;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* Boutons "Retour" et "Lire la suite" */
        .detail-article-wrapper .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6b8e23;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
            margin-bottom: 15px; /* Un peu d'espace sous les boutons */
        }

        .detail-article-wrapper .button:hover {
            background-color: #4c5f2d;
        }

        /* Style pour les retours à la page précédente */
        .back-button {
            margin-bottom: 20px; /* Un peu plus d'espace en bas */
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?> <!-- Inclusion du header -->

<div class="detail-article-wrapper">
    <p>
        <a href="actualite.php" class="button back-button">← Retour aux actualités</a>
    </p>

    <?php
    include('../libs/simplehtmldom-master/simple_html_dom.php');

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
