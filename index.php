<?php
/**
 * index.php - Page d'accueil de PureOxy
 */
session_start();

// Détection automatique de l'URL de base (utile pour les liens dans le contenu HTML)
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy</title>

    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">

    <!-- Feuilles de style -->
    <link rel="stylesheet" href="styles/base.css">
    <link rel="stylesheet" href="styles/includes.css">
    <link rel="stylesheet" href="styles/index.css">
    <link rel="stylesheet" href="styles/boutons.css">
    <link rel="stylesheet" href="styles/chatbot.css">


</head>
<body>
<!-- Header -->
<?php include 'includes/header.php'; ?>

<section id="introduction">
    <div class="intro-content">
        <h2>Bienvenue sur PureOxy</h2>

        <!-- Fonctionnalités -->
        <div class="features-floating">
            <h3>Nos fonctionnalités</h3>
            <ul class="features-list">
                <li>
                    <a href="<?= $baseUrl ?>pages/carte.php">
                        <strong>Carte interactive</strong><br>
                        Visualisez les niveaux de pollution dans toute la France.
                    </a>
                </li>
                <li>
                    <a href="<?= $baseUrl ?>pages/recherche.php">
                        <strong>Recherche par ville</strong><br>
                        Trouvez les données de pollution pour votre ville.
                    </a>
                </li>
                <li>
                    <a href="<?= $baseUrl ?>pages/classement.php">
                        <strong>Classement</strong><br>
                        Classez les villes les plus polluées pour chaque polluant.
                    </a>
                </li>
            </ul>
        </div>

        <!-- Bouton d'inscription -->
        <a href="<?= $baseUrl ?>pages/compte.php" class="hero-button">Nous rejoindre</a>
    </div>
</section>

<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
