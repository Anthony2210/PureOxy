<?php
session_start();
include '../bd/bd.php';

/**
 * 1) Récupération du classement global, ordonné par polluant, puis par avg_value (desc).
 *    On joint donnees_villes pour récupérer le nom de la ville.
 */
$sql = "
    SELECT m.id_ville,
           m.pollutant,
           m.avg_value,
           v.ville AS city
    FROM moy_pollution_villes AS m
    JOIN donnees_villes AS v ON m.id_ville = v.id_ville
    ORDER BY m.pollutant, m.avg_value DESC
";
$result = $conn->query($sql);

/**
 * 2) Stockage des données dans $rankingData sous la forme :
 *    $rankingData['NO2'] = [
 *       [ 'city' => 'Paris', 'avg_val' => 50.2 ],
 *       [ 'city' => 'Lyon',  'avg_val' => 47.1 ],
 *       ...
 *    ];
 *
 * On ignore le polluant "C6H6" (benzène) en le filtrant dans la boucle.
 */
$rankingData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pollutant = $row['pollutant'];

        // Ignorer C6H6
        if ($pollutant === 'C6H6') {
            continue;
        }

        $city   = $row['city'];
        $avgVal = (float) $row['avg_value'];

        if (!isset($rankingData[$pollutant])) {
            $rankingData[$pollutant] = [];
        }
        $rankingData[$pollutant][] = [
            'city'    => $city,
            'avg_val' => $avgVal
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Données Nationales</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour le classement -->
    <link rel="stylesheet" href="../styles/classement.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<section id="classement">
    <h2>Classement des villes les plus polluées</h2>
    <p>Sélectionnez un polluant pour afficher la liste (25 villes à la fois) :</p>

    <!-- Menu déroulant pour choisir le polluant -->
    <select id="pollutant-select">
        <?php
        // Liste des polluants présents dans $rankingData
        $pollutantsList = array_keys($rankingData);
        foreach ($pollutantsList as $poll) {
            echo '<option value="'.htmlspecialchars($poll).'">'.htmlspecialchars($poll).'</option>';
        }
        ?>
    </select>

    <!-- Paragraphe qui affiche l'explication du polluant sélectionné -->
    <p id="pollutant-info" style="font-style: italic; color: #666; margin-top: 5px;">
        <!-- Le texte sera injecté par JS -->
    </p>

    <!-- Conteneur où s'affiche le classement -->
    <div id="rankingContainer"></div>

    <!-- Bouton "Voir plus" -->
    <button id="loadMoreButton" class="btn-voir-plus" style="display: none;">Voir plus</button>

    <!-- Passage des données PHP -> JS -->
    <script>
        // Données du classement
        var rankingData = <?php echo json_encode($rankingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

        // Petites explications sur chaque polluant
        var pollutantExplanations = {
            'NO2':  "Le NO2 (Dioxyde d'azote) est un gaz irritant émis notamment par le trafic routier.",
            'PM10': "Particules de diamètre < 10 micromètres. Principales sources : transport, industries.",
            'PM2.5':"Particules fines < 2,5 micromètres, encore plus dangereuses pour la santé.",
            'SO2':  "Le dioxyde de soufre (SO2) provient surtout de la combustion de charbon et de pétrole.",
            'O3':   "L'ozone troposphérique (O3) se forme par réaction photochimique, surtout en été."
            // Ajoutez d'autres polluants si besoin
        };
    </script>
</section>

<!-- Script JS inline pour gérer l'affichage -->
<script>
    (function() {
        // Variables globales pour la pagination
        let currentPollutant = null;
        let currentOffset = 0;
        const pageSize = 25;

        // Références DOM
        const pollutantSelect = document.getElementById('pollutant-select');
        const rankingContainer = document.getElementById('rankingContainer');
        const loadMoreButton   = document.getElementById('loadMoreButton');
        const pollutantInfo    = document.getElementById('pollutant-info');

        // Fonction pour afficher l'explication du polluant
        function updatePollutantExplanation(poll) {
            if (!pollutantExplanations[poll]) {
                pollutantInfo.textContent = "Aucune explication disponible pour ce polluant.";
            } else {
                pollutantInfo.textContent = pollutantExplanations[poll];
            }
        }

        // Fonction pour afficher le classement
        function renderRanking() {
            if (!currentPollutant || !rankingData[currentPollutant]) {
                rankingContainer.innerHTML = '<p>Aucune donnée pour ce polluant.</p>';
                loadMoreButton.style.display = 'none';
                return;
            }

            // On récupère toutes les lignes de la ville
            const allRows = rankingData[currentPollutant];
            // On slice pour n'en prendre que "currentOffset + pageSize"
            const rowsToDisplay = allRows.slice(0, currentOffset + pageSize);

            // Génération HTML
            let html = '<table class="table-classement">';
            html += '<thead><tr><th>Rang</th><th>Ville</th><th>Moyenne (µg/m³)</th></tr></thead>';
            html += '<tbody>';

            rowsToDisplay.forEach((item, index) => {
                // Rang = index + 1 (dans la portion visible)
                const rangAbsolu = index + 1;
                // Ville cliquable => lien vers details.php?ville=...
                const cityLink = `<a href="../fonctionnalites/details.php?ville=${encodeURIComponent(item.city)}">${item.city}</a>`;

                html += `
                    <tr>
                        <td>${rangAbsolu}</td>
                        <td>${cityLink}</td>
                        <td>${item.avg_val.toFixed(2)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            rankingContainer.innerHTML = html;

            // Gérer la visibilité du bouton "Voir plus"
            if (rowsToDisplay.length < allRows.length) {
                // Il reste encore des villes à afficher
                loadMoreButton.style.display = 'inline-block';
            } else {
                // Tout est affiché
                loadMoreButton.style.display = 'none';
            }
        }

        // Changement de polluant
        pollutantSelect.addEventListener('change', function() {
            currentPollutant = this.value;
            currentOffset = 0;  // On repart de zéro
            updatePollutantExplanation(currentPollutant);
            renderRanking();
        });

        // Clic sur "Voir plus" => on incrémente l'offset
        loadMoreButton.addEventListener('click', function() {
            currentOffset += pageSize;
            renderRanking();
        });

        // Initialisation : on prend le premier polluant par défaut
        const allPolls = Object.keys(rankingData);
        if (pollutantSelect.value) {
            currentPollutant = pollutantSelect.value;
        } else if (allPolls.length > 0) {
            currentPollutant = allPolls[0];
        }

        // Met à jour le sous-texte du polluant et affiche le classement
        if (currentPollutant) {
            updatePollutantExplanation(currentPollutant);
        }
        renderRanking();
    })();
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
