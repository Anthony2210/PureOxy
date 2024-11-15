let lastQuery = "";
let cache = {};

function initializeSuggestions(inputId, suggestionsListId, hiddenInputId, addButtonId) {
    const inputElement = document.getElementById(inputId);
    const suggestionsList = document.getElementById(suggestionsListId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const addButton = document.getElementById(addButtonId);

    // Désactiver le bouton Ajouter par défaut
    if (addButton) {
        addButton.disabled = true;
    }

    inputElement.addEventListener("input", function() {
        const query = this.value.trim();
        const suggestionsList = document.getElementById(suggestionsListId);

        // Réinitialiser le champ caché et désactiver le bouton Ajouter
        if (hiddenInput) {
            hiddenInput.value = "";
        }
        if (addButton) {
            addButton.disabled = true;
        }

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
            displaySuggestions(cache[query], suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId);
            return;
        }

        // Envoyer la requête AJAX pour obtenir les suggestions
        fetch(`../fonctionnalites/suggestions.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(results => {
                cache[query] = results; // Ajouter les résultats au cache
                displaySuggestions(results, suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId);
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
}

// Fonction pour afficher les suggestions
function displaySuggestions(results, suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId) {
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

// Fonction pour sélectionner une ville
function selectCity(city, inputId, suggestionsListId, hiddenInputId, addButtonId) {
    const inputElement = document.getElementById(inputId);
    const suggestionsList = document.getElementById(suggestionsListId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const addButton = document.getElementById(addButtonId);

    inputElement.value = city;

    // Réinitialiser le cache pour forcer la recherche à jour
    lastQuery = "";

    // Cacher les suggestions
    suggestionsList.innerHTML = "";
    suggestionsList.classList.remove("show");

    // Mettre à jour le champ caché et activer le bouton Ajouter
    if (hiddenInput) {
        hiddenInput.value = city;
    }
    if (addButton) {
        addButton.disabled = false;
    }
}

// Initialiser les suggestions pour le champ de recherche
document.addEventListener('DOMContentLoaded', function() {
    initializeSuggestions('search-bar', 'suggestions-list');
});

// Ajouter l'écouteur pour le bouton de recherche
const searchBar = document.getElementById("search-bar");
const searchButton = document.getElementById("search-button");

searchButton.addEventListener("click", function() {
    let query = searchBar.value.trim();
    if (query !== "") {
        // Mettre en majuscule la première lettre de chaque mot
        query = query.split(' ').map(function(word) {
            return word.charAt(0).toUpperCase() + word.slice(1);
        }).join(' ');
        window.location.href = `/PUREOXY/fonctionnalites/details.php?ville=${encodeURIComponent(query)}`;
    } else {
        alert("Veuillez entrer le nom d'une ville.");
    }
});
