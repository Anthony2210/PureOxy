<?php
/**
 * details.php
 *
 * Gère la récupération et l'affichage des données de pollution pour une ville donnée.
 * Enregistre également l'historique des recherches et gère les villes favorites.
 */

session_start();
ob_start();

// Inclusion de la connexion à la base
include '../bd/bd.php';

/**
 * ------------------------------------------
 * 1) GESTION DE L'AJAX POUR COMPARER DEUX VILLES
 * ------------------------------------------
 */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1'
    && isset($_GET['action']) && $_GET['action'] === 'getPollutants'
) {
    header('Content-Type: application/json; charset=utf-8');

    // Récupère la ville demandée via GET
    $cityToFetch = $_GET['ville'] ?? '';
    if (empty($cityToFetch)) {
        echo json_encode(['error' => 'Aucune ville spécifiée']);
        exit;
    }

    // 1. Trouver l'id_ville correspondant dans donnees_villes
    $sqlFindIdVille = "SELECT id_ville FROM donnees_villes WHERE UPPER(ville) = UPPER(?)";
    $stmtId = $conn->prepare($sqlFindIdVille);
    $stmtId->bind_param("s", $cityToFetch);
    $stmtId->execute();
    $resId = $stmtId->get_result();
    if ($resId->num_rows === 0) {
        echo json_encode(['error' => 'La ville "'.$cityToFetch.'" n’existe pas dans donnees_villes']);
        exit;
    }
    $rowId = $resId->fetch_assoc();
    $idVille = (int)$rowId['id_ville'];

    // 2. Récupérer les mesures (Pollutant, valeur_journaliere) depuis all_years_cleaned_daily
    $sqlCity = "
        SELECT Polluant, valeur_journaliere
        FROM all_years_cleaned_daily
        WHERE id_ville = ?
    ";
    $stmtCity = $conn->prepare($sqlCity);
    $stmtCity->bind_param("i", $idVille);
    $stmtCity->execute();
    $resCity = $stmtCity->get_result();

    if ($resCity->num_rows === 0) {
        echo json_encode(['error' => 'Aucune mesure pour la ville ' . $cityToFetch]);
        exit;
    }

    // On regroupe les valeurs par polluant
    $pollutantsArr = [];
    while ($row = $resCity->fetch_assoc()) {
        $pollSymbol = $row['Pollutant'];
        $val = (float) $row['valeur_journaliere'];
        $pollutantsArr[$pollSymbol][] = $val;
    }

    // Calcul de la moyenne pour chaque polluant
    $finalData = [];
    foreach ($pollutantsArr as $symbol => $values) {
        $moyenne = array_sum($values) / count($values);
        $finalData[$symbol] = round($moyenne, 2);
    }

    // On renvoie le résultat au format JSON
    echo json_encode($finalData);
    exit; // STOP ici pour la requête AJAX
}

/**
 * ------------------------------------------
 * 2) RÉCUPÉRATION DE LA VILLE VIA GET
 * ------------------------------------------
 */
$ville = isset($_GET['ville']) ? $_GET['ville'] : '';
if (!$ville) {
    echo "<h1>Erreur : Ville non spécifiée</h1>";
    exit;
}

// ----------------------------------------------------
// 2A) TROUVER L'ID_VILLE + DEPARTEMENT + REGION DANS donnees_villes
// ----------------------------------------------------
$sqlVille = "SELECT id_ville, departement, region FROM donnees_villes WHERE UPPER(ville) = UPPER(?)";
$stmtVille = $conn->prepare($sqlVille);
$stmtVille->bind_param("s", $ville);
$stmtVille->execute();
$resVille = $stmtVille->get_result();

if ($resVille->num_rows === 0) {
    // Aucune correspondance => cityNotFound = true
    $cityNotFound = true;
} else {
    $cityNotFound = false;
    $rowVille     = $resVille->fetch_assoc();
    $idVille      = (int)$rowVille['id_ville'];
    $departement  = $rowVille['departement'];
    $region       = $rowVille['region'];
}

// ----------------------------------------------------
// 2B) SI UTILISATEUR CONNECTÉ => ENREGISTREMENT DANS search_history
// ----------------------------------------------------
if (!$cityNotFound && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Vérifier si la recherche est déjà enregistrée récemment (id_ville)
    $check_stmt = $conn->prepare("
        SELECT *
        FROM search_history
        WHERE user_id = ?
          AND id_ville = ?
          AND search_date > (NOW() - INTERVAL 1 HOUR)
    ");
    $check_stmt->bind_param("ii", $user_id, $idVille);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        // Insérer la nouvelle recherche : on stocke l'id_ville
        $insert_stmt = $conn->prepare("
            INSERT INTO search_history (user_id, id_ville, search_date)
            VALUES (?, ?, NOW())
        ");
        $insert_stmt->bind_param("ii", $user_id, $idVille);
        $insert_stmt->execute();
    }
}

/**
 * ------------------------------------------
 * 3) SI LA VILLE EST TROUVÉE => RÉCUPÉRER LES MESURES
 * ------------------------------------------
 */
if (!$cityNotFound) {
    // Requête : on ne récupère plus unite_de_mesure
    $sql = "
        SELECT Polluant, jour, valeur_journaliere
        FROM all_years_cleaned_daily
        WHERE id_ville = ?
        ORDER BY jour
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idVille);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Aucune mesure
        $cityNotFound = true;
    } else {
        // Sinon on construit les tableaux
        $pollutants_data = [];
        $dates = [];

        while ($row = $result->fetch_assoc()) {
            $pollutant_full = $row['Polluant'];
            $date           = $row['jour'];    // ex. "2023-01-20"
            $value          = (float)$row['valeur_journaliere'];

            // On n'a plus de "Location", on peut mettre "Inconnu" ou faire autrement
            $location       = 'Inconnu';

            // Formatage de la date en "Mois Année"
            $timestamp = strtotime($date);
            $annee    = date('Y', $timestamp);
            $num_mois = date('m', $timestamp);
            $mois_fr = [
                '01'=>'Janv','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
                '07'=>'Juil.','08'=>'Août','09'=>'Sept.','10'=>'Oct.','11'=>'Nov.','12'=>'Déc'
            ];
            $nom_mois = $mois_fr[$num_mois] ?? $num_mois;
            $formattedDate = $nom_mois . ' ' . $annee;

            // Identifiant pour la colonne (mois + location)
            $columnIdentifier = $formattedDate . ' - ' . $location;

            // Stocker l'identifiant dans $dates si pas déjà présent
            $exists = array_filter($dates, function($d) use ($columnIdentifier) {
                return $d['identifier'] === $columnIdentifier;
            });
            if (empty($exists)) {
                $dates[] = [
                    'date'      => $formattedDate,
                    'location'  => $location,
                    'identifier'=> $columnIdentifier
                ];
            }

            // PollSymbol (en majuscules)
            $pollSymbol = strtoupper($pollutant_full);

            // Initialiser la structure si besoin
            if (!isset($pollutants_data[$pollSymbol])) {
                $pollutants_data[$pollSymbol] = [
                    'name'   => $pollutant_full,
                    'values' => []
                ];
            }

            // On stocke juste la valeur (sans l’unité)
            // car on suppose désormais tout est en µg/m³
            $pollutants_data[$pollSymbol]['values'][$columnIdentifier] = $value;
        }

        // Calcul de la moyenne par polluant
        $city_pollution_averages = [];
        foreach ($pollutants_data as $pollSymbol => $data) {
            $vals = $data['values']; // tableau de valeurs numériques
            $total = array_sum($vals);
            $count = count($vals);
            $average = ($count > 0) ? ($total / $count) : 0;
            $city_pollution_averages[$pollSymbol] = round($average, 2);
        }

        // Récupérer la population (table population_francaise_par_departement_2018)
        $sql_population = "
            SELECT population
            FROM population_francaise_par_departement_2018
            WHERE Département = ?
        ";
        $stmt_pop = $conn->prepare($sql_population);
        $stmt_pop->bind_param("s", $departement);
        $stmt_pop->execute();
        $res_pop = $stmt_pop->get_result();
        $population = $res_pop->fetch_assoc()['population'] ?? 'Inconnue';

        // ---------------------------------------
        // Récupération des SEUILS (table seuils_normes)
        // ---------------------------------------
        $sql_seuils = "SELECT polluant, type_norme, valeur, unite, origine FROM seuils_normes";
        $result_seuils = $conn->query($sql_seuils);

        $seuils = [];
        if ($result_seuils && $result_seuils->num_rows > 0) {
            while ($row_s = $result_seuils->fetch_assoc()) {
                $pollSymbol2 = strtoupper($row_s['polluant']);
                $type_norme  = $row_s['type_norme'];
                $valeur      = $row_s['valeur'];
                $unite       = $row_s['unite'];
                $origine     = $row_s['origine'];

                $seuils[$pollSymbol2][$type_norme] = [
                    'valeur' => $valeur,
                    'unite'  => $unite,
                    'origine'=> $origine
                ];
            }
        }
        // Ne garder que les seuils correspondant aux polluants mesurés
        foreach ($seuils as $pollSymbol2 => $types) {
            if (!isset($pollutants_data[$pollSymbol2])) {
                unset($seuils[$pollSymbol2]);
            }
        }

        // ---------------------------------------
        // Gestion des favoris (table favorite_cities)
        // ---------------------------------------
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];

            // Récupérer la liste des favoris (jointure pour avoir le nom de la ville)
            $stmtFav = $conn->prepare("
                SELECT dv.ville
                FROM favorite_cities fc
                JOIN donnees_villes dv ON fc.id_ville = dv.id_ville
                WHERE fc.user_id = ?
            ");
            $stmtFav->bind_param("i", $user_id);
            $stmtFav->execute();
            $favorites_result = $stmtFav->get_result();

            $favorite_cities = [];
            while ($r = $favorites_result->fetch_assoc()) {
                $favorite_cities[] = strtolower($r['ville']);
            }
            $is_favorite = in_array(strtolower($ville), $favorite_cities);

            // Ajout / retrait des favoris
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
                    $idRowFav = $resFind->fetch_assoc();
                    $idVilleFav = (int)$idRowFav['id_ville'];

                    if ($action == 'add_favorite') {
                        // Vérifier le nombre de favoris
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $count_result = $stmt->get_result();
                        $count_row = $count_result->fetch_assoc();

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
                                $stmtIns = $conn->prepare("INSERT INTO favorite_cities (user_id, id_ville) VALUES (?, ?)");
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
                        // Redirection
                        header("Location: details.php?ville=" . urlencode($ville));
                        exit;
                    }
                    $idRowFav = $resFind->fetch_assoc();
                    $idVilleFav = (int)$idRowFav['id_ville'];

                    if ($action == 'add_favorite') {
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $count_result = $stmt->get_result();
                        $count_row = $count_result->fetch_assoc();

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
                                $stmtIns = $conn->prepare("INSERT INTO favorite_cities (user_id, id_ville) VALUES (?, ?)");
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

                    // Redirection pour recharger la page
                    header("Location: details.php?ville=" . urlencode($ville));
                    exit;
                }
            }
        } else {
            $is_favorite = false;
        }

        // ---------------------------------------
        // Calcul du "classement" (ranking) par polluant
        // ---------------------------------------
        $sqlAllCities = "
            SELECT v.ville AS City, d.Polluant, AVG(d.valeur_journaliere) AS avg_val
            FROM all_years_cleaned_daily d
            JOIN donnees_villes v ON d.id_ville = v.id_ville
            GROUP BY v.ville, d.Polluant
            ORDER BY d.Polluant, avg_val DESC
        ";
        $resultAllCities = $conn->query($sqlAllCities);

        $rankingByPollutant = [];
        if ($resultAllCities && $resultAllCities->num_rows > 0) {
            while ($rowAll = $resultAllCities->fetch_assoc()) {
                $poll   = $rowAll['Polluant'];
                $city   = $rowAll['City'];
                $avgVal = (float) $rowAll['avg_val'];

                if (!isset($rankingByPollutant[$poll])) {
                    $rankingByPollutant[$poll] = [];
                }
                $rankingByPollutant[$poll][] = [
                    'city'    => $city,
                    'avg_val' => $avgVal
                ];
            }
        }
        // Attribuer un rang
        foreach ($rankingByPollutant as $poll => &$rows) {
            usort($rows, function($a, $b) {
                // Tri décroissant
                return $b['avg_val'] <=> $a['avg_val'];
            });
            $rang = 1;
            foreach ($rows as &$item) {
                $item['rank'] = $rang++;
            }
        }
        unset($rows);

        // Retrouver le rang de la ville courante
        $cityRankByPollutant = [];
        foreach ($rankingByPollutant as $poll => $rows) {
            $totalVilles = count($rows);
            foreach ($rows as $item) {
                if (strtolower($item['city']) === strtolower($ville)) {
                    $cityRankByPollutant[$poll] = [
                        'rank'  => $item['rank'],
                        'total' => $totalVilles
                    ];
                    break;
                }
            }
        }

        // ---------------------------------------
        // Récupérer les prédictions (prediction_cities)
        // ---------------------------------------
        $sqlPred = "
            SELECT jour, Polluant, valeur_predite
            FROM prediction_cities
            WHERE UPPER(ville) = UPPER(?)
            ORDER BY jour
        ";
        $stmtPred = $conn->prepare($sqlPred);
        $stmtPred->bind_param("s", $ville);
        $stmtPred->execute();
        $resPred = $stmtPred->get_result();

        $predictions_data = [];
        if ($resPred && $resPred->num_rows > 0) {
            while ($rowp = $resPred->fetch_assoc()) {
                $poll = $rowp['Polluant'];
                $date = $rowp['jour'];
                $valp = (float)$rowp['valeur_predite'];

                if (!isset($predictions_data[$poll])) {
                    $predictions_data[$poll] = [];
                }
                $predictions_data[$poll][] = [
                    'date'  => $date,
                    'value' => $valp
                ];
            }
        }
        $predictions_table = [];    // stocke les valeurs par polluant et par clé "YYYY-MM"
        $pred_date_labels  = [];    // stocke les libellés humains "Février 2025" etc.

        $mois_fr = [
            '01' => 'Janv', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
            '05' => 'Mai',   '06' => 'Juin',   '07' => 'Juil.', '08' => 'Août',
            '09' => 'Sept.', '10' => 'Oct.',   '11' => 'Nov.',  '12' => 'Déc'
        ];

        if (!empty($predictions_data)) {
            // On parcourt les prédictions
            foreach ($predictions_data as $polluant => $rows) {
                foreach ($rows as $row) {
                    $date       = $row['date']; // ex. "2025-02-11"
                    $timestamp  = strtotime($date);
                    $annee      = date('Y', $timestamp);  // ex. "2025"
                    $num_mois   = date('m', $timestamp);  // ex. "02"

                    // Clé de tri = "2025-02"
                    $monthKey   = $annee . '-' . $num_mois;

                    // Libellé humain (ex. "Février 2025")
                    $monthLabel = $mois_fr[$num_mois] . ' ' . $annee;

                    // On mémorise ce label
                    $pred_date_labels[$monthKey] = $monthLabel;

                    // On stocke la valeur prédite dans un tableau (liste),
                    // car il peut y avoir plusieurs mesures pour le même polluant/mois
                    $predictions_table[$polluant][$monthKey][] = $row['value'];
                }
            }

            // Récupérer la liste des clés "2025-02", "2025-03", etc.
            $pred_dates = array_keys($pred_date_labels);

            // Trier dans l'ordre croissant => tri chronologique
            sort($pred_dates);

            // Calculer la moyenne pour chaque (polluant, mois)
            $predictions_table_avg = [];
            foreach ($predictions_table as $polluant => $moisArr) {
                foreach ($moisArr as $monthKey => $values) {
                    $moyenne = array_sum($values) / count($values);
                    $predictions_table_avg[$polluant][$monthKey] = round($moyenne, 2);
                }
            }
        } else {
            // Pas de prédictions
            $pred_dates            = [];
            $pred_date_labels      = [];
            $predictions_table_avg = [];
        }

    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Données détaillées de <?php echo htmlspecialchars($ville); ?></title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <!-- Tes styles -->
    <link rel="stylesheet" href="../styles/recherche.css">
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/details.css">
    <link rel="stylesheet" href="../styles/commentaire.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <link rel="stylesheet" href="../styles/messages.css">
    <!-- Scripts (Chart.js, jQuery, Popper, Bootstrap) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div id="message-container">
    <?php
    // Affichage des messages de succès/erreur suite à l'ajout/retrait de favoris
    if (isset($response['success']) && $response['success'] === true) {
        echo '<div class="success-message">' . htmlspecialchars($response['message'], ENT_QUOTES, 'UTF-8') . '</div>';
    } elseif (isset($response['success']) && $response['success'] === false) {
        echo '<div class="error-message">' . htmlspecialchars($response['message'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>
</div>

<main>
    <div id="details-page" class="container">
        <?php if ($cityNotFound): ?>
            <section id="city-not-found" class="text-center">
                <h1>Oups ! Aucune donnée disponible pour la ville de "<?php echo htmlspecialchars($ville); ?>"</h1>
                <p>Il semble que nous n'ayons pas de données pour cette ville.</p>
                <p>Pour trouver une ville proche de la vôtre, vous pouvez :</p>
                <ul>
                    <li>
                        <button>
                            Taper la région ou le début du code postal dans la
                            <a href="../pages/recherche.php">barre de recherche</a>.
                        </button>
                    </li>
                    <li>
                        <button>
                            Rechercher une ville proche géographiquement avec notre
                            <a href="../pages/carte.php">carte interactive</a>.
                        </button>
                    </li>
                    <li>
                        <button>
                            Nous envoyer une demande pour ajouter votre ville via notre
                            <a href="../pages/contact.php">formulaire de contact</a>.
                        </button>
                    </li>
                </ul>
            </section>
        <?php else: ?>
        <section id="intro">
            <h1 class="text-center mb-4">
                <?php echo htmlspecialchars($ville); ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form id="favorite-form" method="post" style="display: inline;">
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
                Département :
                <?php echo htmlspecialchars($departement), '  (', number_format($population, 0, ',', ' '), ' habitants)'; ?>
            </p>
            <p>Région : <?php echo htmlspecialchars($region); ?></p>

            <?php if (!empty($cityRankByPollutant)): ?>
                <div style="margin-top: 1em;">
                    <strong>Classement en termes de pollution :</strong>
                    <ul style="list-style: none; padding-left: 0;">
                        <?php foreach ($cityRankByPollutant as $poll => $info):
                            $rank  = $info['rank'];
                            $total = $info['total'];
                            ?>
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
                <a class="nav-link active" id="polluants-tab" data-toggle="tab" href="#polluants" role="tab"
                   aria-controls="polluants" aria-selected="true">
                    Concentrations de polluants atmosphériques
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="depassements-tab" data-toggle="tab" href="#depassements" role="tab"
                   aria-controls="depassements" aria-selected="false">
                    Dépassements des seuils réglementaires
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="predictions-tab" data-toggle="tab" href="#predictions" role="tab"
                   aria-controls="predictions" aria-selected="false">
                    Prédictions des concentrations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="comparaison-tab" data-toggle="tab" href="#comparaison" role="tab"
                   aria-controls="comparaison" aria-selected="false">
                    Comparer les concentrations
                </a>
            </li>
        </ul>

        <div class="tab-content" id="detailsTabsContent">
            <!-- Onglet Polluants -->
            <div class="tab-pane fade show active" id="polluants" role="tabpanel" aria-labelledby="polluants-tab">
                <h2 class="mt-4">Concentrations de polluants atmosphériques</h2>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <canvas id="concentrationsChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="polluantsChart" height="200"></canvas>
                    </div>
                </div>


                <div class="table-responsive">
                    <table id="details-table" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Polluant</th>
                            <?php foreach ($dates as $entry): ?>
                                <th data-location="<?php echo htmlspecialchars($entry['location']); ?>">
                                    <?php echo htmlspecialchars($entry['date']); ?>
                                    <?php if ($entry['location'] !== 'Inconnu'): ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($entry['location']); ?></small>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pollutants_data as $pollSymbol => $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pollSymbol); ?></td>
                                <?php foreach ($dates as $entry):
                                    $identifier = $entry['identifier'];
                                    $val = isset($data['values'][$identifier]) ? $data['values'][$identifier] : null;
                                    ?>
                                    <td>
                                        <?php echo $val !== null ? round($val, 2) . ' µg/m³' : '/'; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p><strong>Sources : </strong>
                    <a href="https://www.eea.europa.eu/fr" target="_blank">
                        EEA France (Agence Européenne de l'Environnement)
                    </a>.
                </p>
            </div>

            <!-- Onglet Dépassements -->
            <div class="tab-pane fade" id="depassements" role="tabpanel" aria-labelledby="depassements-tab">
                <h2 class="mt-4">Dépassements des seuils réglementaires</h2>
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
                        <div id="seuil-type-container" class="form-group" style="display: none;">
                            <label>Types de seuil :</label>
                            <div id="seuil-types-checkboxes"></div>
                        </div>
                    </div>

                    <canvas id="depassementsChart" class="my-4"></canvas>
                    <div id="depassements-text" class="mt-4"></div>
                <?php else: ?>
                    <div class="alert alert-info mt-4" role="alert">
                        <p>Aucun seuil réglementaire disponible pour cette ville.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Prédictions -->
            <div class="tab-pane fade" id="predictions" role="tabpanel" aria-labelledby="predictions-tab">
                <h2 class="mt-4">Prédictions des concentrations</h2>
                <?php if (!empty($predictions_data)): ?>
                    <p>
                        Voici les prédictions pour la ville de
                        <strong><?php echo htmlspecialchars($ville); ?></strong>.
                    </p>

                    <h3>Tableau récapitulatif des prédictions</h3>
                    <div class="table-responsive">
                        <table class="table table-striped" id="predictions-pivot-table">
                            <thead>
                            <tr>
                                <th>Polluant</th>
                                <?php foreach ($pred_dates as $monthKey): ?>
                                    <th><?php echo htmlspecialchars($pred_date_labels[$monthKey]); ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($predictions_table_avg as $polluant => $moisArr): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($polluant); ?></td>
                                    <?php foreach ($pred_dates as $monthKey): ?>
                                        <td>
                                            <?php
                                            echo isset($moisArr[$monthKey])
                                                ? $moisArr[$monthKey] . ' µg/m³'
                                                : '/';
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php /**<div class="row mb-3">
                        <div class="col-md-6">
                            <label for="prediction-pollutant-select">Filtrer par polluant :</label>
                            <select id="prediction-pollutant-select" class="form-control">
                                <option value="">Tous les polluants</option>
                                <?php
                                $uniquePolluants = array_keys($predictions_data);
                                foreach ($uniquePolluants as $poll) {
                                    echo '<option value="'.htmlspecialchars($poll).'">'.htmlspecialchars($poll).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="prediction-month-select">Filtrer par mois :</label>
                            <select id="prediction-month-select" class="form-control">
                                <option value="">Tous les mois</option>
                                <?php
                                $uniqueMonths = [];
                                foreach ($predictions_data as $poll => $rows) {
                                    foreach ($rows as $r) {
                                        $month = substr($r['date'], 0, 7);
                                        if (!in_array($month, $uniqueMonths)) {
                                            $uniqueMonths[] = $month;
                                        }
                                    }
                                }
                                sort($uniqueMonths);
                                foreach ($uniqueMonths as $m) {
                                    echo '<option value="'.$m.'">'.$m.'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- TABLEAU DES PRÉDICTIONS DÉTAILLÉ -->
                    <div class="table-responsive" id="predictions-table-container">
                        <table class="table table-striped" id="predictions-table">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Polluant</th>
                                <th>Valeur Prédite (µg/m³)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($predictions_data as $poll => $rows): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr data-polluant="<?php echo htmlspecialchars($poll); ?>"
                                        data-date="<?php echo htmlspecialchars($row['date']); ?>">
                                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td><?php echo htmlspecialchars($poll); ?></td>
                                        <td><?php echo round($row['value'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>**/?>

                    <!-- DEUX GRAPHIQUES -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <canvas id="predictionChart1" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="predictionChart2" height="200"></canvas>
                        </div>
                    </div>

                <?php else: ?>
                    <p>Aucune donnée de prédiction disponible pour cette ville.</p>
                <?php endif; ?>
            </div>

            <!-- Onglet Comparaison -->
            <div class="tab-pane fade" id="comparaison" role="tabpanel" aria-labelledby="comparaison-tab">
                <h2 class="mt-4">Comparer les concentrations de deux villes</h2>
                <div class="form-row">
                    <!-- Ville 1 (verrouillée) -->
                    <div class="form-group col-md-6">
                        <label for="city1"><?php echo htmlspecialchars($ville); ?></label>
                        <input type="text" id="city1" class="form-control"
                               value="<?php echo htmlspecialchars($ville); ?>" disabled>
                    </div>

                    <!-- Ville 2 (avec suggestions) -->
                    <div class="form-group col-md-6 position-relative">
                        <label for="city2">Ville à comparer</label>
                        <input type="text" id="city2" class="form-control"
                               placeholder="Entrez le nom de la ville à comparer" autocomplete="off">
                        <ul id="suggestions-list"></ul>
                        <input type="hidden" id="city2_hidden">
                    </div>
                    <button id="compareCitiesButton" class="btn btn-primary">Comparer</button>
                    <div class="row mt-4">
                        <div class="col-12">
                            <canvas id="cityComparisonChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
</main>

<?php
// Passage des variables PHP vers JS
$cityNotFoundJs = $cityNotFound ? 'true' : 'false';
$pollutants_data_js = json_encode($pollutants_data);
$seuils_js = json_encode($seuils);
$dates_js = json_encode($dates);
$city_pollution_averages_js = json_encode($city_pollution_averages);
?>

<script>
    var cityNotFound = <?php echo $cityNotFoundJs; ?>;
    var pollutantsData = <?php echo $pollutants_data_js; ?>;
    var seuilsData = <?php echo $seuils_js; ?>;
    var measurementIdentifiers = <?php echo json_encode(array_column($dates, 'identifier')); ?>;
    var measurementLabels = <?php echo json_encode(array_map(function($entry) {
        return $entry['date'] . ($entry['location'] !== 'Inconnu' ? ' - ' . $entry['location'] : '');
    }, $dates)); ?>;
    var city_pollution_averages = <?php echo $city_pollution_averages_js; ?>;
    var predictionsData = <?php echo json_encode($predictions_data); ?>;
</script>

<!-- Scripts pour suggestions, détails, etc. -->
<script src="../script/suggestions.js"></script>
<script src="../script/details.js"></script>

<?php
include 'commentaires.php';
include '../includes/footer.php';
?>

</body>
</html>
