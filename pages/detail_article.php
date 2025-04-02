<?php
/**
* detail_article.php
*
* Ce fichier récupère et affiche en détail un article sur la qualité de l'air depuis le site Atmo-France.
* Il valide l'URL passée en paramètre et extrait le contenu HTML pertinent (titre, date, image et texte).
*
* Références :
* - simple_html_dom pour le parsing du contenu HTML.
* - ChatGPT pour la structuration de la requête et la sécurisation de l'affichage.
*
* Utilisation :
* - Ce fichier est appelé lorsqu'un utilisateur clique sur un article dans la page actualite.php.
*
* Fichier placé dans le dossier pages.
*/
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../libs/simplehtmldom-master/simple_html_dom.php';

// Récupération et validation de l'URL passée en paramètre
$urlParam = $_GET['url'] ?? '';
if (!filter_var($urlParam, FILTER_VALIDATE_URL) || strpos($urlParam, "https://www.atmo-france.org") !== 0) {
echo "<p>URL invalide.</p>";
exit;
}

$html = file_get_html($urlParam);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de l'Article</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Feuilles de styles -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Style spécifique pour le détail d'article -->
    <link rel="stylesheet" href="../styles/actualites.css">
    <script src="../script/erreur_formulaire.js"></script>
</head>
<body>
<div class="detail-article-wrapper">
    <a href="actualite.php" class="back-button">← Retour aux actualités</a>
    <?php
    if ($html) {
        // Extraction du titre
        $titleElement = $html->find('h1', 0);
        $title = $titleElement ? trim($titleElement->plaintext) : 'Titre non disponible';
        // Extraction de la date
        $dateElement = $html->find('p.text-muted', 0);
        $date = $dateElement ? trim($dateElement->plaintext) : 'Date non disponible';
        // Extraction de l'image principale
        $imageElement = $html->find('img.image-style-top-page-banner', 0);
        $image = $imageElement ? $imageElement->src : '';

        // Extraction du contenu principal
        $contentContainer = $html->find('div.field--name-field-text-content .field__item', 0);
        $blocks = [];
        if ($contentContainer) {
            foreach ($contentContainer->find('p, h2, h3, h4, li') as $block) {
                $blockText = trim($block->plaintext);
                $blockHtml = trim($block->outertext);
                if (strlen($blockText) > 20 && !in_array($blockText, array_map('strip_tags', $blocks))) {
                    $blocks[] = $blockHtml;
                }
            }
        }

        $maxChars = 3000;
        $finalContent = '';
        $currentLength = 0;
        $truncated = false;
        foreach ($blocks as $blockHtml) {
            $plainText = strip_tags($blockHtml);
            $blockLength = mb_strlen($plainText);
            if ($currentLength + $blockLength > $maxChars) {
                $truncated = true;
                break;
            } else {
                $finalContent .= $blockHtml;
                $currentLength += $blockLength;
            }
        }
        if ($truncated) {
            $finalContent .= '<p>...</p>';
        }
        ?>
        <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="date"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($image): ?>
            <img src="https://www.atmo-france.org<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="detail-article-content">
            <?= $finalContent; ?>
        </div>
        <p>
            <a href="<?= htmlspecialchars($urlParam, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="button">
                Lire l'article complet sur le site source
            </a>
        </p>
        <?php
    } else {
        echo "<p>Impossible de charger le contenu de l'article.</p>";
    }
    ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
