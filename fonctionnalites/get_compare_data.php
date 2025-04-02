<?php
/**
 * get_compare_data.php
 *
 * Ce script récupère les données de pollution pour une comparaison entre plusieurs villes.
 * Il effectue des requêtes sur la base de données pour extraire les valeurs moyennes
 * de pollution (historique ou prédiction) pour chaque polluant, en fonction des villes sélectionnées.
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

$data_type = $_POST['data_type'] ?? 'historique'; // "historique" ou "predictions"
$month = $_POST['month'] ?? ''; // ex. "janv2023" ou "moy_predic_janv2025"
$pollutantFilter = $_POST['pollutant'] ?? ''; // filtre par polluant
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
        } else {
            if (!empty($month)) {
                $query = "SELECT polluant, ROUND($month, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            } else {
                $query = "SELECT polluant, ROUND(avg_value, 2) as avg_value FROM moy_pollution_villes WHERE id_ville = ?";
            }
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
            $cityData[$poll['polluant']] = $poll['avg_value'];
            if (!in_array($poll['polluant'], $allPollutants)) {
                $allPollutants[] = $poll['polluant'];
            }
        }
        $stmtPoll->close();
        $comparisonData[$cityName] = $cityData;
    }
    $stmt->close();
}

sort($allPollutants);

$chartLabels = $allPollutants;
$datasets = [];
$totalCities = count($comparisonData);
$colorIndex = 0;
// Utilisation d'une palette de couleurs subtiles prédéfinies
$subtleColors = [
    "#6b8e23", "#8fbc8f", "#4682b4", "#a9a9a9", "#d2b48c",
    "#b0c4de", "#90ee90", "#f4a460", "#cd853f", "#d3d3d3"
];
foreach ($comparisonData as $cityName => $pollData) {
    $dataValues = [];
    foreach ($allPollutants as $poll) {
        $dataValues[] = isset($pollData[$poll]) ? floatval($pollData[$poll]) : 0;
    }
// Utilisation cyclique des couleurs de la palette
    $color = $subtleColors[$colorIndex % count($subtleColors)];
    $datasets[] = [
        "label" => $cityName,
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
foreach ($comparisonData as $cityName => $pollData) {
    $link = "../fonctionnalites/details.php?ville=" . urlencode($cityName);
    $tableHtml .= "<th>" . $i . ". <a href='" . $link . "'>" . htmlspecialchars($cityName) . "</a></th>";
    $i++;
}
$tableHtml .= "</tr></thead><tbody>";
foreach ($allPollutants as $poll) {
    $tableHtml .= "<tr><td>" . htmlspecialchars($poll) . "</td>";
    foreach ($comparisonData as $cityName => $pollData) {
        $value = isset($pollData[$poll]) ? $pollData[$poll] : "-";
        $tableHtml .= "<td>" . htmlspecialchars($value) . "</td>";
    }
    $tableHtml .= "</tr>";
}
$tableHtml .= "</tbody></table>";

echo json_encode(["chartData" => $chartData, "tableHtml" => $tableHtml]);
exit;
?>