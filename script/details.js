document.addEventListener('DOMContentLoaded', function () {
    // Gestion du changement d'onglet principal
    const tabs = document.querySelectorAll('.tabs li');
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const selectedTab = this.getAttribute('data-tab');
            document.querySelectorAll('.tabs li').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById(selectedTab).classList.add('active');
            // Réinitialiser les sous-onglets du panneau actif
            const subTabs = document.querySelectorAll(`#${selectedTab} .sub-tabs li`);
            subTabs.forEach((st, index) => st.classList.toggle('active', index === 0));
            const subPanels = document.querySelectorAll(`#${selectedTab} .sub-tab-panel`);
            subPanels.forEach((panel, index) => panel.classList.toggle('active', index === 0));
            loadTabData(selectedTab);
        });
    });

    // Gestion du changement de sous-onglets
    const subTabs = document.querySelectorAll('.sub-tabs li');
    subTabs.forEach(subTab => {
        subTab.addEventListener('click', function () {
            const parent = this.closest('.tab-panel');
            const selectedSubTab = this.getAttribute('data-subtab');
            parent.querySelectorAll('.sub-tabs li').forEach(st => st.classList.remove('active'));
            this.classList.add('active');
            parent.querySelectorAll('.sub-tab-panel').forEach(panel => {
                panel.classList.toggle('active', panel.id === selectedSubTab);
            });
        });
    });

    // Sélecteurs de filtres
    const historiquePollutantFilter = document.getElementById('pollutant-filter-historique');
    const historiqueMonthFilter = document.getElementById('month-filter-historique');
    const predictionsPollutantFilter = document.getElementById('pollutant-filter-predictions');
    const predictionsMonthFilter = document.getElementById('month-filter-predictions');

    if (historiquePollutantFilter) {
        historiquePollutantFilter.addEventListener('change', () => loadTabData('historique'));
    }
    if (historiqueMonthFilter) {
        historiqueMonthFilter.addEventListener('change', () => loadTabData('historique'));
    }
    if (predictionsPollutantFilter) {
        predictionsPollutantFilter.addEventListener('change', () => loadTabData('predictions'));
    }
    if (predictionsMonthFilter) {
        predictionsMonthFilter.addEventListener('change', () => loadTabData('predictions'));
    }

    // Fonction de chargement des données via AJAX
    function loadTabData(tab) {
        let pollutant = '';
        let month = '';
        if (tab === 'historique') {
            pollutant = historiquePollutantFilter.value;
            month = historiqueMonthFilter.value;
        } else if (tab === 'predictions') {
            pollutant = predictionsPollutantFilter.value;
            month = predictionsMonthFilter.value;
        }
        const params = new URLSearchParams();
        params.append('tab', tab);
        params.append('pollutant', pollutant);
        params.append('month', month);
        params.append('id_ville', idVille);

        fetch('../fonctionnalites/get_tab_data.php', {
            method: 'POST',
            body: params,
        })
            .then(response => response.json())
            .then(data => {console.log(data);
                updateBarChart(tab, data.barData);
                updateTimeChart(tab, data.timeData);
                updateDataTable(tab, data.tableHtml);
            })
            .catch(error => console.error('Erreur:', error));
    }

    // Variables globales pour Chart.js
    let barChartHistorique, timeChartHistorique, barChartPredictions, timeChartPredictions;
    function updateBarChart(tab, barData) {
        const ctx = document.getElementById(`bar-chart-${tab}`).getContext('2d');
        const config = {
            type: 'bar',
            data: {
                labels: barData.labels,
                datasets: [{
                    label: 'Moyenne des polluants',
                    data: barData.values,
                    backgroundColor: barData.colors
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } }
            }
        };
        if (tab === 'historique') {
            if (barChartHistorique) barChartHistorique.destroy();
            barChartHistorique = new Chart(ctx, config);
        } else if (tab === 'predictions') {
            if (barChartPredictions) barChartPredictions.destroy();
            barChartPredictions = new Chart(ctx, config);
        }
    }

    function updateTimeChart(tab, timeData) {
        const ctx = document.getElementById(`time-chart-${tab}`).getContext('2d');
        const config = {
            type: 'line',
            data: {
                labels: timeData.labels,
                datasets: timeData.datasets
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: {
                    x: { title: { display: true, text: 'Date' } },
                    y: { title: { display: true, text: 'Valeur' } }
                }
            }
        };
        if (tab === 'historique') {
            if (timeChartHistorique) timeChartHistorique.destroy();
            timeChartHistorique = new Chart(ctx, config);
        } else if (tab === 'predictions') {
            if (timeChartPredictions) timeChartPredictions.destroy();
            timeChartPredictions = new Chart(ctx, config);
        }
    }

    function updateDataTable(tab, tableHtml) {
        document.getElementById(`data-table-${tab}`).innerHTML = tableHtml;
    }

    // Chargement initial de l'onglet Historique
    loadTabData('historique');
});
