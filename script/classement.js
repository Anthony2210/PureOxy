/**
 * classement.js
 *
 * Ce script gère l'interactivité de la page "classement.php".
 * Il permet de :
 *  - Afficher dynamiquement le classement des villes par polluant.
 *  - Mettre à jour la description et les seuils du polluant sélectionné.
 *  - Gérer le tri des colonnes ainsi que la pagination.
 *
 * Les données dynamiques (rankingData et thresholdData) sont transmises depuis classement.php.
 *
 * Fichier placé dans le dossier script.
 */

(function() {
    // Explications textuelles pour certains polluants
    var polluantExplanations = {
        'NO':   "Le NO (Monoxyde d'azote) est un gaz émis principalement par les moteurs diesel et la combustion industrielle. Il peut contribuer à la formation de NO₂ et d'ozone dans l'atmosphère. Des niveaux trop élevés peuvent accentuer les problèmes respiratoires et cardiovasculaires.",
        'NO2':  "Le NO₂ (Dioxyde d'azote) est un gaz irritant émis surtout par le trafic routier. Une exposition prolongée peut provoquer des inflammations des voies respiratoires.",
        'PM10': "Les PM₁₀ désignent des particules en suspension d'un diamètre inférieur à 10 µm. Elles proviennent principalement du trafic routier, des activités industrielles et du chauffage domestique. Elles peuvent irriter les voies respiratoires et aggraver l'asthme.",
        'PM2.5':"Les PM₂.₅ sont des particules de diamètre inférieur à 2,5 µm, plus fines que les PM₁₀. Elles peuvent pénétrer profondément dans l'organisme et affecter la santé cardiovasculaire et pulmonaire.",
        'SO2':  "Le dioxyde de soufre (SO₂) est principalement émis lors de la combustion de charbon et de pétrole. Il peut irriter les voies respiratoires et contribuer à la formation de pluies acides.",
        'O3':   "L'ozone troposphérique (O₃) se forme par réaction photochimique en présence de soleil et de polluants précurseurs. Des concentrations élevées peuvent provoquer des irritations oculaires et respiratoires."
    };

    // Variables de pagination et de tri
    let currentPollutant = null;
    let currentOffset = 0;
    const pageSize = 25;

    let sortColumn = null;
    let sortAsc = true;

    // Références aux éléments DOM
    const polluantSelect         = document.getElementById('polluant-select');
    const rankingContainer       = document.getElementById('rankingContainer');
    const loadMoreButton         = document.getElementById('loadMoreButton');
    const polluantInfo           = document.getElementById('polluant-info');
    const polluantThresholdsBox  = document.getElementById('polluant-thresholds');

    const chkAvgVal = document.getElementById('chkAvgVal');
    const chkAvgHab = document.getElementById('chkAvgHab');
    const chkAvgKm2 = document.getElementById('chkAvgKm2');

    // Remplissage de la liste déroulante des polluants
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

    /**
     * Met à jour la description du polluant et affiche ses seuils et recommandations.
     * @param {string} poll - Le polluant sélectionné.
     */
    function updatePollutantExplanation(poll) {
        if (!poll) {
            polluantInfo.textContent = '';
            polluantThresholdsBox.innerHTML = 'Aucune donnée disponible.';
            return;
        }
        // Mise à jour du descriptif du polluant
        polluantInfo.textContent = polluantExplanations[poll] || "Aucune explication disponible pour ce polluant.";
        // Mise à jour des seuils et recommandations si disponibles
        if (thresholdData[poll]) {
            let thr = thresholdData[poll];
            let val = thr.value;
            let unite = thr.unite;
            let details = thr.details || '';
            let origines = thr.origines || '';
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

    /**
     * Trie les lignes du tableau en fonction de la colonne sélectionnée.
     * @param {Array} rows - Les données à trier.
     * @returns {Array} Les données triées.
     */
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

    /**
     * Rendu du tableau de classement en fonction du polluant sélectionné,
     * de la pagination et des colonnes à afficher.
     */
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

        // Affichage conditionnel du bouton "Voir plus" selon le nombre de résultats
        loadMoreButton.style.display = (rowsToDisplay.length < allRows.length) ? 'inline-block' : 'none';
    }

    // Fonction globale pour le tri accessible depuis le HTML
    window.sortBy = function(colKey) {
        if (sortColumn === colKey) {
            sortAsc = !sortAsc;
        } else {
            sortColumn = colKey;
            sortAsc = true;
        }
        renderRanking();
    };

    // Mise à jour lors du changement de polluant
    polluantSelect.addEventListener('change', function() {
        currentPollutant = this.value;
        currentOffset = 0;
        updatePollutantExplanation(currentPollutant);
        renderRanking();
    });

    // Gestion de la pagination avec le bouton "Voir plus"
    loadMoreButton.addEventListener('click', function() {
        currentOffset += pageSize;
        renderRanking();
    });

    // Actualisation de l'affichage lors du changement des cases à cocher pour les colonnes
    chkAvgVal.addEventListener('change', renderRanking);
    chkAvgHab.addEventListener('change', renderRanking);
    chkAvgKm2.addEventListener('change', renderRanking);

    // Initialisation : mise à jour du descriptif et affichage initial du classement
    if (currentPollutant) {
        updatePollutantExplanation(currentPollutant);
    }
    renderRanking();
})();
