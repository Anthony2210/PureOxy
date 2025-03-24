/**
 * details.js
 *
 * NE PAS SUPPRIMER DE LIGNES, UNIQUEMENT DES AJUSTEMENTS
 */
if (!cityNotFound) {
    console.log('polluantsData:', polluantsData);
    console.log('seuilsData:', seuilsData);
    console.log('measurementLabels:', measurementLabels);
    console.log('predictionsData:', predictionsData);

    // ==========================================================
    // 0) VARIABLES / CHARTS GLOBAUX (nouveaux + existants)
    // ==========================================================
    var depassementsChart;        // Chart déjà présent
    var depassementsBarChart;     // *** NOUVEAU *** pour un 2ᵉ graphique en “bar” (onglet Dépassements)

    // *** NOUVEAU *** pour l’onglet Polluants (2 graphiques filtrés)
    var polluantsLineChart = null;
    var polluantsBarChart  = null;

    // *** NOUVEAU *** petit helper pour des couleurs aléatoires
    function randomColor(){
        var r = Math.floor(Math.random() * 255);
        var g = Math.floor(Math.random() * 255);
        var b = Math.floor(Math.random() * 255);
        return 'rgba(' + r + ',' + g + ',' + b + ',1)';
    }

    // ==========================================================
    // 1) GESTION DU GRAPHIQUE DÉPASSEMENTS (existant + ajout)
    // ==========================================================
    function getColorForTypeNorme(typeNorme){
        var c = {
            'Objectif de qualité': 'rgba(75,192,192,1)',
            'Valeur limite pour la protection de la santé humaine': 'rgba(255,99,132,1)',
            'Seuil d\'information et de recommandation': 'rgba(255,206,86,1)',
            'Seuil d\'alerte': 'rgba(153,102,255,1)'
        };
        return c[typeNorme] || 'rgba(201,203,207,1)';
    }

    /**
     * initDepassementsChart
     * @param {string} selectedPolluant
     * @param {Array}  selectedSeuilTypes
     * @param {string} selectedMonth (format YYYY-MM) - *** NOUVEAU ***
     */
    function initDepassementsChart(selectedPolluant, selectedSeuilTypes, selectedMonth) {

        // 1) Vérifier si on a des seuils pour ce polluant
        if (!seuilsData[selectedPolluant] || Object.keys(seuilsData[selectedPolluant]).length === 0) {
            console.warn('Aucun seuil pour polluant:', selectedPolluant);
            if (depassementsChart) depassementsChart.destroy();
            if (depassementsBarChart) depassementsBarChart.destroy(); // *** NOUVEAU ***
            document.getElementById('depassements-text').innerHTML = '';
            return;
        }

        // 2) Récupérer / filtrer les mesures
        var measurements = polluantsData[selectedPolluant] ? polluantsData[selectedPolluant].values : {};
        var measurementValues = [];

        // *** AJOUT : on va créer un tableau de labels filtrés pour gérer le selectedMonth ***
        var filteredLabels = [];

        measurementLabels.forEach(function(identifier){
            var v = (measurements[identifier] !== undefined) ? parseFloat(measurements[identifier]) : null;

            if (selectedMonth) {
                // Exemple d'analyse du label : "Février 2023 - Inconnu"
                var splitted = identifier.split(' ');
                // splitted[0] => 'Février'
                // splitted[1] => '2023'
                var monthMap = {
                    'Janv':'01','Février':'02','Mars':'03','Avril':'04','Mai':'05','Juin':'06',
                    'Juil.':'07','Août':'08','Sept.':'09','Oct.':'10','Nov.':'11','Déc':'12'
                };
                var mo = splitted[0];  // ex: 'Février'
                var yr = splitted[1];  // ex: '2023'
                var mm = monthMap[mo] || '??';
                var formed = yr + '-' + mm;
                if (formed === selectedMonth) {
                    measurementValues.push(v);
                    filteredLabels.push(identifier);
                }
            } else {
                measurementValues.push(v);
                filteredLabels.push(identifier);
            }
        });

        // 3) Choisir line ou bar
        var nonNull = measurementValues.filter(function(x){ return x !== null; }).length;
        var chartType = (nonNull === 1) ? 'bar' : 'line';

        // 4) Vérifier si l’utilisateur a coché des seuils
        var seuilsSelected = selectedSeuilTypes || [];
        if (seuilsSelected.length === 0) {
            console.warn('Aucun seuil coché');
            if (depassementsChart) depassementsChart.destroy();
            if (depassementsBarChart) depassementsBarChart.destroy();
            document.getElementById('depassements-text').innerHTML = '';
            return;
        }

        // 5) Construire datasets
        var datasets = [{
            label: 'Mesures (µg/m³)',
            data: measurementValues,
            borderColor: 'rgba(54,162,235,1)',
            backgroundColor: 'rgba(54,162,235,0.2)',
            fill: (chartType === 'line') ? false : true,
            tension: 0.1,
            pointRadius: 3
        }];

        seuilsSelected.forEach(function(typeNorme){
            if (seuilsData[selectedPolluant][typeNorme]) {
                var sVal = seuilsData[selectedPolluant][typeNorme].valeur;
                var sUni = seuilsData[selectedPolluant][typeNorme].unite;
                var sOrg = seuilsData[selectedPolluant][typeNorme].origine;
                // *** AJUSTEMENT : data = Array(filteredLabels.length).fill(sVal) ***
                datasets.push({
                    label: typeNorme + ' (' + sOrg + ') (' + sVal + ' ' + sUni + ')',
                    data: Array(filteredLabels.length).fill(sVal),
                    borderColor: getColorForTypeNorme(typeNorme),
                    backgroundColor: 'rgba(255,99,132,0.2)',
                    fill: false,
                    borderDash: [5,5],
                    tension: 0.1,
                    pointRadius: 0
                });
            }
        });

        // 6) Détruire / recréer le chart principal
        if (depassementsChart) depassementsChart.destroy();
        var ctxDep = document.getElementById('depassementsChart').getContext('2d');
        depassementsChart = new Chart(ctxDep, {
            type: chartType,
            data: {
                labels: filteredLabels, // *** on utilise filteredLabels ***
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(ctx){
                                var lab = ctx.dataset.label || '';
                                var val = ctx.parsed.y;
                                if (val !== null) {
                                    if (ctx.dataset.borderDash && ctx.dataset.borderDash.length > 0) {
                                        return lab + ': ' + val; // seuil
                                    } else {
                                        return lab + ': ' + val + ' µg/m³'; // mesures
                                    }
                                }
                                return lab;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Concentration (µg/m³)' }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                }
            }
        });

        // 7) Vérifier dépassement
        var depExiste = false;
        for (var i = 0; i < measurementValues.length; i++){
            var mes = measurementValues[i];
            if (mes === null) continue;
            for (var j = 0; j < seuilsSelected.length; j++){
                var s = seuilsData[selectedPolluant][seuilsSelected[j]].valeur;
                if (mes > s) {
                    depExiste = true;
                    break;
                }
            }
            if (depExiste) break;
        }
        var depText = document.getElementById('depassements-text');
        if (depExiste) {
            depText.innerHTML = '<div class="alert alert-danger">Certaines mesures dépassent les seuils sélectionnés.</div>';
        } else {
            depText.innerHTML = '<div class="alert alert-success">Aucune mesure ne dépasse les seuils.</div>';
        }

        // 8) *** NOUVEAU *** second chart “depassementsBarChart” pour comparer
        if (depassementsBarChart) depassementsBarChart.destroy();
        var ctxBar = document.getElementById('depassementsBarChart');
        if (ctxBar) {
            // Calculer la moyenne mesurée
            var validVals = measurementValues.filter(v => v !== null);
            var avgMesure = (validVals.length > 0) ? validVals.reduce((a,b) => a + b, 0) / validVals.length : 0;
            // Récupérer le max des seuils cochés
            var maxSeuil = 0;
            seuilsSelected.forEach(function(tn){
                var sVal = parseFloat(seuilsData[selectedPolluant][tn].valeur);
                if (sVal > maxSeuil) maxSeuil = sVal;
            });
            depassementsBarChart = new Chart(ctxBar.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Mesure Moy.', 'Seuil Max'],
                    datasets: [{
                        label: 'Comparaison (µg/m³)',
                        data: [avgMesure, maxSeuil],
                        backgroundColor: ['rgba(54,162,235,0.7)', 'rgba(255,99,132,0.7)']
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    }


    // ==========================================================
    // 2) GESTION DES ÉVÉNEMENTS
    // ==========================================================
    document.addEventListener('DOMContentLoaded', function(){

        // *** A) Dépassements : polluant-select + checkboxes + mois
        var polluantSelect = document.getElementById('polluant-select');
        var seuilTypeContainer = document.getElementById('seuil-type-container');
        var seuilTypesCheckboxes = document.getElementById('seuil-types-checkboxes');
        var depassementsMonthSelect = document.getElementById('depassements-month-select'); // *** NOUVEAU ***

        // *** NOUVEAU *** fonction pour tout recalculer
        function applyDepassementsFilter(){
            var sp = polluantSelect ? polluantSelect.value : '';
            var selMonth = depassementsMonthSelect ? depassementsMonthSelect.value : '';
            if (sp) {
                var types = seuilsData[sp] ? Object.keys(seuilsData[sp]) : [];
                // On lit les checkboxes cochées
                var selSeuils = Array.from(document.querySelectorAll('.seuil-type-checkbox:checked'))
                    .map(function(x){ return x.value; });
                initDepassementsChart(sp, selSeuils, selMonth);
            } else {
                // Rien sélectionné => on détruit
                if (depassementsChart) depassementsChart.destroy();
                if (depassementsBarChart) depassementsBarChart.destroy();
                document.getElementById('depassements-text').innerHTML = '';
            }
        }

        if (polluantSelect) {
            polluantSelect.addEventListener('change', function(){
                // Recréer les checkboxes
                var sp = this.value;
                if (sp) {
                    var types = seuilsData[sp] ? Object.keys(seuilsData[sp]) : [];
                    seuilTypesCheckboxes.innerHTML = '';
                    types.forEach(function(tn){
                        var cId = 'seuil-' + sp + '-' + tn;
                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.className = 'form-check-input seuil-type-checkbox';
                        cb.id = cId;
                        cb.value = tn;
                        cb.checked = true;

                        var lab = document.createElement('label');
                        lab.className = 'form-check-label mr-3';
                        lab.htmlFor = cId;

                        var sVal = seuilsData[sp][tn].valeur;
                        var sUni = seuilsData[sp][tn].unite;
                        var sOrg = seuilsData[sp][tn].origine;
                        lab.textContent = tn + ' (' + sOrg + ') (' + sVal + ' ' + sUni + ')';

                        var d = document.createElement('div');
                        d.className = 'form-check form-check-inline';
                        d.appendChild(cb);
                        d.appendChild(lab);
                        seuilTypesCheckboxes.appendChild(d);
                    });

                    seuilTypeContainer.style.display = 'block';
                    applyDepassementsFilter();

                    var allCbs = document.querySelectorAll('.seuil-type-checkbox');
                    allCbs.forEach(function(box){
                        box.addEventListener('change', function(){
                            applyDepassementsFilter();
                        });
                    });
                } else {
                    seuilTypeContainer.style.display = 'none';
                    if (depassementsChart) depassementsChart.destroy();
                    if (depassementsBarChart) depassementsBarChart.destroy();
                    document.getElementById('depassements-text').innerHTML = '';
                }
            });
        }
        if (depassementsMonthSelect){
            depassementsMonthSelect.addEventListener('change', function(){
                applyDepassementsFilter();
            });
        }

        // *** B) Filtrer colonnes -> déjà existant
        var arrSelect = document.getElementById('arrondissement-select');
        if (arrSelect){
            arrSelect.addEventListener('change', function(){
                // ...
            });
        }

        // *** C) Hash -> inchangé
        var hash = window.location.hash;
        if (hash){
            setTimeout(function(){
                var el = document.querySelector(hash);
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            }, 500);
        }

        // *** D) Form favoris -> inchangé
        var favoriteForm = document.getElementById('favorite-form');
        if (favoriteForm){
            favoriteForm.addEventListener('submit', function(e){
                e.preventDefault();
                var formData = new FormData(favoriteForm);
                formData.append('ajax','1');
                var action = favoriteForm.querySelector('.favorite-icon').getAttribute('data-action');
                formData.set('favorite_action', action);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        var icon = favoriteForm.querySelector('.favorite-icon i');
                        var msgC = document.getElementById('message-container');
                        if(data.success){
                            if(data.action === 'added'){
                                icon.classList.remove('far');
                                icon.classList.add('fas');
                                favoriteForm.querySelector('.favorite-icon').setAttribute('data-action','remove_favorite');
                            } else if(data.action === 'removed'){
                                icon.classList.remove('fas');
                                icon.classList.add('far');
                                favoriteForm.querySelector('.favorite-icon').setAttribute('data-action','add_favorite');
                            }
                            msgC.innerHTML = '<div class="success-message">' + data.message + '</div>';
                            setTimeout(function(){ msgC.innerHTML = ''; }, 3000);
                        } else {
                            msgC.innerHTML = '<div class="error-message">' + data.message + '</div>';
                            setTimeout(function(){ msgC.innerHTML = ''; }, 5000);
                        }
                    })
                    .catch(err => { console.error('Erreur AJAX favoris:', err); });
            });
        }

        // *** E) Suggestions city2 + Comparer
        if (typeof initializeSuggestions === 'function'){
            initializeSuggestions('city2', 'suggestions-list', 'city2_hidden', null);
        }
        var compareBtn = document.getElementById('compareCitiesButton');
        if (compareBtn){
            compareBtn.addEventListener('click', function(){
                var c1 = document.getElementById('city1').value.trim();
                var c2 = document.getElementById('city2').value.trim();
                if(!c1 || !c2){
                    alert("Veuillez entrer deux villes valides.");
                    return;
                }
                Promise.all([
                    fetch('details.php?ajax=1&action=getpolluants&ville=' + encodeURIComponent(c1)).then(r => r.json()),
                    fetch('details.php?ajax=1&action=getpolluants&ville=' + encodeURIComponent(c2)).then(r => r.json())
                ])
                    .then(function([d1, d2]){
                        if(d1.error){
                            alert("Erreur pour la ville " + c1 + ": " + d1.error);
                            return;
                        }
                        if(d2.error){
                            alert("Erreur pour la ville " + c2 + ": " + d2.error);
                            return;
                        }
                        var pollSet = new Set([...Object.keys(d1), ...Object.keys(d2)]);
                        var pollLabels = Array.from(pollSet);

                        var ds1 = {
                            label: c1,
                            data: pollLabels.map(p => d1[p] !== undefined ? parseFloat(d1[p]) : 0),
                            backgroundColor: 'rgba(255,99,132,0.5)',
                            borderColor: 'rgba(255,99,132,1)',
                            borderWidth: 1
                        };
                        var ds2 = {
                            label: c2,
                            data: pollLabels.map(p => d2[p] !== undefined ? parseFloat(d2[p]) : 0),
                            backgroundColor: 'rgba(54,162,235,0.5)',
                            borderColor: 'rgba(54,162,235,1)',
                            borderWidth: 1
                        };
                        if (window.cityComparisonChart && typeof window.cityComparisonChart.destroy === 'function'){
                            window.cityComparisonChart.destroy();
                        }
                        var ctxCmp = document.getElementById('cityComparisonChart').getContext('2d');
                        window.cityComparisonChart = new Chart(ctxCmp, {
                            type: 'bar',
                            data: {
                                labels: pollLabels,
                                datasets: [ds1, ds2]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: { display: true, text: 'Concentration (µg/m³)' }
                                    }
                                },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function(ctx){
                                                return ctx.dataset.label + ': ' + ctx.parsed.y + ' µg/m³';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    })
                    .catch(function(err){
                        console.error('Erreur comparaison:', err);
                        alert("Erreur lors de la récupération des données.");
                    });
            });
        }

        // *** F) Filtrage Prédictions ***
        var polluantSelectPred = document.getElementById('prediction-polluant-select');
        var monthSelect        = document.getElementById('prediction-month-select');
        var tableRows          = document.querySelectorAll('#predictions-table tbody tr');
        var ctx1               = document.getElementById('predictionChart1')?.getContext('2d');
        var ctx2               = document.getElementById('predictionChart2')?.getContext('2d');
        var chart1 = null;
        var chart2 = null;

        function applyPredictionsFilter(){
            var selPoll  = polluantSelectPred ? polluantSelectPred.value : '';
            var selMonth = monthSelect ? monthSelect.value : '';

            // 1) Filtrer le tableau #predictions-table
            tableRows.forEach(function(row){
                var rowPoll = row.getAttribute('data-polluant') || '';
                var displayRow = true;
                if(selPoll && selPoll !== rowPoll){
                    displayRow = false;
                }
                row.style.display = (displayRow) ? '' : 'none';
            });

            // 2) Mettre à jour les graphiques
            updatePredictionCharts(selPoll, selMonth);
        }

        function updatePredictionCharts(selectedPolluant, selectedMonth){
            if(chart1) chart1.destroy();
            if(chart2) chart2.destroy();

            // *** CHART 1 : line (évolution)
            var allDatesSet = new Set();
            for(var p in predictionsData){
                predictionsData[p].forEach(function(d){
                    var m = d.date.substring(0,7);
                    if(!selectedMonth || m === selectedMonth){
                        allDatesSet.add(d.date);
                    }
                });
            }
            var allDatesArr = Array.from(allDatesSet).sort();
            var lineDatasets = [];

            if(!selectedPolluant){
                // une courbe par polluant
                for(var p in predictionsData){
                    var arrF = predictionsData[p].filter(function(x){
                        return (!selectedMonth || x.date.substring(0,7) === selectedMonth);
                    });
                    var dateMap = {};
                    arrF.forEach(function(x){
                        dateMap[x.date] = x.value;
                    });
                    var vals = allDatesArr.map(function(dt){
                        return (dateMap[dt] !== undefined) ? dateMap[dt] : null;
                    });
                    lineDatasets.push({
                        label: p,
                        data: vals,
                        borderColor: randomColor(),
                        backgroundColor: 'rgba(0,0,0,0)',
                        tension: 0.1
                    });
                }
            } else {
                // un seul polluant
                var arrSel = predictionsData[selectedPolluant] || [];
                if(selectedMonth){
                    arrSel = arrSel.filter(function(x){
                        return x.date.substring(0,7) === selectedMonth;
                    });
                }
                arrSel.sort(function(a,b){
                    return a.date.localeCompare(b.date);
                });
                var lineLabels = arrSel.map(function(x){ return x.date; });
                var lineVals   = arrSel.map(function(x){ return x.value; });
                lineDatasets.push({
                    label: selectedPolluant,
                    data: lineVals,
                    borderColor: 'rgba(54,162,235,1)',
                    backgroundColor: 'rgba(54,162,235,0.2)',
                    tension: 0.1
                });
                allDatesArr = lineLabels;
            }
            if(ctx1){
                chart1 = new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: allDatesArr,
                        datasets: lineDatasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: 'Évolution dans le temps' },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        scales: {
                            x: { title: { display: true, text: 'Date' } },
                            y: { title: { display: true, text: 'µg/m³' }, beginAtZero: false }
                        }
                    }
                });
            }

            // *** CHART 2 : bar (comparaison polluants)
            var barLabels = [];
            var barValues = [];
            for(var p2 in predictionsData){
                var arr2 = predictionsData[p2].filter(function(x){
                    return (!selectedMonth || x.date.substring(0,7) === selectedMonth);
                });
                if(arr2.length > 0){
                    var s = 0;
                    arr2.forEach(function(o){ s += o.value; });
                    var avg = s / arr2.length;
                    barLabels.push(p2);
                    barValues.push(avg);
                }
            }
            if(ctx2){
                chart2 = new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: barLabels,
                        datasets: [{
                            label: 'Concentration moyenne (µg/m³)',
                            data: barValues,
                            backgroundColor: 'rgba(255,99,132,0.6)',
                            borderColor: 'rgba(255,99,132,1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: 'Comparaison des polluants' },
                            legend: { display: false }
                        },
                        scales: {
                            x: { title: { display: true, text: 'Polluant' } },
                            y: { beginAtZero: true, title: { display: true, text: 'µg/m³' } }
                        }
                    }
                });
            }
        }

        if(polluantSelectPred || monthSelect){
            applyPredictionsFilter();
            if(polluantSelectPred){
                polluantSelectPred.addEventListener('change', applyPredictionsFilter);
            }
            if(monthSelect){
                monthSelect.addEventListener('change', applyPredictionsFilter);
            }
        }


        // ==========================================================
        // G) Graphiques "Polluants" + "Concentrations" (existant)
        // ==========================================================
        var ctxPolluants      = document.getElementById('polluantsChart')?.getContext('2d');
        var ctxConcentrations = document.getElementById('concentrationsChart')?.getContext('2d');

        // bar chart polluants
        if(ctxPolluants && city_pollution_averages){
            var polluantsLabels = [];
            var polluantsChartData = [];
            for(var sym in city_pollution_averages){
                if(city_pollution_averages.hasOwnProperty(sym)){
                    polluantsLabels.push(sym);
                    polluantsChartData.push(parseFloat(city_pollution_averages[sym]).toFixed(2));
                }
            }
            new Chart(ctxPolluants, {
                type: 'bar',
                data: {
                    labels: polluantsLabels,
                    datasets: [{
                        label: 'Concentration Moyenne (µg/m³)',
                        data: polluantsChartData,
                        backgroundColor: 'rgba(107,142,35,0.7)',
                        borderColor: 'rgba(255,255,255,1)',
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Concentration (µg/m³)' }
                        }
                    }
                }
            });
        }

        // line chart concentrations
        if(ctxConcentrations && polluantsData){
            var datasets = [];
            for(var pollKey in polluantsData){
                if(polluantsData.hasOwnProperty(pollKey)){
                    var dataObj = polluantsData[pollKey].values;
                    var arr = measurementLabels.map(function(lbl){
                        return (dataObj[lbl] !== undefined) ? parseFloat(dataObj[lbl]) : null;
                    });
                    datasets.push({
                        label: pollKey,
                        data: arr,
                        borderColor: randomColor(),
                        backgroundColor: 'rgba(0,0,0,0)',
                        tension: 0.1
                    });
                }
            }
            new Chart(ctxConcentrations, {
                type: 'line',
                data: {
                    labels: measurementLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Évolution des concentrations de polluants' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Date et emplacement' } },
                        y: { title: { display: true, text: 'Concentration (µg/m³)' }, beginAtZero: true }
                    }
                }
            });
        }

        // ==========================================================
        // 3) FILTRAGE POUR L'ONGLET POLLUANTS (table polluants-table)
        // ==========================================================
        var polluantsMonthSelect   = document.getElementById('polluants-month-select');
        var polluantsPolluantSelect= document.getElementById('polluants-polluant-select');
        var polluantsTable         = document.getElementById('polluants-table');
        var originalPolluantsTableHTML = polluantsTable ? polluantsTable.innerHTML : '';

        // Génère un tableau mensuel (Janv. 2023 à Janv. 2025) pour chaque polluant
        // => On crée un en-tête (mois) et un <tr> par polluant, colonnes = moyennes
        function buildPolluantsTableAll() {
            // On suppose qu'on veut 2023-01 ... 2024-12, + 2025-01
            // => 2023-01 à 2025-01 inclus
            var monthsArr = [];
            var start = new Date('2023-01-01');
            var end   = new Date('2025-02-01'); // exclure 2025-02
            for(var d = new Date(start); d < end; d.setMonth(d.getMonth()+1)) {
                var y = d.getFullYear();
                var m = (d.getMonth()+1);
                if(m < 10) m = '0' + m;
                var key = y + '-' + m;
                monthsArr.push(key);
            }

            // En-tête
            var theadHTML = '<tr><th>Polluant</th>';
            monthsArr.forEach(function(mm){
                theadHTML += '<th>' + mm + '</th>';
            });
            theadHTML += '</tr>';

            // Corps
            var tbodyHTML = '';
            // polluantsData => { NO: {values: { "Janv 2023 - Inconnu": 12, ...}}, ...}
            var polluantsList = Object.keys(polluantsData);
            polluantsList.forEach(function(poll){
                tbodyHTML += '<tr data-polluant="'+poll+'"><td>'+poll+'</td>';
                monthsArr.forEach(function(mm){
                    // On va chercher si on a des mesures ce mois
                    // => Pour simplifier, on met "?"
                    tbodyHTML += '<td>?</td>';
                });
                tbodyHTML += '</tr>';
            });

            polluantsTable.innerHTML = '<thead>' + theadHTML + '</thead><tbody>' + tbodyHTML + '</tbody>';
        }

        function applyPolluantsFilter() {
            if(!polluantsTable) return;
            var selPoll  = polluantsPolluantSelect ? polluantsPolluantSelect.value : '';
            var selMonth = polluantsMonthSelect ? polluantsMonthSelect.value : '';

            // 1) Si aucun filtre => tout afficher
            if(!selPoll && !selMonth) {
                // On (ré)construit un tableau global
                buildPolluantsTableAll();
                return;
            }

            // 2) Sinon, on construit un tableau plus restreint
            var theadHTML = '<tr><th>Polluant</th>';
            if(selMonth) {
                theadHTML += '<th>' + selMonth + '</th>';
            } else {
                theadHTML += '<th>?</th>';
            }
            theadHTML += '</tr>';

            var tbodyHTML = '';
            var polluantsList = selPoll ? [selPoll] : Object.keys(polluantsData);
            polluantsList.forEach(function(p){
                tbodyHTML += '<tr data-polluant="'+p+'"><td>'+p+'</td>';
                if(selMonth) {
                    tbodyHTML += '<td>?</td>';
                } else {
                    tbodyHTML += '<td>?</td>';
                }
                tbodyHTML += '</tr>';
            });

            polluantsTable.innerHTML = '<thead>'+theadHTML+'</thead><tbody>'+tbodyHTML+'</tbody>';

            // On pourrait ici appeler une fonction updatePolluantsCharts(selPoll, selMonth)
            // pour mettre à jour polluantsLineChart / polluantsBarChart
            updatePolluantsCharts(selPoll, selMonth);
        }

        // Met à jour les 2 graphiques de l’onglet Polluants
        function updatePolluantsCharts(selectedPolluant, selectedMonth){
            // Détruire si existants
            if(polluantsLineChart) polluantsLineChart.destroy();
            if(polluantsBarChart)  polluantsBarChart.destroy();

            var ctxLine = document.getElementById('polluantsLineChart')?.getContext('2d');
            var ctxBar  = document.getElementById('polluantsBarChart')?.getContext('2d');
            if(!ctxLine || !ctxBar) return;

            // 1) Graphique line
            var lineLabels   = measurementLabels.slice(); // copie
            var lineDatasets = [];
            var pollList     = selectedPolluant ? [selectedPolluant] : Object.keys(polluantsData);
            pollList.forEach(function(p){
                var arr = measurementLabels.map(function(lbl){
                    var val = polluantsData[p].values[lbl];
                    return (val !== undefined) ? parseFloat(val) : null;
                });
                lineDatasets.push({
                    label: p,
                    data: arr,
                    borderColor: randomColor(),
                    backgroundColor: 'rgba(0,0,0,0)',
                    tension: 0.1
                });
            });
            polluantsLineChart = new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: lineLabels,
                    datasets: lineDatasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Évolution polluants (filtre: ' + selectedPolluant + ')' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Date/Label' } },
                        y: { title: { display: true, text: 'µg/m³' }, beginAtZero: false }
                    }
                }
            });

            // 2) Graphique bar
            var barLabels = [];
            var barValues = [];
            pollList.forEach(function(p){
                var sum = 0, count = 0;
                for(var lbl in polluantsData[p].values){
                    var val = polluantsData[p].values[lbl];
                    if(val !== undefined && val !== null){
                        sum += parseFloat(val);
                        count++;
                    }
                }
                var avg = (count>0)?(sum/count):0;
                barLabels.push(p);
                barValues.push(avg);
            });
            polluantsBarChart = new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [{
                        label: 'Moyenne sur tout l’historique',
                        data: barValues,
                        backgroundColor: 'rgba(255,99,132,0.6)',
                        borderColor: 'rgba(255,99,132,1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Comparaison polluants (filtre: ' + selectedPolluant + ')' },
                        legend: { display: false }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Polluant' } },
                        y: { beginAtZero: true, title: { display: true, text: 'µg/m³' } }
                    }
                }
            });
        }

        // On initialise l’affichage
        if(polluantsTable){
            if(polluantsPolluantSelect){
                polluantsPolluantSelect.addEventListener('change', applyPolluantsFilter);
            }
            if(polluantsMonthSelect){
                polluantsMonthSelect.addEventListener('change', applyPolluantsFilter);
            }
            applyPolluantsFilter();
        }

        // *** FIN DU DOMContentLoaded ***
    });
}
