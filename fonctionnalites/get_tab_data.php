<?php
// Pour le débogage, activez temporairement les exceptions MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once '../bd/bd.php';
$db = new Database();

header('Content-Type: application/json');

// Récupération des paramètres POST
$tab             = isset($_POST['tab']) ? $_POST['tab'] : '';
$pollutantFilter = isset($_POST['pollutant']) ? $_POST['pollutant'] : '';
$monthFilter     = isset($_POST['month']) ? $_POST['month'] : '';
$id_ville        = isset($_POST['id_ville']) ? intval($_POST['id_ville']) : 0;
$page            = isset($_POST['page']) ? intval($_POST['page']) : 1;
$limit           = 5;
$offset          = ($page - 1) * $limit;

if (empty($tab) || $id_ville <= 0) {
    echo json_encode(["error" => "Paramètres manquants"]);
    exit;
}

$defaultColor   = "rgba(75, 192, 192, 0.6)";
$highlightColor = "rgba(255, 99, 132, 0.8)";

$monthsMap = [
    "janv"  => "01",
    "fev"   => "02",
    "mars"  => "03",
    "avril" => "04",
    "mai"   => "05",
    "juin"  => "06",
    "juil"  => "07",
    "aout"  => "08",
    "sept"  => "09",
    "oct"   => "10",
    "nov"   => "11",
    "dec"   => "12"
];

function formatDate($dateStr) {
    $timestamp = strtotime($dateStr);
    $monthsFr = [
        '01' => 'janv.',
        '02' => 'févr.',
        '03' => 'mars',
        '04' => 'avr.',
        '05' => 'mai',
        '06' => 'juin',
        '07' => 'juil.',
        '08' => 'août',
        '09' => 'sept.',
        '10' => 'oct.',
        '11' => 'nov.',
        '12' => 'déc.'
    ];
    $day = date('j', $timestamp);
    $monthNum = date('m', $timestamp);
    $year = date('Y', $timestamp);
    $monthFr = isset($monthsFr[$monthNum]) ? $monthsFr[$monthNum] : $monthNum;
    return $day . ' ' . $monthFr . ' ' . $year;
}

try {
    if ($tab === "historique") {
        // BAR CHART
        $sql = "SELECT polluant, avg_value";
        if (!empty($monthFilter)) {
            $col = "moy_" . $monthFilter;
            $sql .= ", $col as monthly_avg";
        } else {
            $sql .= ", NULL as monthly_avg";
        }
        $sql .= " FROM moy_pollution_villes WHERE id_ville = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $id_ville);
        $stmt->execute();
        $result = $stmt->get_result();
        $barLabels = $barValues = $barColors = [];
        while ($row = $result->fetch_assoc()) {
            $poll = $row['polluant'];
            $barLabels[] = $poll;
            $value = (!empty($monthFilter) && isset($row['monthly_avg'])) ? $row['monthly_avg'] : $row['avg_value'];
            $barValues[] = round(floatval($value), 2);
            $barColors[] = (!empty($pollutantFilter) && $poll == $pollutantFilter) ? $highlightColor : $defaultColor;
        }
        $stmt->close();

        // TIME CHART (daily)
        $dailyQuery = "SELECT jour, polluant, valeur_journaliere FROM all_years_cleaned_daily WHERE id_ville = ?";
        if (!empty($monthFilter)) {
            $monAbbr = substr($monthFilter, 0, strpos($monthFilter, "2"));
            $yearStr = substr($monthFilter, strpos($monthFilter, "2"));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "%";
                $dailyQuery .= " AND jour LIKE ?";
            } else {
                $dateLike = "";
            }
        }
        $stmtDaily = $db->prepare($dailyQuery);
        if (!empty($monthFilter)) {
            $stmtDaily->bind_param("is", $id_ville, $dateLike);
        } else {
            $stmtDaily->bind_param("i", $id_ville);
        }
        $stmtDaily->execute();
        $resultDaily = $stmtDaily->get_result();
        $timeLabels = [];
        $timeDataPerPoll = [];
        while ($row = $resultDaily->fetch_assoc()) {
            $date  = $row['jour'];
            $poll  = $row['polluant'];
            $value = round(floatval($row['valeur_journaliere']), 2);
            if (!empty($pollutantFilter) && $poll != $pollutantFilter) continue;
            if (!in_array($date, $timeLabels)) {
                $timeLabels[] = $date;
            }
            if (!isset($timeDataPerPoll[$poll])) {
                $timeDataPerPoll[$poll] = [];
            }
            $timeDataPerPoll[$poll][$date] = $value;
        }
        $stmtDaily->close();
        sort($timeLabels);
        $timeDatasets = [];
        $colors = ["rgba(255, 99, 132, 0.6)", "rgba(54, 162, 235, 0.6)", "rgba(255, 206, 86, 0.6)", "rgba(75, 192, 192, 0.6)"];
        $colorIndex = 0;
        foreach ($timeDataPerPoll as $poll => $dataPoints) {
            $values = [];
            foreach ($timeLabels as $label) {
                $values[] = isset($dataPoints[$label]) ? $dataPoints[$label] : null;
            }
            $timeDatasets[] = [
                "label"           => $poll,
                "data"            => $values,
                "borderColor"     => $colors[$colorIndex % count($colors)],
                "backgroundColor" => $colors[$colorIndex % count($colors)],
                "fill"            => false
            ];
            $colorIndex++;
        }

        // Tableau pivoté pour Historique
        if (empty($monthFilter)) {
            $allMonths = [];
            $monthsArr = ["janv", "fev", "mars", "avril", "mai", "juin", "juil", "aout", "sept", "oct", "nov", "dec"];
            for ($year = 2023; $year <= 2024; $year++) {
                foreach ($monthsArr as $mon) {
                    $allMonths[] = $mon . $year;
                }
            }
            $allMonths[] = "janv2025";
            $pagedMonths = array_slice($allMonths, $offset, $limit);
            $sqlTable = "SELECT polluant, avg_value";
            foreach ($pagedMonths as $mCode) {
                $sqlTable .= ", moy_" . $mCode . " as moy_" . $mCode;
            }
            $sqlTable .= " FROM moy_pollution_villes WHERE id_ville = ?";
            if (!empty($pollutantFilter)) {
                $sqlTable .= " AND polluant = ?";
            }
            $stmtTable = $db->prepare($sqlTable);
            if (!empty($pollutantFilter)) {
                $stmtTable->bind_param("is", $id_ville, $pollutantFilter);
            } else {
                $stmtTable->bind_param("i", $id_ville);
            }
            $stmtTable->execute();
            $resultTable = $stmtTable->get_result();
            $data = [];
            while ($row = $resultTable->fetch_assoc()) {
                $data[$row['polluant']] = $row;
            }
            $stmtTable->close();
            $polluants = array_keys($data);
            $tableHtml = "<table class='table table-striped'><thead><tr><th>Mois</th>";
            foreach ($polluants as $p) {
                $tableHtml .= "<th>" . htmlspecialchars($p) . "</th>";
            }
            $tableHtml .= "</tr></thead><tbody>";
            foreach ($pagedMonths as $mCode) {
                $monAbbr = substr($mCode, 0, strpos($mCode, "2"));
                $yearStr = substr($mCode, strpos($mCode, "2"));
                $displayMonth = ucfirst($monAbbr) . ". " . $yearStr;
                $tableHtml .= "<tr><td>" . htmlspecialchars($displayMonth) . "</td>";
                foreach ($polluants as $p) {
                    $colName = "moy_" . $mCode;
                    $val = isset($data[$p][$colName]) ? round(floatval($data[$p][$colName]), 2) : "";
                    $tableHtml .= "<td>" . htmlspecialchars($val) . "</td>";
                }
                $tableHtml .= "</tr>";
            }
            $tableHtml .= "</tbody></table>";
            if (count($pagedMonths) == $limit) {
                $tableHtml .= "<button class='btn-load-more' onclick=\"loadMore('historique')\">Voir plus</button>";
            }
        } else {
            $monAbbr = substr($monthFilter, 0, strpos($monthFilter, "2"));
            $yearStr = substr($monthFilter, strpos($monthFilter, "2"));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "-%";
            } else {
                $dateLike = "";
            }
            $sqlTable = "SELECT jour, polluant, valeur_journaliere FROM all_years_cleaned_daily WHERE id_ville = ? AND jour LIKE ?";
            if (!empty($pollutantFilter)) {
                $sqlTable .= " AND polluant = ?";
            }
            $stmtTable = $db->prepare($sqlTable);
            if (!empty($pollutantFilter)) {
                $stmtTable->bind_param("iss", $id_ville, $dateLike, $pollutantFilter);
            } else {
                $stmtTable->bind_param("is", $id_ville, $dateLike);
            }
            $stmtTable->execute();
            $resultTable = $stmtTable->get_result();
            $tableHtml = "<table class='table table-striped'><thead><tr>
                           <th>Date</th>
                           <th>Polluant</th>
                           <th>Valeur Journalière</th>
                           </tr></thead><tbody>";
            while ($row = $resultTable->fetch_assoc()) {
                $formattedDate = formatDate($row['jour']);
                $tableHtml .= "<tr>";
                $tableHtml .= "<td>" . htmlspecialchars($formattedDate) . "</td>";
                $tableHtml .= "<td>" . htmlspecialchars($row['polluant']) . "</td>";
                $tableHtml .= "<td>" . htmlspecialchars(round(floatval($row['valeur_journaliere']), 2)) . "</td>";
                $tableHtml .= "</tr>";
            }
            $tableHtml .= "</tbody></table>";
            $stmtTable->close();
        }

        $response = [
            "barData" => [
                "labels" => $barLabels,
                "values" => $barValues,
                "colors" => $barColors
            ],
            "timeData" => [
                "labels" => $timeLabels,
                "datasets" => $timeDatasets
            ],
            "tableHtml" => $tableHtml
        ];
        echo json_encode($response);
        exit;
    } else if ($tab === "predictions") {
        // BAR CHART pour Prédictions
        $sql = "SELECT polluant, avg_value";
        if (!empty($monthFilter)) {
            $col = $monthFilter;
            $sql .= ", $col as monthly_avg";
        } else {
            $sql .= ", NULL as monthly_avg";
        }
        $sql .= " FROM moy_pollution_villes WHERE id_ville = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $id_ville);
        $stmt->execute();
        $result = $stmt->get_result();
        $barLabels = $barValues = $barColors = [];
        while ($row = $result->fetch_assoc()) {
            $poll = $row['polluant'];
            $barLabels[] = $poll;
            $value = (!empty($monthFilter) && isset($row['monthly_avg'])) ? $row['monthly_avg'] : $row['avg_value'];
            $barValues[] = round(floatval($value), 2);
            $barColors[] = (!empty($pollutantFilter) && $poll == $pollutantFilter) ? $highlightColor : $defaultColor;
        }
        $stmt->close();

        // TIME CHART pour Prédictions
        $dailyQuery = "SELECT jour, polluant, valeur_predite FROM prediction_cities WHERE id_ville = ?";
        if (!empty($monthFilter)) {
            $temp = str_replace("moy_predic_", "", $monthFilter);
            $monAbbr = substr($temp, 0, strpos($temp, "2"));
            $yearStr = substr($temp, strpos($temp, "2"));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "%";
                $dailyQuery .= " AND jour LIKE ?";
            } else {
                $dateLike = "";
            }
        }
        $stmtDaily = $db->prepare($dailyQuery);
        if (!empty($monthFilter)) {
            $stmtDaily->bind_param("is", $id_ville, $dateLike);
        } else {
            $stmtDaily->bind_param("i", $id_ville);
        }
        $stmtDaily->execute();
        $resultDaily = $stmtDaily->get_result();
        $timeLabels = [];
        $timeDataPerPoll = [];
        while ($row = $resultDaily->fetch_assoc()) {
            $date  = $row['jour'];
            $poll  = $row['polluant'];
            $value = round(floatval($row['valeur_predite']), 2);
            if (!empty($pollutantFilter) && $poll != $pollutantFilter) continue;
            if (!in_array($date, $timeLabels)) {
                $timeLabels[] = $date;
            }
            if (!isset($timeDataPerPoll[$poll])) {
                $timeDataPerPoll[$poll] = [];
            }
            $timeDataPerPoll[$poll][$date] = $value;
        }
        $stmtDaily->close();
        sort($timeLabels);
        $timeDatasets = [];
        $colors = ["rgba(255, 99, 132, 0.6)", "rgba(54, 162, 235, 0.6)", "rgba(255, 206, 86, 0.6)", "rgba(75, 192, 192, 0.6)"];
        $colorIndex = 0;
        foreach ($timeDataPerPoll as $poll => $dataPoints) {
            $values = [];
            foreach ($timeLabels as $label) {
                $values[] = isset($dataPoints[$label]) ? $dataPoints[$label] : null;
            }
            $timeDatasets[] = [
                "label"           => $poll,
                "data"            => $values,
                "borderColor"     => $colors[$colorIndex % count($colors)],
                "backgroundColor" => $colors[$colorIndex % count($colors)],
                "fill"            => false
            ];
            $colorIndex++;
        }

        // Tableau pour Prédictions
        if (empty($monthFilter)) {
            $monthsArr = ["janv", "fev", "mars", "avril", "mai", "juin", "juil", "aout", "sept", "oct", "nov", "dec"];
            $allMonths = [];
            for ($year = 2025; $year <= 2026; $year++) {
                foreach ($monthsArr as $index => $mon) {
                    if ($year == 2026 && $index > 0) break;
                    $allMonths[] = $mon . $year;
                }
            }
            $pagedMonths = array_slice($allMonths, $offset, $limit);
            $sqlTable = "SELECT polluant, avg_value";
            foreach ($pagedMonths as $mCode) {
                $sqlTable .= ", moy_predic_" . $mCode . " as moy_predic_" . $mCode;
            }
            $sqlTable .= " FROM moy_pollution_villes WHERE id_ville = ?";
            if (!empty($pollutantFilter)) {
                $sqlTable .= " AND polluant = ?";
            }
            $stmtTable = $db->prepare($sqlTable);
            if (!empty($pollutantFilter)) {
                $stmtTable->bind_param("is", $id_ville, $pollutantFilter);
            } else {
                $stmtTable->bind_param("i", $id_ville);
            }
            $stmtTable->execute();
            $resultTable = $stmtTable->get_result();
            $data = [];
            while ($row = $resultTable->fetch_assoc()) {
                $data[$row['polluant']] = $row;
            }
            $stmtTable->close();
            $polluants = array_keys($data);
            $tableHtml = "<table class='table table-striped'><thead><tr><th>Mois</th>";
            foreach ($polluants as $p) {
                $tableHtml .= "<th>" . htmlspecialchars($p) . "</th>";
            }
            $tableHtml .= "</tr></thead><tbody>";
            foreach ($pagedMonths as $mCode) {
                $monAbbr = substr($mCode, 0, strpos($mCode, "2"));
                $yearStr = substr($mCode, strpos($mCode, "2"));
                $displayMonth = ucfirst($monAbbr) . ". " . $yearStr;
                $tableHtml .= "<tr><td>" . htmlspecialchars($displayMonth) . "</td>";
                foreach ($polluants as $p) {
                    $colName = "moy_predic_" . $mCode;
                    $val = isset($data[$p][$colName]) ? round(floatval($data[$p][$colName]), 2) : "";
                    $tableHtml .= "<td>" . htmlspecialchars($val) . "</td>";
                }
                $tableHtml .= "</tr>";
            }
            $tableHtml .= "</tbody></table>";
            if (count($pagedMonths) == $limit) {
                $tableHtml .= "<button class='btn-load-more' onclick=\"loadMore('predictions')\">Voir plus</button>";
            }
        } else {
            $temp = str_replace("moy_predic_", "", $monthFilter);
            $monAbbr = substr($temp, 0, strpos($temp, "2"));
            $yearStr = substr($temp, strpos($temp, "2"));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "-%";
            } else {
                $dateLike = "";
            }
            $sqlTable = "SELECT jour, polluant, valeur_predite FROM prediction_cities WHERE id_ville = ? AND jour LIKE ? ";
            if (empty($pollutantFilter)) {
                $sqlTable .= " ORDER BY jour ASC";
            }
            if (!empty($pollutantFilter)) {
                $sqlTable .= " AND polluant = ?";
            }
            $stmtTable = $db->prepare($sqlTable);
            if (!empty($pollutantFilter)) {
                $stmtTable->bind_param("iss", $id_ville, $dateLike, $pollutantFilter);
            } else {
                $stmtTable->bind_param("is", $id_ville, $dateLike);
            }
            $stmtTable->execute();
            $resultTable = $stmtTable->get_result();
            $tableHtml = "<table class='table table-striped'><thead><tr>
                       <th>Date</th>
                       <th>Polluant</th>
                       <th>Valeur Journalière</th>
                       </tr></thead><tbody>";
            while ($row = $resultTable->fetch_assoc()) {
                $formattedDate = formatDate($row['jour']);
                $tableHtml .= "<tr>";
                $tableHtml .= "<td>" . htmlspecialchars($formattedDate) . "</td>";
                $tableHtml .= "<td>" . htmlspecialchars($row['polluant']) . "</td>";
                $tableHtml .= "<td>" . htmlspecialchars(round(floatval($row['valeur_predite']), 2)) . "</td>";
                $tableHtml .= "</tr>";
            }
            $tableHtml .= "</tbody></table>";
            $stmtTable->close();
        }

        $response = [
            "barData" => [
                "labels" => $barLabels,
                "values" => $barValues,
                "colors" => $barColors
            ],
            "timeData" => [
                "labels" => $timeLabels,
                "datasets" => $timeDatasets
            ],
            "tableHtml" => $tableHtml
        ];
        echo json_encode($response);
        exit;
    } else {
        echo json_encode(["error" => "Onglet non valide"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}
?>
