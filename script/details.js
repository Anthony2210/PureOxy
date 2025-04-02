/**
 * details.js
 *
 * Ce script gère l'interactivité sur la page "details.php".
 * Il prend en charge la pagination pour les onglets Historique et Prédictions,
 * la gestion des filtres (polluant et mois) et le rafraîchissement dynamique des graphiques
 * (bar chart, time chart) ainsi que du tableau de données.
 *
 * Références :
 * - ChatGPT pour la structuration des événements et la gestion de la pagination.
 *
 * Utilisation :
 * - Ce script est chargé sur la page "details.php" et s'active lors de l'interaction de l'utilisateur.
 *
 * Fichier placé dans le dossier script.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Variables de pagination pour Historique et Prédictions
    let currentPageHistorique = 1;
    let currentPagePredictions = 1;

    // Stockage des dernières données chargées pour recréer les graphiques
    let latestBarData = { historique: null, predictions: null };
    let latestTimeData = { historique: null, predictions: null };

    // Gestion des onglets principaux
    const tabs = document.querySelectorAll('.tabs li');
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const selectedTab = this.getAttribute('data-tab');
            // Réinitialisation des onglets
            document.querySelectorAll('.tabs li').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById(selectedTab).classList.add('active');
            // Réinitialisation des sous-onglets
            const subTabs = document.querySelectorAll(`#${selectedTab} .sub-tabs li`);
            subTabs.forEach((st, index) => st.classList.toggle('active', index === 0));
            const subPanels = document.querySelectorAll(`#${selectedTab} .sub-tab-panel`);
            subPanels.forEach((panel, index) => panel.classList.toggle('active', index === 0));
            // Réinitialisation de la pagination pour l'onglet sélectionné
            if (selectedTab === 'historique') {
                currentPageHistorique = 1;
            } else if (selectedTab === 'predictions') {
                currentPagePredictions = 1;
            }
            loadTabData(selectedTab, false);
        });
    });

    // Gestion des sous-onglets (Bar, Ligne, Tableau)
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
            // Recréation des graphiques après un court délai
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

    // Sélecteurs pour les filtres des onglets
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

    /**
     * Charge les données de l'onglet via AJAX.
     *
     * @param {string} tab L'onglet à charger ("historique" ou "predictions").
     * @param {boolean} isLoadMore Si true, les données sont ajoutées au tableau existant.
     */
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
                // Sauvegarde des données pour recréer les graphiques en cas de changement de sous-onglet
                latestBarData[tab] = data.barData;
                latestTimeData[tab] = data.timeData;
                // Destruction des graphiques existants
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

    // Mise à jour complète du tableau (remplacement)
    function updateDataTable(tab, tableHtml) {
        document.getElementById(`data-table-${tab}`).innerHTML = tableHtml;
    }

    // Ajoute des lignes au tableau existant (pour la pagination)
    function appendDataTable(tab, extraHtml) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(extraHtml, 'text/html');
        const newRows = doc.querySelectorAll('table tbody tr');
        const container = document.querySelector(`#data-table-${tab} table tbody`);
        // Supprime le bouton "Voir plus" existant
        const oldBtn = document.querySelector(`#data-table-${tab} .btn-load-more`);
        if (oldBtn) {
            oldBtn.remove();
        }
        newRows.forEach(row => {
            container.appendChild(row);
        });
        // Ajoute un nouveau bouton "Voir plus" si présent
        const newBtn = doc.querySelector('.btn-load-more');
        if (newBtn) {
            container.parentElement.insertAdjacentHTML('beforeend', newBtn.outerHTML);
        }
    }

    // Fonction de chargement pour la pagination
    function loadMore(tab) {
        if (tab === 'historique') {
            currentPageHistorique++;
        } else if (tab === 'predictions') {
            currentPagePredictions++;
        }
        loadTabData(tab, true);
    }
    window.loadMore = loadMore;

    // Variables globales pour Chart.js
    let barChartHistorique, timeChartHistorique, barChartPredictions, timeChartPredictions;

    /**
     * Met à jour le graphique en barres avec les données fournies.
     *
     * @param {string} tab "historique" ou "predictions"
     * @param {Object} barData Données pour le graphique en barres
     */
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

    /**
     * Met à jour le graphique linéaire avec les données temporelles.
     *
     * @param {string} tab "historique" ou "predictions"
     * @param {Object} timeData Données pour le graphique linéaire
     */
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
