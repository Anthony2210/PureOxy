document.addEventListener('DOMContentLoaded', function () {
    // Variables de pagination
    let currentPageHistorique = 1;
    let currentPagePredictions = 1;

    // Stockage des dernières données chargées pour chaque onglet
    let latestBarData = { historique: null, predictions: null };
    let latestTimeData = { historique: null, predictions: null };

    // Gestion des onglets principaux
    const tabs = document.querySelectorAll('.tabs li');
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const selectedTab = this.getAttribute('data-tab');
            // Réinitialiser les onglets
            document.querySelectorAll('.tabs li').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById(selectedTab).classList.add('active');
            // Réinitialiser les sous-onglets du panneau actif
            const subTabs = document.querySelectorAll(`#${selectedTab} .sub-tabs li`);
            subTabs.forEach((st, index) => st.classList.toggle('active', index === 0));
            const subPanels = document.querySelectorAll(`#${selectedTab} .sub-tab-panel`);
            subPanels.forEach((panel, index) => panel.classList.toggle('active', index === 0));
            // Réinitialiser la pagination pour le nouvel onglet
            if (selectedTab === 'historique') {
                currentPageHistorique = 1;
            } else if (selectedTab === 'predictions') {
                currentPagePredictions = 1;
            }
            loadTabData(selectedTab, false);
        });
    });

    // Gestion des sous-onglets
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
            // Après un court délai, recréer le graphique du sous-onglet visible
            setTimeout(() => {
                if (selectedSubTab.startsWith('line')) {
                    if (parent.id === 'historique' && latestTimeData.historique) {
                        if (timeChartHistorique) timeChartHistorique.destroy();
                        updateTimeChart('historique', latestTimeData.historique);
                    } else if (parent.id === 'predictions' && latestTimeData.predictions) {
                        if (timeChartPredictions) timeChartPredictions.destroy();
                        updateTimeChart('predictions', latestTimeData.predictions);
                    }
                } else if (selectedSubTab.startsWith('bar')) {
                    if (parent.id === 'historique' && latestBarData.historique) {
                        if (barChartHistorique) barChartHistorique.destroy();
                        updateBarChart('historique', latestBarData.historique);
                    } else if (parent.id === 'predictions' && latestBarData.predictions) {
                        if (barChartPredictions) barChartPredictions.destroy();
                        updateBarChart('predictions', latestBarData.predictions);
                    }
                }
            }, 100);
        });
    });

    // Sélecteurs de filtres
    const historiquePollutantFilter = document.getElementById('pollutant-filter-historique');
    const historiqueMonthFilter = document.getElementById('month-filter-historique');
    const predictionsPollutantFilter = document.getElementById('pollutant-filter-predictions');
    const predictionsMonthFilter = document.getElementById('month-filter-predictions');

    if (historiquePollutantFilter) {
        historiquePollutantFilter.addEventListener('change', () => {
            currentPageHistorique = 1;
            loadTabData('historique', false);
        });
    }
    if (historiqueMonthFilter) {
        historiqueMonthFilter.addEventListener('change', () => {
            currentPageHistorique = 1;
            loadTabData('historique', false);
        });
    }
    if (predictionsPollutantFilter) {
        predictionsPollutantFilter.addEventListener('change', () => {
            currentPagePredictions = 1;
            loadTabData('predictions', false);
        });
    }
    if (predictionsMonthFilter) {
        predictionsMonthFilter.addEventListener('change', () => {
            currentPagePredictions = 1;
            loadTabData('predictions', false);
        });
    }

    // Fonction de chargement des données via AJAX
    // isLoadMore indique si l'on doit ajouter (concaténer) ou remplacer le tableau
    function loadTabData(tab, isLoadMore) {
        let pollutant = '';
        let month = '';
        let page = 1;
        if (tab === 'historique') {
            pollutant = historiquePollutantFilter ? historiquePollutantFilter.value : '';
            month = historiqueMonthFilter ? historiqueMonthFilter.value : '';
            page = currentPageHistorique;
        } else if (tab === 'predictions') {
            pollutant = predictionsPollutantFilter ? predictionsPollutantFilter.value : '';
            month = predictionsMonthFilter ? predictionsMonthFilter.value : '';
            page = currentPagePredictions;
        }
        const params = new URLSearchParams();
        params.append('tab', tab);
        params.append('pollutant', pollutant);
        params.append('month', month);
        params.append('id_ville', idVille);
        params.append('page', page);

        fetch('../fonctionnalites/get_tab_data.php', {
            method: 'POST',
            body: params,
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                // Sauvegarder les données pour recréation ultérieure
                latestBarData[tab] = data.barData;
                latestTimeData[tab] = data.timeData;
                // Détruire les graphiques existants avant de les recréer
                if (tab === 'historique') {
                    if (barChartHistorique) { barChartHistorique.destroy(); }
                    if (timeChartHistorique) { timeChartHistorique.destroy(); }
                } else if (tab === 'predictions') {
                    if (barChartPredictions) { barChartPredictions.destroy(); }
                    if (timeChartPredictions) { timeChartPredictions.destroy(); }
                }
                updateBarChart(tab, data.barData);
                updateTimeChart(tab, data.timeData);
                if (!isLoadMore) {
                    updateDataTable(tab, data.tableHtml);
                } else {
                    appendDataTable(tab, data.tableHtml);
                }
            })
            .catch(error => console.error('Erreur:', error));
    }

    // Fonction pour mettre à jour le tableau (remplacement)
    function updateDataTable(tab, tableHtml) {
        document.getElementById(`data-table-${tab}`).innerHTML = tableHtml;
    }

    // Fonction pour ajouter des lignes au tableau existant
    // On utilise un DOMParser pour extraire les lignes (<tr>) du <tbody> du HTML renvoyé
    function appendDataTable(tab, extraHtml) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(extraHtml, 'text/html');
        const newRows = doc.querySelectorAll('table tbody tr');
        const container = document.querySelector(`#data-table-${tab} table tbody`);
        // Supprimer le bouton "Voir plus" s'il existe
        const oldBtn = document.querySelector(`#data-table-${tab} .btn-load-more`);
        if (oldBtn) {
            oldBtn.remove();
        }
        newRows.forEach(row => {
            container.appendChild(row);
        });
        // Si le extraHtml contient un bouton "Voir plus", on l'ajoute à la fin du tbody
        const newBtn = doc.querySelector('.btn-load-more');
        if (newBtn) {
            container.parentElement.insertAdjacentHTML('beforeend', newBtn.outerHTML);
        }
    }

    // Fonction pour charger plus de données (pagination)
    function loadMore(tab) {
        if (tab === 'historique') {
            currentPageHistorique++;
        } else if (tab === 'predictions') {
            currentPagePredictions++;
        }
        loadTabData(tab, true);
    }

    // Exposer la fonction loadMore pour l'appel depuis le HTML
    window.loadMore = loadMore;

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
            barChartHistorique = new Chart(ctx, config);
        } else if (tab === 'predictions') {
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
            timeChartHistorique = new Chart(ctx, config);
        } else if (tab === 'predictions') {
            timeChartPredictions = new Chart(ctx, config);
        }
    }

    // Chargement initial de l'onglet Historique
    loadTabData('historique', false);
});
