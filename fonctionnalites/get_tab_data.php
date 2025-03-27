<?php
// Pour le débogage, activez temporairement les exceptions MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once '../bd/bd.php'; // Connexion à la BD

header('Content-Type: application/json');

// Récupération des paramètres POST
$tab             = isset($_POST['tab']) ? $_POST['tab'] : '';
$pollutantFilter = isset($_POST['pollutant']) ? $_POST['pollutant'] : '';
$monthFilter     = isset($_POST['month']) ? $_POST['month'] : ''; // Peut être vide ou contenir une valeur (ex: "janv2023" ou "moy_predic_janv2025")
$id_ville        = isset($_POST['id_ville']) ? intval($_POST['id_ville']) : 0;
// Paramètres de pagination pour le tableau pivoté (mode pivot toujours)
$page            = isset($_POST['page']) ? intval($_POST['page']) : 1;
$limit           = 5; // afficher 5 dates par page
$offset          = ($page - 1) * $limit;

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

// Fonction de formatage de date pour affichage (ex: "2023-03-02" -> "2 mars 2023")
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

        // ---------- Graphiques (Bar & Time) restent inchangés ----------
        // BAR CHART pour Historique
        $sql = "SELECT polluant, avg_value";
        if (!empty($monthFilter)) {
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
            $value = (!empty($monthFilter) && isset($row['monthly_avg'])) ? $row['monthly_avg'] : $row['avg_value'];
            $barValues[] = round(floatval($value), 2);
            $barColors[] = (!empty($pollutantFilter) && $poll == $pollutantFilter) ? $highlightColor : $defaultColor;
        }
        $stmt->close();

        // TIME CHART pour Historique
        $dailyQuery = "SELECT jour, polluant, valeur_journaliere FROM all_years_cleaned_daily WHERE id_ville = ?";
        if (!empty($monthFilter)) {
            // Si un mois est sélectionné, filtrer sur cette période
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

        // ---------- Tableau pivoté pour Historique (pour tous les tableaux, qu'un mois soit sélectionné ou non) ----------
        // Ici, nous récupérons les dates distinctes depuis all_years_cleaned_daily (daily mode)
        // Si $monthFilter est renseigné, on ne prendra que les dates de cette période, sinon toutes les dates disponibles
        $dailyDatesQuery = "SELECT DISTINCT jour FROM all_years_cleaned_daily WHERE id_ville = ?";
        $params = [$id_ville];
        $types = "i";
        if (!empty($monthFilter)) {
            $monAbbr = substr($monthFilter, 0, strpos($monthFilter, "2"));
            $yearStr = substr($monthFilter, strpos($monthFilter, "2"));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "-%";
                $dailyDatesQuery .= " AND jour LIKE ?";
                $params[] = $dateLike;
                $types .= "s";
            }
        }
        $dailyDatesQuery .= " ORDER BY jour ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmtDates = $conn->prepare($dailyDatesQuery);
        $stmtDates->bind_param($types, ...$params);
        $stmtDates->execute();
        $resDates = $stmtDates->get_result();
        $dates = [];
        while ($rowD = $resDates->fetch_assoc()) {
            $dates[] = $rowD['jour'];
        }
        $stmtDates->close();

        if (count($dates) === 0) {
            $tableHtml = "<p>Aucune donnée pour cette page.</p>";
        } else {
            // Récupérer la liste des polluants pour ces dates
            $polluants = [];
            if (!empty($pollutantFilter)) {
                $polluants[] = $pollutantFilter;
            } else {
                $inClause = implode(',', array_fill(0, count($dates), '?'));
                $sqlPolls = "SELECT DISTINCT polluant FROM all_years_cleaned_daily 
                             WHERE id_ville = ? AND jour IN ($inClause) ORDER BY polluant";
                $paramsPolls = array_merge([$id_ville], $dates);
                $typesPolls = "i" . str_repeat("s", count($dates));
                $stmtPolls = $conn->prepare($sqlPolls);
                $stmtPolls->bind_param($typesPolls, ...$paramsPolls);
                $stmtPolls->execute();
                $resPolls = $stmtPolls->get_result();
                while ($rp = $resPolls->fetch_assoc()) {
                    $polluants[] = $rp['polluant'];
                }
                $stmtPolls->close();
            }
            // Récupérer les valeurs pour le pivot
            $inClause = implode(',', array_fill(0, count($dates), '?'));
            if (!empty($pollutantFilter)) {
                $sqlPivot = "SELECT jour, polluant, valeur_journaliere FROM all_years_cleaned_daily 
                             WHERE id_ville = ? AND jour IN ($inClause) AND polluant = ?";
                $pivotParams = array_merge([$id_ville], $dates, [$pollutantFilter]);
                $pivotTypes = "i" . str_repeat("s", count($dates)) . "s";
            } else {
                $sqlPivot = "SELECT jour, polluant, valeur_journaliere FROM all_years_cleaned_daily 
                             WHERE id_ville = ? AND jour IN ($inClause)";
                $pivotParams = array_merge([$id_ville], $dates);
                $pivotTypes = "i" . str_repeat("s", count($dates));
            }
            $stmtPivot = $conn->prepare($sqlPivot);
            $stmtPivot->bind_param($pivotTypes, ...$pivotParams);
            $stmtPivot->execute();
            $resPivot = $stmtPivot->get_result();
            $pivot = [];
            foreach ($dates as $d) {
                $pivot[$d] = [];
                foreach ($polluants as $p) {
                    $pivot[$d][$p] = null;
                }
            }
            while ($rowP = $resPivot->fetch_assoc()) {
                $d = $rowP['jour'];
                $p = $rowP['polluant'];
                $v = round(floatval($rowP['valeur_journaliere']), 2);
                $pivot[$d][$p] = $v;
            }
            $stmtPivot->close();

            // Construction du tableau pivoté : lignes = dates, colonnes = polluants
            $tableHtml = "<table class='table table-striped'>";
            $tableHtml .= "<thead><tr><th>Date</th>";
            foreach ($polluants as $p) {
                $tableHtml .= "<th>" . htmlspecialchars($p) . "</th>";
            }
            $tableHtml .= "</tr></thead><tbody>";
            foreach ($dates as $d) {
                $formattedDate = formatDate($d);
                $tableHtml .= "<tr><td>" . htmlspecialchars($formattedDate) . "</td>";
                foreach ($polluants as $p) {
                    $val = isset($pivot[$d][$p]) ? $pivot[$d][$p] : "";
                    $tableHtml .= "<td>" . htmlspecialchars($val) . "</td>";
                }
                $tableHtml .= "</tr>";
            }
            $tableHtml .= "</tbody></table>";
            if (count($dates) == $limit) {
                $tableHtml .= "<button class='btn-load-more' onclick=\"loadMore('historique')\">Voir plus</button>";
            }
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

        // ===============================
        // BAR CHART pour Prédictions
        // ===============================
        $sql = "SELECT polluant, avg_value";
        if (!empty($monthFilter)) {
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
            $value = (!empty($monthFilter) && isset($row['monthly_avg'])) ? $row['monthly_avg'] : $row['avg_value'];
            $barValues[] = round(floatval($value), 2);
            $barColors[] = (!empty($pollutantFilter) && $poll == $pollutantFilter) ? $highlightColor : $defaultColor;
        }
        $stmt->close();

        // ===============================
        // TIME CHART pour Prédictions
        // ===============================
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
        if (empty($monthFilter)) {
            // Pour Prédictions, mode "Tous les mois" : tableau pivoté avec les mois en lignes et les polluants en colonnes.
            $monthsArr = ["janv", "fev", "mars", "avril", "mai", "juin", "juil", "aout", "sept", "oct", "nov", "dec"];
            $allMonths = [];
            for ($year = 2025; $year <= 2026; $year++) {
                foreach ($monthsArr as $index => $mon) {
                    if ($year == 2026 && $index > 0) break;
                    $allMonths[] = $mon . $year;
                }
            }
            // Pagination : 5 mois par page
            $pagedMonths = array_slice($allMonths, $offset, $limit);
            $sqlTable = "SELECT polluant, avg_value";
            foreach ($allMonths as $mCode) {
                $sqlTable .= ", moy_predic_" . $mCode . " as moy_predic_" . $mCode;
            }
            $sqlTable .= " FROM moy_pollution_villes WHERE id_ville = ?";
            if (!empty($pollutantFilter)) {
                $sqlTable .= " AND polluant = ?";
            }
            $stmtTable = $conn->prepare($sqlTable);
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
            $tableHtml = "<table class='table table-striped'>";
            $tableHtml .= "<thead><tr><th>Mois</th>";
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
            // Pour Prédictions, si un mois est sélectionné : tableau pivoté sur les dates de ce mois
            $temp = str_replace("moy_predic_", "", $monthFilter);
            $monAbbr = substr($temp, 0, strpos($temp, "2"));
            $yearStr = substr($temp, strpos($temp, "2"));
            if (isset($monthsMap[$monAbbr])) {
                $monthNum = $monthsMap[$monAbbr];
                $dateLike = $yearStr . "-" . $monthNum . "-%";
            } else {
                $dateLike = "";
            }
            $sqlDates = "SELECT DISTINCT jour FROM prediction_cities 
                         WHERE id_ville = ? AND jour LIKE ? ORDER BY jour ASC LIMIT ? OFFSET ?";
            $stmtDates = $conn->prepare($sqlDates);
            $stmtDates->bind_param("isii", $id_ville, $dateLike, $limit, $offset);
            $stmtDates->execute();
            $resDates = $stmtDates->get_result();
            $dates = [];
            while ($rowD = $resDates->fetch_assoc()) {
                $dates[] = $rowD['jour'];
            }
            $stmtDates->close();
            if (count($dates) === 0) {
                $tableHtml = "<p>Aucune donnée pour cette page.</p>";
            } else {
                $polluants = [];
                if (!empty($pollutantFilter)) {
                    $polluants[] = $pollutantFilter;
                } else {
                    $inClause = implode(',', array_fill(0, count($dates), '?'));
                    $sqlPolls = "SELECT DISTINCT polluant FROM prediction_cities 
                                 WHERE id_ville = ? AND jour IN ($inClause) ORDER BY polluant";
                    $params = array_merge([$id_ville], $dates);
                    $types = "i" . str_repeat("s", count($dates));
                    $stmtPolls = $conn->prepare($sqlPolls);
                    $stmtPolls->bind_param($types, ...$params);
                    $stmtPolls->execute();
                    $resPolls = $stmtPolls->get_result();
                    while ($rp = $resPolls->fetch_assoc()) {
                        $polluants[] = $rp['polluant'];
                    }
                    $stmtPolls->close();
                }
                $inClause = implode(',', array_fill(0, count($dates), '?'));
                if (!empty($pollutantFilter)) {
                    $sqlPivot = "SELECT jour, polluant, valeur_predite FROM prediction_cities 
                                 WHERE id_ville = ? AND jour IN ($inClause) AND polluant = ?";
                    $pivotParams = array_merge([$id_ville], $dates, [$pollutantFilter]);
                    $pivotTypes = "i" . str_repeat("s", count($dates)) . "s";
                } else {
                    $sqlPivot = "SELECT jour, polluant, valeur_predite FROM prediction_cities 
                                 WHERE id_ville = ? AND jour IN ($inClause)";
                    $pivotParams = array_merge([$id_ville], $dates);
                    $pivotTypes = "i" . str_repeat("s", count($dates));
                }
                $stmtPivot = $conn->prepare($sqlPivot);
                $stmtPivot->bind_param($pivotTypes, ...$pivotParams);
                $stmtPivot->execute();
                $resPivot = $stmtPivot->get_result();
                $pivot = [];
                foreach ($dates as $d) {
                    $pivot[$d] = [];
                    foreach ($polluants as $p) {
                        $pivot[$d][$p] = null;
                    }
                }
                while ($rowP = $resPivot->fetch_assoc()) {
                    $d = $rowP['jour'];
                    $p = $rowP['polluant'];
                    $v = round(floatval($rowP['valeur_predite']), 2);
                    $pivot[$d][$p] = $v;
                }
                $stmtPivot->close();
                $tableHtml = "<table class='table table-striped'>";
                $tableHtml .= "<thead><tr><th>Date</th>";
                foreach ($polluants as $p) {
                    $tableHtml .= "<th>" . htmlspecialchars($p) . "</th>";
                }
                $tableHtml .= "</tr></thead><tbody>";
                foreach ($dates as $d) {
                    $formattedDate = formatDate($d);
                    $tableHtml .= "<tr><td>" . htmlspecialchars($formattedDate) . "</td>";
                    foreach ($polluants as $p) {
                        $val = isset($pivot[$d][$p]) ? $pivot[$d][$p] : "";
                        $tableHtml .= "<td>" . htmlspecialchars($val) . "</td>";
                    }
                    $tableHtml .= "</tr>";
                }
                $tableHtml .= "</tbody></table>";
                if (count($dates) == $limit) {
                    $tableHtml .= "<button class='btn-load-more' onclick=\"loadMore('predictions')\">Voir plus</button>";
                }
            }
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
