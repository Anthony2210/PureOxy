<?php
/**
 * carte_content.php
 *
 * Ce fichier récupère et organise les données de pollution pour les villes à partir de la base de données,
 * puis affiche les filtres et le conteneur pour la carte interactive.
 *
 * Les données sont extraites via une requête SQL qui joint les tables des mesures de pollution et des informations
 * sur les villes. Les données sont ensuite organisées par ville et par polluant, incluant les valeurs moyennes et mensuelles.
 * Le résultat est encodé en JSON pour être utilisé par le script JavaScript de la carte.
 *
 * Références :
 * - ChatGPT pour des conseils sur la structuration et la documentation du code.
 *
 * Utilisation :
 * - Ce fichier est inclus dans la page carte.php pour afficher la carte interactive avec les filtres.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

include '../bd/bd.php';
$db = new Database();

/**
 * Fonction getPollutionData
 *
 * Exécute une requête SQL pour récupérer les données de pollution des villes,
 * les organise par ville et par polluant, et retourne le résultat encodé en JSON.
 *
 * @param Database $db Instance de la base de données
 * @return string JSON encodé des données de pollution des villes
 */
function getPollutionData($db) {
    // Requête SQL pour extraire les données de pollution et les informations sur les villes
    $sql = "
        SELECT 
            dv.ville AS nom,
            dv.latitude AS lat,
            dv.longitude AS lon,
            dv.departement AS location,
            mpv.polluant,
            mpv.avg_value,
            mpv.moy_janv2023,
            mpv.moy_fev2023,
            mpv.moy_mars2023,
            mpv.moy_avril2023,
            mpv.moy_mai2023,
            mpv.moy_juin2023,
            mpv.moy_juil2023,
            mpv.moy_aout2023,
            mpv.moy_sept2023,
            mpv.moy_oct2023,
            mpv.moy_nov2023,
            mpv.moy_dec2023,
            mpv.moy_janv2024,
            mpv.moy_fev2024,
            mpv.moy_mars2024,
            mpv.moy_avril2024,
            mpv.moy_mai2024,
            mpv.moy_juin2024,
            mpv.moy_juil2024,
            mpv.moy_aout2024,
            mpv.moy_sept2024,
            mpv.moy_oct2024,
            mpv.moy_nov2024,
            mpv.moy_dec2024,
            mpv.moy_janv2025
        FROM moy_pollution_villes mpv
        JOIN donnees_villes dv ON dv.id_ville = mpv.id_ville
        ORDER BY dv.ville
    ";
    // Exécution de la requête SQL
    $result = $db->getConnection()->query($sql);
    if (!$result) {
        // En cas d'erreur SQL, on log l'erreur et on interrompt l'exécution
        error_log('Erreur SQL : ' . $db->getConnection()->error);
        exit('Une erreur est survenue lors du chargement des données.');
    }
    $villes = []; // Initialisation du tableau qui contiendra les données de chaque ville
    // Parcours des résultats de la requête
    while ($row = $result->fetch_assoc()) {
        $city_key  = $row['nom'];      // Utilisation du nom de la ville comme clé
        $pollutant = $row['polluant'];  // Polluant mesuré
        // Si la ville n'existe pas encore dans le tableau, on l'initialise
        if (!isset($villes[$city_key])) {
            $villes[$city_key] = [
                'nom'       => $row['nom'],
                'lat'       => $row['lat'],
                'lon'       => $row['lon'],
                'location'  => $row['location'],
                'pollutants'=> [] // Tableau pour stocker les données par polluant
            ];
        }
        // Si le polluant n'est pas encore présent pour la ville, on l'ajoute
        if (!isset($villes[$city_key]['pollutants'][$pollutant])) {
            $villes[$city_key]['pollutants'][$pollutant] = [
                'avg_value' => $row['avg_value'] ?? 0, // Valeur moyenne (avec valeur par défaut 0)
                'monthly'   => [ // Valeurs mensuelles pour différentes périodes
                    '2023-01' => $row['moy_janv2023'],
                    '2023-02' => $row['moy_fev2023'],
                    '2023-03' => $row['moy_mars2023'],
                    '2023-04' => $row['moy_avril2023'],
                    '2023-05' => $row['moy_mai2023'],
                    '2023-06' => $row['moy_juin2023'],
                    '2023-07' => $row['moy_juil2023'],
                    '2023-08' => $row['moy_aout2023'],
                    '2023-09' => $row['moy_sept2023'],
                    '2023-10' => $row['moy_oct2023'],
                    '2023-11' => $row['moy_nov2023'],
                    '2023-12' => $row['moy_dec2023'],
                    '2024-01' => $row['moy_janv2024'],
                    '2024-02' => $row['moy_fev2024'],
                    '2024-03' => $row['moy_mars2024'],
                    '2024-04' => $row['moy_avril2024'],
                    '2024-05' => $row['moy_mai2024'],
                    '2024-06' => $row['moy_juin2024'],
                    '2024-07' => $row['moy_juil2024'],
                    '2024-08' => $row['moy_aout2024'],
                    '2024-09' => $row['moy_sept2024'],
                    '2024-10' => $row['moy_oct2024'],
                    '2024-11' => $row['moy_nov2024'],
                    '2024-12' => $row['moy_dec2024'],
                    '2025-01' => $row['moy_janv2025'],
                ]
            ];
        }
    }
    // Retourne les données sous forme de JSON, en réindexant le tableau des villes
    return json_encode(array_values($villes));
}

$json_villes = getPollutionData($db); // Récupération des données de pollution sous forme de JSON
$db->getConnection()->close(); // Fermeture de la connexion à la base de données
?>
<!-- Inclusion de la police Google League Spartan -->
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">

<!-- Section principale de la carte interactive -->
<section id="carte-interactive">
    <!-- FILTRES : Permet à l'utilisateur de sélectionner le polluant et le mois souhaité -->
    <div id="filters-container">
        <!-- Filtre pour choisir le polluant -->
        <div id="heatmap-filter">
            <label for="pollutant-filter">Polluant :</label>
            <select id="pollutant-filter">
                <option value="">(Aucun)</option>
                <option value="PM2.5">PM2.5</option>
                <option value="PM10">PM10</option>
                <option value="NO">NO</option>
                <option value="NO2">NO2</option>
                <option value="O3">O3</option>
                <option value="CO">CO</option>
            </select>
        </div>

        <!-- Filtre pour choisir le mois (de Janv 2023 à Janv 2025) -->
        <div id="month-filter">
            <label for="month-filter-select">Mois :</label>
            <select id="month-filter-select">
                <option value="">(Aucun)</option>
                <option value="2023-01">Janv. 2023</option>
                <option value="2023-02">Févr. 2023</option>
                <option value="2023-03">Mars 2023</option>
                <option value="2023-04">Avril 2023</option>
                <option value="2023-05">Mai 2023</option>
                <option value="2023-06">Juin 2023</option>
                <option value="2023-07">Juil. 2023</option>
                <option value="2023-08">Août 2023</option>
                <option value="2023-09">Sept. 2023</option>
                <option value="2023-10">Oct. 2023</option>
                <option value="2023-11">Nov. 2023</option>
                <option value="2023-12">Déc. 2023</option>
                <option value="2024-01">Janv. 2024</option>
                <option value="2024-02">Févr. 2024</option>
                <option value="2024-03">Mars 2024</option>
                <option value="2024-04">Avril 2024</option>
                <option value="2024-05">Mai 2024</option>
                <option value="2024-06">Juin 2024</option>
                <option value="2024-07">Juil. 2024</option>
                <option value="2024-08">Août 2024</option>
                <option value="2024-09">Sept. 2024</option>
                <option value="2024-10">Oct. 2024</option>
                <option value="2024-11">Nov. 2024</option>
                <option value="2024-12">Déc. 2024</option>
                <option value="2025-01">Janv. 2025</option>
            </select>
        </div>
    </div>

    <!-- Conteneur pour la carte Leaflet -->
    <div id="map"></div>

    <!-- Transmission des données JSON des villes au script via un élément <script> -->
    <script type="application/json" id="villes-data">
        <?php echo $json_villes; ?>
    </script>
</section>
