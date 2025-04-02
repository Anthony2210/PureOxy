<?php
/**
 * classement.php
 *
 * Cette page affiche le classement des villes en fonction de la pollution pour chaque polluant
 * (sauf 'C6H6'). Elle récupère depuis la base de données les seuils de pollution et les données
 * de classement, puis les organise pour un affichage dynamique.
 *
 * Références :
 * - ChatGPT pour la structuration et la documentation du code.
 *
 * Utilisation :
 * - Accéder à cette page pour visualiser un classement détaillé avec des informations sur les seuils
 *   et des recommandations associées pour chaque polluant.
 *
 * Fichier placé dans le dossier pages.
 */

// Démarrage de la session et inclusion de la connexion à la base de données
session_start();
require_once '../bd/bd.php';
$db = new Database();

/************************************************
 * 1) Récupération des données de seuils
 * On extrait depuis la table "seuils_normes" les seuils de pollution correspondant
 * à la "moyenne annuelle" et on organise ces données par polluant.
 ************************************************/
$thresholdsMulti = [];
$sqlT = "
    SELECT polluant, polluant_complet, valeur, unite, details, origine
    FROM seuils_normes
    WHERE periode = 'moyenne annuelle'
";
$resT = $db->getConnection()->query($sqlT);
if ($resT && $resT->num_rows > 0) {
    while ($rowT = $resT->fetch_assoc()) {
        $poll = $rowT['polluant'];
        // Initialisation du tableau pour le polluant si nécessaire
        if (!isset($thresholdsMulti[$poll])) {
            $thresholdsMulti[$poll] = [];
        }
        // Stockage des informations relatives au seuil pour ce polluant
        $thresholdsMulti[$poll][] = [
            'value'    => (float) $rowT['valeur'],
            'unite'    => $rowT['unite'],
            'details'  => $rowT['details'],
            'origine'  => $rowT['origine'],
            'complet'  => $rowT['polluant_complet']
        ];
    }
}

// Traitement des seuils pour chaque polluant : sélection du seuil maximum et assemblage des informations
$thresholds = [];
foreach ($thresholdsMulti as $poll => $rows) {
    if (empty($rows)) continue;
    // Tri décroissant pour obtenir le seuil maximum en première position
    usort($rows, function($a, $b) {
        return $b['value'] <=> $a['value'];
    });
    // Récupération des informations du seuil maximum
    $maxVal = $rows[0]['value'];
    $maxUnite = $rows[0]['unite'];
    $maxDetails = $rows[0]['details'];
    $maxOrigins = [$rows[0]['origine']];
    $others = [];
    // Séparation des seuils égaux et inférieurs au maximum
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if ($row['value'] == $maxVal) {
            $maxOrigins[] = $row['origine'];
        } else {
            $others[] = $row;
        }
    }
    // Construction du texte affiché au survol
    $hoverText = "Seuil = {$maxVal} {$maxUnite}, origine : " . implode(", ", $maxOrigins) . ".";
    if (!empty($others)) {
        $hoverText .= " Autres seuils inférieurs : ";
        $tmp = [];
        foreach ($others as $o) {
            $tmp[] = "{$o['value']} {$o['unite']} ({$o['origine']})";
        }
        $hoverText .= implode("; ", $tmp) . ".";
    }
    $thresholds[$poll] = [
        'value'    => $maxVal,
        'unite'    => $maxUnite,
        'details'  => $maxDetails,
        'origines' => implode(", ", $maxOrigins),
        'hover'    => $hoverText
    ];
}

/************************************************
 * 2) Récupération du classement global
 * On récupère le classement des villes en fonction de la pollution
 * pour chaque polluant (en excluant 'C6H6').
 ************************************************/
$sql = "
    SELECT
        m.id_ville,
        m.polluant,
        m.avg_value,
        m.avg_par_habitant,
        m.avg_par_km2,
        v.ville AS city
    FROM moy_pollution_villes AS m
    JOIN donnees_villes AS v ON m.id_ville = v.id_ville
    ORDER BY m.polluant, m.avg_value DESC
";
$result = $db->getConnection()->query($sql);

/************************************************
 * 3) Stockage des données dans $rankingData
 * On organise les données par polluant pour faciliter leur affichage dans le tableau.
 ************************************************/
$rankingData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $polluant = $row['polluant'];
        // Ignorer le polluant 'C6H6'
        if ($polluant === 'C6H6') continue;
        $avgVal = (float)$row['avg_value'];
        $avgHab = (float)$row['avg_par_habitant'];
        $avgKm2 = (float)$row['avg_par_km2'];
        if (!isset($rankingData[$polluant])) {
            $rankingData[$polluant] = [];
        }
        // Stockage des informations de classement pour la ville
        $rankingData[$polluant][] = [
            'city'        => $row['city'],
            'avg_val'     => $avgVal,
            'avg_hab'     => $avgHab,
            'avg_par_km2' => $avgKm2
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Classement</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">

    <!-- Feuilles de style -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/classement.css">
    <link rel="stylesheet" href="../styles/boutons.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<!-- Section principale du classement -->
<section id="classement">
    <h2>Classement des villes les plus polluées</h2>
    <!-- Menu déroulant pour choisir le polluant -->
    <label for="polluant-select" class="polluant-label">Polluant :</label>
    <select id="polluant-select" class="pollutant-dropdown" style="appearance: none; -webkit-appearance: none; background: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'10\' height=\'5\'><polygon points=\'0,0 10,0 5,5\' fill=\'%236b8e23\' /></svg>') no-repeat right 10px center; background-color: white;"></select>

    <!-- Bloc d'informations sur le polluant sélectionné -->
    <div id="polluant-box">
        <div class="polluant-descriptif">
            <h3>Descriptif</h3>
            <p id="polluant-info" class="polluant-info"></p>
        </div>
        <div class="polluant-thresholds">
            <h3>Seuils & Recommandations</h3>
            <div id="polluant-thresholds" class="polluant-thresholds-content"></div>
        </div>
    </div>

    <!-- Options d'affichage des colonnes du tableau -->
    <fieldset id="columns-choices">
        <legend>Colonnes à afficher :</legend>
        <label>
            <input type="checkbox" id="chkAvgVal" checked>
            Moy. (µg/m³)
        </label>
        <label>
            <input type="checkbox" id="chkAvgHab" checked>
            Moy. par habitant
        </label>
        <label>
            <input type="checkbox" id="chkAvgKm2" checked>
            Moy. par km²
        </label>
    </fieldset>

    <!-- Conteneur dans lequel le tableau de classement sera généré dynamiquement par JavaScript -->
    <div id="rankingContainer"></div>

    <!-- Bouton "Voir plus" pour la pagination -->
    <button id="loadMoreButton" class="btn-voir-plus" title="Charger plus de résultats" style="display: none;">
        Voir plus
    </button>
</section>

<!-- Passage des données dynamiques au script via des variables JavaScript -->
<script>
    var rankingData = <?php echo json_encode($rankingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    var thresholdData = <?php echo json_encode($thresholds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- Inclusion du script classement.js situé dans le dossier "script" -->
<script src="../script/classement.js" defer></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
