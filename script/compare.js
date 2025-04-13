
/**
 * compare.js
 *
 * Ce script gère l’interface de la page "Compare" de PureOxy.
 *
 * Fonctionnalités principales :
 *  - Permet de choisir le type de données à afficher : Historique, Prédiction, Moyennes par habitants ou par superficie.
 *  - Peuplement dynamique du sélecteur de mois en fonction du type sélectionné.
 *  - Ajout de villes individuelles via un champ de recherche avec suggestions.
 *  - Sélection de groupes de villes :
 *      • L’utilisateur choisit un type de groupe (département, région, superficie, population, densité) dans un sélecteur.
 *      • Pour "superficie" et "population", les options (paliers) sont définies en dur.
 *      • Pour "department", "region" et "densite", une requête AJAX récupère la liste distincte via get_group_values.php.
 *      • Au clic sur une option, une requête AJAX appelle get_cities_by_filter.php pour récupérer toutes les villes du groupe,
 *        lesquelles sont ajoutées individuellement à la sélection.
 *  - La liste des villes (individuelles et issues de groupes) est affichée et peut être modifiée (suppression).
 *  - Au clic sur "Comparer", la liste est envoyée via AJAX à get_compare_data.php pour générer un graphique et un tableau.
 *
 * Fichier placé dans le dossier script.
 */

document.addEventListener('DOMContentLoaded', function () {

    let selectedCities = []; // Contiendra les noms de villes (chaque ville ajoutée individuellement)
    const selectedCitiesDiv = document.getElementById('selected-cities');
    const clearCitiesBtn = document.getElementById('clear-cities');
    const compareButton = document.getElementById('compare-button');
    const monthSelect = document.getElementById('month-selection');
    const pollutantFilterSelect = document.getElementById('pollutant-filter');
    const dataTypeRadios = document.getElementsByName('data-type');

    // Zone de recherche pour villes individuelles
    const cityInput = document.getElementById('city-selection');
    const suggestionsList = document.getElementById('suggestions-list-compare');
    const addCityBtn = document.getElementById('add-city');

    // Sélecteur de groupe
    const groupTypeSelect = document.getElementById('group-type-select');
    const availableGroupsDiv = document.getElementById('available-groups');

    let compareChart; // Instance Chart.js

    const historicalMonths = [
        {value: "", display: "Globale (toutes données)"},
        {value: "janv2023", display: "Janv. 2023"},
        {value: "fev2023", display: "Fév. 2023"},
        {value: "mars2023", display: "Mars 2023"},
        {value: "avril2023", display: "Avril 2023"},
        {value: "mai2023", display: "Mai 2023"},
        {value: "juin2023", display: "Juin 2023"},
        {value: "juil2023", display: "Juil. 2023"},
        {value: "aout2023", display: "Août 2023"},
        {value: "sept2023", display: "Sept. 2023"},
        {value: "oct2023", display: "Oct. 2023"},
        {value: "nov2023", display: "Nov. 2023"},
        {value: "dec2023", display: "Déc. 2023"},
        {value: "janv2024", display: "Janv. 2024"},
        {value: "fev2024", display: "Fév. 2024"},
        {value: "mars2024", display: "Mars 2024"},
        {value: "avril2024", display: "Avril 2024"},
        {value: "mai2024", display: "Mai 2024"},
        {value: "juin2024", display: "Juin 2024"},
        {value: "juil2024", display: "Juil. 2024"},
        {value: "aout2024", display: "Août 2024"},
        {value: "sept2024", display: "Sept. 2024"},
        {value: "oct2024", display: "Oct. 2024"},
        {value: "nov2024", display: "Nov. 2024"},
        {value: "dec2024", display: "Déc. 2024"},
        {value: "janv2025", display: "Janv. 2025"}
    ];

    const predictionMonths = [
        {value: "", display: "Globale (toutes données)"},
        {value: "moy_predic_janv2025", display: "Janv. 2025 (prédiction)"},
        {value: "moy_predic_fev2025", display: "Fév. 2025 (prédiction)"},
        {value: "moy_predic_mars2025", display: "Mars 2025 (prédiction)"},
        {value: "moy_predic_avril2025", display: "Avril 2025 (prédiction)"},
        {value: "moy_predic_mai2025", display: "Mai 2025 (prédiction)"},
        {value: "moy_predic_juin2025", display: "Juin 2025 (prédiction)"},
        {value: "moy_predic_juil2025", display: "Juil. 2025 (prédiction)"},
        {value: "moy_predic_aout2025", display: "Août 2025 (prédiction)"},
        {value: "moy_predic_sept2025", display: "Sept. 2025 (prédiction)"},
        {value: "moy_predic_oct2025", display: "Oct. 2025 (prédiction)"},
        {value: "moy_predic_nov2025", display: "Nov. 2025 (prédiction)"},
        {value: "moy_predic_dec2025", display: "Déc. 2025 (prédiction)"},
        {value: "moy_predic_janv2026", display: "Janv. 2026 (prédiction)"}

    ];

    // Fonction pour peupler le sélecteur de mois en fonction du type de données sélectionné
    function populateMonthSelect() {
        let selectedDataType = "historique";
        for (let radio of dataTypeRadios) {
            if (radio.checked) {
                selectedDataType = radio.value;
                break;
            }
        }
        if (selectedDataType === "habitants" || selectedDataType === "superficie") {
            monthSelect.innerHTML = "";
            monthSelect.disabled = true;
        } else {
            monthSelect.disabled = false;
            monthSelect.innerHTML = "";
            let options = selectedDataType === "historique" ? historicalMonths : predictionMonths;
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.display;
                monthSelect.appendChild(option);
            });
        }
    }
    populateMonthSelect();
    dataTypeRadios.forEach(radio => {
        radio.addEventListener('change', populateMonthSelect);
    });

    // Fonction de rendu de la liste des villes sélectionnées
    function renderSelectedCities() {
        selectedCitiesDiv.innerHTML = "";
        selectedCities.forEach((item, index) => {
            const span = document.createElement('span');
            span.className = 'badge badge-secondary';
            span.innerHTML = `<i class="fa-solid fa-location-dot"></i> ${index + 1}. ${item} <i class="fa-solid fa-xmark delete-icon"></i>`;
            span.title = 'Cliquez sur l’icône pour retirer cet élément';
            span.querySelector('.delete-icon').addEventListener('click', function (e) {
                e.stopPropagation();
                selectedCities.splice(index, 1);
                renderSelectedCities();
            });
            selectedCitiesDiv.appendChild(span);
        });
        clearCitiesBtn.style.display = selectedCities.length ? "inline-block" : "none";
    }

    // Recherche et ajout d'une ville individuelle
    cityInput.addEventListener('input', function () {
        const query = this.value.trim();
        if (!query.length) {
            suggestionsList.innerHTML = "";
            return;
        }
        fetch(`../fonctionnalites/suggestions.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(results => {
                suggestionsList.innerHTML = "";
                results.forEach(result => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item list-group-item-action';
                    li.innerHTML = `<i class="fa-solid fa-city"></i> ${result.ville} (${result.code_postal}, ${result.region})`;
                    li.style.cursor = 'pointer';
                    li.addEventListener('click', function () {
                        addCity(result.ville);
                    });
                    suggestionsList.appendChild(li);
                });
            })
            .catch(err => console.error(err));
    });

    function addCity(city) {
        if (!selectedCities.includes(city)) {
            selectedCities.push(city);
            renderSelectedCities();
        }
        cityInput.value = "";
        suggestionsList.innerHTML = "";
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#city-selection')) {
            suggestionsList.innerHTML = "";
        }
    });

    clearCitiesBtn.addEventListener('click', function () {
        selectedCities = [];
        renderSelectedCities();
    });

    // Chargement des groupes disponibles en fonction du type sélectionné
    groupTypeSelect.addEventListener('change', function () {
        const groupType = this.value;
        availableGroupsDiv.innerHTML = "";
        if (!groupType) return;

        if (groupType === "superficie") {
            const options = [
                { value: "moins10", label: "Moins de 10 km²" },
                { value: "10_50", label: "Entre 10 et 50 km²" },
                { value: "plus50", label: "Plus de 50 km²" }
            ];
            options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'btn btn-outline-secondary btn-sm m-1';
                btn.textContent = opt.label;
                btn.addEventListener('click', function () {
                    fetch(`../fonctionnalites/get_cities_by_filter.php?filter=superficie&group_value=${encodeURIComponent(opt.value)}`)
                        .then(response => response.json())
                        .then(cities => {
                            if (cities.error) {
                                alert(cities.error);
                                return;
                            }
                            // Ajoute chaque ville du groupe à la sélection
                            cities.forEach(city => {
                                if (!selectedCities.includes(city)) {
                                    selectedCities.push(city);
                                }
                            });
                            renderSelectedCities();
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Erreur lors du chargement des villes pour ce groupe.");
                        });
                });
                availableGroupsDiv.appendChild(btn);
            });
        } else if (groupType === "population") {
            const options = [
                { value: "moins10k", label: "Moins de 10k" },
                { value: "10k_50k", label: "Entre 10k et 50k" },
                { value: "plus50k", label: "Plus de 50k" }
            ];
            options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'btn btn-outline-secondary btn-sm m-1';
                btn.textContent = opt.label;
                btn.addEventListener('click', function () {
                    fetch(`../fonctionnalites/get_cities_by_filter.php?filter=population&group_value=${encodeURIComponent(opt.value)}`)
                        .then(response => response.json())
                        .then(cities => {
                            if (cities.error) {
                                alert(cities.error);
                                return;
                            }
                            cities.forEach(city => {
                                if (!selectedCities.includes(city)) {
                                    selectedCities.push(city);
                                }
                            });
                            renderSelectedCities();
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Erreur lors du chargement des villes pour ce groupe.");
                        });
                });
                availableGroupsDiv.appendChild(btn);
            });
        } else {
            // Pour "department", "region" et "densite"
            fetch(`../fonctionnalites/get_group_values.php?group_type=${groupType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        availableGroupsDiv.textContent = data.error;
                        return;
                    }
                    data.forEach(groupVal => {
                        // Pour department, region et densite, on récupère la liste des villes correspondantes
                        const btn = document.createElement('button');
                        btn.className = 'btn btn-outline-secondary btn-sm m-1';
                        btn.textContent = groupVal;
                        btn.addEventListener('click', function () {
                            fetch(`../fonctionnalites/get_cities_by_filter.php?filter=${groupType}&group_value=${encodeURIComponent(groupVal)}`)
                                .then(response => response.json())
                                .then(cities => {
                                    if (cities.error) {
                                        alert(cities.error);
                                        return;
                                    }
                                    cities.forEach(city => {
                                        if (!selectedCities.includes(city)) {
                                            selectedCities.push(city);
                                        }
                                    });
                                    renderSelectedCities();
                                })
                                .catch(err => {
                                    console.error(err);
                                    alert("Erreur lors du chargement des villes pour ce groupe.");
                                });
                        });
                        availableGroupsDiv.appendChild(btn);
                    });
                })
                .catch(err => {
                    console.error(err);
                    availableGroupsDiv.textContent = "Erreur lors du chargement.";
                });
        }
    });

    // Gestion du bouton Comparer
    compareButton.addEventListener('click', function () {
        let selectedDataType = "historique";
        for (let radio of dataTypeRadios) {
            if (radio.checked) {
                selectedDataType = radio.value;
                break;
            }
        }
        let monthValue = monthSelect.value;
        if (selectedDataType === "habitants" || selectedDataType === "superficie") {
            monthValue = "";
        }
        const pollutantFilter = pollutantFilterSelect.value;
        const params = new URLSearchParams();
        params.append('data_type', selectedDataType);
        params.append('month', monthValue);
        params.append('pollutant', pollutantFilter);
        if (selectedCities.length === 0) {
            alert("Veuillez sélectionner au moins une ville ou un groupe.");
            return;
        }
        params.append('cities', selectedCities.join(','));

        console.log("PARAMS envoyés à PHP :", params.toString());

        fetch('../fonctionnalites/get_compare_data.php', {
            method: 'POST',
            body: params
        })
            .then(response => response.json())
            .then(data => {
                console.log("RÉPONSE reçue depuis get_compare_data.php :", data);
                if (compareChart) compareChart.destroy();
                const ctx = document.getElementById('compare-chart').getContext('2d');
                compareChart = new Chart(ctx, {
                    type: 'bar',
                    data: data.chartData,
                    options: { responsive: true, plugins: { legend: { display: true } } }
                });
                document.getElementById('compare-table').innerHTML = data.tableHtml;
            })
            .catch(err => console.error(err));
    });
});

