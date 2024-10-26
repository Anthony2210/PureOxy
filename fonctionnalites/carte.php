<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/carte.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="../script/erreur_formulaire.js"></script>

</head>
<body>

<?php include '../includes/header.php'; ?>

<?php
include '../bd/bd.php';  // Connexion à la base de données

// Requête SQL pour récupérer les points de pollution
$sql = "SELECT City AS nom, Latitude AS lat, Longitude AS lon, value AS pollution, Pollutant AS pollutant, Location AS location, `LastUpdated` AS date
        FROM pollution_villes
        ORDER BY date";
$result = $conn->query($sql);

// Créer un tableau pour regrouper les polluants par nom de ville
$villes = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Utiliser le nom de la ville comme clé pour fusionner les points
        $city_key = $row['nom'];

        // Si la ville n'existe pas encore, l'ajouter au tableau
        if (!isset($villes[$city_key])) {
            $villes[$city_key] = [
                'nom' => $row['nom'],
                'lat' => $row['lat'],
                'lon' => $row['lon'],
                'location' => $row['location'],
                'pollutants' => [],
                'dates' => []
            ];
        }

        // Ajouter les données spécifiques à chaque ville et polluant
        if (!isset($villes[$city_key]['pollutants'][$row['pollutant']])) {
            $villes[$city_key]['pollutants'][$row['pollutant']] = [];
        }

        $villes[$city_key]['pollutants'][$row['pollutant']][] = [
            'value' => $row['pollution'],
            'date' => $row['date'],
            'location' => $row['location']
        ];

        // Ajouter la date à la liste des dates
        $villes[$city_key]['dates'][] = [
            'date' => $row['date'],
            'location' => $row['location']
        ];
    }
}

// Encoder les résultats en JSON pour les utiliser dans JavaScript
$json_villes = json_encode(array_values($villes));
?>

<section id="carte-interactive">
    <h2>Carte interactive de la qualité de l'air</h2>
    <div id="map"></div>
</section>

<?php include '../includes/footer.php'; ?>

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

    // Variable pour stocker le dernier popup ouvert
    var currentPopup = null;

    // Fonction pour afficher les polluants dans une fenêtre pop-up
    function displayPollutantVariation(pollutant, value) {
        return `<li><strong>${pollutant} :</strong> ${value.toFixed(2)} µg/m³</li>`;
    }

    // Calculer la moyenne des valeurs de pollution
    function calculateAverage(values) {
        if (values.length === 0) return 0;
        let sum = values.reduce((acc, val) => acc + parseFloat(val), 0);
        return sum / values.length;
    }

    // Afficher les points sur la carte avec une fenêtre pop-up
    villes.forEach(function(ville) {
        var marker = L.marker([ville.lat, ville.lon]).addTo(map);

        // Créer le contenu du pop-up
        var pollutantList = '';
        for (var pollutant in ville.pollutants) {
            let values = ville.pollutants[pollutant].map(data => data.value);
            let average = calculateAverage(values);
            pollutantList += displayPollutantVariation(pollutant, average);
        }

        var popupContent = `
            <div class="popup-content">
                <strong>Ville :</strong> ${ville.nom}<br>
                ${ville.location !== 'Inconnu' ? `<strong>Localisation :</strong> ${ville.location}<br>` : ''}
                <ul>${pollutantList}</ul>
                <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(ville.nom)}" id="see-more">Voir plus</a>
            </div>
        `;

        // Créer le pop-up associé au marqueur
        marker.bindPopup(popupContent);

        // Gérer l'événement de clic sur le marqueur pour ouvrir le pop-up
        marker.on('click', function() {
            // Fermer le pop-up précédent s'il y en a un
            if (currentPopup) {
                currentPopup._close();
            }

            // Ouvrir le nouveau pop-up et mettre à jour la variable currentPopup
            currentPopup = marker.getPopup();
            marker.openPopup();
        });
    });
</script>

</body>
</html>
