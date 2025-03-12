<?php
session_start();
include 'bd/bd.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Respirez mieux, vivez mieux</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="styles/includes.css">
    <!-- Styles pour la page d'accueil -->
    <link rel="stylesheet" href="styles/index.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="styles/boutons.css">
    <!-- Styles pour le chatbot -->
    <link rel="stylesheet" href="styles/chatbot.css">
    <!-- Script du chatbot -->
    <script src="./script/chatbot.js" defer></script>
</head>
<body>
<?php include 'includes/header.php'; ?>

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
        <li>
            <a href="http://localhost/PUREOXY/pages/carte.php">
                <strong>Carte interactive</strong> : Visualisez les niveaux de pollution dans toute la France.
            </a>
        </li>
        <li>
            <a href="http://localhost/PUREOXY/pages/recherche.php">
                <strong>Recherche par ville</strong> : Trouvez rapidement les données de pollution pour votre ville.
            </a>
        </li>
        <li>
            <a href="http://localhost/PUREOXY/fonctionnalites/predictions.php">
                <strong>Prédictions</strong> : Obtenez des prévisions sur la qualité de l'air grâce au machine learning.
            </a>
        </li>
    </ul>
</section>

<!-- Section d'appel à l'action pour commencer -->
<section id="cta">
    <h3>Commencez dès maintenant à nous rejoindre afin de profiter de nouvelles fonctionnalités exclusives !</h3>
    <ul>
        <li>
            <strong>L'accès à l'Espace commentaires</strong> : Discutez et débattez avec d'autres personnes.
        </li>
        <li>
            <strong>Favoris</strong> : Ajoutez différentes villes à vos favoris afin de les retrouver plus rapidement.
        </li>
        <li>
            <strong>Historique</strong> : Retrouvez vos dernières recherches.
        </li>
        <li>
            <strong>Et bien plus encore !</strong>
        </li>
    </ul>
    <a href="pages/compte.php" class="button">Nous rejoindre !</a>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>
