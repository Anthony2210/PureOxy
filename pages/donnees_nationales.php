<?php
session_start();
include '../bd/bd.php';

/************************************************
 * 1) Récupération des données de seuils
 ************************************************/
// On stocke d’abord TOUTES les lignes de seuils "moyenne annuelle" dans un tableau temporaire
$thresholdsMulti = [];  // ex. $thresholdsMulti['NO2'] = [ [valeur=>40, origine=>'FR', ...], ... ]

$sqlT = "
    SELECT polluant, polluant_complet, valeur, unite, details, origine
    FROM seuils_normes
    WHERE periode = 'moyenne annuelle'
";
$resT = $conn->query($sqlT);
if ($resT && $resT->num_rows > 0) {
    while ($rowT = $resT->fetch_assoc()) {
        $poll = $rowT['polluant']; // ex. "NO2"
        if (!isset($thresholdsMulti[$poll])) {
            $thresholdsMulti[$poll] = [];
        }
        $thresholdsMulti[$poll][] = [
            'value'    => (float) $rowT['valeur'],
            'unite'    => $rowT['unite'],
            'details'  => $rowT['details'],
            'origine'  => $rowT['origine'],
            // Optionnellement, vous pouvez aussi stocker polluant_complet si utile
            'complet'  => $rowT['polluant_complet']
        ];
    }
}

// Maintenant on agrège pour n’avoir qu’un seul objet par polluant
$thresholds = []; // ex. $thresholds['NO2'] = [ 'value'=>40, 'unite'=>'µg/m³', 'origines'=>'FR,UE', 'hover'=>'...' ]
foreach ($thresholdsMulti as $poll => $rows) {
    if (empty($rows)) {
        continue;
    }
    // On trie par valeur DESC pour trouver la plus élevée
    usort($rows, function($a, $b) {
        return $b['value'] <=> $a['value']; // tri décroissant
    });

    // La première ligne est la valeur la plus élevée
    $maxVal = $rows[0]['value'];
    $maxUnite = $rows[0]['unite'];
    $maxDetails = $rows[0]['details'];
    // On va collecter les origines pour toutes les lignes qui ont la même value max
    $maxOrigins = [$rows[0]['origine']];

    // Les autres seuils (valeurs inférieures ou différentes)
    $others = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if ($row['value'] == $maxVal) {
            // Même valeur max => on ajoute juste l’origine
            $maxOrigins[] = $row['origine'];
        } else {
            // Valeur plus basse => on la stocke dans "others"
            // pour l’afficher au survol
            $others[] = $row;
        }
    }

    // On construit un texte pour l’infobulle
    // - mention de la valeur max + origines
    // - mention des autres (si existants)
    $hoverText = "Seuil = {$maxVal} {$maxUnite}, origine : " . implode(", ", $maxOrigins) . ".";
    if (!empty($others)) {
        $hoverText .= " Autres seuils inférieurs : ";
        $tmp = [];
        foreach ($others as $o) {
            $tmp[] = "{$o['value']} {$o['unite']} ({$o['origine']})";
        }
        $hoverText .= implode("; ", $tmp) . ".";
    }

    // On stocke dans $thresholds ce qu’on veut réutiliser côté JS
    $thresholds[$poll] = [
        'value'   => $maxVal,          // la valeur la plus élevée
        'unite'   => $maxUnite,
        'details' => $maxDetails,
        'origines'=> implode(", ", $maxOrigins),
        'hover'   => $hoverText        // un texte explicatif tout prêt
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
        // Option : ignorer un polluant particulier, ex. 'C6H6'
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

// Passage des données en JSON pour le JS
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
    <label for="polluant-select" style="font-weight: bold;">Polluant :</label>
    <select id="polluant-select" class="polluant-dropdown"></select>

    <!-- Paragraphe d'explication du polluant -->
    <p id="polluant-info" style="font-style: italic; color: #666; margin-top: 5px;"></p>

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
    <button id="loadMoreButton" class="btn-voir-plus" title="Charger plus de résultats" style="display: none;">
        Voir plus
    </button>
</section>

<script>
    (function() {
        /**************************************************************
         * 1) Données du classement + seuils (récupérées en PHP -> JSON)
         **************************************************************/
        var rankingData = <?php echo json_encode($rankingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
        var thresholdData = <?php echo json_encode($thresholds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

        /**************************************************************
         * 2) Explications textuelles pour certains polluants
         **************************************************************/
        var polluantExplanations = {
            'NO2':  "Le NO2 (Dioxyde d'azote) est un gaz irritant émis notamment par le trafic routier.",
            'PM10': "Particules de diamètre < 10 µm. Principales sources : transport, industries, etc.",
            'PM2.5':"Particules fines < 2,5 µm, encore plus dangereuses pour la santé.",
            'SO2':  "Le dioxyde de soufre (SO2) provient surtout de la combustion de charbon et de pétrole.",
            'O3':   "L'ozone troposphérique (O3) se forme par réaction photochimique, surtout en été."
        };

        /**************************************************************
         * 3) Variables globales et références DOM
         **************************************************************/
        let currentPollutant = null; // polluant en cours d'affichage
        let currentOffset = 0;       // pagination : combien de villes déjà affichées
        const pageSize = 25;         // nombre de villes à afficher par "page"

        let sortColumn = null;       // colonne utilisée pour trier ("city", "avg_val", "avg_hab", "avg_par_km2")
        let sortAsc = true;          // sens du tri : croissant (true) ou décroissant (false)

        // Récupération des éléments du DOM
        const polluantSelect    = document.getElementById('polluant-select');
        const rankingContainer  = document.getElementById('rankingContainer');
        const loadMoreButton    = document.getElementById('loadMoreButton');
        const polluantInfo      = document.getElementById('polluant-info');

        // Cases à cocher
        const chkAvgVal = document.getElementById('chkAvgVal');
        const chkAvgHab = document.getElementById('chkAvgHab');
        const chkAvgKm2 = document.getElementById('chkAvgKm2');

        /**************************************************************
         * 4) Initialisation du menu déroulant des polluants
         **************************************************************/
        const allPolls = Object.keys(rankingData);
        if (allPolls.length === 0) {
            polluantSelect.innerHTML = '<option>Aucun polluant</option>';
        } else {
            let optionsHtml = '';
            allPolls.forEach(p => {
                optionsHtml += `<option value="${p}">${p}</option>`;
            });
            polluantSelect.innerHTML = optionsHtml;
            currentPollutant = allPolls[0]; // on prend le premier polluant par défaut
        }

        /**************************************************************
         * 5) Fonction pour afficher une explication sur le polluant
         **************************************************************/
        function updatePollutantExplanation(poll) {
            if (!poll) {
                polluantInfo.textContent = '';
                return;
            }
            if (!polluantExplanations[poll]) {
                polluantInfo.textContent = "Aucune explication disponible pour ce polluant.";
            } else {
                polluantInfo.textContent = polluantExplanations[poll];
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
                    // "avg_val", "avg_hab", "avg_par_km2"
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
         * 7) Rendu du tableau (avec pagination + colonnes dynamiques)
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

            // Construction de l'entête de tableau avec flèches
            function getArrow(col) {
                if (col !== sortColumn) return '';
                return sortAsc ? ' &#x25B2;' : ' &#x25BC;'; // flèche up / down
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

            // Récupération du seuil agrégé pour le polluant en cours
            let seuilInfo = thresholdData[currentPollutant] || null;
            // Ex. seuilInfo = { value:40, unite:'µg/m³', details:'A ne pas dépasser plus de 18h/an', origines:'FR,UE', hover:'...' }

            let seuilVal = seuilInfo ? seuilInfo.value : null;
            let hoverBase = seuilInfo ? seuilInfo.hover : '';

            // Remplir le tableau
            rowsToDisplay.forEach((item, index) => {
                // Rang : si tri décroissant => rang = (allRows.length - index), sinon rang = index+1
                const rangAbsolu = sortAsc ? (index + 1) : (allRows.length - index);
                const cityLink = `<a href="../fonctionnalites/details.php?ville=${encodeURIComponent(item.city)}">${item.city}</a>`;

                // Couleur de fond en fonction du seuil
                let ratio = 0;
                let bgColorClass = '';
                let hoverTitle = hoverBase || 'Aucun seuil défini.';
                if (seuilVal) {
                    ratio = item.avg_val / seuilVal;
                    // <0.8 = vert, <1 = orange, >=1 = rouge
                    if (ratio < 0.8) {
                        bgColorClass = 'bg-green';
                        hoverTitle = `Valeur en dessous de 80% du seuil (${seuilVal} ${seuilInfo.unite}). ${hoverBase}`;
                    } else if (ratio < 1) {
                        bgColorClass = 'bg-orange';
                        hoverTitle = `Valeur proche du seuil (${seuilVal} ${seuilInfo.unite}). ${hoverBase}`;
                    } else {
                        bgColorClass = 'bg-red';
                        hoverTitle = `Valeur au-dessus du seuil (${seuilVal} ${seuilInfo.unite}). ${hoverBase}`;
                    }
                }

                html += `<tr class="${bgColorClass}" title="${hoverTitle}">`;
                // On fusionne Rang et Ville dans une seule colonne
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
        window.sortBy = function(colKey) {
            // Si on reclique sur la même colonne, on inverse le sens
            if (sortColumn === colKey) {
                sortAsc = !sortAsc;
            } else {
                // On change de colonne, on se met en ordre croissant par défaut
                sortColumn = colKey;
                sortAsc = true;
            }
            renderRanking();
        };

        /**************************************************************
         * 9) Écouteurs d'événements
         **************************************************************/
        // Changement de polluant
        polluantSelect.addEventListener('change', function() {
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
