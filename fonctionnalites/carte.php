<?php
/**
 * Carte Interactive de la Qualité de l'Air - PureOxy
 *
 * Cette page affiche une carte interactive utilisant Leaflet pour visualiser les niveaux de pollution atmosphérique
 * dans différentes villes de France. Les données sont récupérées depuis la base de données et affichées sous forme
 * de marqueurs sur la carte, avec des informations détaillées dans des popups.
 *
 * @package PureOxy
 */

session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la Carte -->
    <link rel="stylesheet" href="../styles/carte.css" />
    <!-- Styles pour les Commentaires -->
    <link rel="stylesheet" href="../styles/commentaire.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<body>

<?php
/**
 * Inclut l'en-tête de la page.
 *
 * L'en-tête contient généralement le logo, le menu de navigation, et d'autres éléments communs
 * à toutes les pages du site.
 *
 * @see ../includes/header.php
 */
include '../includes/header.php';

/**
 * Inclut le fichier de connexion à la base de données.
 *
 * @see ../bd/bd.php
 */
include '../bd/bd.php';

/**
 * Récupère les données de pollution des villes depuis la base de données.
 *
 * @param mysqli $conn Connexion à la base de données.
 *
 * @return string JSON encodé contenant les informations des villes et leurs niveaux de pollution.
 */
function getPollutionData($conn) {
    $sql = "SELECT City AS nom, Latitude AS lat, Longitude AS lon, value AS pollution, Pollutant AS pollutant, Location AS location, `LastUpdated` AS date
            FROM pollution_villes
            ORDER BY date";
    $result = $conn->query($sql);

    if (!$result) {
        error_log("Erreur lors de l'exécution de la requête SQL : " . $conn->error);
        exit('Une erreur est survenue lors du chargement des données.');
    }

    $villes = array();
    if ($result->num_rows > 0) {
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

    return json_encode(array_values($villes));
}

// Récupérer les données de pollution au format JSON
$json_villes = getPollutionData($conn);

// Fermer la connexion à la base de données
$conn->close();
?>

<section id="carte-interactive">
    <h2>Carte interactive de la qualité de l'air</h2>
    <div id="map"></div>
</section>

<?php
/**
 * Inclut le pied de page de la page.
 *
 * Le pied de page contient généralement des informations de contact, des liens vers les réseaux sociaux,
 * et d'autres éléments communs à toutes les pages du site.
 *
 * @see ../includes/footer.php
 */
include '../includes/footer.php';
?>

<script>
    /**
     * Initialise la carte Leaflet centrée sur la France.
     */
    var map = L.map('map').setView([46.603354, 1.888334], 6);

    /**
     * Ajoute les tuiles OpenStreetMap à la carte.
     */
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    /**
     * Définition d'une icône verte personnalisée pour les marqueurs.
     */
    var greenIcon = L.icon({
        iconUrl: '../images/green-icon.png', // Assurez-vous que ce chemin correspond à l'emplacement réel de votre image
        iconSize: [20, 20], // Taille de l'icône
    });

    /**
     * Données des villes encodées en JSON depuis PHP.
     *
     * @type {Array<Object>}
     */
    var villes = <?php echo $json_villes; ?>;

    /**
     * Génère une liste HTML des variations des polluants.
     *
     * @param {string} pollutant Le nom du polluant.
     * @param {number} value La valeur moyenne du polluant.
     * @returns {string} HTML de la liste.
     */
    function displayPollutantVariation(pollutant, value) {
        return `<li><strong>${pollutant} :</strong> ${value.toFixed(2)} µg/m³</li>`;
    }

    /**
     * Calcule la moyenne d'un tableau de valeurs.
     *
     * @param {Array<number>} values Tableau des valeurs numériques.
     * @returns {number} La moyenne des valeurs.
     */
    function calculateAverage(values) {
        if (values.length === 0) return 0;
        let sum = values.reduce((acc, val) => acc + parseFloat(val), 0);
        return sum / values.length;
    }

    /**
     * Parcourt chaque ville et ajoute un marqueur sur la carte avec un popup détaillé.
     */
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
