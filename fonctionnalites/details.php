<?php
include '../includes/header.php';
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

        // Si aucune recherche récente similaire n'est trouvée, on l'enregistre
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

    // Vérifier si des données ont été trouvées
    if ($result->num_rows === 0) {
        echo "<h1>Aucune donnée disponible pour la ville de " . htmlspecialchars($ville) . ".</h1>";
        exit;
    }

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
    $population = $result_population->fetch_assoc()['Population'];

    // Récupérer les dépassements des seuils réglementaires
    $sql_seuils = "SELECT POLLUANT, NB_ANNEE_DEP FROM depassements_seuils_reglementaires2023 WHERE NOM_AGGLOMERATION = ?";
    $stmt_seuils = $conn->prepare($sql_seuils);
    $stmt_seuils->bind_param("s", $ville);
    $stmt_seuils->execute();
    $result_seuils = $stmt_seuils->get_result();

    // Récupérer les émissions historiques pour chaque année disponible
    $sql_emissions = "SELECT `2021`, `2020`, `2019`, `2018`, `2017`, `2016`, `2015`, `2014`, `2013`, `2012`, `2011`, `2010` FROM historical_emissions WHERE Country = 'France' AND `Sector` = 'Total excluding LULUCF' AND Gas = 'CO2'";
    $result_emissions = $conn->query($sql_emissions);


    // Préparer les données pour le graphique des émissions
    $years = [];
    $values = [];

    if ($row_emissions = $result_emissions->fetch_assoc()) {
        // Boucler sur chaque colonne d'année
        foreach (range(2021, 2010, -1) as $year) {
            // Vérifier si la valeur n'est pas nulle
            if (!is_null($row_emissions[(string)$year])) {
                $years[] = $year;
                $values[] = $row_emissions[(string)$year];
            }
        }
    }


    // Préparer les données à afficher dans le tableau des polluants
    // Votre code existant pour préparer les données ($pollutants_data, $dates, $arrondissements)
    $pollutants_data = [];
    $dates = [];
    $arrondissements = [];

    while ($row = $result->fetch_assoc()) {
        $pollutant = $row['Pollutant'];
        $date = $row['LastUpdated'];
        $location = $row['Location'];

        // Formater la date en "Mois AAAA"
        $formattedDate = date('F Y', strtotime($date));

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

    // Vérifier si la région est l'Île-de-France
    $isIDF = ($region === 'Île-de-France');

    if ($isIDF) {
        $sql_iqa = "SELECT date, no2, o3, pm10, ninsee, id_qualité FROM indices_qa_commune_idf_filtree_id WHERE ninsee = ? ORDER BY date DESC LIMIT 1";
        $stmt_iqa = $conn->prepare($sql_iqa);
        $stmt_iqa->bind_param("s", $ville);
        $stmt_iqa->execute();
        $result_iqa = $stmt_iqa->get_result();
        $iqa_data = $result_iqa->fetch_assoc();
    }


    // Calculer la moyenne de la pollution pour chaque polluant dans la ville
    $city_pollution_averages = [];

    foreach ($pollutants_data as $pollutant => $data) {
        $total = 0;
        $count = 0;
        foreach ($data as $value) {
            $total += $value;
            $count++;
        }
        $average = $count ? $total / $count : 0;
        $city_pollution_averages[$pollutant] = $average;
    }

    // Requête SQL pour obtenir les données de pollution de toutes les villes
    $sql_all = "SELECT Pollutant, value FROM pollution_villes";
    $result_all = $conn->query($sql_all);

    $national_pollution_totals = [];
    $national_pollution_counts = [];

    // Parcourir les données pour calculer les totaux et les comptes
    while ($row_all = $result_all->fetch_assoc()) {
        $pollutant = $row_all['Pollutant'];
        $value = $row_all['value'];

        if (!isset($national_pollution_totals[$pollutant])) {
            $national_pollution_totals[$pollutant] = 0;
            $national_pollution_counts[$pollutant] = 0;
        }
        $national_pollution_totals[$pollutant] += $value;
        $national_pollution_counts[$pollutant]++;
    }

    // Calculer la moyenne nationale pour chaque polluant
    $national_pollution_averages = [];
    foreach ($national_pollution_totals as $pollutant => $total) {
        $count = $national_pollution_counts[$pollutant];
        $average = $count ? $total / $count : 0;
        $national_pollution_averages[$pollutant] = $average;
    }

    // Moyennes régionales
    $sql_reg_avg = "SELECT Pollutant, AVG(value) as avg_value FROM pollution_villes WHERE Region = ? GROUP BY Pollutant";
    $stmt_reg_avg = $conn->prepare($sql_reg_avg);
    $stmt_reg_avg->bind_param("s", $region);
    $stmt_reg_avg->execute();
    $result_reg_avg = $stmt_reg_avg->get_result();

    $region_pollution_averages = [];
    while ($row_reg_avg = $result_reg_avg->fetch_assoc()) {
        $region_pollution_averages[$row_reg_avg['Pollutant']] = $row_reg_avg['avg_value'];
    }

    // Moyennes départementales
    $sql_dep_avg = "SELECT Pollutant, AVG(value) as avg_value FROM pollution_villes WHERE Department = ? GROUP BY Pollutant";
    $stmt_dep_avg = $conn->prepare($sql_dep_avg);
    $stmt_dep_avg->bind_param("s", $departement);
    $stmt_dep_avg->execute();
    $result_dep_avg = $stmt_dep_avg->get_result();

    $department_pollution_averages = [];
    while ($row_dep_avg = $result_dep_avg->fetch_assoc()) {
        $department_pollution_averages[$row_dep_avg['Pollutant']] = $row_dep_avg['avg_value'];
    }


    // Préparer les données pour l'affichage
    $comparisons_extended = [];

    foreach ($city_pollution_averages as $pollutant => $city_avg) {
        $dep_avg = isset($department_pollution_averages[$pollutant]) ? $department_pollution_averages[$pollutant] : 0;
        $reg_avg = isset($region_pollution_averages[$pollutant]) ? $region_pollution_averages[$pollutant] : 0;
        $national_avg = isset($national_pollution_averages[$pollutant]) ? $national_pollution_averages[$pollutant] : 0;

        $comparisons_extended[] = [
            'pollutant' => $pollutant,
            'city_avg' => round($city_avg, 2),
            'dep_avg' => round($dep_avg, 2),
            'reg_avg' => round($reg_avg, 2),
            'national_avg' => round($national_avg, 2)
        ];
    }


} else {
    echo "<h1>Erreur : Ville non spécifiée</h1>";
    exit; // Arrêter l'exécution si la ville n'est pas spécifiée
}
// Affichage des données pour le débogage
echo '<script>';
echo 'console.log(' . json_encode($years) . ');';
echo 'console.log(' . json_encode($values) . ');';
echo '</script>';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Données détaillées de <?php echo htmlspecialchars($ville); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles/details.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>

<div id="details-page" class="container">
    <!-- Introduction -->
    <section id="intro">
        <h1 class="text-center mb-4"><?php echo htmlspecialchars($ville); ?></h1>
        <p>Département : <?php echo htmlspecialchars($departement), '  (', number_format($population, 0, ',', ' '), ' habitants)'; ?></p>
        <p>Région : <?php echo htmlspecialchars($region); ?></p>
    </section>

    <h2>Concentrations de polluants atmosphériques</h2>
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


    <section id="polluants">
        <div class="table-responsive">
            <table id="details-table" class="table table-bordered table-hover">
                <thead class="thead-dark">
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
                        <td><?php echo htmlspecialchars($pollutant); ?></td>
                        <?php foreach ($dates as $entry): ?>
                            <?php $identifier = $entry['identifier']; ?>
                            <?php if (isset($data[$identifier])): ?>
                                <td data-location="<?php echo htmlspecialchars($entry['location']); ?>"><?php echo htmlspecialchars($data[$identifier]); ?> µg/m³</td>
                            <?php else: ?>
                                <td data-location="<?php echo htmlspecialchars($entry['location']); ?>">/</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="depassements">
        <h2>Dépassements des seuils réglementaires</h2>
        <?php if (isset($result_seuils) && $result_seuils->num_rows > 0): ?>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Moyenne pour la ville</th>
                    <th>Polluant</th>
                    <th>Nombre de dépassements (Recommandation)</th>
                    <th>Seuil de recommandation</th>
                    <th>Nombre de dépassements (Alerte)</th>
                    <th>Seuil d'alerte</th>
                    <th>Valeur limite pour la santé humaine</th>
                    <th>Description</th>
                </tr>
                </thead>
                <tbody>
                <?php
                // Définition des normes pour chaque polluant
                $seuils = [
                    'NO2' => [
                        'nom' => 'Dioxyde d’Azote (NO2)',
                        'valeur_recommandation' => 200,
                        'valeur_alerte' => 400,
                        'valeur_limite' => 200,
                        'unite' => 'µg/m³',
                        'description' => 'Moyenne horaire, 18h/an pour la protection de la santé humaine'
                    ],
                    'PM10' => [
                        'nom' => 'Particules PM10',
                        'valeur_recommandation' => 50,
                        'valeur_alerte' => 80,
                        'valeur_limite' => 50,
                        'unite' => 'µg/m³',
                        'description' => 'Moyenne journalière, 35 jours/an pour la protection de la santé humaine'
                    ],
                    'PM2.5' => [
                        'nom' => 'Particules PM2.5',
                        'valeur_recommandation' => 20,
                        'valeur_alerte' => 25,
                        'valeur_limite' => 25,
                        'unite' => 'µg/m³',
                        'description' => 'Moyenne annuelle pour la protection de la santé humaine'
                    ],
                    'O3' => [
                        'nom' => 'Ozone (O3)',
                        'valeur_recommandation' => 180,
                        'valeur_alerte' => 240,
                        'valeur_limite' => 120,
                        'unite' => 'µg/m³',
                        'description' => 'Maximum journalier de la moyenne sur 8h'
                    ],
                ];

                while ($row_seuils = $result_seuils->fetch_assoc()):
                    $polluant = $row_seuils['POLLUANT'];
                    $nb_dep_recommandation = $row_seuils['NB_ANNEE_DEP'] ?? 0;
                    $nb_dep_alerte = $nb_dep_recommandation > 0 ? '' : 0;

                    // Récupérer la moyenne pour la ville avec 2 décimales
                    $moyenne_ville = isset($city_pollution_averages[$polluant]) ? round($city_pollution_averages[$polluant], 2) . ' µg/m³' : 'N/A';

                    // Récupérer les informations de seuils
                    $info_polluant = $seuils[$polluant] ?? null;
                    $nom_polluant = $info_polluant['nom'] ?? htmlspecialchars($polluant);
                    $valeur_recommandation = $info_polluant['valeur_recommandation'] ?? 'N/A';
                    $valeur_alerte = $info_polluant['valeur_alerte'] ?? 'N/A';
                    $valeur_limite = $info_polluant['valeur_limite'] ?? 'N/A';
                    $description = $info_polluant['description'] ?? 'Informations non disponibles';

                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($moyenne_ville); ?></td>
                        <td><?php echo htmlspecialchars($nom_polluant); ?></td>
                        <td><?php echo htmlspecialchars($nb_dep_recommandation); ?></td>
                        <td><?php echo htmlspecialchars($valeur_recommandation) . ' µg/m³'; ?></td>
                        <td><?php echo htmlspecialchars($nb_dep_alerte); ?></td>
                        <td><?php echo htmlspecialchars($valeur_alerte) . ' µg/m³'; ?></td>
                        <td><?php echo htmlspecialchars($valeur_limite) . ' µg/m³'; ?></td>
                        <td><?php echo htmlspecialchars($description); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <p><strong>Sources :</strong> UE/FR</p> <!-- Auteur déplacé en tant que source sous le tableau -->
        <?php else: ?>
            <p>Aucun dépassement des seuils réglementaires enregistré pour les données disponibles.</p>
        <?php endif; ?>
    </section>


    <section id="graphiques">
        <h2>Émissions historiques de CO₂</h2>
        <canvas id="emissionsChart" width="400" height="200"></canvas>
    </section>

    <section id="effets">
        <h2>Effets de la pollution atmosphérique</h2>
        <p>La pollution atmosphérique a des effets néfastes sur la santé humaine, notamment des problèmes respiratoires, cardiovasculaires et des allergies. Elle impacte également l'environnement en contribuant au changement climatique et en affectant les écosystèmes.</p>
    </section>

</div>
<section id="cta">
    <h2>Nos articles</h2>
    <a href="../pages/qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
    <a href="../pages/lutte-pollution.php" class="button">Lutte contre la pollution de l'air</a>
</section>
    <!-- Vos scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>

        // Données pour le graphique
        console.log(<?php echo json_encode($years); ?>);
        console.log(<?php echo json_encode($values); ?>);

        const ctx = document.getElementById('emissionsChart').getContext('2d');
        const emissionsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($years); ?>,  // Années des émissions
                datasets: [{
                    label: 'Émissions de CO₂ (MtCO₂e)',
                    data: <?php echo json_encode($values); ?>,  // Valeurs des émissions
                    backgroundColor: 'rgba(78, 105, 32, 0.5)',
                    borderColor: 'rgba(78, 105, 32, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4  // Lissage des courbes
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#333'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return tooltipItem.raw + ' MtCO₂e';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Émissions (MtCO₂e)',
                            color: '#333',
                            font: { size: 14 }
                        },
                        grid: {
                            color: '#ccc'
                        },
                        ticks: {
                            color: '#333'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Année',
                            color: '#333',
                            font: { size: 14 }
                        },
                        ticks: {
                            color: '#333'
                        }
                    }
                }
            }
        });

        // Filtrer les colonnes du tableau par arrondissement
        document.getElementById('arrondissement-select').addEventListener('change', function() {
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
<?php include '../includes/footer.php'; ?>
</body>
</html>

