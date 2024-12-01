<?php
session_start();
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../bd/bd.php';

// Récupérer le nom de la ville depuis l'URL
$ville = isset($_GET['ville']) ? $_GET['ville'] : '';

if ($ville) {

    // Enregistrement de la recherche si l'utilisateur est connecté
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Vérifier si la recherche est déjà enregistrée récemment pour éviter les doublons
        $check_stmt = $conn->prepare("SELECT * FROM search_history WHERE user_id = ? AND search_query = ? AND search_date > (NOW() - INTERVAL 1 HOUR)");
        $check_stmt->bind_param("is", $user_id, $ville);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $insert_stmt = $conn->prepare("INSERT INTO search_history (user_id, search_query) VALUES (?, ?)");
            $insert_stmt->bind_param("is", $user_id, $ville);
            $insert_stmt->execute();
        }
    }

    // Requête SQL pour obtenir les données spécifiques à la ville
    $sql = "SELECT * FROM pollution_villes WHERE City = ? ORDER BY `LastUpdated`";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ville);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $cityNotFound = true;
    } else {
        $cityNotFound = false;

        // Récupérer les informations de la première ligne pour le département et la région
        $row = $result->fetch_assoc();
        $departement = $row['Department'];
        $region = $row['Region'];
        // Remettre le pointeur du résultat au début
        $result->data_seek(0);

        // Récupérer la population du département
        $sql_population = "SELECT Population FROM population_francaise_par_departement_2018 WHERE Département = ?";
        $stmt_population = $conn->prepare($sql_population);
        $stmt_population->bind_param("s", $departement);
        $stmt_population->execute();
        $result_population = $stmt_population->get_result();
        $population = $result_population->fetch_assoc()['Population'] ?? 'Inconnue';


        // Préparer les données à afficher dans le tableau des polluants
        $pollutants_data = [];
        $dates = [];
        $arrondissements = [];

        while ($row = $result->fetch_assoc()) {
            $pollutant_full = $row['Pollutant']; // Exemple: "Monoxyde de carbone (CO)"
            $date = $row['LastUpdated'];
            $location = $row['Location'];

            // Extraire le symbole du polluant
            if (preg_match('/\((.*?)\)/', $pollutant_full, $matches)) {
                $polluant_symbol = strtoupper(trim($matches[1])); // e.g., "CO"
                $polluant_name = trim(str_replace($matches[0], '', $pollutant_full)); // e.g., "Monoxyde de carbone"
            } else {
                $polluant_symbol = strtoupper(trim($pollutant_full)); // Fallback
                $polluant_name = $pollutant_full;
            }

            // Tableau des noms de mois en français
            $mois_francais = [
                '01' => 'Janvier',
                '02' => 'Février',
                '03' => 'Mars',
                '04' => 'Avril',
                '05' => 'Mai',
                '06' => 'Juin',
                '07' => 'Juillet',
                '08' => 'Août',
                '09' => 'Septembre',
                '10' => 'Octobre',
                '11' => 'Novembre',
                '12' => 'Décembre'
            ];

            // Extraire le numéro du mois et l'année
            $timestamp = strtotime($date);
            $num_mois = date('m', $timestamp);
            $annee = date('Y', $timestamp);

            // Obtenir le nom du mois en français
            $nom_mois = $mois_francais[$num_mois];

            // Assembler la date formatée
            $formattedDate = $nom_mois . ' ' . $annee;

            // Ajouter l'arrondissement dans le tableau s'il n'est pas encore présent (pour Paris, Lyon, Marseille)
            if (in_array(strtolower($ville), ['paris', 'lyon', 'marseille']) && !in_array($location, $arrondissements)) {
                $arrondissements[] = $location;
            }

            // Identifier l'entrée par la date et la localisation
            $columnIdentifier = $formattedDate;
            if ($location !== 'Inconnu') {
                $columnIdentifier .= ' - ' . $location;
            }

            // Éviter les doublons dans les colonnes
            if (!in_array($columnIdentifier, array_column($dates, 'identifier'))) {
                $dates[] = ['date' => $formattedDate, 'location' => $location, 'identifier' => $columnIdentifier];
            }

            // Ajouter les données du polluant
            if (!isset($pollutants_data[$polluant_symbol])) {
                $pollutants_data[$polluant_symbol] = [
                    'name' => $polluant_name,
                    'values' => []
                ];
            }
            $pollutants_data[$polluant_symbol]['values'][$columnIdentifier] = $row['value'];
        }

        // Calculer la moyenne de la pollution pour chaque polluant dans la ville
        $city_pollution_averages = [];
        foreach ($pollutants_data as $polluant_symbol => $data) {
            $total = array_sum($data['values']);
            $count = count($data['values']);
            $average = $count ? $total / $count : 0;
            $city_pollution_averages[$polluant_symbol] = $average;
        }

        // Récupérer tous les seuils depuis la table seuils_normes
        $sql_seuils = "SELECT polluant, type_norme, valeur, unite, origine FROM seuils_normes";
        $result_seuils = $conn->query($sql_seuils);

        $seuils = [];
        $seuil_types = []; // Pour les filtres

        if ($result_seuils->num_rows > 0) {
            while ($row_seuil = $result_seuils->fetch_assoc()) {
                $polluant_full = $row_seuil['polluant']; // Exemple: "Monoxyde de carbone (CO)"
                // Extraire le symbole du polluant
                if (preg_match('/\((.*?)\)/', $polluant_full, $matches)) {
                    $polluant_symbol = strtoupper(trim($matches[1])); // e.g., "CO"
                } else {
                    $polluant_symbol = strtoupper(trim($polluant_full)); // Fallback
                }

                $type_norme = $row_seuil['type_norme'];
                $valeur = $row_seuil['valeur'];
                $unite = $row_seuil['unite'];
                $origine = $row_seuil['origine']; // Nouvelle variable pour l'origine

                // Organiser les seuils par polluant et type_norme
                $seuils[$polluant_symbol][$type_norme] = [
                    'valeur' => $valeur,
                    'unite' => $unite,
                    'origine' => $origine // Ajouter l'origine ici
                ];

                // Collecter les types de normes uniques pour les filtres
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

        // Récupérer les types de normes après filtrage
        $seuil_types_filtered = [];
        foreach ($seuils as $polluant_symbol => $types) {
            foreach ($types as $type_norme => $info) {
                if (!in_array($type_norme, $seuil_types_filtered)) {
                    $seuil_types_filtered[] = $type_norme;
                }
            }
        }

        // Vérifier si l'utilisateur est connecté et récupérer ses favoris
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];

            // Récupérer les villes favorites de l'utilisateur
            $stmt = $conn->prepare("SELECT city_name FROM favorite_cities WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $favorites_result = $stmt->get_result();

            $favorite_cities = [];
            while ($row = $favorites_result->fetch_assoc()) {
                $favorite_cities[] = strtolower($row['city_name']); // Conversion en minuscules
            }

            // Vérifier si la ville actuelle est dans les favoris
            $is_favorite = in_array(strtolower($ville), $favorite_cities);
        } else {
            $is_favorite = false; // L'utilisateur n'est pas connecté, donc pas de favoris
        }

        // Ajouter ou retirer la ville des favoris
        if (isset($_POST['favorite_action']) && isset($_SESSION['user_id'])) {
            $city_name = strtolower($_POST['city_name']); // Conversion en minuscules
            $user_id = $_SESSION['user_id']; // Récupérer directement de la session
            $action = $_POST['favorite_action'];

            if ($action == 'add_favorite') {
                // Vérifier que l'utilisateur n'a pas déjà 5 villes favorites
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorite_cities WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $count_result = $stmt->get_result();
                $count_row = $count_result->fetch_assoc();

                if ($count_row['count'] < 5) {
                    // Ajouter la ville aux favoris
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
                // Retirer la ville des favoris
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

            // Vérifier si la requête est une requête AJAX
            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                ini_set('display_errors', 0);
                error_reporting(0);
                if (ob_get_length()) {
                    ob_end_clean(); // Nettoyer et fermer le buffer de sortie
                }
                header('Content-Type: application/json');
                echo json_encode($response);
                exit; // Arrêter le script
            } else {
                // Si ce n'est pas une requête AJAX, rediriger normalement
                header("Location: details.php?ville=" . urlencode($ville));
                exit; // Arrêter le script
            }
        }

        // Vérifier s'il y a des dépassements
        $has_depassements = false;
        foreach ($pollutants_data as $polluant_symbol => $data) {
            if (isset($seuils[$polluant_symbol])) {
                foreach ($data['values'] as $value) {
                    foreach ($seuils[$polluant_symbol] as $type_norme => $seuil_info) {
                        if ($value > $seuil_info['valeur']) {
                            $has_depassements = true;
                            break 3; // Sortir de toutes les boucles si un dépassement est trouvé
                        }
                    }
                }
            }
        }
    }

} else {
    echo "<h1>Erreur : Ville non spécifiée</h1>";
    exit; // Arrêter l'exécution si la ville n'est pas spécifiée
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Données détaillées de <?php echo htmlspecialchars($ville); ?></title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap"
          rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Lien Font Awesome pour les icônes -->
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php
include '../includes/header.php';
?>

<!-- Conteneur pour les messages -->
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
    <!-- Introduction -->
    <section id="intro">
        <h1 class="text-center mb-4">
            <?php echo htmlspecialchars($ville); ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form id="favorite-form" method="post" style="display: inline;">
                    <input type="hidden" name="city_name" value="<?php echo htmlspecialchars($ville); ?>">
                    <!-- Champ caché pour l'action -->
                    <input type="hidden" name="favorite_action" id="favorite_action" value="">
                    <button type="submit" class="favorite-icon"
                            data-action="<?php echo $is_favorite ? 'remove_favorite' : 'add_favorite'; ?>">
                        <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                    </button>
                </form>

            <?php endif; ?>
        </h1>
        <p>Département
            : <?php echo htmlspecialchars($departement), '  (', number_format($population, 0, ',', ' '), ' habitants)'; ?></p>
        <p>Région : <?php echo htmlspecialchars($region); ?></p>
    </section>

<!-- Onglets -->
    <ul class="nav nav-tabs" id="detailsTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="polluants-tab" data-toggle="tab" href="#polluants" role="tab"
               aria-controls="polluants" aria-selected="true">Concentrations de polluants atmosphériques</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="depassements-tab" data-toggle="tab" href="#depassements" role="tab"
               aria-controls="depassements" aria-selected="false">Dépassements des seuils réglementaires</a>
        </li>
        <!-- L'onglet Émissions a été supprimé -->
    </ul>

    <div class="tab-content" id="detailsTabsContent">
        <!-- Onglet Polluants -->
        <div class="tab-pane fade show active" id="polluants" role="tabpanel" aria-labelledby="polluants-tab">
            <h2 class="mt-4">Concentrations de polluants atmosphériques</h2>

            <!-- Graphique des polluants -->
            <canvas id="pollutantsChart" class="my-4"></canvas>

            <p>Le tableau ci-dessous présente les concentrations des différents polluants atmosphériques mesurées dans
                la ville aux dates et emplacements spécifiés.</p>

            <?php if (in_array(strtolower($ville), ['paris', 'lyon', 'marseille'])): ?>
                <select id="arrondissement-select" class="form-control mb-4">
                    <option value="all">Tous les arrondissements</option>
                    <?php
                    // Trie les arrondissements en ordre numérique
                    sort($arrondissements, SORT_NUMERIC);

                    // Boucle pour afficher chaque arrondissement
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

        <!-- Onglet Dépassements -->
        <div class="tab-pane fade" id="depassements" role="tabpanel" aria-labelledby="depassements-tab">
            <h2 class="mt-4">Dépassements des seuils réglementaires</h2>

            <?php if (!empty($seuils)): ?>
                <!-- Système de filtres -->
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
                        <div id="seuil-types-checkboxes">
                            <!-- Checkboxes des types de seuil seront ajoutés ici dynamiquement -->
                        </div>
                    </div>
                </div>

                <!-- Graphique des mesures et seuils -->
                <canvas id="depassementsChart" class="my-4"></canvas>

                <!-- Texte indiquant s'il y a des dépassements -->
                <div id="depassements-text" class="mt-4">
                    <!-- Le texte sera ajouté ici via JavaScript -->
                </div>

            <?php else: ?>
                <div class="alert alert-info mt-4" role="alert">
                    <p>Aucun seuil réglementaire disponible pour cette ville.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section des effets -->
        <section id="effets" class="mt-5">
            <h2>Effets de la pollution atmosphérique</h2>
            <p>La pollution atmosphérique a des effets néfastes sur la santé humaine, notamment des problèmes respiratoires,
                cardiovasculaires et des allergies. Elle impacte également l'environnement en contribuant au changement
                climatique et en affectant les écosystèmes.</p>
        </section>

        <?php endif; ?>
    </div>
    </main>
    <!-- Vos scripts JavaScript -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="../script/messagesAjax.js"></script>

    <!-- Scripts pour les graphiques Polluants et Dépassements -->
    <script>
        <?php if (!$cityNotFound): ?>

        // Données détaillées des mesures
        var pollutantsData = <?php echo json_encode($pollutants_data); ?>;
        var seuilsData = <?php echo json_encode($seuils); ?>;

        // Préparer les labels (dates et emplacements)
        var measurementIdentifiers = <?php echo json_encode(array_column($dates, 'identifier')); ?>;
        var measurementLabels = <?php echo json_encode(array_map(function($entry) {
            return $entry['date'] . ($entry['location'] !== 'Inconnu' ? ' - ' . $entry['location'] : '');
        }, $dates)); ?>;

        // Débogage : Afficher les données dans la console
        console.log('pollutantsData:', pollutantsData);
        console.log('seuilsData:', seuilsData);
        console.log('measurementLabels:', measurementLabels);

        // Créer le graphique des polluants (Concentrations)
        var pollutantsLabels = [];
        var pollutantsChartData = [];

        // Calculer les moyennes par polluant
        var pollutantsAvgData = <?php echo json_encode($city_pollution_averages); ?>;

        // Préparer les labels et données pour le graphique des polluants
        for (var pollutant_symbol in pollutantsAvgData) {
            if (pollutantsAvgData.hasOwnProperty(pollutant_symbol)) {
                pollutantsLabels.push(pollutant_symbol); // Utiliser le symbole comme label
                pollutantsChartData.push(parseFloat(pollutantsAvgData[pollutant_symbol]).toFixed(2));
            }
        }

        // Créer le graphique des polluants
        var ctxPolluants = document.getElementById('pollutantsChart').getContext('2d');
        var pollutantsChart = new Chart(ctxPolluants, {
            type: 'bar',
            data: {
                labels: pollutantsLabels,
                datasets: [{
                    label: 'Concentration Moyenne (µg/m³)',
                    data: pollutantsChartData,
                    backgroundColor: 'rgba(107,142,35, 0.7)', // Vert harmonieux
                    borderColor: 'rgba(255,255,255, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Concentration (µg/m³)'
                        }
                    }
                }
            }
        });

        // Variables globales pour le graphique des dépassements
        var depassementsChart;

        // Fonction pour initialiser le graphique des dépassements
        function initDepassementsChart(selectedPolluant, selectedSeuilTypes) {
            // Vérifier si les seuils pour le polluant sélectionné existent
            if (!seuilsData[selectedPolluant] || Object.keys(seuilsData[selectedPolluant]).length === 0) {
                console.warn('Aucun seuil disponible pour le polluant sélectionné:', selectedPolluant);
                // Détruire tout graphique existant et vider le texte
                if (depassementsChart) {
                    depassementsChart.destroy();
                }
                document.getElementById('depassements-text').innerHTML = '';
                return;
            }

            // Récupérer les mesures pour le polluant sélectionné
            var measurements = pollutantsData[selectedPolluant] ? pollutantsData[selectedPolluant]['values'] : {};

            // Préparer les données
            var measurementValues = [];
            var measurementLabelsLocal = measurementLabels;

            measurementLabelsLocal.forEach(function(identifier) {
                var value = measurements[identifier] !== undefined ? parseFloat(measurements[identifier]) : null;
                measurementValues.push(value);
            });

            // Déterminer le nombre de mesures non nulles
            var nonNullMeasurements = measurementValues.filter(function(value) {
                return value !== null;
            }).length;

            // Définir dynamiquement le type de graphique
            var chartType = nonNullMeasurements === 1 ? 'bar' : 'line';

            // Récupérer les seuils sélectionnés
            var seuilsSelected = selectedSeuilTypes || [];

            // Vérifier si des seuils sont sélectionnés
            if (seuilsSelected.length === 0) {
                console.warn('Aucun seuil sélectionné pour le polluant:', selectedPolluant);
                // Détruire tout graphique existant et vider le texte
                if (depassementsChart) {
                    depassementsChart.destroy();
                }
                document.getElementById('depassements-text').innerHTML = '';
                return;
            }

            // Préparer les datasets
            var datasets = [{
                label: 'Mesures (µg/m³)',
                data: measurementValues,
                borderColor: 'rgba(54, 162, 235, 1)', // Bleu
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: chartType === 'line' ? false : true, // Remplir uniquement pour les barres
                tension: 0.1,
                pointRadius: 3
            }];

            // Ajouter les seuils sélectionnés
            seuilsSelected.forEach(function(typeNorme) {
                if (seuilsData[selectedPolluant] && seuilsData[selectedPolluant][typeNorme]) {
                    var seuilValue = seuilsData[selectedPolluant][typeNorme]['valeur'];
                    var seuilUnite = seuilsData[selectedPolluant][typeNorme]['unite'];
                    var seuilOrigine = seuilsData[selectedPolluant][typeNorme]['origine']; // Récupérer l'origine

                    datasets.push({
                        label: typeNorme + ' (' + seuilOrigine + ') (' + seuilValue + ' ' + seuilUnite + ')',
                        data: Array(measurementLabelsLocal.length).fill(seuilValue),
                        borderColor: getColorForTypeNorme(typeNorme),
                        backgroundColor: 'rgba(255, 99, 132, 0.2)', // Red
                        fill: false,
                        borderDash: [5, 5],
                        tension: 0.1,
                        pointRadius: 0
                    });
                }
            });

            // Détruire le graphique précédent s'il existe
            if (depassementsChart) {
                depassementsChart.destroy();
            }

            // Créer le graphique avec le type déterminé
            var ctxDepassements = document.getElementById('depassementsChart').getContext('2d');
            depassementsChart = new Chart(ctxDepassements, {
                type: chartType,
                data: {
                    labels: measurementLabelsLocal,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (context.parsed.y !== null) {
                                        if (context.dataset.borderDash && context.dataset.borderDash.length > 0) {
                                            // Seuil
                                            return label + ': ' + context.parsed.y;
                                        } else {
                                            // Mesure
                                            return label + ': ' + context.parsed.y + ' µg/m³';
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Concentration (µg/m³)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });

            // Vérifier s'il y a des dépassements
            var depassementExiste = false;
            if (selectedSeuilTypes && selectedSeuilTypes.length > 0) {
                for (var i = 0; i < measurementValues.length; i++) {
                    var mesure = measurementValues[i];
                    if (mesure === null) continue;
                    for (var j = 0; j < selectedSeuilTypes.length; j++) {
                        var seuilValue = seuilsData[selectedPolluant][selectedSeuilTypes[j]]['valeur'];
                        if (mesure > seuilValue) {
                            depassementExiste = true;
                            break;
                        }
                    }
                    if (depassementExiste) break;
                }
            }

            // Mettre à jour le texte des dépassements
            var depassementsText = document.getElementById('depassements-text');
            if (depassementExiste) {
                depassementsText.innerHTML = '<div class="alert alert-danger" role="alert">Attention ! Certaines mesures dépassent les seuils sélectionnés.</div>';
            } else {
                depassementsText.innerHTML = '<div class="alert alert-success" role="alert">Bonne nouvelle ! Aucune mesure ne dépasse les seuils sélectionnés.</div>';
            }
        }

        // Fonction pour obtenir une couleur pour chaque type_norme
        function getColorForTypeNorme(typeNorme) {
            var colors = {
                'Objectif de qualité': 'rgba(75, 192, 192, 1)', // Teal
                'Valeur limite pour la protection de la santé humaine': 'rgba(255, 99, 132, 1)', // Red
                'Seuil d\'information et de recommandation': 'rgba(255, 206, 86, 1)', // Yellow
                'Seuil d\'alerte': 'rgba(153, 102, 255, 1)', // Purple
                // Ajoutez d'autres types de normes et leurs couleurs ici
            };
            return colors[typeNorme] || 'rgba(201, 203, 207, 1)'; // Gris par défaut
        }

        // Gestion des filtres
        document.addEventListener('DOMContentLoaded', function () {
            var polluantSelect = document.getElementById('polluant-select');
            var seuilTypeContainer = document.getElementById('seuil-type-container');
            var seuilTypesCheckboxes = document.getElementById('seuil-types-checkboxes');

            polluantSelect.addEventListener('change', function () {
                var selectedPolluant = this.value;

                if (selectedPolluant) {
                    // Afficher les types de seuils pour le polluant sélectionné
                    var types = seuilsData[selectedPolluant] ? Object.keys(seuilsData[selectedPolluant]) : [];
                    seuilTypesCheckboxes.innerHTML = '';

                    types.forEach(function (typeNorme) {
                        var checkboxId = 'seuil-' + selectedPolluant + '-' + typeNorme;
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'form-check-input seuil-type-checkbox';
                        checkbox.id = checkboxId;
                        checkbox.value = typeNorme;
                        checkbox.checked = true;

                        var label = document.createElement('label');
                        label.className = 'form-check-label mr-3';
                        label.htmlFor = checkboxId;

                        // Récupérer l'origine pour ce type_norme
                        var origine = seuilsData[selectedPolluant][typeNorme]['origine'];

                        label.textContent = typeNorme + ' (' + origine + ') (' + seuilsData[selectedPolluant][typeNorme]['valeur'] + ' ' + seuilsData[selectedPolluant][typeNorme]['unite'] + ')';

                        var div = document.createElement('div');
                        div.className = 'form-check form-check-inline';
                        div.appendChild(checkbox);
                        div.appendChild(label);

                        seuilTypesCheckboxes.appendChild(div);
                    });

                    seuilTypeContainer.style.display = 'block';

                    // Initialiser le graphique avec toutes les seuils sélectionnés
                    var selectedSeuilTypes = Array.from(document.querySelectorAll('.seuil-type-checkbox:checked')).map(function (cb) { return cb.value; });
                    initDepassementsChart(selectedPolluant, selectedSeuilTypes);

                    // Ajouter des écouteurs aux checkboxes
                    var seuilTypeCheckboxElements = document.querySelectorAll('.seuil-type-checkbox');
                    seuilTypeCheckboxElements.forEach(function (checkbox) {
                        checkbox.addEventListener('change', function () {
                            var updatedSeuilTypes = Array.from(document.querySelectorAll('.seuil-type-checkbox:checked')).map(function (cb) { return cb.value; });
                            initDepassementsChart(selectedPolluant, updatedSeuilTypes);
                        });
                    });

                } else {
                    // Aucun polluant sélectionné, masquer les types de seuils et détruire le graphique
                    seuilTypeContainer.style.display = 'none';
                    if (depassementsChart) {
                        depassementsChart.destroy();
                    }
                    document.getElementById('depassements-text').innerHTML = '';
                }
            });
        });

        <?php endif; ?>
    </script>
    <script>
        // Filtrer les colonnes du tableau par arrondissement
        document.getElementById('arrondissement-select')?.addEventListener('change', function () {
            var selectedArrondissement = this.value;
            var columns = document.querySelectorAll('#details-table th, #details-table td');

            columns.forEach(function (column) {
                var location = column.getAttribute('data-location');

                // Si c'est la colonne des polluants, on la montre toujours
                if (column.cellIndex === 0) {
                    column.style.display = '';  // Toujours afficher la première colonne (polluants)
                } else if (selectedArrondissement === 'all' || location === selectedArrondissement) {
                    column.style.display = '';  // Afficher les colonnes correspondant à l'arrondissement sélectionné
                } else {
                    column.style.display = 'none';  // Masquer les colonnes qui ne correspondent pas
                }
            });
        });

        // Attendre que le contenu soit chargé pour gérer le hash
        document.addEventListener("DOMContentLoaded", function () {
            var hash = window.location.hash;
            if (hash) {
                // Attendre un court instant pour s'assurer que le contenu est disponible
                setTimeout(function () {
                    var element = document.querySelector(hash);
                    if (element) {
                        element.scrollIntoView({behavior: 'smooth'});
                    }
                }, 500); // Ajustez le délai si nécessaire
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var favoriteForm = document.getElementById('favorite-form');
            if (favoriteForm) {
                favoriteForm.addEventListener('submit', function (e) {
                    e.preventDefault(); // Empêcher la soumission traditionnelle du formulaire

                    var formData = new FormData(favoriteForm);
                    formData.append('ajax', '1'); // Indiquer que c'est une requête AJAX

                    // Définir l'action basée sur le bouton cliqué
                    var action = favoriteForm.querySelector('.favorite-icon').getAttribute('data-action');
                    formData.set('favorite_action', action);

                    fetch('details.php?ville=<?php echo urlencode($ville); ?>', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            var icon = favoriteForm.querySelector('.favorite-icon i');
                            if (data.success) {
                                if (data.action === 'added') {
                                    icon.classList.remove('far');
                                    icon.classList.add('fas');
                                    favoriteForm.querySelector('.favorite-icon').setAttribute('data-action', 'remove_favorite');
                                } else if (data.action === 'removed') {
                                    icon.classList.remove('fas');
                                    icon.classList.add('far');
                                    favoriteForm.querySelector('.favorite-icon').setAttribute('data-action', 'add_favorite');
                                }
                                // Afficher un message de succès ou d'erreur
                                var messageContainer = document.getElementById('message-container');
                                messageContainer.innerHTML = '<div class="success-message">' + data.message + '</div>';
                                // Optionnellement, supprimer le message après quelques secondes
                                setTimeout(function () {
                                    messageContainer.innerHTML = '';
                                }, 3000);
                            } else {
                                // Afficher un message d'erreur
                                var messageContainer = document.getElementById('message-container');
                                messageContainer.innerHTML = '<div class="error-message">' + data.message + '</div>';
                                // Optionnellement, supprimer le message après quelques secondes
                                setTimeout(function () {
                                    messageContainer.innerHTML = '';
                                }, 5000);
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                        });
                });
            }
        });
    </script>

    <?php
    // Inclure le fichier de gestion des commentaires et le footer
    include 'commentaires.php';
    include '../includes/footer.php';
    ?>
</body>
</html>
