<?php
/**
* compare.php
*
* Ce fichier permet à l'utilisateur de comparer les données de pollution entre différentes villes.
* Il propose des filtres (type de données, mois, polluant) et une recherche de villes.
* Les résultats sont affichés sous forme de graphique (Chart.js) et de tableau.
*
* Références :
* - Bootstrap pour la mise en page.
* - Chart.js pour l'affichage graphique.
*
* Utilisation :
* - Ce fichier est accessible via le navigateur pour comparer les données de pollution entre villes.
*
* Fichier placé dans le dossier fonctionnalites.
*/

session_start();
require_once '../bd/bd.php';
$db = new Database();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Comparaison des villes</title>
    <!-- Polices, Bootstrap, FontAwesome et styles personnalisés -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/messages.css">
    <!-- Icônes FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <!-- Fichier de style spécifique -->
    <link rel="stylesheet" href="../styles/compare.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container my-4">
    <h1 class="text-center">Comparaison des villes</h1>

    <!-- Filtres du haut (mois, polluant, type de données) -->
    <div class="row mb-3">
        <div class="col-md-3">
            <label><i class="fa-solid fa-chart-line"></i> Type de données :</label><br>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="data-type" id="data-historique" value="historique" checked>
                <label class="form-check-label" for="data-historique">Historique</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="data-type" id="data-predictions" value="predictions">
                <label class="form-check-label" for="data-predictions">Prédiction</label>
            </div>
        </div>
        <div class="col-md-4">
            <label for="month-selection"><i class="fa-solid fa-calendar"></i> Mois :</label>
            <select id="month-selection" class="form-control">
                <!-- Options ajoutées dynamiquement -->
            </select>
        </div>
        <div class="col-md-4">
            <label for="pollutant-filter"><i class="fa-solid fa-droplet"></i> Polluant :</label>
            <select id="pollutant-filter" class="form-control">
                <option value="">Tous les polluants</option>
                <option value="NO">NO</option>
                <option value="NO2">NO2</option>
                <option value="O3">O3</option>
                <option value="PM10">PM10</option>
                <option value="PM2.5">PM2.5</option>
            </select>
        </div>
    </div>

    <!-- Barre de recherche et gestion des villes -->
    <div class="search-row mb-3">
        <div class="form-group position-relative search-column">
            <label for="city-selection"><i class="fa-solid fa-magnifying-glass"></i> Rechercher une ville :</label>
            <input type="text" id="city-selection" class="form-control" placeholder="Entrez le nom d'une ville...">
            <ul id="suggestions-list-compare" class="list-group"></ul>
        </div>
        <div class="btn-group search-column" style="margin-top: 32px;">
            <button id="add-department" class="btn btn-secondary btn-sm">Ajouter tout le département</button>
            <button id="add-region" class="btn btn-secondary btn-sm">Ajouter toute la région</button>
            <button id="clear-cities" class="btn btn-danger btn-sm" style="display:none;">
                <i class="fa-solid fa-trash"></i> Supprimer tout
            </button>
        </div>
    </div>

    <!-- Conteneur pour les villes sélectionnées, en wrap -->
    <div id="selected-cities" class="selected-cities-container mb-3">
        <!-- Les badges seront injectés ici via compare.js -->
    </div>

    <button id="compare-button" class="btn btn-primary">
        <i class="fa-solid fa-check"></i> Comparer
    </button>

    <!-- Zone d'affichage des résultats -->
    <div id="comparison-results" class="mt-4">
        <canvas id="compare-chart" class="chart"></canvas>
        <div id="compare-table" class="table-responsive mt-4"></div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../script/compare.js"></script>
<script src="../script/suggestions.js"></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
