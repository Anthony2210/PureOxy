<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/carte.css" />
</head>
<body>

<?php include 'header.php'; ?>

<?php
include 'bd/bd.php';  // Fichier contenant la connexion à la base de données

// Requête SQL pour récupérer les polluants distincts
$sql = "SELECT DISTINCT pollutant FROM pollution_villes";
$pollutantsResult = $conn->query($sql);

// Créer un tableau pour stocker les types de polluants
$pollutants = array();
if ($pollutantsResult->num_rows > 0) {
    while($row = $pollutantsResult->fetch_assoc()) {
        $pollutants[] = $row['pollutant'];
    }
}

// Requête SQL pour récupérer les données des villes avec les types de polluants
$sql = "SELECT city AS nom, latitude AS lat, longitude AS lon, value AS pollution, pollutant FROM pollution_villes";
$result = $conn->query($sql);

// Créer un tableau pour stocker les données
$villes = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $coord_key = $row['lat'] . ',' . $row['lon'];
        if (!isset($villes[$coord_key])) {
            $villes[$coord_key] = [
                'nom' => $row['nom'],
                'lat' => $row['lat'],
                'lon' => $row['lon'],
                'pollutants' => []
            ];
        }
        // Ajouter chaque polluant et sa valeur à la liste des polluants de cette ville
        $villes[$coord_key]['pollutants'][$row['pollutant']] = $row['pollution'];
    }
}

// Encoder les résultats en JSON pour les utiliser dans JavaScript
$json_villes = json_encode(array_values($villes));
$pollutants_json = json_encode($pollutants);
?>

<section id="carte-interactive">
    <h2>Carte interactive de la qualité de l'air</h2>
    <div id="map"></div>
</section>

<?php include 'footer.php'; ?>

<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<script>
    // Initialiser la carte avec Leaflet
    var map = L.map('map').setView([46.603354, 1.888334], 6);

    // Ajouter les tuiles OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Injecter les données PHP (JSON) dans JavaScript
    var villes = <?php echo $json_villes; ?>;
    var pollutants = <?php echo $pollutants_json; ?>;

    // Créer des groupes de clusters pour chaque polluant
    var layers = {};
    var allClusters = L.markerClusterGroup({
        maxClusterRadius: 50,  // Optimisation pour limiter les clusters trop larges
        disableClusteringAtZoom: 12  // Ne pas créer de clusters au-dessus d'un certain niveau de zoom
    });

    // Créer un cluster pour chaque polluant
    pollutants.forEach(function(pollutant) {
        layers[pollutant] = L.markerClusterGroup({
            maxClusterRadius: 50,
            disableClusteringAtZoom: 12
        });
    });

    // Boucler à travers les villes et ajouter les marqueurs dans les bons clusters
    villes.forEach(function(ville) {
        var marker = L.marker([ville.lat, ville.lon])
            .bindPopup(`
                <div class="popup-container">
                    <h3>Pollution à ${ville.nom}</h3>
                    <ul>
                        ${Object.keys(ville.pollutants).map(function(pollutant) {
                return `<li><strong>${pollutant} :</strong> ${ville.pollutants[pollutant]} µg/m³</li>`;
            }).join('')}
                    </ul>
                </div>
            `);

        // Ajouter le marqueur à la couche correspondant au polluant
        Object.keys(ville.pollutants).forEach(function(pollutant) {
            if (layers[pollutant]) {
                layers[pollutant].addLayer(marker);  // Ajouter au cluster du polluant
                allClusters.addLayer(marker);  // Ajouter également au cluster "Tous les polluants"
            }
        });
    });

    // Afficher "Tous les polluants" par défaut
    map.addLayer(allClusters);

    // Gestion du contrôle des filtres pour activer/désactiver les clusters
    var baseLayers = {};
    pollutants.forEach(function(pollutant) {
        baseLayers[pollutant] = layers[pollutant];  // Ajouter chaque cluster au contrôle de couches
    });
    baseLayers["Tous les polluants"] = allClusters;  // Ajouter "Tous les polluants" au contrôle de couches

    // Ajouter le contrôle de couches à la carte
    var layerControl = L.control.layers(null, baseLayers, { collapsed: false }).addTo(map);

    // Gérer l'activation/désactivation de la couche "Tous les polluants"
    map.on('overlayadd', function(e) {
        if (e.name === "Tous les polluants") {
            // Activer toutes les couches si "Tous" est activé
            pollutants.forEach(function(pollutant) {
                if (!map.hasLayer(layers[pollutant])) {
                    map.addLayer(layers[pollutant]);
                }
            });
        }
    });

    map.on('overlayremove', function(e) {
        if (e.name === "Tous les polluants") {
            // Désactiver toutes les couches si "Tous" est décoché
            pollutants.forEach(function(pollutant) {
                if (map.hasLayer(layers[pollutant])) {
                    map.removeLayer(layers[pollutant]);
                }
            });
        }
    });

    // Gérer les filtres individuels
    map.on('overlayadd', function(e) {
        if (e.name !== "Tous les polluants") {
            // Ajouter uniquement la couche du polluant sélectionné
            map.addLayer(layers[e.name]);
        }
    });

    map.on('overlayremove', function(e) {
        if (e.name !== "Tous les polluants") {
            // Retirer uniquement la couche du polluant désactivé
            map.removeLayer(layers[e.name]);
        }
    });
</script>

</body>
</html>
