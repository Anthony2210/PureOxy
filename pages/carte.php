<?php
/**
 * carte.php
 *
 * Page principale de la carte interactive de PureOxy.
 * Ce fichier initialise la session, inclut l'en-tête et le pied de page,
 * et charge le contenu spécifique de la carte via l'inclusion de "carte_content.php".
 *
 * Références :
 * - ChatGPT pour des conseils sur la structuration et la documentation.
 *
 * Utilisation :
 * - Accéder à cette page pour visualiser la carte interactive affichant la qualité de l'air.
 *
 * Fichier placé dans le dossier pages.
 */
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>

    <!-- Inclusion des feuilles de style spécifiques à la carte et styles globaux -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/carte.css"/>
    <link rel="stylesheet" href="../styles/boutons.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">

    <!-- Inclusion de la librairie Leaflet et du script de la carte -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="../script/carte.js" defer></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<!-- Titre de la page -->
<h2 class="carte-title">Carte interactive de la qualité de l’air</h2>

<!-- Inclusion du contenu spécifique de la carte -->
<?php include('../fonctionnalites/carte_content.php'); ?>

<?php include '../includes/footer.php'; ?>
</body>
</html>
