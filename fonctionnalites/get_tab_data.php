<?php
// Pour le débogage, activez temporairement les exceptions MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once '../bd/bd.php'; // Connexion à la BD

header('Content-Type: application/json');

try {
    // Récupération des paramètres POST
    $tab             = isset($_POST['tab']) ? $_POST['tab'] : '';
    $pollutantFilter = isset($_POST['pollutant']) ? $_POST['pollutant'] : '';
    $monthFilter     = isset($_POST['month']) ? $_POST['month'] : '';
    $id_ville        = isset($_POST['id_ville']) ? intval($_POST['id_ville']) : 0;

    if (empty($tab) || $id_ville <= 0) {
        echo json_encode(["error" => "Paramètres manquants"]);
        exit;
    }

    // Couleurs pour les graphiques
    $defaultColor   = "rgba(75, 192, 192, 0.6)";
    $highlightColor = "rgba(255, 99, 132, 0.8)";

    // Tableau associatif pour convertir une abréviation de mois en numéro
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

    if ($tab === "historique") {

        // ===============================
        // BAR CHART pour Historique
        // ===============================
        $sql = "SELECT polluant, avg_value";
        if (!empty($monthFilter)) {
            // Exemple : si $monthFilter vaut "janv2023", la colonne recherchée sera "moy_janv2023"
            $col = "moy_" . $monthFilter;
            $sql .= ", $col as monthly_avg";
        } else {
            $sql .= ", NULL as monthly_avg";
        }
        $sql .= " FROM moy_pollution_villes WHERE id_ville = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_ville);
        $stmt->execute();
        $result = $stmt->get_result();

        $barLabels = [];
        $barValues = [];
        $barColors = [];
        while ($row = $result->fetch_assoc()) {
            $poll = $row['polluant'];
            $barLabels[] = $poll;
            if (!empty($monthFilter) && isset($row['monthly_avg'])) {
                $value = $row['monthly_avg'];
            } else {
                $value = $row['avg_value'];
            }
            $barValues[] = round(floatval($value), 2);
            if (!empty($pollutantFilter) && $poll == $pollutantFilter) {
                $barColors[] = $highlightColor;
            } else {
                $barColors[] = $defaultColor;
            }
        }
        $stmt->close();

        // ===============================
        // TIME CHART pour Historique
        // ===============================
        $dailyQuery = "SELECT jour, polluant, valeur_journaliere FROM all_years_cleaned_daily WHERE id_ville = ?";
        if (!empty($monthFilter)) {
            // Convertir par exemple "janv2023" en "2023-01%"
            $monAbbr = substr($monthFilter, 0, strpos($monthFilter, "2")); // ex: "janv"
            $yearStr = substr($monthFilter, strlen($monAbbr));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "%";
                $dailyQuery .= " AND jour LIKE ?";
            } else {
                $dateLike = "";
            }
        }
        $stmtDaily = $conn->prepare($dailyQuery);
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
            if (!empty($pollutantFilter) && $poll != $pollutantFilter) {
                continue;
            }
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
        $colors = [
            "rgba(255, 99, 132, 0.6)",
            "rgba(54, 162, 235, 0.6)",
            "rgba(255, 206, 86, 0.6)",
            "rgba(75, 192, 192, 0.6)"
        ];
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

        // ===============================
        // TABLEAU pour Historique
        // ===============================
        $historiqueMonths = [];
        $monthsArr = ["janv", "fev", "mars", "avril", "mai", "juin", "juil", "aout", "sept", "oct", "nov", "dec"];
        for ($year = 2023; $year <= 2025; $year++) {
            foreach ($monthsArr as $index => $mon) {
                if ($year == 2025 && $index > 0) break;
                $historiqueMonths[] = $mon . $year;
            }
        }
        $sqlTable = "SELECT polluant, avg_value";
        foreach ($historiqueMonths as $colVal) {
            $colName = "moy_" . $colVal;
            $sqlTable .= ", $colName as `$colName`";
        }
        $sqlTable .= " FROM moy_pollution_villes WHERE id_ville = ? LIMIT 1";
        $stmtTable = $conn->prepare($sqlTable);
        $stmtTable->bind_param("i", $id_ville);
        $stmtTable->execute();
        $resultTable = $stmtTable->get_result();
        $tableHtml = "";
        if ($rowTable = $resultTable->fetch_assoc()) {
            $tableHtml .= "<table class='table table-striped'>";
            $tableHtml .= "<thead><tr><th>Polluant</th>";
            foreach ($historiqueMonths as $colVal) {
                $monAbbr = substr($colVal, 0, strpos($colVal, "2"));
                $yearStr = substr($colVal, strlen($monAbbr));
                $tableHtml .= "<th>" . ucfirst($monAbbr) . ". " . $yearStr . "</th>";
            }
            $tableHtml .= "</tr></thead><tbody>";
            $tableHtml .= "<tr>";
            $tableHtml .= "<td>" . htmlspecialchars($rowTable['polluant']) . "</td>";
            foreach ($historiqueMonths as $colVal) {
                $colName = "moy_" . $colVal;
                $val = isset($rowTable[$colName]) ? round(floatval($rowTable[$colName]), 2) : "";
                $tableHtml .= "<td>" . htmlspecialchars($val) . "</td>";
            }
            $tableHtml .= "</tr>";
            $tableHtml .= "</tbody></table>";
        } else {
            $tableHtml = "<p>Aucune donnée de tableau disponible.</p>";
        }
        $stmtTable->close();

        $response = [
            "barData" => [
                "labels" => $barLabels,
                "values" => $barValues,
                "colors" => $barColors
            ],
            "timeData" => [
                "labels"   => $timeLabels,
                "datasets" => $timeDatasets
            ],
            "tableHtml" => $tableHtml
        ];
        echo json_encode($response);
        exit;

    } else if ($tab === "predictions") {

        // ===============================
        // BAR CHART pour Prédictions
        // ===============================
        $sql = "SELECT polluant, avg_value";
        if (!empty($monthFilter)) {
            // $monthFilter est du type "moy_predic_janv2025"
            $col = $monthFilter;
            $sql .= ", $col as monthly_avg";
        } else {
            $sql .= ", NULL as monthly_avg";
        }
        $sql .= " FROM moy_pollution_villes WHERE id_ville = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_ville);
        $stmt->execute();
        $result = $stmt->get_result();

        $barLabels = [];
        $barValues = [];
        $barColors = [];
        while ($row = $result->fetch_assoc()) {
            $poll = $row['polluant'];
            $barLabels[] = $poll;
            if (!empty($monthFilter) && isset($row['monthly_avg'])) {
                $value = $row['monthly_avg'];
            } else {
                $value = $row['avg_value'];
            }
            $barValues[] = round(floatval($value), 2);
            if (!empty($pollutantFilter) && $poll == $pollutantFilter) {
                $barColors[] = $highlightColor;
            } else {
                $barColors[] = $defaultColor;
            }
        }
        $stmt->close();

        // ===============================
        // TIME CHART pour Prédictions
        // ===============================
        $dailyQuery = "SELECT jour, polluant, valeur_predite FROM prediction_cities WHERE id_ville = ?";
        if (!empty($monthFilter)) {
            // $monthFilter est du type "moy_predic_janv2025" : extraire "janv2025"
            $temp = str_replace("moy_predic_", "", $monthFilter);
            $monAbbr = substr($temp, 0, strpos($temp, "2"));
            $yearStr = substr($temp, strlen($monAbbr));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "%";
                $dailyQuery .= " AND jour LIKE ?";
            } else {
                $dateLike = "";
            }
        }
        $stmtDaily = $conn->prepare($dailyQuery);
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
            if (!empty($pollutantFilter) && $poll != $pollutantFilter) {
                continue;
            }
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
        $colors = [
            "rgba(255, 99, 132, 0.6)",
            "rgba(54, 162, 235, 0.6)",
            "rgba(255, 206, 86, 0.6)",
            "rgba(75, 192, 192, 0.6)"
        ];
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

        // ===============================
        // TABLEAU pour Prédictions
        // ===============================
        $predictionsMonths = [];
        for ($year = 2025; $year <= 2026; $year++) {
            foreach ($monthsArr as $index => $mon) {
                if ($year == 2026 && $index > 0) break;
                $predictionsMonths[] = "moy_predic_" . $mon . $year;
            }
        }
        $sqlTable = "SELECT polluant, avg_value";
        foreach ($predictionsMonths as $colName) {
            $sqlTable .= ", $colName as `$colName`";
        }
        $sqlTable .= " FROM moy_pollution_villes WHERE id_ville = ? LIMIT 1";
        $stmtTable = $conn->prepare($sqlTable);
        $stmtTable->bind_param("i", $id_ville);
        $stmtTable->execute();
        $resultTable = $stmtTable->get_result();
        $tableHtml = "";
        if ($rowTable = $resultTable->fetch_assoc()) {
            $tableHtml .= "<table class='table table-striped'>";
            $tableHtml .= "<thead><tr><th>Polluant</th>";
            foreach ($predictionsMonths as $colName) {
                $temp = str_replace("moy_predic_", "", $colName);
                $monAbbr = substr($temp, 0, strpos($temp, "2"));
                $yearStr = substr($temp, strlen($monAbbr));
                $tableHtml .= "<th>" . ucfirst($monAbbr) . ". " . $yearStr . "</th>";
            }
            $tableHtml .= "</tr></thead><tbody>";
            $tableHtml .= "<tr>";
            $tableHtml .= "<td>" . htmlspecialchars($rowTable['polluant']) . "</td>";
            foreach ($predictionsMonths as $colName) {
                $val = isset($rowTable[$colName]) ? round(floatval($rowTable[$colName]), 2) : "";
                $tableHtml .= "<td>" . htmlspecialchars($val) . "</td>";
            }
            $tableHtml .= "</tr>";
            $tableHtml .= "</tbody></table>";
        } else {
            $tableHtml = "<p>Aucune donnée de tableau disponible.</p>";
        }
        $stmtTable->close();

        $response = [
            "barData" => [
                "labels" => $barLabels,
                "values" => $barValues,
                "colors" => $barColors
            ],
            "timeData" => [
                "labels"   => $timeLabels,
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