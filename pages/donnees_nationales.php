<?php
session_start();
include '../bd/bd.php';

/**
 * Étape 1 : Récupération du classement global
 * ------------------------------------------
 * - Jointure entre moy_pollution_villes (m) et donnees_villes (v)
 * - On récupère la valeur moyenne (avg_value), la ville, et les deux ratios (avg_par_habitant, avg_par_km2)
 * - On classe par polluant, puis par la valeur moyenne décroissante
 */
$sql = "
    SELECT
        m.id_ville,
        m.pollutant,
        m.avg_value,
        m.avg_par_habitant,
        m.avg_par_km2,
        v.ville AS city
    FROM moy_pollution_villes AS m
    JOIN donnees_villes AS v
        ON m.id_ville = v.id_ville
    ORDER BY m.pollutant, m.avg_value DESC
";
$result = $conn->query($sql);

/**
 * Étape 2 : Stockage des données dans $rankingData
 * -----------------------------------------------
 * - On ignore éventuellement le polluant 'C6H6' si on ne veut pas l'afficher
 * - Structure finale : $rankingData['NO2'] = [
 *     [ 'city' => 'Paris', 'avg_val' => 12.34, 'avg_hab' => 0.01, 'avg_km2' => 0.45 ],
 *     ...
 *   ];
 */
$rankingData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pollutant = $row['pollutant'];

        // Option : ignorer un polluant particulier, ex. 'C6H6'
        if ($pollutant === 'C6H6') {
            continue;
        }

        // Conversion en float
        $avgVal = (float) $row['avg_value'];
        $avgHab = (float) $row['avg_par_habitant'];
        $avgKm2 = (float) $row['avg_par_km2'];

        // Création du tableau si inexistant
        if (!isset($rankingData[$pollutant])) {
            $rankingData[$pollutant] = [];
        }

        // Ajout d'un enregistrement
        $rankingData[$pollutant][] = [
            'city'    => $row['city'],
            'avg_val' => $avgVal,
            'avg_hab' => $avgHab,
            'avg_km2' => $avgKm2
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

    <!-- Feuilles de style -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/classement.css">
    <link rel="stylesheet" href="../styles/boutons.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<section id="classement">
    <h2>Classement des villes les plus polluées</h2>
    <p>
        Sélectionnez un polluant pour afficher la liste (25 villes à la fois).
        Vous pouvez également trier le tableau en cliquant sur les en-têtes de colonnes,
        et choisir quelles colonnes afficher via les cases à cocher.
    </p>

    <!-- Menu déroulant pour choisir le polluant -->
    <label for="pollutant-select" style="font-weight: bold;">Polluant :</label>
    <select id="pollutant-select" style="margin-left: 5px;"></select>

    <!-- Paragraphe d'explication du polluant -->
    <p id="pollutant-info" style="font-style: italic; color: #666; margin-top: 5px;">
        <!-- Le texte sera mis à jour en JavaScript -->
    </p>

    <!-- Cases à cocher pour afficher/masquer les colonnes -->
    <fieldset id="columns-choices" style="margin: 1em 0;">
        <legend style="font-weight: bold;">Colonnes à afficher :</legend>
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

    <!-- Conteneur du tableau de classement -->
    <div id="rankingContainer"></div>

    <!-- Bouton "Voir plus" pour la pagination -->
    <button id="loadMoreButton" class="btn-voir-plus" style="display: none;">
        Voir plus
    </button>
</section>

<script>
    (function() {
        /**************************************************************
         * 1) Données du classement (récupérées en PHP, passées en JSON)
         **************************************************************/
        var rankingData = <?php echo json_encode($rankingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

        /**************************************************************
         * 2) Explications textuelles pour certains polluants
         **************************************************************/
        var pollutantExplanations = {
            'NO2':  "Le NO2 (Dioxyde d'azote) est un gaz irritant émis notamment par le trafic routier.",
            'PM10': "Particules de diamètre < 10 µm. Principales sources : transport, industries, etc.",
            'PM2.5':"Particules fines < 2,5 µm, encore plus dangereuses pour la santé.",
            'SO2':  "Le dioxyde de soufre (SO2) provient surtout de la combustion de charbon et de pétrole.",
            'O3':   "L'ozone troposphérique (O3) se forme par réaction photochimique, surtout en été."
        };

        /**************************************************************
         * 3) Variables globales et références DOM
         **************************************************************/
        let currentPollutant = null;    // polluant en cours d'affichage
        let currentOffset = 0;          // pagination : combien de villes déjà affichées
        const pageSize = 25;            // nombre de villes à afficher par "page"

        let sortColumn = null;          // colonne utilisée pour trier ("city", "avg_val", "avg_hab", "avg_km2")
        let sortAsc = true;             // sens du tri : croissant (true) ou décroissant (false)

        // Récupération des éléments du DOM
        const pollutantSelect = document.getElementById('pollutant-select');
        const rankingContainer = document.getElementById('rankingContainer');
        const loadMoreButton   = document.getElementById('loadMoreButton');
        const pollutantInfo    = document.getElementById('pollutant-info');

        // Cases à cocher
        const chkAvgVal = document.getElementById('chkAvgVal');
        const chkAvgHab = document.getElementById('chkAvgHab');
        const chkAvgKm2 = document.getElementById('chkAvgKm2');

        /**************************************************************
         * 4) Initialisation du menu déroulant des polluants
         **************************************************************/
        const allPolls = Object.keys(rankingData);
        if (allPolls.length === 0) {
            pollutantSelect.innerHTML = '<option>Aucun polluant</option>';
        } else {
            let optionsHtml = '';
            allPolls.forEach(p => {
                optionsHtml += `<option value="${p}">${p}</option>`;
            });
            pollutantSelect.innerHTML = optionsHtml;
            currentPollutant = allPolls[0]; // on prend le premier polluant par défaut
        }

        /**************************************************************
         * 5) Fonction pour afficher une explication sur le polluant
         **************************************************************/
        function updatePollutantExplanation(poll) {
            if (!poll) {
                pollutantInfo.textContent = '';
                return;
            }
            if (!pollutantExplanations[poll]) {
                pollutantInfo.textContent = "Aucune explication disponible pour ce polluant.";
            } else {
                pollutantInfo.textContent = pollutantExplanations[poll];
            }
        }

        /**************************************************************
         * 6) Fonction de tri des données
         **************************************************************/
        function sortRows(rows) {
            if (!sortColumn) {
                return rows; // pas de tri si sortColumn est null
            }

            // On copie le tableau pour ne pas modifier l'original
            const sorted = rows.slice();

            // Tri personnalisé
            sorted.sort((a, b) => {
                let va, vb;

                if (sortColumn === 'city') {
                    va = a.city.toLowerCase();
                    vb = b.city.toLowerCase();
                } else {
                    // "avg_val", "avg_hab", "avg_km2"
                    va = a[sortColumn];
                    vb = b[sortColumn];
                }

                if (va < vb) return sortAsc ? -1 : 1;
                if (va > vb) return sortAsc ? 1 : -1;
                return 0;
            });

            return sorted;
        }

        /**************************************************************
         * 7) Rendu du tableau (avec pagination et colonnes dynamiques)
         **************************************************************/
        function renderRanking() {
            // Vérifier si on a des données pour le polluant sélectionné
            if (!currentPollutant || !rankingData[currentPollutant]) {
                rankingContainer.innerHTML = '<p>Aucune donnée pour ce polluant.</p>';
                loadMoreButton.style.display = 'none';
                return;
            }

            // Récupérer les enregistrements et appliquer le tri
            let allRows = rankingData[currentPollutant];
            allRows = sortRows(allRows);

            // Gestion de la pagination
            const rowsToDisplay = allRows.slice(0, currentOffset + pageSize);

            // Colonnes à afficher
            const showAvgVal = chkAvgVal.checked;
            const showAvgHab = chkAvgHab.checked;
            const showAvgKm2 = chkAvgKm2.checked;

            // Construction du tableau HTML
            let html = '<table class="table-classement">';
            html += '<thead><tr>'
                + '<th onclick="sortBy(\'city\')">Rang / Ville</th>';

            // On propose le tri en cliquant sur les en-têtes
            if (showAvgVal) {
                html += '<th onclick="sortBy(\'avg_val\')">Moy. (µg/m³)</th>';
            }
            if (showAvgHab) {
                html += '<th onclick="sortBy(\'avg_hab\')">Moy. par hab</th>';
            }
            if (showAvgKm2) {
                html += '<th onclick="sortBy(\'avg_km2\')">Moy. par km²</th>';
            }
            html += '</tr></thead><tbody>';

            // Remplir le tableau
            rowsToDisplay.forEach((item, index) => {
                const rangAbsolu = index + 1;
                const cityLink = `<a href="../fonctionnalites/details.php?ville=${encodeURIComponent(item.city)}">${item.city}</a>`;

                html += `<tr>`;
                // On fusionne Rang et Ville dans une seule colonne pour simplifier
                html += `<td>${rangAbsolu}. ${cityLink}</td>`;

                if (showAvgVal) {
                    html += `<td>${item.avg_val.toFixed(2)}</td>`;
                }
                if (showAvgHab) {
                    html += `<td>${item.avg_hab.toFixed(4)}</td>`;
                }
                if (showAvgKm2) {
                    html += `<td>${item.avg_km2.toFixed(4)}</td>`;
                }

                html += `</tr>`;
            });

            html += '</tbody></table>';
            rankingContainer.innerHTML = html;

            // Gestion du bouton "Voir plus"
            if (rowsToDisplay.length < allRows.length) {
                loadMoreButton.style.display = 'inline-block';
            } else {
                loadMoreButton.style.display = 'none';
            }
        }

        /**************************************************************
         * 8) Fonction de tri (cliquable sur en-tête)
         **************************************************************/
        // On rattache cette fonction à window pour y accéder via onclick="sortBy('avg_val')"
        window.sortBy = function(colKey) {
            // Si on reclique sur la même colonne, on inverse le sens
            if (sortColumn === colKey) {
                sortAsc = !sortAsc;
            } else {
                // On change de colonne, on se met en ordre croissant
                sortColumn = colKey;
                sortAsc = true;
            }
            // On reconstruit le tableau
            renderRanking();
        };

        /**************************************************************
         * 9) Écouteurs d'événements
         **************************************************************/
        // Changement de polluant
        pollutantSelect.addEventListener('change', function() {
            currentPollutant = this.value;
            currentOffset = 0; // on réinitialise la pagination
            updatePollutantExplanation(currentPollutant);
            renderRanking();
        });

        // Bouton "Voir plus" => on augmente l'offset
        loadMoreButton.addEventListener('click', function() {
            currentOffset += pageSize;
            renderRanking();
        });

        // Cases à cocher => on refait le rendu (colonnes affichées)
        chkAvgVal.addEventListener('change', renderRanking);
        chkAvgHab.addEventListener('change', renderRanking);
        chkAvgKm2.addEventListener('change', renderRanking);

        /**************************************************************
         * 10) Initialisation au chargement
         **************************************************************/
        if (currentPollutant) {
            updatePollutantExplanation(currentPollutant);
        }
        renderRanking();
    })();
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
