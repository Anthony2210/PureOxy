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
    <!-- Liens CSS et JS spécifiques à la recherche -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/recherche.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="../script/suggestions.js" defer></script>
</head>
<body id="recherche-page">
<div class="content-wrapper">
    <?php include('../includes/header.php'); ?>
    <main>
        <!-- Inclusion du contenu de la recherche -->
        <?php include('../fonctionnalites/recherche_content.php'); ?>
    </main>
    <?php include('../includes/footer.php'); ?>
</div>
</body>
</html>
