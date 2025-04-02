// compare.js
document.addEventListener('DOMContentLoaded', function(){
    let selectedCities = [];
    const cityInput = document.getElementById('city-selection');
    const suggestionsList = document.getElementById('suggestions-list-compare');
    const selectedCitiesDiv = document.getElementById('selected-cities');
    const clearCitiesBtn = document.getElementById('clear-cities');
    const compareButton = document.getElementById('compare-button');
    const monthSelect = document.getElementById('month-selection');
    const pollutantFilterSelect = document.getElementById('pollutant-filter');
    const addDepartmentBtn = document.getElementById('add-department');
    const addRegionBtn = document.getElementById('add-region');
    const dataTypeRadios = document.getElementsByName('data-type');
    let compareChart; // Pour stocker le graphique Chart.js

    // Options de mois pour chaque type de données
    const historicalMonths = [
        { value: "", display: "Globale (toutes données)" },
        { value: "janv2023", display: "Janv. 2023" },
        { value: "fev2023", display: "Fév. 2023" },
        { value: "mars2023", display: "Mars 2023" },
        { value: "avril2023", display: "Avril 2023" },
        { value: "mai2023", display: "Mai 2023" },
        { value: "juin2023", display: "Juin 2023" },
        { value: "juil2023", display: "Juil. 2023" },
        { value: "aout2023", display: "Août 2023" },
        { value: "sept2023", display: "Sept. 2023" },
        { value: "oct2023", display: "Oct. 2023" },
        { value: "nov2023", display: "Nov. 2023" },
        { value: "dec2023", display: "Déc. 2023" },
        { value: "janv2024", display: "Janv. 2024" },
        { value: "fev2024", display: "Fév. 2024" },
        { value: "mars2024", display: "Mars 2024" },
        { value: "avril2024", display: "Avril 2024" },
        { value: "mai2024", display: "Mai 2024" },
        { value: "juin2024", display: "Juin 2024" },
        { value: "juil2024", display: "Juil. 2024" },
        { value: "aout2024", display: "Août 2024" },
        { value: "sept2024", display: "Sept. 2024" },
        { value: "oct2024", display: "Oct. 2024" },
        { value: "nov2024", display: "Nov. 2024" },
        { value: "dec2024", display: "Déc. 2024" },
        { value: "janv2025", display: "Janv. 2025" }
    ];

    const predictionMonths = [
        { value: "", display: "Globale (toutes données)" },
        { value: "moy_predic_janv2025", display: "Janv. 2025 (prédiction)" },
        { value: "moy_predic_fev2025", display: "Fév. 2025 (prédiction)" },
        { value: "moy_predic_mars2025", display: "Mars 2025 (prédiction)" },
        { value: "moy_predic_avril2025", display: "Avril 2025 (prédiction)" },
        { value: "moy_predic_mai2025", display: "Mai 2025 (prédiction)" },
        { value: "moy_predic_juin2025", display: "Juin 2025 (prédiction)" },
        { value: "moy_predic_juil2025", display: "Juil. 2025 (prédiction)" },
        { value: "moy_predic_aout2025", display: "Août 2025 (prédiction)" },
        { value: "moy_predic_sept2025", display: "Sept. 2025 (prédiction)" },
        { value: "moy_predic_oct2025", display: "Oct. 2025 (prédiction)" },
        { value: "moy_predic_nov2025", display: "Nov. 2025 (prédiction)" },
        { value: "moy_predic_dec2025", display: "Déc. 2025 (prédiction)" },
        { value: "moy_predic_janv2026", display: "Janv. 2026 (prédiction)" },
        { value: "moy_predic_fev2026", display: "Fév. 2026 (prédiction)" },
        { value: "moy_predic_mars2026", display: "Mars 2026 (prédiction)" },
        { value: "moy_predic_avril2026", display: "Avril 2026 (prédiction)" },
        { value: "moy_predic_mai2026", display: "Mai 2026 (prédiction)" },
        { value: "moy_predic_juin2026", display: "Juin 2026 (prédiction)" },
        { value: "moy_predic_juil2026", display: "Juil. 2026 (prédiction)" },
        { value: "moy_predic_aout2026", display: "Août 2026 (prédiction)" },
        { value: "moy_predic_sept2026", display: "Sept. 2026 (prédiction)" },
        { value: "moy_predic_oct2026", display: "Oct. 2026 (prédiction)" },
        { value: "moy_predic_nov2026", display: "Nov. 2026 (prédiction)" },
        { value: "moy_predic_dec2026", display: "Déc. 2026 (prédiction)" }
    ];

    // Peupler le sélecteur de mois selon le type de données sélectionné
    function populateMonthSelect(){
        let selectedDataType = "historique";
        for(let radio of dataTypeRadios) {
            if(radio.checked){
                selectedDataType = radio.value;
                break;
            }
        }
        monthSelect.innerHTML = "";
        let options = selectedDataType === "historique" ? historicalMonths : predictionMonths;
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.display;
            monthSelect.appendChild(option);
        });
    }
    populateMonthSelect();
    dataTypeRadios.forEach(radio => {
        radio.addEventListener('change', populateMonthSelect);
    });

    function updateFilterButtonsVisibility(){
        if(selectedCities.length === 1){
            addDepartmentBtn.style.display = "inline-block";
            addRegionBtn.style.display = "inline-block";
        } else {
            addDepartmentBtn.style.display = "none";
            addRegionBtn.style.display = "none";
        }
    }

    function addCity(city){
        if(!selectedCities.includes(city)){
            selectedCities.push(city);
            renderSelectedCities();
        }
        cityInput.value = "";
        suggestionsList.innerHTML = "";
    }

    function renderSelectedCities(){
        selectedCitiesDiv.innerHTML = "";
        selectedCities.forEach((city, index) => {
            const span = document.createElement('span');
            span.className = 'badge badge-secondary';
            span.innerHTML = `<i class="fa-solid fa-location-dot"></i> ${index+1}. ${city} <i class="fa-solid fa-xmark delete-icon"></i>`;
            span.title = 'Cliquez sur l’icône pour retirer cette ville';
            span.querySelector('.delete-icon').addEventListener('click', function(e){
                e.stopPropagation();
                selectedCities.splice(index, 1);
                renderSelectedCities();
            });
            selectedCitiesDiv.appendChild(span);
        });
        updateFilterButtonsVisibility();
        clearCitiesBtn.style.display = selectedCities.length > 0 ? "inline-block" : "none";
    }

    cityInput.addEventListener('input', function(){
        const query = this.value.trim();
        if(query.length === 0){
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
                    li.addEventListener('click', function(){
                        addCity(result.ville);
                    });
                    suggestionsList.appendChild(li);
                });
            })
            .catch(err => console.error(err));
    });

    document.addEventListener('click', function(e){
        if(!e.target.closest('#city-selection')){
            suggestionsList.innerHTML = "";
        }
    });

    clearCitiesBtn.addEventListener('click', function(){
        selectedCities = [];
        renderSelectedCities();
    });

    function addCitiesByFilter(filterType){
        if(selectedCities.length !== 1){
            alert("Veuillez sélectionner exactement une ville pour définir le filtre.");
            return;
        }
        const baseCity = selectedCities[0];
        fetch(`../fonctionnalites/get_cities_by_filter.php?filter=${filterType}&base_city=${encodeURIComponent(baseCity)}`)
            .then(response => response.json())
            .then(results => {
                if(results.error){
                    alert(results.error);
                } else {
                    results.forEach(city => {
                        if(!selectedCities.includes(city)){
                            selectedCities.push(city);
                        }
                    });
                    renderSelectedCities();
                    addDepartmentBtn.style.display = "none";
                    addRegionBtn.style.display = "none";
                }
            })
            .catch(err => console.error(err));
    }

    addDepartmentBtn.addEventListener('click', function(){
        addCitiesByFilter("department");
    });
    addRegionBtn.addEventListener('click', function(){
        addCitiesByFilter("region");
    });

    compareButton.addEventListener('click', function(){
        let dataType = "historique";
        for(let radio of dataTypeRadios) {
            if(radio.checked){
                dataType = radio.value;
                break;
            }
        }
        let monthValue = monthSelect.value;
        const pollutantFilter = pollutantFilterSelect.value;
        const params = new URLSearchParams();
        params.append('data_type', dataType);
        params.append('month', monthValue);
        params.append('pollutant', pollutantFilter);
        if(selectedCities.length === 0){
            alert("Veuillez sélectionner au moins une ville ou utiliser l'outil d'ajout par filtre.");
            return;
        }
        params.append('cities', selectedCities.join(','));

        fetch('../fonctionnalites/get_compare_data.php', {
            method: 'POST',
            body: params
        })
            .then(response => response.json())
            .then(data => {
                if(compareChart) compareChart.destroy();
                const ctx = document.getElementById('compare-chart').getContext('2d');
                compareChart = new Chart(ctx, {
                    type: 'bar',
                    data: data.chartData,
                    options: {
                        responsive: true,
                        plugins: { legend: { display: true } }
                    }
                });
                document.getElementById('compare-table').innerHTML = data.tableHtml;
            })
            .catch(err => console.error(err));
    });
});
