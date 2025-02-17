/**
 * details.js :
 *
 * Affiche les tableaux, graphiques, etc.. de la page details.php
 */
/**
 * Vérifie si la ville a été trouvée dans la base de données.
 * Si cityNotFound est true, on n'exécute pas les parties du code concernant les graphiques.
 */
if (!cityNotFound) {
    // Log des données reçues pour débogage
    console.log('pollutantsData:', pollutantsData);
    console.log('seuilsData:', seuilsData);
    console.log('measurementLabels:', measurementLabels);

    /**
     * Création du graphique des polluants (Concentrations moyennes)
     * Ce graphique montre les concentrations moyennes (µg/m³) de chaque polluant pour la ville sélectionnée.
     */

        // Tableau des labels (polluants) et données (concentrations moyennes)
    var pollutantsLabels = [];
    var pollutantsChartData = [];

    // Parcours de l'objet city_pollution_averages pour récupérer les moyennes par polluant
    for (var pollutant_symbol in city_pollution_averages) {
        if (city_pollution_averages.hasOwnProperty(pollutant_symbol)) {
            // Ajouter le label du polluant (ex: "CO", "NO2")
            pollutantsLabels.push(pollutant_symbol);
            // Ajouter la valeur moyenne arrondie à 2 décimales
            pollutantsChartData.push(parseFloat(city_pollution_averages[pollutant_symbol]).toFixed(2));
        }
    }

    // Initialisation du contexte du graphique pour les polluants
    var ctxPolluants = document.getElementById('pollutantsChart').getContext('2d');

    // Création du graphique des polluants (type: bar)
    var pollutantsChart = new Chart(ctxPolluants, {
        type: 'bar',
        data: {
            labels: pollutantsLabels,
            datasets: [{
                label: 'Concentration Moyenne (µg/m³)',
                data: pollutantsChartData,
                backgroundColor: 'rgba(107,142,35, 0.7)',  // Couleur de remplissage
                borderColor: 'rgba(255,255,255, 1)',        // Couleur des bordures
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Concentration (µg/m³)'
                    }
                }
            }
        }
    });

    /**
     * Variable globale pour le graphique des dépassements
     * Ce graphique sera mis à jour dynamiquement en fonction du polluant et des seuils sélectionnés.
     */
    var depassementsChart;

    /**
     * Fonction initDepassementsChart
     * @param {string} selectedPolluant - Le polluant sélectionné par l'utilisateur pour le graphique.
     * @param {Array} selectedSeuilTypes - Les types de seuil sélectionnés (ex: "Valeur limite", "Seuil d'alerte").
     *
     * Cette fonction crée ou met à jour le graphique des dépassements.
     * Elle affiche les valeurs mesurées du polluant ainsi que les lignes représentant les seuils réglementaires choisis.
     */
    function initDepassementsChart(selectedPolluant, selectedSeuilTypes) {
        // Vérifier la présence de seuils pour le polluant choisi
        if (!seuilsData[selectedPolluant] || Object.keys(seuilsData[selectedPolluant]).length === 0) {
            console.warn('Aucun seuil disponible pour le polluant sélectionné:', selectedPolluant);
            if (depassementsChart) {
                depassementsChart.destroy();
            }
            document.getElementById('depassements-text').innerHTML = '';
            return;
        }

        // Récupérer les mesures du polluant sélectionné
        var measurements = pollutantsData[selectedPolluant] ? pollutantsData[selectedPolluant]['values'] : {};
        var measurementValues = [];
        var measurementLabelsLocal = measurementLabels;

        // Convertir les mesures en tableau de valeurs numériques (ou null si non disponible)
        measurementLabelsLocal.forEach(function(identifier) {
            var value = measurements[identifier] !== undefined ? parseFloat(measurements[identifier]) : null;
            measurementValues.push(value);
        });

        // Déterminer le type de graphique (bar ou line) en fonction du nombre de mesures non nulles
        var nonNullMeasurements = measurementValues.filter(function(value) {
            return value !== null;
        }).length;
        var chartType = nonNullMeasurements === 1 ? 'bar' : 'line';

        // Vérifier que des seuils sont bien sélectionnés
        var seuilsSelected = selectedSeuilTypes || [];
        if (seuilsSelected.length === 0) {
            console.warn('Aucun seuil sélectionné pour le polluant:', selectedPolluant);
            if (depassementsChart) {
                depassementsChart.destroy();
            }
            document.getElementById('depassements-text').innerHTML = '';
            return;
        }

        // Préparer le dataset des mesures
        var datasets = [{
            label: 'Mesures (µg/m³)',
            data: measurementValues,
            borderColor: 'rgba(54, 162, 235, 1)',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            fill: chartType === 'line' ? false : true,
            tension: 0.1,
            pointRadius: 3
        }];

        // Ajouter les lignes des seuils sélectionnés
        seuilsSelected.forEach(function(typeNorme) {
            if (seuilsData[selectedPolluant] && seuilsData[selectedPolluant][typeNorme]) {
                var seuilValue = seuilsData[selectedPolluant][typeNorme]['valeur'];
                var seuilUnite = seuilsData[selectedPolluant][typeNorme]['unite'];
                var seuilOrigine = seuilsData[selectedPolluant][typeNorme]['origine'];

                datasets.push({
                    label: typeNorme + ' (' + seuilOrigine + ') (' + seuilValue + ' ' + seuilUnite + ')',
                    data: Array(measurementLabelsLocal.length).fill(seuilValue), // Remplir tout le tableau avec la valeur du seuil
                    borderColor: getColorForTypeNorme(typeNorme),
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: false,
                    borderDash: [5, 5],
                    tension: 0.1,
                    pointRadius: 0
                });
            }
        });

        // Détruire l'ancien graphique avant de le recréer, pour éviter les doublons
        if (depassementsChart) {
            depassementsChart.destroy();
        }

        // Initialisation du contexte du graphique des dépassements
        var ctxDepassements = document.getElementById('depassementsChart').getContext('2d');
        depassementsChart = new Chart(ctxDepassements, {
            type: chartType,
            data: {
                labels: measurementLabelsLocal,
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
                            // Personnalisation de l'affichage dans le tooltip
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (context.parsed.y !== null) {
                                    // Si c'est un seuil (ligne pointillée)
                                    if (context.dataset.borderDash && context.dataset.borderDash.length > 0) {
                                        return label + ': ' + context.parsed.y;
                                    } else {
                                        // Si c'est une mesure
                                        return label + ': ' + context.parsed.y + ' µg/m³';
                                    }
                                }
                                return label;
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
                        title: {
                            display: true,
                            text: 'Concentration (µg/m³)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });

        // Vérification de la présence de dépassements des seuils
        var depassementExiste = false;
        if (selectedSeuilTypes && selectedSeuilTypes.length > 0) {
            for (var i = 0; i < measurementValues.length; i++) {
                var mesure = measurementValues[i];
                if (mesure === null) continue;
                for (var j = 0; j < selectedSeuilTypes.length; j++) {
                    var seuilValue = seuilsData[selectedPolluant][selectedSeuilTypes[j]]['valeur'];
                    if (mesure > seuilValue) {
                        depassementExiste = true;
                        break;
                    }
                }
                if (depassementExiste) break;
            }
        }

        // Mise à jour du texte indiquant si des dépassements existent
        var depassementsText = document.getElementById('depassements-text');
        if (depassementExiste) {
            depassementsText.innerHTML = '<div class="alert alert-danger" role="alert">Attention ! Certaines mesures dépassent les seuils sélectionnés.</div>';
        } else {
            depassementsText.innerHTML = '<div class="alert alert-success" role="alert">Bonne nouvelle ! Aucune mesure ne dépasse les seuils sélectionnés.</div>';
        }
    }

    /**
     * Fonction getColorForTypeNorme
     * @param {string} typeNorme - Le type de norme (ex: "Valeur limite", "Seuil d'alerte").
     * @returns {string} Couleur (RGBA) associée au type de norme.
     *
     * Cette fonction assigne des couleurs spécifiques aux différents types de normes
     * afin de les distinguer facilement sur le graphique.
     */
    function getColorForTypeNorme(typeNorme) {
        var colors = {
            'Objectif de qualité': 'rgba(75, 192, 192, 1)',
            'Valeur limite pour la protection de la santé humaine': 'rgba(255, 99, 132, 1)',
            'Seuil d\'information et de recommandation': 'rgba(255, 206, 86, 1)',
            'Seuil d\'alerte': 'rgba(153, 102, 255, 1)'
        };
        return colors[typeNorme] || 'rgba(201, 203, 207, 1)';
    }

    /**
     * À la fin du chargement du document, on met en place l'écoute des événements
     * pour la sélection des polluants et des seuils pour le graphique des dépassements.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var polluantSelect = document.getElementById('polluant-select');
        var seuilTypeContainer = document.getElementById('seuil-type-container');
        var seuilTypesCheckboxes = document.getElementById('seuil-types-checkboxes');

        // Si l'élément polluant-select existe, on gère le changement de polluant
        if (polluantSelect) {
            polluantSelect.addEventListener('change', function () {
                var selectedPolluant = this.value;
                if (selectedPolluant) {
                    // Récupérer la liste des types de normes disponibles pour ce polluant
                    var types = seuilsData[selectedPolluant] ? Object.keys(seuilsData[selectedPolluant]) : [];
                    seuilTypesCheckboxes.innerHTML = '';

                    // Créer des checkboxes pour chaque type de norme
                    types.forEach(function (typeNorme) {
                        var checkboxId = 'seuil-' + selectedPolluant + '-' + typeNorme;
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'form-check-input seuil-type-checkbox';
                        checkbox.id = checkboxId;
                        checkbox.value = typeNorme;
                        checkbox.checked = true; // Par défaut, toutes les normes sont cochées

                        var label = document.createElement('label');
                        label.className = 'form-check-label mr-3';
                        label.htmlFor = checkboxId;
                        var origine = seuilsData[selectedPolluant][typeNorme]['origine'];
                        label.textContent = typeNorme + ' (' + origine + ') (' + seuilsData[selectedPolluant][typeNorme]['valeur'] + ' ' + seuilsData[selectedPolluant][typeNorme]['unite'] + ')';

                        var div = document.createElement('div');
                        div.className = 'form-check form-check-inline';
                        div.appendChild(checkbox);
                        div.appendChild(label);
                        seuilTypesCheckboxes.appendChild(div);
                    });

                    // Afficher le conteneur de types de seuils
                    seuilTypeContainer.style.display = 'block';

                    // Initialiser le graphique avec les seuils actuellement cochés
                    var selectedSeuilTypes = Array.from(document.querySelectorAll('.seuil-type-checkbox:checked')).map(function (cb) { return cb.value; });
                    initDepassementsChart(selectedPolluant, selectedSeuilTypes);

                    // Ajouter un écouteur sur le changement de chaque checkbox pour mettre à jour le graphique
                    var seuilTypeCheckboxElements = document.querySelectorAll('.seuil-type-checkbox');
                    seuilTypeCheckboxElements.forEach(function (checkbox) {
                        checkbox.addEventListener('change', function () {
                            var updatedSeuilTypes = Array.from(document.querySelectorAll('.seuil-type-checkbox:checked')).map(function (cb) { return cb.value; });
                            initDepassementsChart(selectedPolluant, updatedSeuilTypes);
                        });
                    });

                } else {
                    // Aucun polluant sélectionné, masquer les seuils et détruire le graphique
                    seuilTypeContainer.style.display = 'none';
                    if (depassementsChart) {
                        depassementsChart.destroy();
                    }
                    document.getElementById('depassements-text').innerHTML = '';
                }
            });
        }
    });

}

// Filtrer les colonnes du tableau par arrondissement (uniquement pour Paris, Lyon, Marseille)
document.getElementById('arrondissement-select')?.addEventListener('change', function () {
    var selectedArrondissement = this.value;
    var columns = document.querySelectorAll('#details-table th, #details-table td');

    // Parcours de toutes les colonnes du tableau
    columns.forEach(function (column) {
        var location = column.getAttribute('data-location');
        // Afficher toujours la première colonne (celle des polluants)
        if (column.cellIndex === 0) {
            column.style.display = '';
        } else if (selectedArrondissement === 'all' || location === selectedArrondissement) {
            column.style.display = '';  // Afficher les colonnes correspondant à l'arrondissement choisi
        } else {
            column.style.display = 'none'; // Masquer les autres
        }
    });
});

// Gérer le hash dans l'URL pour faire défiler la page jusqu'à un élément spécifique
document.addEventListener("DOMContentLoaded", function () {
    var hash = window.location.hash;
    if (hash) {
        setTimeout(function () {
            var element = document.querySelector(hash);
            if (element) {
                element.scrollIntoView({behavior: 'smooth'});
            }
        }, 500);
    }
});

// Gestion de l'ajout/retrait des villes favorites via AJAX
// Permet à l'utilisateur connecté d'ajouter ou retirer une ville de ses favoris sans recharger la page
document.addEventListener('DOMContentLoaded', function () {
    var favoriteForm = document.getElementById('favorite-form');
    if (favoriteForm) {
        favoriteForm.addEventListener('submit', function (e) {
            e.preventDefault();  // Empêche le rechargement de la page
            var formData = new FormData(favoriteForm);
            formData.append('ajax', '1'); // Indiquer que c'est une requête AJAX
            var action = favoriteForm.querySelector('.favorite-icon').getAttribute('data-action');
            formData.set('favorite_action', action);

            // Envoi de la requête AJAX
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    var icon = favoriteForm.querySelector('.favorite-icon i');
                    var messageContainer = document.getElementById('message-container');
                    if (data.success) {
                        // Mise à jour de l'icône selon l'action réalisée (ajout/suppression)
                        if (data.action === 'added') {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            favoriteForm.querySelector('.favorite-icon').setAttribute('data-action', 'remove_favorite');
                        } else if (data.action === 'removed') {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            favoriteForm.querySelector('.favorite-icon').setAttribute('data-action', 'add_favorite');
                        }
                        // Affichage d'un message de succès
                        messageContainer.innerHTML = '<div class="success-message">' + data.message + '</div>';
                        setTimeout(function () {
                            messageContainer.innerHTML = '';
                        }, 3000);
                    } else {
                        // Affichage d'un message d'erreur
                        messageContainer.innerHTML = '<div class="error-message">' + data.message + '</div>';
                        setTimeout(function () {
                            messageContainer.innerHTML = '';
                        }, 5000);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
        });
    }
});
document.addEventListener('DOMContentLoaded', function() {
    // Active les suggestions uniquement pour city2
    if (typeof initializeSuggestions === "function") {
        initializeSuggestions('city2', 'suggestions-city2', 'city2_hidden', null);
    }

    // Gestion du clic sur le bouton "Comparer"
    document.getElementById('compareCitiesButton').addEventListener('click', function() {
        var city1 = document.getElementById('city1').value.trim();
        var city2 = document.getElementById('city2').value.trim();

        if (city1 === "" || city2 === "") {
            alert("Veuillez entrer deux villes valides.");
            return;
        }

        // Appel AJAX pour récupérer les données des deux villes
        Promise.all([
            fetch('details.php?ajax=1&action=getPollutants&ville=' + encodeURIComponent(city1))
                .then(response => response.json()),
            fetch('details.php?ajax=1&action=getPollutants&ville=' + encodeURIComponent(city2))
                .then(response => response.json())
        ])
            .then(function(results) {
                var data1 = results[0];
                var data2 = results[1];

                // Vérification des éventuelles erreurs retournées
                if (data1.error) {
                    alert("Erreur pour la ville " + city1 + ": " + data1.error);
                    return;
                }
                if (data2.error) {
                    alert("Erreur pour la ville " + city2 + ": " + data2.error);
                    return;
                }

                // Créer l'union des clés (polluants) des deux jeux de données
                var pollutantsSet = new Set([...Object.keys(data1), ...Object.keys(data2)]);
                var pollutantLabels = Array.from(pollutantsSet);

                // Préparer le dataset pour la première ville
                var dataset1 = {
                    label: city1,
                    data: pollutantLabels.map(function(p) {
                        return data1[p] !== undefined ? parseFloat(data1[p]) : 0;
                    }),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                };

                // Préparer le dataset pour la deuxième ville
                var dataset2 = {
                    label: city2,
                    data: pollutantLabels.map(function(p) {
                        return data2[p] !== undefined ? parseFloat(data2[p]) : 0;
                    }),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                };

                // Vérifier si un graphique existe déjà, et le détruire si c'est bien un objet Chart
                console.log("Avant destroy, cityComparisonChart =", window.cityComparisonChart);
                if (
                    window.cityComparisonChart &&
                    typeof window.cityComparisonChart.destroy === 'function'
                ) {
                    window.cityComparisonChart.destroy();
                }

                // Créer le nouveau graphique avec Chart.js
                var ctx = document.getElementById('cityComparisonChart').getContext('2d');
                window.cityComparisonChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: pollutantLabels,  // les polluants sur l'axe des abscisses
                        datasets: [dataset1, dataset2]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Concentration (µg/m³)'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y + ' µg/m³';
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(function(error) {
                console.error('Erreur lors de la récupération des données :', error);
                alert("Erreur lors de la récupération des données pour les villes.");
            });
    });
});
