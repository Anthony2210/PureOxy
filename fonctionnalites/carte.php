<?php
/**
 * carte.php
 *
 * Cette page affiche une carte interactive utilisant Leaflet pour visualiser les niveaux de pollution atmosphérique
 * dans différentes villes de France. Les données sont récupérées depuis la base de données et affichées sous forme
 * de marqueurs sur la carte, avec des informations détaillées dans des popups.
 *
 */

session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la Carte -->
    <link rel="stylesheet" href="../styles/carte.css"/>
    <!-- Styles pour les Commentaires -->
    <link rel="stylesheet" href="../styles/commentaire.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <!-- Inclusion de Leaflet.heat -->
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <!-- Inclusion du script externe carte.js avec l'attribut defer pour s'assurer qu'il est exécuté après le parsing du HTML -->
    <script src="../script/carte.js" defer></script>
</head>
<body>

<?php
include '../includes/header.php';
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

$json_villes = getPollutionData($conn);
$conn->close();
?>

<section id="carte-interactive">
    <h2>Carte interactive de la qualité de l'air</h2>

    <!-- Ajout du sélecteur pour le filtre heat map -->
    <div id="heatmap-filter">
        <label for="pollutant-filter">Filtrer par polluant (Heat Map) :</label>
        <select id="pollutant-filter">
            <option value="">-- Sélectionner un polluant --</option>
            <option value="PM2.5">PM2.5</option>
            <option value="NO2">NO2</option>
            <option value="O3">O3</option>
            <!-- Vous pouvez ajouter d'autres options selon vos données -->
        </select>
    </div>

    <div id="map"></div>

    <!-- Élément caché contenant les données des villes au format JSON -->
    <script type="application/json" id="villes-data">
        <?php echo $json_villes; ?>
    </script>
</section>

<?php
include '../includes/footer.php';
?>

</body>
</html>
