<?php
// carte-content.php
// Ce fichier contient uniquement le contenu de la carte interactive, sans la structure HTML complète.

include '../bd/bd.php';

/**
 * Récupère la moyenne de pollution des villes depuis la table moy_pollution_villes,
 * puis joint à donnees_villes pour obtenir la lat/lon, etc.
 */
function getPollutionData($conn) {
    $sql = "
        SELECT 
            dv.ville AS nom,
            dv.latitude AS lat,
            dv.longitude AS lon,
            dv.departement AS location,
            mpv.avg_value AS pollution,
            mpv.pollutant AS pollutant
        FROM moy_pollution_villes mpv
        JOIN donnees_villes dv 
            ON dv.id_ville = mpv.id_ville
        ORDER BY dv.ville
    ";

    $result = $conn->query($sql);
    if (!$result) {
        error_log('Erreur lors de l\'exécution de la requête SQL : ' . $conn->error);
        exit('Une erreur est survenue lors du chargement des données.');
    }

    $villes = [];
    while ($row = $result->fetch_assoc()) {
        $city_key = $row['nom'];

        // Si on n'a pas encore enregistré cette ville, on l'initialise
        if (!isset($villes[$city_key])) {
            $villes[$city_key] = [
                'nom'       => $row['nom'],
                'lat'       => $row['lat'],
                'lon'       => $row['lon'],
                'location'  => $row['location'],
                'pollutants'=> []
            ];
        }

        // On ajoute le polluant et sa valeur moyenne
        $pollutant = $row['pollutant'];
        if (!isset($villes[$city_key]['pollutants'][$pollutant])) {
            $villes[$city_key]['pollutants'][$pollutant] = [];
        }

        // Comme c’est déjà agrégé, on n’a qu’une valeur par (ville, polluant),
        // mais on le stocke quand même dans un tableau pour ne pas casser le code existant.
        $villes[$city_key]['pollutants'][$pollutant][] = [
            'value'    => $row['pollution'],
            'date'     => null,        // plus de date ici
            'location' => $row['location']
        ];
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
