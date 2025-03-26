<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../bd/bd.php';

// Vérif des paramètres obligatoires
if(!isset($_GET['idVille']) || !isset($_GET['tab'])) {
    echo json_encode(['error'=>'Missing parameters']);
    exit;
}

$idVille  = (int) $_GET['idVille'];
$tab      = $_GET['tab']; // 'historique' ou 'predictions'
$polluant = isset($_GET['polluant']) ? trim($_GET['polluant']) : '';
$mois     = isset($_GET['mois']) ? trim($_GET['mois']) : '';

// Liste des colonnes
$monthsHistorique = [
    'moy_janv2023','moy_fev2023','moy_mar2023','moy_avril2023','moy_mai2023','moy_juin2023',
    'moy_juil2023','moy_aout2023','moy_sept2023','moy_oct2023','moy_nov2023','moy_dec2023',
    'moy_janv2024','moy_fev2024','moy_mar2024','moy_avril2024','moy_mai2024','moy_juin2024',
    'moy_juil2024','moy_aout2024','moy_sept2024','moy_oct2024','moy_nov2024','moy_dec2024',
    'moy_janv2025'
];

$monthsPrediction = [
    'moy_predic_janv2025','moy_predic_fev2025','moy_predic_mars2025','moy_predic_avril2025',
    'moy_predic_mai2025','moy_predic_juin2025','moy_predic_juil2025','moy_predic_aout2025',
    'moy_predic_sept2025','moy_predic_oct2025','moy_predic_nov2025','moy_predic_dec2025',
    'moy_predic_janv2026'
];

$allCols = array_merge($monthsHistorique, $monthsPrediction);
$colsStr = implode(',', $allCols);

// Requête pour la table moy_pollution_villes
$sql = "SELECT polluant, $colsStr
        FROM moy_pollution_villes
        WHERE id_ville = ?";
if($polluant !== '') {
    $sql .= " AND polluant = ?";
}
$stmt = $conn->prepare($sql);
if($polluant !== '') {
    $stmt->bind_param("is", $idVille, $polluant);
} else {
    $stmt->bind_param("i", $idVille);
}
$stmt->execute();
$res = $stmt->get_result();
$monthlyData = [];
while($r = $res->fetch_assoc()) {
    $monthlyData[] = $r;
}
$stmt->close();

// Récupération des données journalières si un mois est sélectionné
$dailyData = [];
if($mois !== '') {
    // Déterminer si c’est un mois d’historique ou de prédiction
    $isPrediction = (strpos($mois, 'predic') !== false);

    // Ex: moy_janv2023 => on parse "janv2023"
    // Ex: moy_predic_mars2025 => on parse "mars2025"
    $temp = str_replace(['moy_', 'predic_'], '', $mois); // ex: "janv2023" ou "mars2025"

    // On mappe le nom du mois sur un numéro
    $mapMois = [
        'janv' =>1,'fev'=>2,'févr'=>2,'mar'=>3,'mars'=>3,'avril'=>4,'avr'=>4,'mai'=>5,'juin'=>6,
        'juil'=>7,'aout'=>8,'sept'=>9,'oct'=>10,'nov'=>11,'dec'=>12,'déc'=>12
    ];
    // On récupère la partie "janv" ou "mars"
    // + l’année (2023, 2024, 2025, 2026)
    if(preg_match('/^([a-zé]+)([0-9]+)/i', $temp, $m)) {
        $moisStr  = $m[1];
        $anneeStr = $m[2];
        $moisNum  = isset($mapMois[$moisStr]) ? $mapMois[$moisStr] : 1;
        $anneeNum = (int)$anneeStr;

        // Table source
        $tableSource = $isPrediction ? 'prediction_cities' : 'all_years_cleaned_daily';
        // Nom de la colonne de valeur
        $colValeur   = $isPrediction ? 'valeur_predite' : 'valeur_journaliere';

        // Construction requête
        $sql2 = "SELECT jour, polluant, $colValeur AS val
                 FROM $tableSource
                 WHERE id_ville = ?
                   AND YEAR(jour) = ?
                   AND MONTH(jour) = ? ";
        if($polluant !== '') {
            $sql2 .= " AND polluant = ? ";
        }
        $sql2 .= " ORDER BY jour ASC";

        $stmt2 = $conn->prepare($sql2);
        if($polluant !== '') {
            $stmt2->bind_param("iiis", $idVille, $anneeNum, $moisNum, $polluant);
        } else {
            $stmt2->bind_param("iii", $idVille, $anneeNum, $moisNum);
        }
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        while($row2 = $r2->fetch_assoc()) {
            $dailyData[] = $row2;
        }
        $stmt2->close();
    }
}

// On renvoie le JSON
echo json_encode([
    'monthlyData' => $monthlyData,
    'dailyData'   => $dailyData
]);
exit;
