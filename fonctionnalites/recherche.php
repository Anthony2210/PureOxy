<?php
/**
 * Page de Recherche de Villes
 *
 * Cette page permet aux utilisateurs de rechercher des villes en entrant le nom ou le code postal.
 * Elle affiche des suggestions en temps réel et affiche les résultats de la recherche.
 *
 * @package PureOxy
 * @subpackage Recherche
 * @author
 * @version 1.0
 * @since 2024-04-27
 */

session_start();

require_once('../bd/bd.php'); // Connexion à la base de données
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Villes</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles de Mise en Page -->
    <link rel="stylesheet" href="../styles/layout.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la Recherche -->
    <link rel="stylesheet" href="../styles/search.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/buttons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body id="recherche-page">
<div class="content-wrapper">
    <?php
    /**
     * Inclusion de l'en-tête de la page.
     *
     * Le fichier header.php contient l'en-tête commun à toutes les pages du site, incluant le logo,
     * le menu de navigation et éventuellement d'autres éléments récurrents.
     */
    include('../includes/header.php');
    ?>

    <main>
        <div id="search-container">
            <h1>Rechercher une ville</h1>

            <!-- Nouveau conteneur pour l'input et les suggestions -->
            <div class="search-input-wrapper">
                <!-- Barre de recherche -->
                <input type="text" id="search-bar" placeholder="Entrez le nom d'une ville" autocomplete="off">
                <!-- Message d'avertissement -->
                <p class="avertissement">Veuillez noter que notre base de données couvre actuellement 443 villes.</p>

                <!-- Liste déroulante pour les suggestions -->
                <ul id="suggestions-list"></ul>
            </div>

            <!-- Bouton de recherche -->
            <button id="search-button"><i class="fas fa-search"></i> Rechercher</button>

        </div>

        <!-- Zone de résultats de la recherche -->
        <div id="search-results"></div>

    </main>

    <?php
    /**
     * Inclusion du pied de page de la page.
     *
     * Le fichier footer.php contient le pied de page commun à toutes les pages du site, incluant
     * des liens utiles, des informations de contact et d'autres éléments récurrents.
     */
    include('../includes/footer.php');
    ?>
</div>

<!-- Inclusion du script JavaScript pour les suggestions -->
<script src="../script/suggestions.js"></script>

</body>
</html>
