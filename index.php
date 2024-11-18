<?php
/**
 * Page d'Accueil de PureOxy
 *
 * Cette page sert de point d'entrée principal pour les utilisateurs visitant le site PureOxy.
 * Elle présente une introduction à la plateforme, ses fonctionnalités principales, et incite les utilisateurs à commencer leur exploration.
 *
 * @package PureOxy
 * @subpackage Pages
 * @version 1.0
 * @since 2024-04-27
 */

session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Respirez mieux, vivez mieux</title>
    <link rel="stylesheet" href="styles/style.css"> <!-- CSS principal -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet"> <!-- Police utilisée -->
    <link rel="stylesheet" href="styles/includes.css"> <!-- Styles supplémentaires pour les inclusions -->
    <script src="script/erreur_formulaire.js"></script> <!-- Script de validation de formulaire -->
</head>
<body>
<?php
/**
 * Inclusion de l'en-tête de la page.
 *
 * Le fichier header.php contient l'en-tête commun à toutes les pages du site, incluant le logo,
 * le menu de navigation et éventuellement d'autres éléments récurrents.
 */
include 'includes/header.php';
?>

<!-- Section d'introduction à PureOxy -->
<section id="introduction">
    <h2>Bienvenue sur PureOxy</h2>
    <p>
        PureOxy est une plateforme interactive dédiée à l'analyse de la qualité de l'air en France.
        Consultez en temps réel les niveaux de pollution dans votre ville, obtenez des prévisions grâce à nos algorithmes de machine learning,
        et recevez des recommandations personnalisées pour protéger votre santé.
    </p>
</section>

<!-- Section présentant les fonctionnalités principales -->
<section id="features">
    <h2>Nos fonctionnalités</h2>
    <ul>
        <li><strong>Carte interactive</strong> : Visualisez les niveaux de pollution dans toute la France.</li>
        <li><strong>Recherche par ville</strong> : Trouvez rapidement les données de pollution pour votre ville.</li>
        <li><strong>Prédictions</strong> : Obtenez des prévisions sur la qualité de l'air grâce au machine learning.</li>
        <li><strong>Recommandations personnalisées</strong> : Recevez des conseils pour limiter l'impact de la pollution sur votre santé.</li>
    </ul>
</section>

<!-- Section d'appel à l'action pour commencer -->
<section id="cta">
    <h2>Commencez dès maintenant</h2>
    <a href="fonctionnalites/recherche.php" class="button">Cliquez ici pour rechercher une ville</a>
</section>

<?php
/**
 * Inclusion du pied de page de la page.
 *
 * Le fichier footer.php contient le pied de page commun à toutes les pages du site, incluant
 * des liens utiles, des informations de contact et d'autres éléments récurrents.
 */
include 'includes/footer.php';
?>
</body>
</html>
