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

<!-- Section d'introduction (Hero) -->
<section id="introduction">
    <div class="intro-content">
        <h2>Bienvenue sur PureOxy</h2>

        <!-- Boîte floue pour les fonctionnalités -->
        <div class="features-floating">
            <h3>Nos fonctionnalités</h3>
            <ul class="features-list">
                <li>
                    <a href="http://localhost/PureOxy/pages/carte.php">
                        <strong>Carte interactive</strong><br>
                        Visualisez les niveaux de pollution dans toute la France.
                    </a>
                </li>
                <li>
                    <a href="http://localhost/PUREOXY/pages/recherche.php">
                        <strong>Recherche par ville</strong><br>
                        Trouvez les données de pollution pour votre ville.
                    </a>
                </li>
                <li>
                    <a href="http://localhost/PUREOXY/pages/classement.php">
                        <strong>Classement</strong><br>
                        Classez les villes les plus polluées pour chaque polluant.
                    </a>
                </li>
            </ul>
        </div>

        <a href="pages/compte.php" class="hero-button">Nous rejoindre</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
</body>
</html>
