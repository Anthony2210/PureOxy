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

        // Récupérer les émissions historiques pour chaque année disponible
        $sql_emissions = "SELECT `2021`, `2020`, `2019`, `2018`, `2017`, `2016`, `2015`, `2014`, `2013`, `2012`, `2011`, `2010` FROM historical_emissions WHERE Country = 'France' AND `Sector` = 'Total excluding LULUCF' AND Gas = 'CO2'";
        $result_emissions = $conn->query($sql_emissions);
    
        // Préparer les données pour le graphique des émissions
        $years = [];
        $values = [];
    
        if ($row_emissions = $result_emissions->fetch_assoc()) {
            foreach (range(2021, 2010, -1) as $year) {
                if (!is_null($row_emissions[(string)$year])) {
                    $years[] = $year;
                    $values[] = $row_emissions[(string)$year];
                }
            }
        }
    
        // Préparer les données à afficher dans le tableau des polluants
        $pollutants_data = [];
        $dates = [];
        $arrondissements = [];
    
        while ($row = $result->fetch_assoc()) {
            $pollutant = $row['Pollutant'];
            $date = $row['LastUpdated'];
            $location = $row['Location'];
    
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
            if (in_array($ville, ['Paris', 'Lyon', 'Marseille']) && !in_array($location, $arrondissements)) {
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
            if (!isset($pollutants_data[$pollutant])) {
                $pollutants_data[$pollutant] = [];
            }
            $pollutants_data[$pollutant][$columnIdentifier] = $row['value'];
        }

        // Calculer la moyenne de la pollution pour chaque polluant dans la ville
        $city_pollution_averages = [];
        foreach ($pollutants_data as $pollutant => $data) {
            $total = array_sum($data);
            $count = count($data);
            $average = $count ? $total / $count : 0;
            $city_pollution_averages[$pollutant] = $average;
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
                $favorite_cities[] = $row['city_name'];
            }
    
            // Vérifier si la ville actuelle est dans les favoris
            $is_favorite = in_array($ville, $favorite_cities);
        } else {
            $is_favorite = false; // L'utilisateur n'est pas connecté, donc pas de favoris
        }

        // Ajouter ou retirer la ville des favoris
        if (isset($_POST['favorite_action']) && isset($_SESSION['user_id'])) {
            $city_name = $_POST['city_name'];
            $user_id = $_SESSION['user_id'];
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


        // Récupérer les seuils depuis la table seuils_normes
        $sql_seuils_normes = "SELECT * FROM seuils_normes";
        $result_seuils_normes = $conn->query($sql_seuils_normes);
    
        $seuils = [];
    
        // Parcourir les résultats et organiser les données
        while ($row = $result_seuils_normes->fetch_assoc()) {
            $polluant = $row['polluant'];
            $type_norme = $row['type_norme'];
            $valeur = $row['valeur'];
            $unite = $row['unite'];
            $periode = $row['periode'];
            $origine = $row['origine'];
            $details = $row['details'];
    
            // Initialiser le tableau pour le polluant s'il n'existe pas
            if (!isset($seuils[$polluant])) {
                $seuils[$polluant] = [];
            }
    
            // Ajouter le type de norme au tableau du polluant
            $seuils[$polluant][$type_norme] = [
                'valeur' => $valeur,
                'unite' => $unite,
                'periode' => $periode,
                'origine' => $origine,
                'details' => $details
            ];
        }

        // Correspondance des noms de polluants
        $polluant_aliases = [
            'NO2' => "Dioxyde d'azote (NO2)",
            'PM10' => "Particules (PM10)",
            'PM2.5' => "Particules (PM2.5)",
            'O3' => "Ozone (O3)",
            'SO2' => "Dioxyde de soufre (SO2)",
            'CO' => "Monoxyde de carbone (CO)",
            'C6H6' => "Benzène (C6H6)",
        ];}
    
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
        <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
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
    
    <div id="details-page" class="container">
        <?php if ($cityNotFound): ?>
            <section id="city-not-found" class="text-center">
                <h1>Oups ! Aucune donnée disponible pour la ville de "<?php echo htmlspecialchars($ville); ?>"</h1>
                <p>Il semble que nous n'ayons pas de données pour cette ville.</p>
                <p>Pour trouver une ville proche de la vôtre, vous pouvez :</p>
                <ul>
                    <li><button>Taper la région ou le début du code postal dans la <a href="../fonctionnalites/recherche.php">barre de recherche</a>.</button></li>
                    <li><button>Rechercher une ville proche géographiquement avec notre <a href="../fonctionnalites/carte.php">carte interactive</a>.</button></li>
                    <li><button>Nous envoyer une demande pour ajouter votre ville via notre <a href="../pages/contact.php">formulaire de contact</a>.</button></li>
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
                        <button type="submit" class="favorite-icon" data-action="<?php echo $is_favorite ? 'remove_favorite' : 'add_favorite'; ?>">
                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    </form>

                <?php endif; ?>
            </h1>
            <p>Département : <?php echo htmlspecialchars($departement), '  (', number_format($population, 0, ',', ' '), ' habitants)'; ?></p>
            <p>Région : <?php echo htmlspecialchars($region); ?></p>
        </section>
    
        <!-- Onglets -->
        <ul class="nav nav-tabs" id="detailsTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="polluants-tab" data-toggle="tab" href="#polluants" role="tab" aria-controls="polluants" aria-selected="true">Concentrations de polluants atmosphériques</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="depassements-tab" data-toggle="tab" href="#depassements" role="tab" aria-controls="depassements" aria-selected="false">Dépassements des seuils réglementaires</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="emissions-tab" data-toggle="tab" href="#emissions" role="tab" aria-controls="emissions" aria-selected="false">Émissions historiques de CO₂</a>
            </li>
        </ul>
    
        <div class="tab-content" id="detailsTabsContent">
            <!-- Onglet Polluants -->
            <div class="tab-pane fade show active" id="polluants" role="tabpanel" aria-labelledby="polluants-tab">
                <h2 class="mt-4">Concentrations de polluants atmosphériques</h2>
    
                <!-- Graphique des polluants -->
                <canvas id="pollutantsChart" class="my-4"></canvas>
    
                <p>Le tableau ci-dessous présente les concentrations des différents polluants atmosphériques mesurées dans la ville aux dates et emplacements spécifiés.</p>
    
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
                    <table id="details-table" class="table">
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
                        <?php foreach ($pollutants_data as $pollutant => $data): ?>
                            <tr>
                                <td data-label="Polluant"><?php echo htmlspecialchars($pollutant); ?></td>
                                <?php foreach ($dates as $entry): ?>
                                    <?php $identifier = $entry['identifier']; ?>
                                    <?php if (isset($data[$identifier])): ?>
                                        <td data-label="<?php echo htmlspecialchars($entry['date']); ?>" data-location="<?php echo htmlspecialchars($entry['location']); ?>"><?php echo htmlspecialchars($data[$identifier]); ?> µg/m³</td>
                                    <?php else: ?>
                                        <td data-label="<?php echo htmlspecialchars($entry['date']); ?>" data-location="<?php echo htmlspecialchars($entry['location']); ?>">/</td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
    
                <p><strong>Sources : </strong><a href="https://www.eea.europa.eu/fr" target="_blank">EEA France (Agence Européenne de l'Environnement).</a></p>
            </div>
    
            <!-- Onglet Dépassements -->
            <div class="tab-pane fade" id="depassements" role="tabpanel" aria-labelledby="depassements-tab">
                <h2 class="mt-4">Dépassements des seuils réglementaires</h2>
    
                <?php if (!empty($pollutants_data)): ?>
                    <!-- Graphique des dépassements -->
                    <canvas id="depassementsChart" class="my-4"></canvas>
    
                    <div class="table-responsive mt-4">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Polluant</th>
                                <th>Moyenne pour la ville</th>
                                <th>Type de norme</th>
                                <th>Valeur</th>
                                <th>Unité</th>
                                <th>Période</th>
                                <th>Origine</th>
                                <th>Nombre de dépassements</th>
                                <th>Détails</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pollutants_data as $pollutant_code => $data): ?>
                                <?php
                                // Obtenir le nom complet du polluant
                                $polluant_nom = isset($polluant_aliases[$pollutant_code]) ? $polluant_aliases[$pollutant_code] : $pollutant_code;
    
                                // Vérifier si le polluant a des seuils définis
                                if (isset($seuils[$polluant_nom])):
                                    // Récupérer la moyenne pour la ville
                                    $moyenne_ville = isset($city_pollution_averages[$pollutant_code]) ? round($city_pollution_averages[$pollutant_code], 2) : 'N/A';
    
                                    // Parcourir chaque type de norme pour le polluant
                                    foreach ($seuils[$polluant_nom] as $type_norme => $info_norme):
                                        $valeur_norme = $info_norme['valeur'];
                                        $periode_norme = $info_norme['periode'];
                                        $origine_norme = $info_norme['origine'];
    
    
                                        // Initialiser le compteur de dépassements
                                        $nb_depassements = 0;
    
                                        // Parcourir les valeurs mesurées pour le polluant
                                        foreach ($data as $value):
                                            if ($value > $valeur_norme) {
                                                $nb_depassements++;
                                            }
                                        endforeach;
                                        ?>
                                        <tr>
                                            <td data-label="Polluant"><?php echo htmlspecialchars($polluant_nom); ?></td>
                                            <td data-label="Moyenne ville"><?php echo htmlspecialchars($moyenne_ville . ' ' . $unite_norme); ?></td>
                                            <td data-label="Type de norme"><?php echo htmlspecialchars($type_norme); ?></td>
                                            <td data-label="Valeur"><?php echo htmlspecialchars($valeur_norme . ' ' . $unite_norme); ?></td>
                                            <td data-label="Période"><?php echo htmlspecialchars($periode_norme); ?></td>
                                            <td data-label="Origine"><?php echo htmlspecialchars($origine_norme); ?></td>
                                            <td data-label="Dépassements"><?php echo htmlspecialchars($nb_depassements); ?></td>
                                        </tr>
                                    <?php
                                    endforeach;
                                endif;
                                ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p><strong>Interprétation des résultats :</strong> Ce tableau présente la moyenne des concentrations pour chaque polluant mesuré dans la ville, les différents types de normes applicables, et le nombre de fois où ces normes ont été dépassées. Un nombre élevé de dépassements peut indiquer une pollution atmosphérique significative, ce qui peut avoir des impacts sur la santé humaine et l'environnement.</p>
                    <p><strong>Sources : </strong><a href="../bd/pdf/Tableau-Normes-Seuils réglementaires.pdf" target="_blank">Données officielles des organismes de surveillance de la qualité de l'air.</a></p>
                <?php else: ?>
                    <p>Aucune donnée de pollution disponible pour cette ville.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
    
            <!-- Onglet Émissions -->
            <div class="tab-pane fade" id="emissions" role="tabpanel" aria-labelledby="emissions-tab">
                <h2 class="mt-4">Émissions historiques de CO₂</h2>
                <canvas id="emissionsChart" width="400" height="200"></canvas>
            </div>
        </div>
    
        <!-- Section des effets -->
        <section id="effets" class="mt-5">
            <h2>Effets de la pollution atmosphérique</h2>
            <p>La pollution atmosphérique a des effets néfastes sur la santé humaine, notamment des problèmes respiratoires, cardiovasculaires et des allergies. Elle impacte également l'environnement en contribuant au changement climatique et en affectant les écosystèmes.</p>
        </section>
    

    </div>
    
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


    <script>
        // Données pour le graphique des émissions
        const years = <?php echo json_encode($years); ?>;
        const values = <?php echo json_encode($values); ?>;
    
        const ctxEmissions = document.getElementById('emissionsChart').getContext('2d');
        const emissionsChart = new Chart(ctxEmissions, {
            type: 'line',
            data: {
                labels: years,
                datasets: [{
                    label: 'Émissions de CO₂ (MtCO₂e)',
                    data: values,
                    backgroundColor: 'rgba(78, 105, 32, 0.5)',
                    borderColor: 'rgba(78, 105, 32, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Émissions (MtCO₂e)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Année'
                        }
                    }
                }
            }
        });
    
        // Données pour le graphique des polluants
        var pollutantsLabels = [];
        var pollutantsData = [];
    
        <?php
        // Préparer les données en PHP
        $pollutants_chart_data = [];
        foreach ($city_pollution_averages as $pollutant => $average) {
            $pollutants_chart_data[] = [
                'pollutant' => $pollutant,
                'average' => $average
            ];
        }
        ?>
    
        // Passer les données PHP à JavaScript
        var pollutantsChartData = <?php echo json_encode($pollutants_chart_data); ?>;
    
        // Préparer les labels et les données
        pollutantsChartData.forEach(function(item) {
            pollutantsLabels.push(item.pollutant);
            pollutantsData.push(item.average);
        });
    
        // Créer le graphique des polluants
        var ctxPolluants = document.getElementById('pollutantsChart').getContext('2d');
        var pollutantsChart = new Chart(ctxPolluants, {
            type: 'bar',
            data: {
                labels: pollutantsLabels,
                datasets: [{
                    label: 'Concentration Moyenne (µg/m³)',
                    data: pollutantsData,
                    backgroundColor: 'rgba(230, 126, 34, 0.7)',
                    borderColor: 'rgba(230, 126, 34, 1)',
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
    
        // Graphique des dépassements
        var depassementsLabels = [];
        var depassementsData = [];
    
        <?php
        // Préparer les données en PHP pour le graphique des dépassements
        $depassements_chart_data = [];
        foreach ($pollutants_data as $pollutant_code => $data) {
            $polluant_nom = isset($polluant_aliases[$pollutant_code]) ? $polluant_aliases[$pollutant_code] : $pollutant_code;
            if (isset($seuils[$polluant_nom])) {
                $total_depassements = 0;
                foreach ($seuils[$polluant_nom] as $type_norme => $info_norme) {
                    $valeur_norme = $info_norme['valeur'];
                    foreach ($data as $value) {
                        if ($value > $valeur_norme) {
                            $total_depassements++;
                        }
                    }
                }
                $depassements_chart_data[] = [
                    'pollutant' => $polluant_nom,
                    'depassements' => $total_depassements
                ];
            }
        }
        ?>
    
        // Passer les données PHP à JavaScript
        var depassementsChartData = <?php echo json_encode($depassements_chart_data); ?>;
    
        // Préparer les labels et les données
        depassementsChartData.forEach(function(item) {
            depassementsLabels.push(item.pollutant);
            depassementsData.push(item.depassements);
        });
    
        // Créer le graphique des dépassements
        var ctxDepassements = document.getElementById('depassementsChart').getContext('2d');
        var depassementsChart = new Chart(ctxDepassements, {
            type: 'pie',
            data: {
                labels: depassementsLabels,
                datasets: [{
                    label: 'Nombre de Dépassements',
                    data: depassementsData,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(155, 89, 182, 0.7)',
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(241, 196, 15, 0.7)',
                        'rgba(231, 76, 60, 0.7)',
                        'rgba(52, 73, 94, 0.7)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
            }
        });
    
        // Filtrer les colonnes du tableau par arrondissement
        document.getElementById('arrondissement-select')?.addEventListener('change', function() {
            var selectedArrondissement = this.value;
            var columns = document.querySelectorAll('#details-table th, #details-table td');
    
            columns.forEach(function(column) {
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
    </script>
    <script>
        // Attendre que le contenu soit chargé
        document.addEventListener("DOMContentLoaded", function() {
            var hash = window.location.hash;
            if (hash) {
                // Attendre un court instant pour s'assurer que le contenu est disponible
                setTimeout(function() {
                    var element = document.querySelector(hash);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth' });
                    }
                }, 500); // Ajustez le délai si nécessaire
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
