<?php
/**
 * get_compare_data.php
 *
 * Ce script récupère les données de pollution pour une comparaison entre plusieurs villes.
 * Il effectue des requêtes sur la base de données pour extraire les valeurs moyennes
 * de pollution (historique ou prédiction) pour chaque polluant, en fonction des villes ou groupes sélectionnés.
 *
 * Références :
 * - Utilisation de MySQLi avec des requêtes préparées pour sécuriser l'accès aux données.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis l'interface de comparaison afin de générer
 *   dynamiquement un graphique (via Chart.js) et un tableau récapitulatif.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */
session_start();
require_once '../bd/bd.php';
$db = new Database();

header('Content-Type: application/json');

function getGroupData($filterType, $groupValue, $data_type, $month, $pollutantFilter, $db) {
    // Prépare la requête selon le type de groupe
    switch($filterType) {
        case 'department':
            $q = "SELECT id_ville FROM donnees_villes WHERE departement = ?";
            break;
        case 'region':
            $q = "SELECT id_ville FROM donnees_villes WHERE region = ?";
            break;
        case 'superficie':
            if($groupValue === "moins10"){
                $q = "SELECT id_ville FROM donnees_villes WHERE superficie_km2 < 10";
            } elseif($groupValue === "10_50"){
                $q = "SELECT id_ville FROM donnees_villes WHERE superficie_km2 BETWEEN 10 AND 50";
            } elseif($groupValue === "plus50"){
                $q = "SELECT id_ville FROM donnees_villes WHERE superficie_km2 > 50";
            }
            break;
        case 'population':
            if($groupValue === "moins10k"){
                $q = "SELECT id_ville FROM donnees_villes WHERE population < 10000";
            } elseif($groupValue === "10k_50k"){
                $q = "SELECT id_ville FROM donnees_villes WHERE population BETWEEN 10000 AND 50000";
            } elseif($groupValue === "plus50k"){
                $q = "SELECT id_ville FROM donnees_villes WHERE population > 50000";
            }
            break;
        case 'densite':
            $q = "SELECT id_ville FROM donnees_villes WHERE grille_densite_texte = ?";
            break;
        default:
            return [];
    }
    if(in_array($filterType, ['department','region','densite'])){
        $stmt = $db->prepare($q);
        $stmt->bind_param("s", $groupValue);
    } else {
        $stmt = $db->prepare($q);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $cityIds = [];
    while($r = $res->fetch_assoc()){
        $cityIds[] = (int)$r['id_ville'];
    }
    $stmt->close();
    if(empty($cityIds)) return [];

    // Agrégation des données pour toutes les villes du groupe
    $groupData = [];
    $counts = [];
    foreach($cityIds as $id) {
        if ($data_type == "historique") {
            if (!empty($month)) {
                $col = "moy_" . $month;
                $query = "SELECT polluant, ROUND($col, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            } else {
                $query = "SELECT polluant, ROUND(avg_value, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            }
        } elseif ($data_type == "predictions") {
            if (!empty($month)) {
                $query = "SELECT polluant, ROUND($month, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            } else {
                $query = "SELECT polluant, ROUND(avg_value, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            }
        } elseif ($data_type == "habitants") {
            // Pour "habitants", ne pas arrondir
            $query = "SELECT polluant, avg_par_habitant as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
        } elseif ($data_type == "superficie") {
            // Pour "superficie", ne pas arrondir
            $query = "SELECT polluant, avg_par_km2 as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
        }
        if (!empty($pollutantFilter)) {
            $query .= " AND polluant = ?";
            $stmtPoll = $db->prepare($query);
            $stmtPoll->bind_param("is", $id, $pollutantFilter);
        } else {
            $stmtPoll = $db->prepare($query);
            $stmtPoll->bind_param("i", $id);
        }
        $stmtPoll->execute();
        $resultPoll = $stmtPoll->get_result();
        while ($poll = $resultPoll->fetch_assoc()) {
            // Filtrer les valeurs négatives et les polluants à exclure ("C6H6" et "SO2")
            if ($poll['avg_value'] < 0 || $poll['polluant'] === "C6H6" || $poll['polluant'] === "SO2") {
                continue;
            }
            $p = $poll['polluant'];
            if (!isset($groupData[$p])) {
                $groupData[$p] = 0;
                $counts[$p] = 0;
            }
            $groupData[$p] += $poll['avg_value'];
            $counts[$p] += 1;
        }
        $stmtPoll->close();
    }
    foreach($groupData as $p => &$value){
        if($data_type === "habitants" || $data_type === "superficie"){
            $value = $counts[$p] > 0 ? ($value / $counts[$p]) : 0;
        } else {
            $value = $counts[$p] > 0 ? round($value / $counts[$p], 2) : 0;
        }
    }
    return $groupData;
}

$data_type = $_POST['data_type'] ?? 'historique';
$month = $_POST['month'] ?? '';
$pollutantFilter = $_POST['pollutant'] ?? '';
$citiesParam = $_POST['cities'] ?? '';
if (empty($citiesParam)) {
    echo json_encode(["error" => "Aucune ville sélectionnée."]);
    exit;
}
$cities = explode(',', $citiesParam);
$cities = array_map('trim', $cities);

$comparisonData = [];
$allPollutants = [];

foreach ($cities as $cityName) {
    if (strpos($cityName, 'DEPT:') === 0) {
        $groupValue = trim(substr($cityName, 5));
        $cityData = getGroupData('department', $groupValue, $data_type, $month, $pollutantFilter, $db);
    } elseif (strpos($cityName, 'REG:') === 0) {
        $groupValue = trim(substr($cityName, 4));
        $cityData = getGroupData('region', $groupValue, $data_type, $month, $pollutantFilter, $db);
    } elseif (strpos($cityName, 'SUPERF:') === 0) {
        $groupValue = trim(substr($cityName, 8));
        $cityData = getGroupData('superficie', $groupValue, $data_type, $month, $pollutantFilter, $db);
    } elseif (strpos($cityName, 'POP:') === 0) {
        $groupValue = trim(substr($cityName, 5));
        $cityData = getGroupData('population', $groupValue, $data_type, $month, $pollutantFilter, $db);
    } elseif (strpos($cityName, 'DENS:') === 0) {
        $groupValue = trim(substr($cityName, 6));
        $cityData = getGroupData('densite', $groupValue, $data_type, $month, $pollutantFilter, $db);
    } else {
        $stmt = $db->prepare("SELECT id_ville FROM donnees_villes WHERE ville = ? LIMIT 1");
        $stmt->bind_param("s", $cityName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $id_ville = (int)$row['id_ville'];
            if ($data_type == "historique") {
                if (!empty($month)) {
                    $col = "moy_" . $month;
                    $query = "SELECT polluant, ROUND($col, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
                } else {
                    $query = "SELECT polluant, ROUND(avg_value, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
                }
            } elseif ($data_type == "predictions") {
                if (!empty($month)) {
                    $query = "SELECT polluant, ROUND($month, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
                } else {
                    $query = "SELECT polluant, ROUND(avg_value, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
                }
            } elseif ($data_type == "habitants") {
                // Pour habitants, ne pas arrondir
                $query = "SELECT polluant, avg_par_habitant as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            } elseif ($data_type == "superficie") {
                // Pour superficie, ne pas arrondir
                $query = "SELECT polluant, avg_par_km2 as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            }
            if (!empty($pollutantFilter)) {
                $query .= " AND polluant = ?";
                $stmtPoll = $db->prepare($query);
                $stmtPoll->bind_param("is", $id_ville, $pollutantFilter);
            } else {
                $stmtPoll = $db->prepare($query);
                $stmtPoll->bind_param("i", $id_ville);
            }
            $stmtPoll->execute();
            $resultPoll = $stmtPoll->get_result();
            $cityData = [];
            while ($poll = $resultPoll->fetch_assoc()) {
                // Filtrer les valeurs négatives et les polluants "C6H6" et "SO2"
                if ($poll['avg_value'] < 0 || $poll['polluant'] === "C6H6" || $poll['polluant'] === "SO2") {
                    continue;
                }
                $cityData[$poll['polluant']] = $poll['avg_value'];
                if (!in_array($poll['polluant'], $allPollutants)) {
                    $allPollutants[] = $poll['polluant'];
                }
            }
            $stmtPoll->close();
        }
        $stmt->close();
    }
    $comparisonData[$cityName] = $cityData;
}

sort($allPollutants);

$chartLabels = $allPollutants;
$datasets = [];
$colorIndex = 0;
$subtleColors = [
    "#6b8e23", "#8fbc8f", "#4682b4", "#a9a9a9", "#d2b48c",
    "#b0c4de", "#90ee90", "#f4a460", "#cd853f", "#d3d3d3"
];
foreach ($comparisonData as $groupLabel => $pollData) {
    $dataValues = [];
    foreach ($allPollutants as $poll) {
        $dataValues[] = isset($pollData[$poll]) ? floatval($pollData[$poll]) : 0;
    }
    $color = $subtleColors[$colorIndex % count($subtleColors)];
    $datasets[] = [
        "label" => $groupLabel,
        "data" => $dataValues,
        "backgroundColor" => $color
    ];
    $colorIndex++;
}

$chartData = [
    "labels" => $chartLabels,
    "datasets" => $datasets
];

$tableHtml = "<table class='table table-bordered'><thead><tr><th>Polluant</th>";
$i = 1;
foreach ($comparisonData as $groupLabel => $pollData) {
    $link = "../fonctionnalites/details.php?ville=" . urlencode($groupLabel);
    $tableHtml .= "<th>" . $i . ". <a href='" . $link . "'>" . htmlspecialchars($groupLabel) . "</a></th>";
    $i++;
}
$tableHtml .= "</tr></thead><tbody>";
foreach ($allPollutants as $poll) {
    $tableHtml .= "<tr><td>" . htmlspecialchars($poll) . "</td>";
    foreach ($comparisonData as $groupLabel => $pollData) {
        $value = isset($pollData[$poll]) ? $pollData[$poll] : "-";
        $tableHtml .= "<td>" . htmlspecialchars($value) . "</td>";
    }
    $tableHtml .= "</tr>";
}
$tableHtml .= "</tbody></table>";

echo json_encode(["chartData" => $chartData, "tableHtml" => $tableHtml]);
exit;
?>
