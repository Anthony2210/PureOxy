<?php
/**
 * compare.php
 *
 * Ce fichier permet à l'utilisateur de comparer les données de pollution entre différentes villes ou groupes de villes.
 * Il propose des filtres (type de données, mois, polluant), un sélecteur de groupe et une zone de recherche pour ajouter
 * une ville individuelle. Les résultats sont affichés sous forme de graphique (Chart.js) et de tableau.
 *
 * Références :
 * - Bootstrap, Chart.js, FontAwesome
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
    <title>PureOxy - Comparaison des villes et groupes</title>
    <!-- Polices, Bootstrap, FontAwesome et styles personnalisés -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/messages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="../styles/compare.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container my-4">
    <h1 class="text-center">Comparaison des villes / Groupes</h1>

    <!-- Filtres généraux -->
    <div class="row mb-3">
        <div class="col-md-3">
            <label><i class="fa-solid fa-chart-line"></i> Type de données :</label><br>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="data-type" id="data-historique" value="historique"
                       checked>
                <label class="form-check-label" for="data-historique">Historique</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="data-type" id="data-predictions" value="predictions">
                <label class="form-check-label" for="data-predictions">Prédiction</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="data-type" id="data-habitants" value="habitants">
                <label class="form-check-label" for="data-habitants">Moy. par habitants</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="data-type" id="data-superficie" value="superficie">
                <label class="form-check-label" for="data-superficie">Moy. par superficie</label>
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

    <!-- Bloc de sélection par groupe -->
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="group-type-select">Filtrer par groupe :</label>
            <select id="group-type-select" class="form-control">
                <option value="">Sélectionnez un type de groupe</option>
                <option value="department">Département</option>
                <option value="region">Région</option>
                <option value="superficie">Superficie</option>
                <option value="population">Population</option>
                <option value="densite">Densité</option>
            </select>
        </div>
        <div class="col-md-8">
            <label>Groupes disponibles :</label>
            <div id="available-groups"></div>
        </div>
    </div>

    <!-- Zone de recherche pour ajouter une ville individuelle -->
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="city-selection"><i class="fa-solid fa-magnifying-glass"></i> Rechercher une ville :</label>
            <input type="text" id="city-selection" class="form-control" placeholder="Entrez le nom d'une ville...">
            <ul id="suggestions-list-compare" class="list-group"></ul>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button id="add-city" class="btn btn-secondary btn-sm">Ajouter la ville</button>
        </div>
    </div>

    <!-- Affichage des éléments sélectionnés (villes et/ou groupes) -->
    <div id="selected-cities" class="selected-cities-container mb-3"></div>
    <button id="clear-cities" class="btn btn-danger btn-sm" style="display:none;">
        <i class="fa-solid fa-trash"></i> Supprimer tout
    </button>

    <button id="compare-button" class="btn btn-primary mt-3">
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
<?php include '../includes/footer.php'; ?>
</body>
</html>