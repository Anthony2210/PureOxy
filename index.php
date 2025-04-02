<?php
/**
 * index.php
 *
 * Page d'accueil du site PureOxy.
 * Cette page accueille les visiteurs avec un aperçu des fonctionnalités du site,
 * et sert de point d'entrée principal pour la navigation.
 *
 * Références :
 * - ChatGPT pour des conseils sur la structuration et la documentation du code.
 *
 * Utilisation :
 * - Ce fichier est accessible via l'URL principale du site.
 */

// Démarrage de la session pour gérer les variables de session utilisateur
session_start();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy</title>

    <!-- Importation des polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">

    <!-- Inclusion des feuilles de style -->
    <!-- Styles de base pour la structure générale -->
    <link rel="stylesheet" href="styles/base.css">
    <!-- Styles spécifiques pour l'en-tête et le pied de page -->
    <link rel="stylesheet" href="styles/includes.css">
    <!-- Styles dédiés à la page d'accueil -->
    <link rel="stylesheet" href="styles/index.css">
    <!-- Styles pour la mise en forme des boutons -->
    <link rel="stylesheet" href="styles/boutons.css">
    <!-- Styles pour l'interface du chatbot -->
    <link rel="stylesheet" href="styles/chatbot.css">

    <!-- Script JavaScript pour le chatbot (chargé en différé) -->
    <script src="./script/chatbot.js" defer></script>
</head>
<body>
<!-- Inclusion de l'en-tête du site -->
<?php include 'includes/header.php'; ?>

<!-- =================================================================
     Section d'introduction
     Contient un message de bienvenue et une présentation des fonctionnalités.
     ================================================================= -->
<section id="introduction">
    <div class="intro-content">
        <h2>Bienvenue sur PureOxy</h2>

        <!-- Boîte floue présentant les fonctionnalités principales -->
        <div class="features-floating">
            <h3>Nos fonctionnalités</h3>
            <ul class="features-list">
                <li>
                    <!-- Lien vers la carte interactive des niveaux de pollution -->
                    <a href="http://localhost/PUREOXY/pages/carte.php">
                        <strong>Carte interactive</strong><br>
                        Visualisez les niveaux de pollution dans toute la France.
                    </a>
                </li>
                <li>
                    <!-- Lien vers la recherche de pollution par ville -->
                    <a href="http://localhost/PUREOXY/pages/recherche.php">
                        <strong>Recherche par ville</strong><br>
                        Trouvez les données de pollution pour votre ville.
                    </a>
                </li>
                <li>
                    <!-- Lien vers le classement des villes les plus polluées -->
                    <a href="http://localhost/PUREOXY/pages/classement.php">
                        <strong>Classement</strong><br>
                        Classez les villes les plus polluées pour chaque polluant.
                    </a>
                </li>
            </ul>
        </div>

        <!-- Bouton invitant l'utilisateur à rejoindre le site (page de création/accès au compte) -->
        <a href="pages/compte.php" class="hero-button">Nous rejoindre</a>
    </div>
</section>

<!-- Inclusion du pied de page du site -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
