<?php
session_start();
ob_start();

require_once '../bd/bd.php'; // Connexion BD

// Vérification paramètre GET ?ville
if (!isset($_GET['ville']) || empty($_GET['ville'])) {
    echo "Aucune ville spécifiée !";
    exit;
}
$nomVille = $_GET['ville'];

// ---------------------------------------------------------------------
// 1) Récupération des infos de la ville (table donnees_villes)
// ---------------------------------------------------------------------
$sqlVille = $conn->prepare("
    SELECT 
        id_ville, 
        ville, 
        postal_code, 
        latitude, 
        longitude, 
        departement, 
        region, 
        population, 
        superficie_km2, 
        densite, 
        grille_densite_texte
    FROM donnees_villes
    WHERE ville = ?
    LIMIT 1
");
$sqlVille->bind_param("s", $nomVille);
$sqlVille->execute();
$resVille  = $sqlVille->get_result();
$infoVille = $resVille->fetch_assoc();
$sqlVille->close();

if (!$infoVille) {
    echo "Ville introuvable dans la base de données.";
    exit;
}

// On stocke l'id_ville pour les requêtes suivantes
$idVille = (int) $infoVille['id_ville'];

// ---------------------------------------------------------------------
// 2) Récupération du rang par polluant (classement global)
// ---------------------------------------------------------------------
$sqlPolluants = $conn->prepare("
    SELECT 
      t1.polluant,
      t1.avg_value,
      (
        SELECT COUNT(*) + 1
        FROM moy_pollution_villes t2
        WHERE t2.polluant = t1.polluant
          AND t2.avg_value < t1.avg_value
      ) AS rang,
      (
        SELECT COUNT(*)
        FROM moy_pollution_villes t3
        WHERE t3.polluant = t1.polluant
      ) AS total
    FROM moy_pollution_villes t1
    WHERE t1.id_ville = ?
");
$sqlPolluants->bind_param("i", $idVille);
$sqlPolluants->execute();
$resPolluants   = $sqlPolluants->get_result();
$listePolluants = [];
while ($row = $resPolluants->fetch_assoc()) {
    $listePolluants[] = $row;
}
$sqlPolluants->close();

/**
 * Retourne une icône FontAwesome en fonction du polluant.
 */
function getPolluantIcon($polluant) {
    switch (strtoupper($polluant)) {
        case 'NO':
            return '<i class="fa-solid fa-cloud"></i> NO';
        case 'NO2':
            return '<i class="fa-solid fa-cloud-bolt"></i> NO2';
        case 'O3':
            return '<i class="fa-solid fa-wind"></i> O3';
        case 'PM10':
            return '<i class="fa-solid fa-cloud-meatball"></i> PM10';
        case 'PM2.5':
            return '<i class="fa-solid fa-smog"></i> PM2.5';
        case 'SO2':
            return '<i class="fa-solid fa-cloud-showers-water"></i> SO2';
        case 'CO':
            return '<i class="fa-solid fa-cloud-rain"></i> CO';
        default:
            return '<i class="fa-solid fa-circle-question"></i> ' . htmlspecialchars($polluant);
    }
}

// ---------------------------------------------------------------------
// 3) Onglets : Historique & Prédictions
//    -> On va récupérer dans la table moy_pollution_villes toutes les colonnes
// ---------------------------------------------------------------------

/**
 * Mois disponibles pour l’historique (janv2023 -> janv2025)
 * => Nom de colonne => Label d’affichage
 */
$monthsHistorique = [
    'moy_janv2023'   => 'Janv. 2023',
    'moy_fev2023'    => 'Févr. 2023',
    'moy_mar2023'    => 'Mars 2023',
    'moy_avril2023'  => 'Avr. 2023',
    'moy_mai2023'    => 'Mai 2023',
    'moy_juin2023'   => 'Juin 2023',
    'moy_juil2023'   => 'Juil. 2023',
    'moy_aout2023'   => 'Août 2023',
    'moy_sept2023'   => 'Sept. 2023',
    'moy_oct2023'    => 'Oct. 2023',
    'moy_nov2023'    => 'Nov. 2023',
    'moy_dec2023'    => 'Déc. 2023',
    'moy_janv2024'   => 'Janv. 2024',
    'moy_fev2024'    => 'Févr. 2024',
    'moy_mar2024'    => 'Mars 2024',
    'moy_avril2024'  => 'Avr. 2024',
    'moy_mai2024'    => 'Mai 2024',
    'moy_juin2024'   => 'Juin 2024',
    'moy_juil2024'   => 'Juil. 2024',
    'moy_aout2024'   => 'Août 2024',
    'moy_sept2024'   => 'Sept. 2024',
    'moy_oct2024'    => 'Oct. 2024',
    'moy_nov2024'    => 'Nov. 2024',
    'moy_dec2024'    => 'Déc. 2024',
    'moy_janv2025'   => 'Janv. 2025',
];

/**
 * Mois disponibles pour les prédictions (janv2025 -> janv2026)
 */
$monthsPrediction = [
    'moy_predic_janv2025'  => 'Janv. 2025',
    'moy_predic_fev2025'   => 'Févr. 2025',
    'moy_predic_mars2025'  => 'Mars 2025',
    'moy_predic_avril2025' => 'Avr. 2025',
    'moy_predic_mai2025'   => 'Mai 2025',
    'moy_predic_juin2025'  => 'Juin 2025',
    'moy_predic_juil2025'  => 'Juil. 2025',
    'moy_predic_aout2025'  => 'Août 2025',
    'moy_predic_sept2025'  => 'Sept. 2025',
    'moy_predic_oct2025'   => 'Oct. 2025',
    'moy_predic_nov2025'   => 'Nov. 2025',
    'moy_predic_dec2025'   => 'Déc. 2025',
    'moy_predic_janv2026'  => 'Janv. 2026',
];

// ---------------------------------------------------------------------
// 4) Gestion des filtres : polluant et mois
//    (Exemple simple : on récupère ?polluant=... et ?mois=... et ?tab=...)
// ---------------------------------------------------------------------
$tab        = isset($_GET['tab']) ? $_GET['tab'] : 'historique'; // 'historique' ou 'predictions'
$filtrePoll = isset($_GET['polluant']) ? trim($_GET['polluant']) : '';
$filtreMois = isset($_GET['mois']) ? trim($_GET['mois']) : '';  // ex: "moy_janv2023" ou "moy_predic_janv2025" etc.

// On prépare la requête pour moy_pollution_villes
// Si un polluant est choisi, on filtre dessus
$sqlCols = array_merge(array_keys($monthsHistorique), array_keys($monthsPrediction));
$colsStr = implode(',', $sqlCols); // pour SELECT ...
// Ex: moy_janv2023,moy_fev2023,...,moy_predic_janv2026

$sqlStr = "SELECT id_ville, polluant, $colsStr 
           FROM moy_pollution_villes
           WHERE id_ville = ? ";
if ($filtrePoll !== '') {
    $sqlStr .= " AND polluant = ? ";
}
$stmt = $conn->prepare($sqlStr);

if ($filtrePoll !== '') {
    $stmt->bind_param("is", $idVille, $filtrePoll);
} else {
    $stmt->bind_param("i", $idVille);
}
$stmt->execute();
$resMoy = $stmt->get_result();
$dataMoy = [];
while ($r = $resMoy->fetch_assoc()) {
    $dataMoy[] = $r;
}
$stmt->close();

// ---------------------------------------------------------------------
// 5) Si un mois est sélectionné, on veut afficher les données journalières
//    -> "Historique" => table all_years_cleaned_daily
//    -> "Prédictions" => table prediction_cities
// ---------------------------------------------------------------------
$dailyData = [];
if ($filtreMois !== '') {
    // On va identifier l’année et le mois
    // Ex: si $filtreMois = 'moy_janv2023' => on en déduit year=2023, month=1
    // si $filtreMois = 'moy_predic_mars2025' => year=2025, month=3
    // On fait un petit parse:
    // Historique: moy_XXX2023 => 2023 ou 2024 ou 2025
    // Predictions: moy_predic_XXX2025 => 2025 ou 2026, etc.

    $isPrediction = (strpos($filtreMois, 'predic') !== false);

    // Extraire l’année
    // ex: moy_janv2023 => 'janv2023'
    // ex: moy_predic_mars2025 => 'mars2025'
    $temp = str_replace(['moy_', 'predic_'], '', $filtreMois);
    // ex: "janv2023" ou "mars2025"
    // On va repérer le mois
    // Pour simplifier, on fait un petit mapping:
    $mapMois = [
        'janv'  => 1,
        'fev'   => 2,
        'mar'   => 3,
        'mars'  => 3, // on sait que 'mars2025' peut exister
        'avril' => 4,
        'avr'   => 4,
        'mai'   => 5,
        'juin'  => 6,
        'juil'  => 7,
        'aout'  => 8,
        'sept'  => 9,
        'oct'   => 10,
        'nov'   => 11,
        'dec'   => 12,
        'déc'   => 12,
    ];

    // on extrait la partie "janv" ou "mars", etc.
    // puis l’année
    preg_match('/^([a-zé]+)([0-9]+)/i', $temp, $matches);
    // matches[1] => 'janv' ou 'mars' ...
    // matches[2] => '2023' ou '2025' ...
    if (count($matches) === 3) {
        $moisStr  = $matches[1]; // ex: 'janv'
        $anneeStr = $matches[2]; // ex: '2023'
        $moisNum  = isset($mapMois[$moisStr]) ? $mapMois[$moisStr] : 1;
        $anneeNum = (int) $anneeStr;

        // Table source
        $tableSource = $isPrediction ? 'prediction_cities' : 'all_years_cleaned_daily';
        // Nom de la colonne de valeur
        $colValeur   = $isPrediction ? 'valeur_predite' : 'valeur_journaliere';

        // Construction de la requête journalière
        $sqlDaily = "SELECT jour, polluant, $colValeur AS val, unite_de_mesure
                     FROM $tableSource
                     WHERE id_ville = ?
                       AND YEAR(jour) = ?
                       AND MONTH(jour) = ? ";
        if ($filtrePoll !== '') {
            $sqlDaily .= " AND polluant = ? ";
        }
        $sqlDaily .= " ORDER BY jour ASC ";

        $stmt2 = $conn->prepare($sqlDaily);
        if ($filtrePoll !== '') {
            $stmt2->bind_param("iiis", $idVille, $anneeNum, $moisNum, $filtrePoll);
        } else {
            $stmt2->bind_param("iii", $idVille, $anneeNum, $moisNum);
        }
        $stmt2->execute();
        $resD = $stmt2->get_result();
        while ($d = $resD->fetch_assoc()) {
            $dailyData[] = $d;
        }
        $stmt2->close();
    }
}

// ---------------------------------------------------------------------
// 6) Affichage HTML
// ---------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Détails de <?php echo htmlspecialchars($infoVille['ville']); ?></title>

    <!-- Polices, Bootstrap, FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <!-- Styles -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/details.css">
    <link rel="stylesheet" href="../styles/includes.css">


</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="details-container">

    <!-- ======================= BLOC GAUCHE : infos ville ====================== -->
    <div class="bloc-ville">
        <h1 class="ville-title">
            <?php echo htmlspecialchars($infoVille['ville']); ?>
        </h1>
        <p class="grille-texte">
            <?php echo nl2br(htmlspecialchars($infoVille['grille_densite_texte'])); ?>
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-user-group"></i> Population : </strong>
            <?php echo number_format($infoVille['population'], 0, ',', ' '); ?> habitants
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-map-pin"></i> Département : </strong>
            <?php echo htmlspecialchars($infoVille['departement']); ?>
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-location-dot"></i> Région : </strong>
            <?php echo htmlspecialchars($infoVille['region']); ?>
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-map"></i> Superficie : </strong>
            <?php echo number_format($infoVille['superficie_km2'], 0, ',', ' '); ?> km²
        </p>
    </div>

    <!-- ======================= BLOC DROITE : classement par polluant ====================== -->
    <div class="bloc-polluants">
        <h2 class="polluants-title">
            <i class="fa-solid fa-ranking-star"></i> Classement par Polluant
        </h2>

        <?php if (!empty($listePolluants)): ?>
            <table class="table-polluants">
                <thead>
                <tr>
                    <th>Polluant</th>
                    <th>Rang</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($listePolluants as $poll): ?>
                    <?php
                    $polluant = $poll['polluant'];
                    $rang     = (int) $poll['rang'];
                    $total    = (int) $poll['total'];
                    ?>
                    <tr>
                        <td><?php echo getPolluantIcon($polluant); ?></td>
                        <td>
                            <i class="fa-solid fa-medal" style="color:#f4c542;"></i>
                            <?php echo $rang . " / " . $total; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="aucun-classement">Aucun classement disponible pour cette ville.</p>
        <?php endif; ?>
    </div>
</div>

<hr>

<!-- ====================== NAV TABS pour Historique / Prédictions ====================== -->
<div class="container mt-4">
    <ul class="nav nav-tabs" id="tabContent" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab === 'historique') ? 'active' : ''; ?>"
               href="?ville=<?php echo urlencode($nomVille); ?>&tab=historique">
                Historique
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab === 'predictions') ? 'active' : ''; ?>"
               href="?ville=<?php echo urlencode($nomVille); ?>&tab=predictions">
                Prédictions
            </a>
        </li>
    </ul>
</div>

<div class="container tab-content py-3" id="myTabContent">
    <?php
    // Choix du bloc selon $tab
    if ($tab === 'predictions') {
        // =========== ONGLET PREDICTIONS ===========
        ?>
        <div class="tab-pane fade show active" id="predictions" role="tabpanel">
            <h3>Prédictions (janv. 2025 à janv. 2026)</h3>

            <!-- Formulaire de filtre (polluant, mois) -->
            <form method="GET" class="form-inline mb-3">
                <input type="hidden" name="ville" value="<?php echo htmlspecialchars($nomVille); ?>">
                <input type="hidden" name="tab" value="predictions">

                <label class="mr-2">Polluant :</label>
                <select name="polluant" class="form-control mr-3">
                    <option value="">-- Tous --</option>
                    <option value="NO"  <?php if($filtrePoll==='NO') echo 'selected'; ?>>NO</option>
                    <option value="NO2" <?php if($filtrePoll==='NO2') echo 'selected'; ?>>NO2</option>
                    <option value="O3"  <?php if($filtrePoll==='O3') echo 'selected'; ?>>O3</option>
                    <option value="PM10"<?php if($filtrePoll==='PM10') echo 'selected'; ?>>PM10</option>
                    <option value="PM2.5"<?php if($filtrePoll==='PM2.5') echo 'selected'; ?>>PM2.5</option>
                    <option value="SO2" <?php if($filtrePoll==='SO2') echo 'selected'; ?>>SO2</option>
                    <option value="CO"  <?php if($filtrePoll==='CO') echo 'selected'; ?>>CO</option>
                </select>

                <label class="mr-2">Mois :</label>
                <select name="mois" class="form-control mr-3">
                    <option value="">-- Aucun --</option>
                    <?php foreach($monthsPrediction as $col=>$label): ?>
                        <option value="<?php echo $col; ?>"
                            <?php if($filtreMois===$col) echo 'selected'; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-success">Filtrer</button>
            </form>

            <!-- Tableau des moyennes mensuelles (janv2025 -> janv2026) -->
            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                <tr>
                    <th>Polluant</th>
                    <?php foreach($monthsPrediction as $col=>$label): ?>
                        <th><?php echo $label; ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if(!empty($dataMoy)): ?>
                    <?php foreach($dataMoy as $rowMoy): ?>
                        <tr>
                            <td><?php echo getPolluantIcon($rowMoy['polluant']); ?></td>
                            <?php foreach($monthsPrediction as $col=>$label): ?>
                                <?php
                                $val = $rowMoy[$col]; // ex: moy_predic_janv2025
                                $val = is_numeric($val) ? round($val, 2) : null;
                                ?>
                                <td><?php echo ($val!==null) ? $val : '-'; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo 1+count($monthsPrediction); ?>">
                            Aucune donnée de prédiction
                        </td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if(!empty($filtreMois) && !empty($dailyData)): ?>
                <h4 class="mt-4">Données journalières pour le mois sélectionné</h4>
                <table class="table table-bordered table-sm">
                    <thead>
                    <tr>
                        <th>Jour</th>
                        <th>Polluant</th>
                        <th>Valeur prédite</th>
                        <th>Unité</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($dailyData as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['jour']); ?></td>
                            <td><?php echo htmlspecialchars($d['polluant']); ?></td>
                            <td><?php echo round($d['val'],2); ?></td>
                            <td><?php echo htmlspecialchars($d['unite_de_mesure']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif(!empty($filtreMois)): ?>
                <p>Aucune donnée journalière pour ce mois.</p>
            <?php endif; ?>
        </div>
        <?php
    } else {
        // =========== ONGLET HISTORIQUE ===========
        ?>
        <div class="tab-pane fade show active" id="historique" role="tabpanel">
            <h3>Historique (janv. 2023 à janv. 2025)</h3>

            <!-- Formulaire de filtre (polluant, mois) -->
            <form method="GET" class="form-inline mb-3">
                <input type="hidden" name="ville" value="<?php echo htmlspecialchars($nomVille); ?>">
                <input type="hidden" name="tab" value="historique">

                <label class="mr-2">Polluant :</label>
                <select name="polluant" class="form-control mr-3">
                    <option value="">-- Tous --</option>
                    <option value="NO"  <?php if($filtrePoll==='NO') echo 'selected'; ?>>NO</option>
                    <option value="NO2" <?php if($filtrePoll==='NO2') echo 'selected'; ?>>NO2</option>
                    <option value="O3"  <?php if($filtrePoll==='O3') echo 'selected'; ?>>O3</option>
                    <option value="PM10"<?php if($filtrePoll==='PM10') echo 'selected'; ?>>PM10</option>
                    <option value="PM2.5"<?php if($filtrePoll==='PM2.5') echo 'selected'; ?>>PM2.5</option>
                    <option value="SO2" <?php if($filtrePoll==='SO2') echo 'selected'; ?>>SO2</option>
                    <option value="CO"  <?php if($filtrePoll==='CO') echo 'selected'; ?>>CO</option>
                </select>

                <label class="mr-2">Mois :</label>
                <select name="mois" class="form-control mr-3">
                    <option value="">-- Aucun --</option>
                    <?php foreach($monthsHistorique as $col=>$label): ?>
                        <option value="<?php echo $col; ?>"
                            <?php if($filtreMois===$col) echo 'selected'; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">Filtrer</button>
            </form>

            <!-- Tableau des moyennes mensuelles (janv2023 -> janv2025) -->
            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                <tr>
                    <th>Polluant</th>
                    <?php foreach($monthsHistorique as $col=>$label): ?>
                        <th><?php echo $label; ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if(!empty($dataMoy)): ?>
                    <?php foreach($dataMoy as $rowMoy): ?>
                        <tr>
                            <td><?php echo getPolluantIcon($rowMoy['polluant']); ?></td>
                            <?php foreach($monthsHistorique as $col=>$label): ?>
                                <?php
                                $val = $rowMoy[$col];
                                $val = is_numeric($val) ? round($val, 2) : null;
                                ?>
                                <td><?php echo ($val!==null) ? $val : '-'; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo 1+count($monthsHistorique); ?>">
                            Aucune donnée
                        </td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if(!empty($filtreMois) && !empty($dailyData)): ?>
                <h4 class="mt-4">Données journalières pour le mois sélectionné</h4>
                <table class="table table-bordered table-sm">
                    <thead>
                    <tr>
                        <th>Jour</th>
                        <th>Polluant</th>
                        <th>Valeur</th>
                        <th>Unité</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($dailyData as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['jour']); ?></td>
                            <td><?php echo htmlspecialchars($d['polluant']); ?></td>
                            <td><?php echo round($d['val'],2); ?></td>
                            <td><?php echo htmlspecialchars($d['unite_de_mesure']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif(!empty($filtreMois)): ?>
                <p>Aucune donnée journalière pour ce mois.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
