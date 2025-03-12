<?php
// carte-content.php
// Ce fichier contient uniquement le contenu de la carte interactive, sans la structure HTML complète.

include '../bd/bd.php';

/**
 * Récupère les données de pollution des villes depuis la base de données.
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

    <!-- Sélecteur pour filtrer par polluant -->
    <div id="heatmap-filter">
        <label for="pollutant-filter">Filtrer par polluant :</label>
        <select id="pollutant-filter">
            <option value="">Aucun</option>
            <option value="PM2.5">PM2.5</option>
            <option value="PM10">PM10</option>
            <option value="NO">NO</option>
            <option value="NO2">NO2</option>
            <option value="O3">O3</option>
            <option value="CO">CO</option>
        </select>
    </div>

    <!-- Conteneur de la carte -->
    <div id="map"></div>

    <!-- Données JSON des villes -->
    <script type="application/json" id="villes-data">
        <?php echo $json_villes; ?>
    </script>
</section>
