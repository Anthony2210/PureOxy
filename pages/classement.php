<?php
session_start();
include '../bd/bd.php';

/************************************************
 * 1) Récupération des données de seuils
 ************************************************/
$thresholdsMulti = [];
$sqlT = "
    SELECT polluant, polluant_complet, valeur, unite, details, origine
    FROM seuils_normes
    WHERE periode = 'moyenne annuelle'
";
$resT = $conn->query($sqlT);
if ($resT && $resT->num_rows > 0) {
    while ($rowT = $resT->fetch_assoc()) {
        $poll = $rowT['polluant'];
        if (!isset($thresholdsMulti[$poll])) {
            $thresholdsMulti[$poll] = [];
        }
        $thresholdsMulti[$poll][] = [
            'value'    => (float) $rowT['valeur'],
            'unite'    => $rowT['unite'],
            'details'  => $rowT['details'],
            'origine'  => $rowT['origine'],
            'complet'  => $rowT['polluant_complet']
        ];
    }
}

// Agrège pour n’avoir qu’un seul objet par polluant
$thresholds = [];
foreach ($thresholdsMulti as $poll => $rows) {
    if (empty($rows)) {
        continue;
    }
    // Tri par valeur DESC
    usort($rows, function($a, $b) {
        return $b['value'] <=> $a['value'];
    });

    $maxVal = $rows[0]['value'];
    $maxUnite = $rows[0]['unite'];
    $maxDetails = $rows[0]['details'];
    $maxOrigins = [$rows[0]['origine']];

    $others = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if ($row['value'] == $maxVal) {
            $maxOrigins[] = $row['origine'];
        } else {
            $others[] = $row;
        }
    }

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
        'value'   => $maxVal,
        'unite'   => $maxUnite,
        'details' => $maxDetails,
        'origines'=> implode(", ", $maxOrigins),
        'hover'   => $hoverText
    ];
}

/************************************************
 * 2) Récupération du classement global
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
    JOIN donnees_villes AS v
        ON m.id_ville = v.id_ville
    ORDER BY m.polluant, m.avg_value DESC
";
$result = $conn->query($sql);

/************************************************
 * 3) Stockage des données dans $rankingData
 ************************************************/
$rankingData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $polluant = $row['polluant'];
        if ($polluant === 'C6H6') {
            continue;
        }
        $avgVal = (float) $row['avg_value'];
        $avgHab = (float) $row['avg_par_habitant'];
        $avgKm2 = (float) $row['avg_par_km2'];

        if (!isset($rankingData[$polluant])) {
            $rankingData[$polluant] = [];
        }
        $rankingData[$polluant][] = [
            'city'    => $row['city'],
            'avg_val' => $avgVal,
            'avg_hab' => $avgHab,
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

<section id="classement">
    <h2>Classement des villes les plus polluées</h2>
    <!-- Menu déroulant pour choisir le polluant -->
    <label for="polluant-select" class="polluant-label">Polluant :</label>
    <select id="polluant-select" class="pollutant-dropdown"></select>

    <!-- Nouveau bloc d'informations sur le polluant + seuils -->
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

    <!-- Cases à cocher pour afficher/masquer les colonnes -->
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

    <!-- Conteneur du tableau de classement -->
    <div id="rankingContainer"></div>

    <!-- Bouton "Voir plus" pour la pagination -->
    <button id="loadMoreButton" class="btn-voir-plus" title="Charger plus de résultats" style="display: none;">
        Voir plus
    </button>
</section>

<script>
    (function() {
        var rankingData   = <?php echo json_encode($rankingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
        var thresholdData = <?php echo json_encode($thresholds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

        // Explications textuelles pour certains polluants
        var polluantExplanations = {
            'NO':   "Le NO (Monoxyde d'azote) est un gaz émis principalement par les moteurs diesel et la combustion industrielle. " +
                "Il peut contribuer à la formation de NO₂ et d'ozone dans l'atmosphère. " +
                "Des niveaux trop élevés peuvent accentuer les problèmes respiratoires et cardiovasculaires.",
            'NO2':  "Le NO₂ (Dioxyde d'azote) est un gaz irritant émis surtout par le trafic routier. " +
                "Une exposition prolongée peut provoquer des inflammations des voies respiratoires.",
            'PM10': "Les PM₁₀ désignent des particules en suspension d'un diamètre inférieur à 10 µm. " +
                "Elles proviennent principalement du trafic routier, des activités industrielles et du chauffage domestique. " +
                "Elles peuvent irriter les voies respiratoires et aggraver l'asthme.",
            'PM2.5':"Les PM₂.₅ sont des particules de diamètre inférieur à 2,5 µm, plus fines que les PM₁₀," +
                "elles peuvent pénétrer profondément dans l'organisme et affecter la santé cardiovasculaire et pulmonaire.",
            'SO2':  "Le dioxyde de soufre (SO₂) est principalement émis lors de la combustion de charbon et de pétrole. " +
                "Il peut irriter les voies respiratoires et contribuer à la formation de pluies acides.",
            'O3':   "L'ozone troposphérique (O₃) se forme par réaction photochimique en présence de soleil et de polluants précurseurs. " +
                "Des concentrations élevées peuvent provoquer des irritations oculaires et respiratoires."
        };

        let currentPollutant = null;
        let currentOffset = 0;
        const pageSize = 25;

        let sortColumn = null;
        let sortAsc = true;

        const polluantSelect    = document.getElementById('polluant-select');
        const rankingContainer  = document.getElementById('rankingContainer');
        const loadMoreButton    = document.getElementById('loadMoreButton');
        const polluantInfo      = document.getElementById('polluant-info');
        const polluantThresholdsBox = document.getElementById('polluant-thresholds');

        const chkAvgVal = document.getElementById('chkAvgVal');
        const chkAvgHab = document.getElementById('chkAvgHab');
        const chkAvgKm2 = document.getElementById('chkAvgKm2');

        // Remplir la liste des polluants
        const allPolls = Object.keys(rankingData);
        if (allPolls.length === 0) {
            polluantSelect.innerHTML = '<option>Aucun polluant</option>';
        } else {
            let optionsHtml = '';
            allPolls.forEach(p => {
                optionsHtml += `<option value="${p}">${p}</option>`;
            });
            polluantSelect.innerHTML = optionsHtml;
            currentPollutant = allPolls[0];
        }

        // Affiche la description du polluant + encadré Seuils
        function updatePollutantExplanation(poll) {
            if (!poll) {
                polluantInfo.textContent = '';
                polluantThresholdsBox.innerHTML = 'Aucune donnée disponible.';
                return;
            }
            // Descriptif
            if (!polluantExplanations[poll]) {
                polluantInfo.textContent = "Aucune explication disponible pour ce polluant.";
            } else {
                polluantInfo.textContent = polluantExplanations[poll];
            }
            // Seuils & Recommandations
            if (thresholdData[poll]) {
                let thr = thresholdData[poll];
                let val = thr.value;
                let unite = thr.unite;
                let details = thr.details || '';
                let origines = thr.origines || '';
                // Construire un petit HTML
                polluantThresholdsBox.innerHTML = `
                    <div class="threshold-box">
                        <p><strong>Valeur annuelle : </strong>${val} ${unite}</p>
                        <p><strong>Origine(s) : </strong>${origines}</p>
                        <p>${details}</p>
                    </div>
                `;
            } else {
                polluantThresholdsBox.innerHTML = "Aucun seuil annuel disponible pour ce polluant.";
            }
        }

        // Tri
        function sortRows(rows) {
            if (!sortColumn) return rows;
            const sorted = rows.slice();
            sorted.sort((a, b) => {
                let va, vb;
                if (sortColumn === 'city') {
                    va = a.city.toLowerCase();
                    vb = b.city.toLowerCase();
                } else {
                    va = a[sortColumn];
                    vb = b[sortColumn];
                }
                if (va < vb) return sortAsc ? -1 : 1;
                if (va > vb) return sortAsc ? 1 : -1;
                return 0;
            });
            return sorted;
        }

        // Rendu du tableau
        function renderRanking() {
            if (!currentPollutant || !rankingData[currentPollutant]) {
                rankingContainer.innerHTML = '<p>Aucune donnée pour ce polluant.</p>';
                loadMoreButton.style.display = 'none';
                return;
            }

            let allRows = rankingData[currentPollutant];
            allRows = sortRows(allRows);

            const rowsToDisplay = allRows.slice(0, currentOffset + pageSize);

            const showAvgVal = chkAvgVal.checked;
            const showAvgHab = chkAvgHab.checked;
            const showAvgKm2 = chkAvgKm2.checked;

            function getArrow(col) {
                if (col !== sortColumn) return '';
                return sortAsc ? ' &#x25B2;' : ' &#x25BC;';
            }

            let html = '<table class="table-classement">';
            html += '<thead><tr>';
            html += `<th onclick="sortBy('city')">Rang / Ville${getArrow('city')}</th>`;

            if (showAvgVal) {
                html += `<th onclick="sortBy('avg_val')">Moy. (µg/m³)${getArrow('avg_val')}</th>`;
            }
            if (showAvgHab) {
                html += `<th onclick="sortBy('avg_hab')">Moy. par hab${getArrow('avg_hab')}</th>`;
            }
            if (showAvgKm2) {
                html += `<th onclick="sortBy('avg_par_km2')">Moy. par km²${getArrow('avg_par_km2')}</th>`;
            }
            html += '</tr></thead><tbody>';

            let seuilInfo = thresholdData[currentPollutant] || null;
            let seuilVal = seuilInfo ? seuilInfo.value : null;
            let hoverBase = seuilInfo ? seuilInfo.hover : '';

            rowsToDisplay.forEach((item, index) => {
                const rangAbsolu = sortAsc ? (index + 1) : (allRows.length - index);
                const cityLink = `<a href="../fonctionnalites/details.php?ville=${encodeURIComponent(item.city)}">${item.city}</a>`;

                let ratio = 0;
                let bgColorClass = '';
                let hoverTitle = hoverBase || 'Aucun seuil défini.';
                if (seuilVal) {
                    ratio = item.avg_val / seuilVal;
                    if (ratio < 0.8) {
                        bgColorClass = 'bg-green';
                        hoverTitle = `Valeur < 80% du seuil (${seuilVal} ${seuilInfo.unite}). ${hoverBase}`;
                    } else if (ratio < 1) {
                        bgColorClass = 'bg-orange';
                        hoverTitle = `Valeur proche du seuil (${seuilVal} ${seuilInfo.unite}). ${hoverBase}`;
                    } else {
                        bgColorClass = 'bg-red';
                        hoverTitle = `Valeur > seuil (${seuilVal} ${seuilInfo.unite}). ${hoverBase}`;
                    }
                }

                html += `<tr class="${bgColorClass}" title="${hoverTitle}">`;
                html += `<td>${rangAbsolu}. ${cityLink}</td>`;

                if (showAvgVal) {
                    html += `<td>${item.avg_val.toFixed(2)}</td>`;
                }
                if (showAvgHab) {
                    html += `<td>${item.avg_hab.toFixed(4)}</td>`;
                }
                if (showAvgKm2) {
                    html += `<td>${item.avg_par_km2.toFixed(4)}</td>`;
                }
                html += `</tr>`;
            });

            html += '</tbody></table>';
            rankingContainer.innerHTML = html;

            if (rowsToDisplay.length < allRows.length) {
                loadMoreButton.style.display = 'inline-block';
            } else {
                loadMoreButton.style.display = 'none';
            }
        }

        // Fonctions globales
        window.sortBy = function(colKey) {
            if (sortColumn === colKey) {
                sortAsc = !sortAsc;
            } else {
                sortColumn = colKey;
                sortAsc = true;
            }
            renderRanking();
        };

        polluantSelect.addEventListener('change', function() {
            currentPollutant = this.value;
            currentOffset = 0;
            updatePollutantExplanation(currentPollutant);
            renderRanking();
        });

        loadMoreButton.addEventListener('click', function() {
            currentOffset += pageSize;
            renderRanking();
        });

        chkAvgVal.addEventListener('change', renderRanking);
        chkAvgHab.addEventListener('change', renderRanking);
        chkAvgKm2.addEventListener('change', renderRanking);

        // Initialisation
        if (currentPollutant) {
            updatePollutantExplanation(currentPollutant);
        }
        renderRanking();
    })();
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
