<?php
// donnees_locales.php
session_start();
ob_start();
include '../bd/bd.php';
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/PUREOXY/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Données locales</title>

    <!-- Styles communs -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Notre CSS unifié pour la page Données locales -->
    <link rel="stylesheet" href="../styles/local_data.css">

    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">

    <!-- Leaflet et FontAwesome -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Scripts spécifiques -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="../script/carte.js" defer></script>
    <script src="../script/suggestions.js" defer></script>
</head>
<body>
<?php include('../includes/header.php'); ?>

<div class="container">
    <h1>Données locales</h1>

    <!-- Section de choix pour la navigation -->
    <div class="choice-container">
        <p>Que voulez vous afficher ? &nbsp;</p>
        <button id="btn-search">Barre de recherche</button>
        <button id="btn-map">Carte interactive</button>
    </div>

    <!-- Section pour la barre de recherche (initialement masquée) -->
    <section id="search-section">
        <?php include('../fonctionnalites/recherche_content.php'); ?>
    </section>

    <!-- Section pour la carte interactive (initialement masquée) -->
    <section id="map-section">
        <?php include('../fonctionnalites/carte_content.php'); ?>
    </section>
</div>

<?php include('../includes/footer.php'); ?>

<!-- Script pour afficher soit la recherche, soit la carte -->
<script>
    const btnSearch = document.getElementById('btn-search');
    const btnMap    = document.getElementById('btn-map');
    const searchSec = document.getElementById('search-section');
    const mapSec    = document.getElementById('map-section');

    // Au chargement, on masque les deux sections
    searchSec.style.display = 'none';
    mapSec.style.display = 'none';

    // Affiche uniquement la recherche
    btnSearch.addEventListener('click', () => {
        searchSec.style.display = 'block';
        mapSec.style.display = 'none';
    });

    // Affiche uniquement la carte
    btnMap.addEventListener('click', () => {
        mapSec.style.display = 'block';
        searchSec.style.display = 'none';
    });
</script>
</body>
</html>
