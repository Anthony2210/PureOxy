<?php
/**
 * details.php
 *
 * Ce code gère la récupération et l'affichage des données de pollution pour une ville donnée.
 * Il enregistre également l'historique des recherches et gère les villes favorites pour les utilisateurs connectés.
 */

session_start();
ob_start();

include '../bd/bd.php';

// Récupérer le nom de la ville
$ville = isset($_GET['ville']) ? $_GET['ville'] : '';

if ($ville) {

    // Enregistrement de la recherche si l'utilisateur est connecté
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Vérifier si la recherche est déjà enregistrée récemment
        $check_stmt = $conn->prepare("SELECT * FROM search_history WHERE user_id = ? AND search_query = ? AND search_date > (NOW() - INTERVAL 1 HOUR)");
        $check_stmt->bind_param("is", $user_id, $ville);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            // Insérer la nouvelle recherche dans l'historique
            $insert_stmt = $conn->prepare("INSERT INTO search_history (user_id, search_query) VALUES (?, ?)");
            $insert_stmt->bind_param("is", $user_id, $ville);
            $insert_stmt->execute();
        }
    }

    // Requête pour récupérer les données de la ville
    $sql = "SELECT * FROM pollution_villes WHERE City = ? ORDER BY `LastUpdated`";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ville);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $cityNotFound = true;
    } else {
        $cityNotFound = false;
        $row = $result->fetch_assoc();
        $departement = $row['Department'];
        $region = $row['Region'];
        $result->data_seek(0);

        // Population du département
        $sql_population = "SELECT Population FROM population_francaise_par_departement_2018 WHERE Département = ?";
        $stmt_population = $conn->prepare($sql_population);
        $stmt_population->bind_param("s", $departement);
        $stmt_population->execute();
        $result_population = $stmt_population->get_result();
        $population = $result_population->fetch_assoc()['Population'] ?? 'Inconnue';

        $pollutants_data = [];
        $dates = [];
        $arrondissements = [];

        // Extraction des données
        while ($row = $result->fetch_assoc()) {
            $pollutant_full = $row['Pollutant'];
            $date = $row['LastUpdated'];
            $location = $row['Location'];

            // Extraire le symbole du polluant
            if (preg_match('/\((.*?)\)/', $pollutant_full, $matches)) {
                $polluant_symbol = strtoupper(trim($matches[1]));
                $polluant_name = trim(str_replace($matches[0], '', $pollutant_full));
            } else {
                $polluant_symbol = strtoupper(trim($pollutant_full));
                $polluant_name = $pollutant_full;
            }

            // Tableau des mois en français
            $mois_francais = [
                '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril', '05' => 'Mai', '06' => 'Juin',
                '07' => 'Juillet', '08' => 'Août', '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'
            ];

            $timestamp = strtotime($date);
            $num_mois = date('m', $timestamp);
            $annee = date('Y', $timestamp);
            $nom_mois = $mois_francais[$num_mois];
            $formattedDate = $nom_mois . ' ' . $annee;

            // Arrondissements (Paris, Lyon, Marseille)
            if (in_array(strtolower($ville), ['paris', 'lyon', 'marseille']) && !in_array($location, $arrondissements)) {
                $arrondissements[] = $location;
            }

            $columnIdentifier = $formattedDate;
            if ($location !== 'Inconnu') {
                $columnIdentifier .= ' - ' . $location;
            }

            if (!in_array($columnIdentifier, array_column($dates, 'identifier'))) {
                $dates[] = ['date' => $formattedDate, 'location' => $location, 'identifier' => $columnIdentifier];
            }

            if (!isset($pollutants_data[$polluant_symbol])) {
                $pollutants_data[$polluant_symbol] = [
                    'name' => $polluant_name,
                    'values' => []
                ];
            }
            $pollutants_data[$polluant_symbol]['values'][$columnIdentifier] = $row['value'];
        }

        // Moyennes de pollution
        $city_pollution_averages = [];
        foreach ($pollutants_data as $polluant_symbol => $data) {
            $total = array_sum($data['values']);
            $count = count($data['values']);
            $average = $count ? $total / $count : 0;
            $city_pollution_averages[$polluant_symbol] = $average;
        }

        // Récupération des seuils
        $sql_seuils = "SELECT polluant, type_norme, valeur, unite, origine FROM seuils_normes";
        $result_seuils = $conn->query($sql_seuils);

        $seuils = [];
        $seuil_types = [];

        if ($result_seuils->num_rows > 0) {
            while ($row_seuil = $result_seuils->fetch_assoc()) {
                $polluant_full = $row_seuil['polluant'];
                if (preg_match('/\((.*?)\)/', $polluant_full, $matches)) {
                    $polluant_symbol = strtoupper(trim($matches[1]));
                } else {
                    $polluant_symbol = strtoupper(trim($polluant_full));
                }

                $type_norme = $row_seuil['type_norme'];
                $valeur = $row_seuil['valeur'];
                $unite = $row_seuil['unite'];
                $origine = $row_seuil['origine'];

                $seuils[$polluant_symbol][$type_norme] = [
                    'valeur' => $valeur,
                    'unite' => $unite,
                    'origine' => $origine
                ];

                if (!in_array($type_norme, $seuil_types)) {
                    $seuil_types[] = $type_norme;
                }
            }
        }

        // Filtrer les seuils pour ne garder que ceux des polluants mesurés
        foreach ($seuils as $polluant_symbol => $types) {
            if (!isset($pollutants_data[$polluant_symbol])) {
                unset($seuils[$polluant_symbol]);
            }
        }

        $seuil_types_filtered = [];
        foreach ($seuils as $polluant_symbol => $types) {
            foreach ($types as $type_norme => $info) {
                if (!in_array($type_norme, $seuil_types_filtered)) {
                    $seuil_types_filtered[] = $type_norme;
                }
            }
        }

        // Favoris utilisateur
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT city_name FROM favorite_cities WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $favorites_result = $stmt->get_result();

            $favorite_cities = [];
            while ($row = $favorites_result->fetch_assoc()) {
                $favorite_cities[] = strtolower($row['city_name']);
            }

            $is_favorite = in_array(strtolower($ville), $favorite_cities);
        } else {
            $is_favorite = false;
        }

        // Ajout/retrait des favoris
        if (isset($_POST['favorite_action']) && isset($_SESSION['user_id'])) {
            $city_name = strtolower($_POST['city_name']);
            $user_id = $_SESSION['user_id'];
            $action = $_POST['favorite_action'];

            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                // AJAX
                if ($action == 'add_favorite') {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $count_result = $stmt->get_result();
                    $count_row = $count_result->fetch_assoc();

                    if ($count_row['count'] < 5) {
                        $stmt = $conn->prepare("INSERT INTO favorite_cities (user_id, city_name) VALUES (?, ?)");
                        $stmt->bind_param("is", $user_id, $city_name);
                        if ($stmt->execute()) {
                            $response = [
                                'success' => true,
                                'message' => 'Ville ajoutée aux favoris.',
                                'action' => 'added'
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Une erreur s\'est produite lors de l\'ajout de la ville.'
                            ];
                        }
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Vous avez atteint le nombre maximum de 5 villes favorites.'
                        ];
                    }
                } elseif ($action == 'remove_favorite') {
                    $stmt = $conn->prepare("DELETE FROM favorite_cities WHERE user_id = ? AND city_name = ?");
                    $stmt->bind_param("is", $user_id, $city_name);
                    if ($stmt->execute()) {
                        $response = [
                            'success' => true,
                            'message' => 'Ville retirée des favoris.',
                            'action' => 'removed'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Une erreur s\'est produite lors de la suppression de la ville.'
                        ];
                    }
                }

                ini_set('display_errors', 0);
                error_reporting(0);
                if (ob_get_length()) {
                    ob_end_clean();
                }
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            } else {
                // Non-AJAX
                if ($action == 'add_favorite') {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $count_result = $stmt->get_result();
                    $count_row = $count_result->fetch_assoc();

                    if ($count_row['count'] < 5) {
                        $stmt = $conn->prepare("INSERT INTO favorite_cities (user_id, city_name) VALUES (?, ?)");
                        $stmt->bind_param("is", $user_id, $city_name);
                        if ($stmt->execute()) {
                            $response = [
                                'success' => true,
                                'message' => 'Ville ajoutée aux favoris.',
                                'action' => 'added'
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Une erreur s\'est produite lors de l\'ajout de la ville.'
                            ];
                        }
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Vous avez atteint le nombre maximum de 5 villes favorites.'
                        ];
                    }
                } elseif ($action == 'remove_favorite') {
                    $stmt = $conn->prepare("DELETE FROM favorite_cities WHERE user_id = ? AND city_name = ?");
                    $stmt->bind_param("is", $user_id, $city_name);
                    if ($stmt->execute()) {
                        $response = [
                            'success' => true,
                            'message' => 'Ville retirée des favoris.',
                            'action' => 'removed'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Une erreur s\'est produite lors de la suppression de la ville.'
                        ];
                    }
                }

                header("Location: details.php?ville=" . urlencode($ville));
                exit;
            }
        }

        // Vérifier dépassements
        $has_depassements = false;
        foreach ($pollutants_data as $polluant_symbol => $data) {
            if (isset($seuils[$polluant_symbol])) {
                foreach ($data['values'] as $value) {
                    foreach ($seuils[$polluant_symbol] as $type_norme => $seuil_info) {
                        if ($value > $seuil_info['valeur']) {
                            $has_depassements = true;
                            break 3;
                        }
                    }
                }
            }
        }

    }

} else {
    echo "<h1>Erreur : Ville non spécifiée</h1>";
    exit;
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
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page détails des villes -->
    <link rel="stylesheet" href="../styles/details.css">
    <!-- Styles pour les commentaires -->
    <link rel="stylesheet" href="../styles/commentaire.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Styles pour les Messages -->
    <link rel="stylesheet" href="../styles/messages.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script>
    <!-- Script pour les interactions AJAX -->
    <script src="../script/messagesAjax.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</head>
<body>

<?php
include '../includes/header.php';
?>

<div id="message-container">
    <?php
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
                        <button>Taper la région ou le début du code postal dans la <a
                                    href="../fonctionnalites/recherche.php">barre de recherche</a>.
                        </button>
                    </li>
                    <li>
                        <button>Rechercher une ville proche géographiquement avec notre <a
                                    href="../fonctionnalites/carte.php">carte interactive</a>.
                        </button>
                    </li>
                    <li>
                        <button>Nous envoyer une demande pour ajouter votre ville via notre <a href="../pages/contact.php">formulaire
                                de contact</a>.
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
                <p>Département : <?php echo htmlspecialchars($departement), '  (', number_format($population, 0, ',', ' '), ' habitants)'; ?></p>
                <p>Région : <?php echo htmlspecialchars($region); ?></p>
            </section>

            <ul class="nav nav-tabs" id="detailsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="polluants-tab" data-toggle="tab" href="#polluants" role="tab"
                       aria-controls="polluants" aria-selected="true">Concentrations de polluants atmosphériques</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="depassements-tab" data-toggle="tab" href="#depassements" role="tab"
                       aria-controls="depassements" aria-selected="false">Dépassements des seuils réglementaires</a>
                </li>
            </ul>

            <div class="tab-content" id="detailsTabsContent">
                <div class="tab-pane fade show active" id="polluants" role="tabpanel" aria-labelledby="polluants-tab">
                    <h2 class="mt-4">Concentrations de polluants atmosphériques</h2>
                    <canvas id="pollutantsChart" class="my-4"></canvas>

                    <p>Le tableau ci-dessous présente les concentrations des différents polluants atmosphériques mesurées dans
                        la ville aux dates et emplacements spécifiés.</p>

                    <?php if (in_array(strtolower($ville), ['paris', 'lyon', 'marseille'])): ?>
                        <select id="arrondissement-select" class="form-control mb-4">
                            <option value="all">Tous les arrondissements</option>
                            <?php
                            sort($arrondissements, SORT_NUMERIC);
                            foreach ($arrondissements as $arrondissement): ?>
                                <option value="<?php echo htmlspecialchars($arrondissement); ?>">
                                    <?php echo htmlspecialchars($arrondissement); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="details-table" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Polluant</th>
                                <?php foreach ($dates as $entry): ?>
                                    <th data-location="<?php echo htmlspecialchars($entry['location']); ?>">
                                        <?php echo htmlspecialchars($entry['date']); ?>
                                        <?php if ($entry['location'] !== 'Inconnu'): ?>
                                            <br><small><?php echo htmlspecialchars($entry['location']); ?></small>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pollutants_data as $polluant_symbol => $data): ?>
                                <tr>
                                    <td data-label="Polluant"><?php echo htmlspecialchars($polluant_symbol); ?></td>
                                    <?php foreach ($dates as $entry): ?>
                                        <?php $identifier = $entry['identifier']; ?>
                                        <?php if (isset($data['values'][$identifier])): ?>
                                            <td data-label="<?php echo htmlspecialchars($entry['date']); ?>"
                                                data-location="<?php echo htmlspecialchars($entry['location']); ?>">
                                                <?php echo htmlspecialchars($data['values'][$identifier]); ?> µg/m³
                                            </td>
                                        <?php else: ?>
                                            <td data-label="<?php echo htmlspecialchars($entry['date']); ?>"
                                                data-location="<?php echo htmlspecialchars($entry['location']); ?>">/
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p><strong>Sources : </strong><a href="https://www.eea.europa.eu/fr" target="_blank">EEA France (Agence
                            Européenne de l'Environnement).</a></p>
                </div>

                <div class="tab-pane fade" id="depassements" role="tabpanel" aria-labelledby="depassements-tab">
                    <h2 class="mt-4">Dépassements des seuils réglementaires</h2>

                    <?php if (!empty($seuils)): ?>
                        <div id="seuil-filters" class="mb-4">
                            <h5>Filtrer par polluant et seuil :</h5>
                            <div class="form-group">
                                <label for="polluant-select">Sélectionnez un polluant :</label>
                                <select id="polluant-select" class="form-control">
                                    <option value="">-- Sélectionnez un polluant --</option>
                                    <?php foreach ($seuils as $polluant_symbol => $types): ?>
                                        <option value="<?php echo htmlspecialchars($polluant_symbol); ?>"><?php echo htmlspecialchars($polluant_symbol); ?></option>
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

                <section id="effets" class="mt-5">
                    <h2>Effets de la pollution atmosphérique</h2>
                    <p>La pollution atmosphérique a des effets néfastes sur la santé humaine, notamment des problèmes respiratoires,
                        cardiovasculaires et des allergies. Elle impacte également l'environnement en contribuant au changement
                        climatique et en affectant les écosystèmes.</p>
                </section>
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
</script>
<script src="../script/details.js"></script>

<?php
include 'commentaires.php';
include '../includes/footer.php';
?>

</body>
</html>