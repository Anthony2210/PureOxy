    let lastQuery = "";
    let cache = {};

    function initializeSuggestions(inputId, suggestionsListId, hiddenInputId, addButtonId) {
        const inputElement = document.getElementById(inputId);
        const suggestionsList = document.getElementById(suggestionsListId);
        const hiddenInput = document.getElementById(hiddenInputId);
        const addButton = document.getElementById(addButtonId);

        // Désactiver le bouton Ajouter par défaut
        addButton.disabled = true;

        inputElement.addEventListener("input", function() {
            const query = this.value.trim();

            // Réinitialiser le champ caché et désactiver le bouton Ajouter
            hiddenInput.value = "";
            addButton.disabled = true;

            // Si la saisie est vide, on cache les suggestions
            if (query === "") {
                suggestionsList.innerHTML = "";
                suggestionsList.classList.remove("show");
                return;
            }

            // Si la requête est la même que la précédente, ne rien faire
            if (query === lastQuery) return;
            lastQuery = query;

            // Si la requête est déjà en cache, l'utiliser
            if (cache[query]) {
                displaySuggestions(cache[query], suggestionsList, inputElement);
                return;
            }

            // Envoyer la requête AJAX pour obtenir les suggestions
            fetch(`../fonctionnalites/suggestions.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(results => {
                    cache[query] = results; // Ajouter les résultats au cache
                    displaySuggestions(results, suggestionsList, inputElement);
                })
                .catch(error => console.error("Erreur de récupération des suggestions :", error));
        });

        // Gérer les clics en dehors des suggestions pour les masquer
        document.addEventListener("click", function(e) {
            if (!e.target.closest('#' + suggestionsListId) && !e.target.closest('#' + inputId)) {
                suggestionsList.innerHTML = "";
                suggestionsList.classList.remove("show");
            }
        });

        // Fonction pour afficher les suggestions
        function displaySuggestions(results, suggestionsList, inputElement) {
            let suggestionsHtml = "";

            if (results.length > 0) {
                results.forEach(function(result, index) {
                    suggestionsHtml += `<li style="--i: ${index}" onclick="selectCity('${result.ville}', '${inputId}', '${suggestionsListId}', '${hiddenInputId}', '${addButtonId}')">${result.ville} (${result.code_postal}, ${result.region})</li>`;
                });
                suggestionsList.classList.add("show"); // Ajouter la classe pour l'animation
            } else {
                suggestionsHtml = `<li>Aucune ville trouvée</li>`;
                suggestionsList.classList.add("show");
            }

            suggestionsList.innerHTML = suggestionsHtml;
        }
    }

    // Fonction pour sélectionner une ville
    function selectCity(city, inputId, suggestionsListId, hiddenInputId, addButtonId) {
        const inputElement = document.getElementById(inputId);
        const suggestionsList = document.getElementById(suggestionsListId);
        const hiddenInput = document.getElementById(hiddenInputId);
        const addButton = document.getElementById(addButtonId);

        inputElement.value = city;
        hiddenInput.value = city; // Mettre à jour le champ caché avec la ville sélectionnée
        suggestionsList.innerHTML = "";
        suggestionsList.classList.remove("show");
        addButton.disabled = false; // Activer le bouton Ajouter
    }
