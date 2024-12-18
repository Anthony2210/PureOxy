<?php
/**
 * recherche.php
 *
 * Cette page permet aux utilisateurs de rechercher des villes en entrant le nom ou le code postal.
 * Elle affiche des suggestions en temps réel et affiche les résultats de la recherche.
 *
 */

session_start();

require_once('../bd/bd.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Villes</title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la Recherche -->
    <link rel="stylesheet" href="../styles/recherche.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Script pour afficher des suggestions de recherche -->
    <script src="../script/suggestions.js"></script>

</head>
<body id="recherche-page">
<div class="content-wrapper">
    <?php
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
    include('../includes/footer.php');
    ?>
</div>

</body>
</html>
