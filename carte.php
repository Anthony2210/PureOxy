<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/carte.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<body>

<?php include 'header.php'; ?>

<?php
include 'bd/bd.php';  // Connexion à la base de données

// Requête SQL pour récupérer les données des villes avec les types de polluants et la date de mise à jour
$sql = "SELECT City AS nom, Latitude AS lat, Longitude AS lon, value AS pollution, Pollutant AS pollutant, Location AS location, `LastUpdated` AS date FROM pollution_villes";
$result = $conn->query($sql);

// Créer un tableau pour regrouper les polluants par coordonnées (latitude, longitude)
$villes = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $coord_key = $row['lat'] . ',' . $row['lon'];
        if (!isset($villes[$coord_key])) {
            $villes[$coord_key] = [
                'nom' => $row['nom'],
                'lat' => $row['lat'],
                'lon' => $row['lon'],
                'location' => $row['location'],  // Inclure la localisation
                'pollutants' => [],
                'date' => $row['date']
            ];
        }
        // Ajouter les différents polluants pour cette localisation
        $villes[$coord_key]['pollutants'][] = [
            'pollutant' => $row['pollutant'],
            'value' => $row['pollution']
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

<?php include 'footer.php'; ?>

<script>
    // Fonction pour formater la date dans le style souhaité
    function formatDate(dateStr) {
        // Convertir la chaîne de date en objet Date
        var dateObj = new Date(dateStr);

        // Obtenir les parties de la date
        var day = dateObj.getDate();
        var month = dateObj.toLocaleString('fr-FR', { month: 'long' });  // Mois en français
        var year = dateObj.getFullYear();
        var hours = dateObj.getHours();
        var minutes = dateObj.getMinutes().toString().padStart(2, '0');  // Ajoute un zéro si nécessaire

        // Retourner la date formatée
        return `Dernière analyse le ${day} ${month} ${year} à ${hours}h${minutes}`;
    }

    // Initialiser la carte avec Leaflet
    var map = L.map('map').setView([46.603354, 1.888334], 6);

    // Ajouter les tuiles OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Injecter les données PHP (JSON) dans JavaScript
    var villes = <?php echo $json_villes; ?>;

    // Boucler à travers les villes et ajouter les marqueurs avec regroupement des polluants
    villes.forEach(function(ville) {
        var marker = L.marker([ville.lat, ville.lon]).addTo(map);

        // Gérer l'événement 'click' pour chaque marqueur
        marker.on('click', function() {
            // Construction de la liste des polluants
            var pollutantList = ville.pollutants.map(function(pollutant) {
                return `<li><strong>${pollutant.pollutant} :</strong> ${pollutant.value} µg/m³</li>`;
            }).join('');

            // Ajouter la localisation uniquement si elle n'est pas "Inconnu"
            var locationInfo = (ville.location !== "Inconnu") ? `<strong>Localisation :</strong> ${ville.location}<br>` : '';

            // Formater la date dans le style souhaité
            var formattedDate = formatDate(ville.date);

            // Mise à jour des détails dans une fenêtre flottante
            var popupContent = `
                <div class="popup-content">
                    <strong>Ville :</strong> ${ville.nom}<br>
                    ${locationInfo}
                    <strong>${formattedDate}</strong><br>
                    <ul>${pollutantList}</ul>
                </div>
            `;

            // Afficher la fenêtre flottante (popup) sur la carte
            var popup = L.popup()
                .setLatLng([ville.lat, ville.lon])
                .setContent(popupContent)
                .openOn(map);
        });
    });
</script>

</body>
</html>
