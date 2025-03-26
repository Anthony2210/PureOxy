document.addEventListener('DOMContentLoaded', function() {

    // HISTORIQUE
    const btnHistFilter = document.getElementById('btnHistFilter');
    if (btnHistFilter) {
        btnHistFilter.addEventListener('click', function() {
            const polluant = document.getElementById('histPolluant').value;
            const mois     = document.getElementById('histMois').value;
            loadDataAjax('historique', polluant, mois, 'histMonthlyContainer', 'histDailyContainer');
        });
    }

    // PREDICTIONS
    const btnPredFilter = document.getElementById('btnPredFilter');
    if (btnPredFilter) {
        btnPredFilter.addEventListener('click', function() {
            const polluant = document.getElementById('predPolluant').value;
            const mois     = document.getElementById('predMois').value;
            loadDataAjax('predictions', polluant, mois, 'predMonthlyContainer', 'predDailyContainer');
        });
    }

});

/**
 * Charge les données en AJAX depuis details_data.php
 * @param {string} tab - 'historique' ou 'predictions'
 * @param {string} polluant
 * @param {string} mois
 * @param {string} monthlyContainerId - ID du div pour le tableau mensuel
 * @param {string} dailyContainerId   - ID du div pour le tableau journalier
 */
function loadDataAjax(tab, polluant, mois, monthlyContainerId, dailyContainerId) {
    // ID_VILLE est défini dans details.php
    const idVille = window.ID_VILLE || 0;

    // Construction de l’URL
    const params = new URLSearchParams({
        idVille: idVille,
        tab: tab
    });
    if (polluant) {
        params.append('polluant', polluant);
    }
    if (mois) {
        params.append('mois', mois);
    }

    fetch('../fonctionnalites/details_data.php?' + params.toString(), {
        method: 'GET'
    })
        .then(response => response.json())
        .then(data => {
            // data.monthlyData, data.dailyData
            renderMonthlyTable(data.monthlyData, tab, monthlyContainerId);
            renderDailyTable(data.dailyData, tab, dailyContainerId);
        })
        .catch(err => {
            console.error('Erreur AJAX : ', err);
        });
}

/**
 * Construit le tableau HTML des moyennes mensuelles
 * @param {Array} monthlyData
 * @param {string} tab
 * @param {string} containerId
 */
function renderMonthlyTable(monthlyData, tab, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Effacer le conteneur avant
    container.innerHTML = '';

    // Définition des colonnes selon l’onglet
    const monthsHistorique = [
        'moy_janv2023','moy_fev2023','moy_mar2023','moy_avril2023','moy_mai2023','moy_juin2023',
        'moy_juil2023','moy_aout2023','moy_sept2023','moy_oct2023','moy_nov2023','moy_dec2023',
        'moy_janv2024','moy_fev2024','moy_mar2024','moy_avril2024','moy_mai2024','moy_juin2024',
        'moy_juil2024','moy_aout2024','moy_sept2024','moy_oct2024','moy_nov2024','moy_dec2024',
        'moy_janv2025'
    ];
    const monthsPrediction = [
        'moy_predic_janv2025','moy_predic_fev2025','moy_predic_mars2025','moy_predic_avril2025',
        'moy_predic_mai2025','moy_predic_juin2025','moy_predic_juil2025','moy_predic_aout2025',
        'moy_predic_sept2025','moy_predic_oct2025','moy_predic_nov2025','moy_predic_dec2025',
        'moy_predic_janv2026'
    ];

    let columns = [];
    if (tab === 'historique') {
        columns = monthsHistorique;
    } else {
        columns = monthsPrediction;
    }

    // Si aucune data => message
    if (!monthlyData || monthlyData.length === 0) {
        container.innerHTML = '<p>Aucune donnée.</p>';
        return;
    }

    // Construction du tableau
    let html = '<table class="table table-bordered table-sm">';
    html += '<thead><tr><th>Polluant</th>';
    columns.forEach(col => {
        html += `<th>${col}</th>`;
    });
    html += '</tr></thead><tbody>';

    monthlyData.forEach(row => {
        html += '<tr>';
        // On affiche le polluant
        html += `<td>${row.polluant}</td>`;
        columns.forEach(col => {
            let val = row[col];
            if (val !== null && val !== undefined && !isNaN(val)) {
                val = parseFloat(val).toFixed(2);
            } else {
                val = '-';
            }
            html += `<td>${val}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

/**
 * Construit le tableau HTML des données journalières
 * @param {Array} dailyData
 * @param {string} tab
 * @param {string} containerId
 */
function renderDailyTable(dailyData, tab, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // On efface avant
    container.innerHTML = '';

    // S’il n’y a pas de données => on n’affiche rien
    if (!dailyData || dailyData.length === 0) {
        return;
    }

    let title = (tab === 'historique')
        ? 'Données journalières (Historique)'
        : 'Données journalières (Prédictions)';

    let html = `<h4>${title}</h4>`;
    html += `<table class="table table-bordered table-sm">
               <thead>
                 <tr>
                   <th>Jour</th>
                   <th>Polluant</th>
                   <th>Valeur</th>
                   <th>Unité</th>
                 </tr>
               </thead>
               <tbody>`;

    dailyData.forEach(d => {
        let val = parseFloat(d.val).toFixed(2);
        // On force l'unité à µg-m3
        html += `<tr>
                   <td>${d.jour}</td>
                   <td>${d.polluant}</td>
                   <td>${val}</td>
                   <td>µg-m3</td>
                 </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}
