<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/carte.css" />
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
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

$villes = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $city_key = $row['nom'];

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

        if (!isset($villes[$city_key]['pollutants'][$row['pollutant']])) {
            $villes[$city_key]['pollutants'][$row['pollutant']] = [];
        }

        $villes[$city_key]['pollutants'][$row['pollutant']][] = [
            'value' => $row['pollution'],
            'date' => $row['date'],
            'location' => $row['location']
        ];

        $villes[$city_key]['dates'][] = [
            'date' => $row['date'],
            'location' => $row['location']
        ];
    }
}

$json_villes = json_encode(array_values($villes));
?>

<section id="carte-interactive">
    <h2>Carte interactive de la qualité de l'air</h2>
    <div id="map"></div>
</section>

<?php include '../includes/footer.php'; ?>

<script>
    var map = L.map('map').setView([46.603354, 1.888334], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Définir une icône verte personnalisée basée sur l'image fournie
    var greenIcon = L.icon({
        iconUrl: '../images/green-icon.png', // Assurez-vous que ce chemin correspond à l'emplacement réel de votre image
        iconSize: [20, 20], // Réduisez la taille pour un meilleur rendu

    });

    var villes = <?php echo $json_villes; ?>;

    function displayPollutantVariation(pollutant, value) {
        return `<li><strong>${pollutant} :</strong> ${value.toFixed(2)} µg/m³</li>`;
    }

    function calculateAverage(values) {
        if (values.length === 0) return 0;
        let sum = values.reduce((acc, val) => acc + parseFloat(val), 0);
        return sum / values.length;
    }

    villes.forEach(function(ville) {
        if (ville.lat && ville.lon) {
            var marker = L.marker([ville.lat, ville.lon], { icon: greenIcon }).addTo(map);

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

            marker.bindPopup(popupContent);

            marker.on('click', function() {
                if (currentPopup) {
                    currentPopup._close();
                }
                currentPopup = marker.getPopup();
                marker.openPopup();
            });
        } else {
            console.warn(`Coordonnées manquantes pour la ville : ${ville.nom}`);
        }
    });
</script>

</body>
</html>
