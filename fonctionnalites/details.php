<?php
/**
 * details.php
 */

session_start();
ob_start();

// Inclusion de la connexion à la base
include '../bd/bd.php';

/**
 * 1) GESTION DE L'AJAX POUR COMPARER DEUX VILLES
 */
if (
    isset($_GET['ajax']) && $_GET['ajax'] == '1'
    && isset($_GET['action']) && $_GET['action'] === 'getpolluants'
) {
    header('Content-Type: application/json; charset=utf-8');

    $cityToFetch = $_GET['ville'] ?? '';
    if (empty($cityToFetch)) {
        echo json_encode(['error' => 'Aucune ville spécifiée']);
        exit;
    }

    // Trouver id_ville
    $sqlFindIdVille = "SELECT id_ville FROM donnees_villes WHERE UPPER(ville) = UPPER(?)";
    $stmtId = $conn->prepare($sqlFindIdVille);
    $stmtId->bind_param("s", $cityToFetch);
    $stmtId->execute();
    $resId = $stmtId->get_result();
    if ($resId->num_rows === 0) {
        echo json_encode(['error' => "La ville \"$cityToFetch\" n’existe pas dans donnees_villes"]);
        exit;
    }
    $rowId  = $resId->fetch_assoc();
    $idVille= (int)$rowId['id_ville'];

    // Récup mesures dans all_years_cleaned_daily
    $sqlCity = "SELECT polluant, valeur_journaliere
                FROM all_years_cleaned_daily
                WHERE id_ville = ?";
    $stmtCity = $conn->prepare($sqlCity);
    $stmtCity->bind_param("i", $idVille);
    $stmtCity->execute();
    $resCity = $stmtCity->get_result();

    if ($resCity->num_rows === 0) {
        echo json_encode(['error' => "Aucune mesure pour la ville $cityToFetch"]);
        exit;
    }

    // Regrouper par polluant
    $polluantsArr = [];
    while ($row = $resCity->fetch_assoc()) {
        $pollSymbol = $row['polluant'];
        $val = (float)$row['valeur_journaliere'];
        $polluantsArr[$pollSymbol][] = $val;
    }

    // Moyennes
    $finalData = [];
    foreach ($polluantsArr as $symbol => $values) {
        $m = array_sum($values)/count($values);
        $finalData[$symbol] = round($m, 2);
    }

    echo json_encode($finalData);
    exit;
}

/**
 * 2) RÉCUPÉRATION DE LA VILLE VIA GET
 */
$ville = $_GET['ville'] ?? '';
if (!$ville) {
    echo "<h1>Erreur : Ville non spécifiée</h1>";
    exit;
}

// id_ville, departement, region
$sqlVille = "SELECT id_ville, departement, region
             FROM donnees_villes
             WHERE UPPER(ville)=UPPER(?)";
$stmtVille = $conn->prepare($sqlVille);
$stmtVille->bind_param("s", $ville);
$stmtVille->execute();
$resVille = $stmtVille->get_result();

if ($resVille->num_rows === 0) {
    $cityNotFound = true;
} else {
    $cityNotFound = false;
    $rowVille     = $resVille->fetch_assoc();
    $idVille      = (int)$rowVille['id_ville'];
    $departement  = $rowVille['departement'];
    $region       = $rowVille['region'];
}

/**
 * 2B) HISTORIQUE DE RECHERCHE
 */
if (!$cityNotFound && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_stmt = $conn->prepare("
        SELECT * FROM search_history
        WHERE user_id = ?
          AND id_ville = ?
          AND search_date > (NOW() - INTERVAL 1 HOUR)
    ");
    $check_stmt->bind_param("ii", $user_id, $idVille);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();
    if ($check_res->num_rows === 0) {
        $insert_stmt = $conn->prepare("
            INSERT INTO search_history(user_id, id_ville, search_date)
            VALUES (?, ?, NOW())
        ");
        $insert_stmt->bind_param("ii", $user_id, $idVille);
        $insert_stmt->execute();
    }
}

/**
 * 3) SI LA VILLE EST TROUVÉE => RÉCUPÉRER LES MESURES
 */
if (!$cityNotFound) {
    // --- Récupération des données historiques (all_years_cleaned_daily) ---
    $sql = "SELECT polluant, jour, valeur_journaliere
            FROM all_years_cleaned_daily
            WHERE id_ville = ?
            ORDER BY jour";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idVille);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $cityNotFound = true;
    } else {
        $polluants_data = [];   // => pour l'onglet "Polluants"
        $dates = [];

        while ($row = $result->fetch_assoc()) {
            $polluant_full = $row['polluant'];
            $date          = $row['jour'];
            $value         = (float)$row['valeur_journaliere'];

            // Juste un label "Inconnu" si pas d'info de localisation précise
            $location = 'Inconnu';

            $ts       = strtotime($date);
            $annee    = date('Y', $ts);
            $num_mois = date('m', $ts);

            $mois_fr = [
                '01'=>'Janv','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
                '07'=>'Juil.','08'=>'Août','09'=>'Sept.','10'=>'Oct.','11'=>'Nov.','12'=>'Déc'
            ];
            $nom_mois       = $mois_fr[$num_mois] ?? $num_mois;
            $formattedDate  = $nom_mois.' '.$annee;

            $columnIdentifier = $formattedDate.' - '.$location;
            $exists = array_filter($dates, function($d) use($columnIdentifier){
                return $d['identifier'] === $columnIdentifier;
            });
            if (empty($exists)) {
                $dates[] = [
                    'date'      => $formattedDate,
                    'location'  => $location,
                    'identifier'=> $columnIdentifier
                ];
            }

            $pollSymbol = strtoupper($polluant_full);
            if (!isset($polluants_data[$pollSymbol])) {
                $polluants_data[$pollSymbol] = [
                    'name'  => $polluant_full,
                    'values'=> []
                ];
            }
            $polluants_data[$pollSymbol]['values'][$columnIdentifier] = $value;
        }

        // Moyennes globales sur all_years_cleaned_daily
        $city_pollution_averages = [];
        foreach ($polluants_data as $pSym => $data) {
            $vals  = $data['values'];
            $sum   = array_sum($vals);
            $count = count($vals);
            $avg   = ($count > 0) ? ($sum / $count) : 0;
            $city_pollution_averages[$pSym] = round($avg, 2);
        }

        // --- Population (table population_francaise_par_departement_2018) ---
        $sql_population = "SELECT Population
                           FROM population_francaise_par_departement_2018
                           WHERE Département = ?";
        $stmt_pop = $conn->prepare($sql_population);
        $stmt_pop->bind_param("s", $departement);
        $stmt_pop->execute();
        $res_pop    = $stmt_pop->get_result();
        $population = $res_pop->fetch_assoc()['Population'] ?? 'Inconnue';

        // --- Seuils (pour onglet Dépassements) ---
        // CHANGEMENT : on récupère polluant_complet AS polluant
        $sql_seuils = "
            SELECT polluant_complet AS polluant, type_norme, valeur, unite, origine
            FROM seuils_normes
        ";
        $result_seuils = $conn->query($sql_seuils);
        $seuils = [];
        if ($result_seuils && $result_seuils->num_rows > 0) {
            while ($row_s = $result_seuils->fetch_assoc()) {
                $pollSymbol2 = strtoupper($row_s['polluant']);
                $tn          = $row_s['type_norme'];
                $val         = $row_s['valeur'];
                $un          = $row_s['unite'];
                $og          = $row_s['origine'];
                $seuils[$pollSymbol2][$tn] = [
                    'valeur' => $val,
                    'unite'  => $un,
                    'origine'=> $og
                ];
            }
        }
        // Retirer les seuils des polluants qui ne sont pas mesurés ici
        foreach ($seuils as $pS2 => $arr2) {
            if (!isset($polluants_data[$pS2])) {
                unset($seuils[$pS2]);
            }
        }

        // --- Gestion des favoris (si user connecté) ---
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $stmtFav = $conn->prepare("
                SELECT dv.ville
                FROM favorite_cities fc
                JOIN donnees_villes dv ON fc.id_ville = dv.id_ville
                WHERE fc.user_id = ?
            ");
            $stmtFav->bind_param("i", $user_id);
            $stmtFav->execute();
            $favorites_result = $stmtFav->get_result();
            $favorite_cities  = [];
            while ($r = $favorites_result->fetch_assoc()) {
                $favorite_cities[] = strtolower($r['ville']);
            }
            $is_favorite = in_array(strtolower($ville), $favorite_cities);

            // Ajout / suppression de favori
            if (isset($_POST['favorite_action'])) {
                // Vérifier si c'est AJAX ou non
                if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                    // Mode AJAX
                    $city_name = strtolower($_POST['city_name']);
                    $action    = $_POST['favorite_action'];
                    $response  = [];

                    // On récupère l'id_ville correspondant
                    $stmtFind = $conn->prepare("SELECT id_ville FROM donnees_villes WHERE LOWER(ville) = LOWER(?) LIMIT 1");
                    $stmtFind->bind_param("s", $city_name);
                    $stmtFind->execute();
                    $resFind = $stmtFind->get_result();
                    if (!$resFind || $resFind->num_rows === 0) {
                        $response = [
                            'success' => false,
                            'message' => 'Ville introuvable.'
                        ];
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
                    $idRowFav  = $resFind->fetch_assoc();
                    $idVilleFav= (int)$idRowFav['id_ville'];

                    if ($action == 'add_favorite') {
                        // Vérifier le nombre de favoris
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $count_result = $stmt->get_result();
                        $count_row    = $count_result->fetch_assoc();

                        if ($count_row['count'] < 5) {
                            // Vérifier si pas déjà favori
                            $stmtCheck = $conn->prepare("
                                SELECT COUNT(*) as count
                                FROM favorite_cities
                                WHERE user_id = ?
                                  AND id_ville = ?
                            ");
                            $stmtCheck->bind_param("ii", $user_id, $idVilleFav);
                            $stmtCheck->execute();
                            $resCheck = $stmtCheck->get_result();
                            $rowCheck = $resCheck->fetch_assoc();

                            if ($rowCheck['count'] == 0) {
                                $stmtIns = $conn->prepare("
                                    INSERT INTO favorite_cities (user_id, id_ville)
                                    VALUES (?, ?)
                                ");
                                $stmtIns->bind_param("ii", $user_id, $idVilleFav);
                                if ($stmtIns->execute()) {
                                    $response = [
                                        'success' => true,
                                        'message' => 'Ville ajoutée aux favoris.',
                                        'action'  => 'added'
                                    ];
                                } else {
                                    $response = [
                                        'success' => false,
                                        'message' => 'Erreur lors de l\'ajout de la ville.'
                                    ];
                                }
                            } else {
                                $response = [
                                    'success' => false,
                                    'message' => 'Cette ville est déjà dans vos favoris.'
                                ];
                            }
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Vous avez atteint le nombre maximum de 5 villes favorites.'
                            ];
                        }
                    } elseif ($action == 'remove_favorite') {
                        $stmtDel = $conn->prepare("
                            DELETE FROM favorite_cities
                            WHERE user_id = ?
                              AND id_ville = ?
                        ");
                        $stmtDel->bind_param("ii", $user_id, $idVilleFav);
                        if ($stmtDel->execute()) {
                            $response = [
                                'success' => true,
                                'message' => 'Ville retirée des favoris.',
                                'action'  => 'removed'
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Erreur lors de la suppression de la ville.'
                            ];
                        }
                    }

                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                } else {
                    // Mode non-AJAX
                    $city_name = strtolower($_POST['city_name']);
                    $action    = $_POST['favorite_action'];
                    $response  = [];

                    // Récupérer l'id_ville
                    $stmtFind = $conn->prepare("SELECT id_ville FROM donnees_villes WHERE LOWER(ville) = LOWER(?) LIMIT 1");
                    $stmtFind->bind_param("s", $city_name);
                    $stmtFind->execute();
                    $resFind = $stmtFind->get_result();
                    if (!$resFind || $resFind->num_rows === 0) {
                        // Ville introuvable
                        $response = [
                            'success' => false,
                            'message' => 'Ville introuvable.'
                        ];
                        header("Location: details.php?ville=" . urlencode($ville));
                        exit;
                    }
                    $idRowFav   = $resFind->fetch_assoc();
                    $idVilleFav = (int)$idRowFav['id_ville'];

                    if ($action == 'add_favorite') {
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $count_result = $stmt->get_result();
                        $count_row    = $count_result->fetch_assoc();

                        if ($count_row['count'] < 5) {
                            // Vérifier si pas déjà favori
                            $stmtCheck = $conn->prepare("
                                SELECT COUNT(*) as count
                                FROM favorite_cities
                                WHERE user_id = ?
                                  AND id_ville = ?
                            ");
                            $stmtCheck->bind_param("ii", $user_id, $idVilleFav);
                            $stmtCheck->execute();
                            $resCheck = $stmtCheck->get_result();
                            $rowCheck = $resCheck->fetch_assoc();

                            if ($rowCheck['count'] == 0) {
                                $stmtIns = $conn->prepare("
                                    INSERT INTO favorite_cities (user_id, id_ville)
                                    VALUES (?, ?)
                                ");
                                $stmtIns->bind_param("ii", $user_id, $idVilleFav);
                                if ($stmtIns->execute()) {
                                    $response = [
                                        'success' => true,
                                        'message' => 'Ville ajoutée aux favoris.',
                                        'action'  => 'added'
                                    ];
                                } else {
                                    $response = [
                                        'success' => false,
                                        'message' => 'Erreur lors de l\'ajout de la ville.'
                                    ];
                                }
                            } else {
                                $response = [
                                    'success' => false,
                                    'message' => 'Cette ville est déjà dans vos favoris.'
                                ];
                            }
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Vous avez atteint le nombre maximum de 5 villes favorites.'
                            ];
                        }
                    } elseif ($action == 'remove_favorite') {
                        $stmtDel = $conn->prepare("
                            DELETE FROM favorite_cities
                            WHERE user_id = ?
                              AND id_ville = ?
                        ");
                        $stmtDel->bind_param("ii", $user_id, $idVilleFav);
                        if ($stmtDel->execute()) {
                            $response = [
                                'success' => true,
                                'message' => 'Ville retirée des favoris.',
                                'action'  => 'removed'
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Erreur lors de la suppression de la ville.'
                            ];
                        }
                    }

                    header("Location: details.php?ville=" . urlencode($ville));
                    exit;
                }
            }
        } else {
            $is_favorite = false;
        }

        // --- Classement => UTILISATION DE LA TABLE moy_pollution_villes ---
        // Au lieu de recalculer la moyenne via all_years_cleaned_daily,
        // on utilise ta table moy_pollution_villes (screenshot 3).
        $sqlAllCities = "
            SELECT dv.ville AS City,
                   mpv.polluant,
                   mpv.avg_value
            FROM moy_pollution_villes mpv
            JOIN donnees_villes dv ON mpv.id_ville = dv.id_ville
            ORDER BY mpv.polluant, mpv.avg_value DESC
        ";
        $resultAllCities = $conn->query($sqlAllCities);
        $rankingBypolluant = [];
        if ($resultAllCities && $resultAllCities->num_rows > 0) {
            while ($rowAll = $resultAllCities->fetch_assoc()) {
                $poll = $rowAll['polluant'];
                $city = $rowAll['City'];
                $avgV = (float)$rowAll['avg_value'];
                if (!isset($rankingBypolluant[$poll])) {
                    $rankingBypolluant[$poll] = [];
                }
                $rankingBypolluant[$poll][] = [
                    'city'   => $city,
                    'avg_value'=> $avgV
                ];
            }
        }
        // On classe chaque polluant du + élevé au - élevé
        foreach ($rankingBypolluant as $poll => &$rows) {
            usort($rows, function($a, $b) {
                return $b['avg_value'] <=> $a['avg_value'];
            });
            $rang = 1;
            foreach ($rows as &$it) {
                $it['rank'] = $rang++;
            }
        }
        unset($rows);

        // Chercher le rang de la ville courante
        $cityRankBypolluant = [];
        foreach ($rankingBypolluant as $poll => $rows) {
            $totVilles = count($rows);
            foreach ($rows as $item) {
                if (strtolower($item['city']) === strtolower($ville)) {
                    $cityRankBypolluant[$poll] = [
                        'rank' => $item['rank'],
                        'total'=> $totVilles
                    ];
                    break;
                }
            }
        }

        // --- Prédictions (table prediction_cities) ---
        $sqlPred = "
            SELECT pc.jour, pc.polluant, pc.valeur_predite
            FROM prediction_cities pc
            JOIN donnees_villes dv ON pc.id_ville = dv.id_ville
            WHERE dv.id_ville = ?
            ORDER BY pc.jour
        ";
        $stmtPred = $conn->prepare($sqlPred);
        $stmtPred->bind_param("i", $idVille);
        $stmtPred->execute();
        $resPred = $stmtPred->get_result();

        $predictions_data = []; // => pour l’onglet "Prédictions"
        if ($resPred && $resPred->num_rows > 0) {
            while ($rowp = $resPred->fetch_assoc()) {
                $poll = $rowp['polluant'];
                $dat  = $rowp['jour'];
                $valp = (float)$rowp['valeur_predite'];
                if (!isset($predictions_data[$poll])) {
                    $predictions_data[$poll] = [];
                }
                $predictions_data[$poll][] = [
                    'date' => $dat,
                    'value'=> $valp
                ];
            }
        }
        // Préparer un mapping "mois -> label" pour un usage plus simple
        $predictions_table      = [];
        $pred_date_labels       = [];
        $mois_fr = [
            '01'=>'Janv','02'=>'Février','03'=>'Mars','04'=>'Avril',
            '05'=>'Mai','06'=>'Juin','07'=>'Juil.','08'=>'Août',
            '09'=>'Sept','10'=>'Oct.','11'=>'Nov.','12'=>'Déc'
        ];
        if (!empty($predictions_data)) {
            foreach ($predictions_data as $polluant => $rows) {
                foreach ($rows as $rw) {
                    $d  = $rw['date'];
                    $ts = strtotime($d);
                    $an = date('Y', $ts);
                    $ms = date('m', $ts);
                    $mk = $an.'-'.$ms;
                    $labelM = ($mois_fr[$ms] ?? $ms).' '.$an;
                    $pred_date_labels[$mk] = $labelM;
                    $predictions_table[$polluant][$mk][] = $rw['value'];
                }
            }
        }
        $pred_dates = array_keys($pred_date_labels);
        sort($pred_dates);

        // => Pour l'affichage tabulaire (moyenne par mois)
        $predictions_table_avg = [];
        foreach ($predictions_table as $poll => $arrM) {
            foreach ($arrM as $mk => $vals) {
                $moy = array_sum($vals)/count($vals);
                $predictions_table_avg[$poll][$mk] = round($moy, 2);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Données détaillées de <?php echo htmlspecialchars($ville); ?></title>
    <!-- Polices Google, Bootstrap, FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <!-- Vos styles -->
    <link rel="stylesheet" href="../styles/recherche.css">
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/details.css">
    <link rel="stylesheet" href="../styles/commentaire.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <link rel="stylesheet" href="../styles/messages.css">
    <!-- Chart.js, jQuery, Popper, Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div id="message-container">
    <?php
    // Messages de succès/erreur lors de l'ajout/suppression favoris
    if (isset($response['success']) && $response['success'] === true) {
        echo '<div class="success-message">'.htmlspecialchars($response['message'], ENT_QUOTES).'</div>';
    } elseif (isset($response['success']) && $response['success'] === false) {
        echo '<div class="error-message">'.htmlspecialchars($response['message'], ENT_QUOTES).'</div>';
    }
    ?>
</div>

<main>
    <div id="details-page" class="container">
        <?php if ($cityNotFound): ?>
            <section id="city-not-found" class="text-center">
                <h1>Oups ! Aucune donnée pour "<?php echo htmlspecialchars($ville); ?>"</h1>
                <p>Il semble qu’aucune donnée ne soit disponible.</p>
                <ul>
                    <li><a href="../pages/recherche.php" class="btn btn-sm btn-info">Recherche</a></li>
                    <li><a href="../pages/carte.php" class="btn btn-sm btn-info">Carte interactive</a></li>
                    <li><a href="../pages/contact.php" class="btn btn-sm btn-info">Contact</a></li>
                </ul>
            </section>
        <?php else: ?>
            <section id="intro">
                <h1 class="text-center mb-4">
                    <?php echo htmlspecialchars($ville); ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form id="favorite-form" method="post" style="display:inline;">
                            <input type="hidden" name="city_name" value="<?php echo htmlspecialchars($ville); ?>">
                            <input type="hidden" name="favorite_action" id="favorite_action" value="">
                            <button type="submit" class="favorite-icon"
                                    data-action="<?php echo $is_favorite ? 'remove_favorite' : 'add_favorite'; ?>">
                                <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </h1>
                <p>
                    Département : <?php echo htmlspecialchars($departement); ?>
                    (<?php echo number_format($population, 0, ',', ' '); ?> habitants)
                </p>
                <p>Région : <?php echo htmlspecialchars($region); ?></p>

                <?php if (!empty($cityRankBypolluant)): ?>
                    <div style="margin-top:1em;">
                        <strong>Classement en termes de pollution :</strong>
                        <ul style="list-style:none; padding-left:0;">
                            <?php foreach ($cityRankBypolluant as $poll => $info):
                                $rank  = $info['rank'];
                                $total = $info['total']; ?>
                                <li>• <strong><?php echo htmlspecialchars($poll); ?></strong> :
                                    Rang <?php echo $rank; ?> sur <?php echo $total; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>

            <ul class="nav nav-tabs" id="detailsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="polluants-tab" data-toggle="tab"
                       href="#polluants" role="tab" aria-controls="polluants" aria-selected="true">
                        Concentrations de polluants atmosphériques
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="depassements-tab" data-toggle="tab"
                       href="#depassements" role="tab" aria-controls="depassements" aria-selected="false">
                        Dépassements des seuils réglementaires
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="predictions-tab" data-toggle="tab"
                       href="#predictions" role="tab" aria-controls="predictions" aria-selected="false">
                        Prédictions des concentrations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="comparaison-tab" data-toggle="tab"
                       href="#comparaison" role="tab" aria-controls="comparaison" aria-selected="false">
                        Comparer les concentrations
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="detailsTabsContent">

                <!-- 1) Onglet Polluants (historique) -->
                <div class="tab-pane fade show active" id="polluants" role="tabpanel" aria-labelledby="polluants-tab">
                    <h2 class="mt-4">Concentrations de polluants atmosphériques</h2>

                    <!-- Filtres polluant + mois (entre 2023-01 et 2025-01) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="polluants-polluant-select">Choisir un polluant :</label>
                            <select id="polluants-polluant-select" class="form-control">
                                <option value="">Tous les polluants</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="polluants-month-select">Filtrer par mois (2023-01 à 2025-01) :</label>
                            <input type="month" id="polluants-month-select" class="form-control"
                                   min="2023-01" max="2025-01">
                        </div>
                    </div>

                    <!-- 2 graphiques (line + bar) filtrés -->
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="polluantsLineChart" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="polluantsBarChart" height="200"></canvas>
                        </div>
                    </div>

                    <hr>

                    <h3 class="mt-4">Tableau des concentrations (historique)</h3>
                    <div class="table-responsive">
                        <table class="table table-striped" id="polluants-table">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- 2) Onglet Dépassements -->
                <div class="tab-pane fade" id="depassements" role="tabpanel" aria-labelledby="depassements-tab">
                    <h2 class="mt-4">Dépassements des seuils réglementaires</h2>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="depassements-month-select">Filtrer par mois (AAAA-MM) :</label>
                            <input type="month" id="depassements-month-select" class="form-control">
                        </div>
                    </div>

                    <?php if (!empty($seuils)): ?>
                        <div id="seuil-filters" class="mb-4">
                            <h5>Filtrer par polluant et seuil :</h5>
                            <div class="form-group">
                                <label for="polluant-select">Sélectionnez un polluant :</label>
                                <select id="polluant-select" class="form-control">
                                    <option value="">-- Sélectionnez un polluant --</option>
                                    <?php foreach ($seuils as $pollSymbol => $types): ?>
                                        <option value="<?php echo htmlspecialchars($pollSymbol); ?>">
                                            <?php echo htmlspecialchars($pollSymbol); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="seuil-type-container" class="form-group" style="display:none;">
                                <label>Types de seuil :</label>
                                <div id="seuil-types-checkboxes"></div>
                            </div>
                        </div>

                        <canvas id="depassementsChart" class="my-4"></canvas>
                        <div id="depassements-text" class="mt-4"></div>

                        <canvas id="depassementsBarChart" height="200"></canvas>
                    <?php else: ?>
                        <div class="alert alert-info mt-4">
                            <p>Aucun seuil réglementaire disponible pour cette ville.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3) Onglet Prédictions -->
                <div class="tab-pane fade" id="predictions" role="tabpanel" aria-labelledby="predictions-tab">
                    <h2 class="mt-4">Prédictions des concentrations</h2>
                    <?php if (!empty($predictions_data)): ?>
                        <p>Voici les prédictions pour la ville de <strong><?php echo htmlspecialchars($ville); ?></strong>.</p>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="prediction-polluant-select">Choisir un polluant :</label>
                                <select id="prediction-polluant-select" class="form-control">
                                    <option value="">Tous les polluants</option>
                                    <?php
                                    // Remplir en PHP direct
                                    $listPredPolluants = array_keys($predictions_data);
                                    foreach ($listPredPolluants as $pKey): ?>
                                        <option value="<?php echo htmlspecialchars($pKey); ?>">
                                            <?php echo htmlspecialchars($pKey); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="prediction-month-select">Filtrer par mois (2025-01 à 2026-01) :</label>
                                <input type="month" id="prediction-month-select" class="form-control"
                                       min="2025-01" max="2026-01">
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <canvas id="predictionChart1" height="200"></canvas>
                            </div>
                            <div class="col-md-6">
                                <canvas id="predictionChart2" height="200"></canvas>
                            </div>
                        </div>

                        <h3 class="mt-4">Tableau récapitulatif des prédictions</h3>
                        <div class="table-responsive">
                            <table class="table table-striped" id="predictions-table">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>Aucune donnée de prédiction disponible pour cette ville.</p>
                    <?php endif; ?>
                </div>

                <!-- 4) Onglet Comparaison -->
                <div class="tab-pane fade" id="comparaison" role="tabpanel" aria-labelledby="comparaison-tab">
                    <h2 class="mt-4">Comparer les concentrations de deux villes</h2>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="city1"><?php echo htmlspecialchars($ville); ?></label>
                            <input type="text" id="city1" class="form-control"
                                   value="<?php echo htmlspecialchars($ville); ?>" disabled>
                        </div>
                        <div class="form-group col-md-6 position-relative">
                            <label for="city2">Ville à comparer</label>
                            <input type="text" id="city2" class="form-control"
                                   placeholder="Entrez le nom de la ville" autocomplete="off">
                            <ul id="suggestions-list"></ul>
                            <input type="hidden" id="city2_hidden">
                        </div>
                        <button id="compareCitiesButton" class="btn btn-primary">Comparer</button>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <canvas id="cityComparisonChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// Passage des variables vers JS
$cityNotFoundJs          = $cityNotFound ? 'true' : 'false';
$polluants_data_js       = json_encode($polluants_data);
$seuils_js               = json_encode($seuils);
$dates_js                = json_encode($dates);
$city_pollution_averages_js = json_encode($city_pollution_averages);
$predictions_data_js     = json_encode($predictions_data);
$pred_dates_js           = json_encode($pred_dates);
$pred_date_labels_js     = json_encode($pred_date_labels);
$predictions_table_avg_js= json_encode($predictions_table_avg);
?>
<script>
    var cityNotFound          = <?php echo $cityNotFoundJs; ?>;
    var polluantsData         = <?php echo $polluants_data_js; ?>;
    var seuilsData            = <?php echo $seuils_js; ?>;
    var measurementIdentifiers= <?php echo json_encode(array_column($dates, 'identifier')); ?>;
    var measurementLabels     = <?php echo json_encode(array_map(function($e){
        return $e['date'].($e['location'] !== 'Inconnu' ? ' - '.$e['location'] : '');
    }, $dates)); ?>;
    var city_pollution_averages = <?php echo $city_pollution_averages_js; ?>;

    // Données prédictions
    var predictionsData       = <?php echo $predictions_data_js; ?>;
    var predDates             = <?php echo $pred_dates_js; ?>;
    var predDateLabels        = <?php echo $pred_date_labels_js; ?>;
    var predictionsTableAvg   = <?php echo $predictions_table_avg_js; ?>;
</script>

<!-- Scripts (suggestions + details.js) -->
<script src="../script/suggestions.js"></script>
<script src="../script/details.js"></script>

<?php
// Inclusion des commentaires et du footer
include 'commentaires.php';
include '../includes/footer.php';
?>
