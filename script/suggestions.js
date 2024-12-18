/**
 * suggestions.js
 *
 * Ce script gère les suggestions de villes en temps réel pour les champs de recherche.
 * Il implémente un système de cache pour optimiser les performances et éviter les requêtes redondantes.
 */

let lastQuery = ""; // Stocke la dernière requête effectuée
let cache = {}; // Objet pour stocker les résultats des requêtes précédentes

/**
 * Initialise les suggestions pour un champ de recherche spécifique.
 *
 * @param {string} inputId - L'ID de l'élément input pour la saisie de la ville.
 * @param {string} suggestionsListId - L'ID de l'élément ul pour afficher les suggestions.
 * @param {string} hiddenInputId - L'ID de l'input caché pour stocker la ville sélectionnée.
 * @param {string} addButtonId - L'ID du bouton "Ajouter" associé.
 */
function initializeSuggestions(inputId, suggestionsListId, hiddenInputId, addButtonId) {
    const inputElement = document.getElementById(inputId);
    const suggestionsList = document.getElementById(suggestionsListId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const addButton = document.getElementById(addButtonId);

    // Désactive le bouton Ajouter par défaut
    if (addButton) {
        addButton.disabled = true;
    }

    /**
     * Ajoute un écouteur d'événement pour détecter les entrées dans le champ de recherche.
     */
    inputElement.addEventListener("input", function() {
        const query = this.value.trim();

        // Réinitialise le champ caché et désactive le bouton Ajouter
        if (hiddenInput) {
            hiddenInput.value = "";
        }
        if (addButton) {
            addButton.disabled = true;
        }

        // Si la saisie est vide, masque les suggestions
        if (query === "") {
            suggestionsList.innerHTML = "";
            suggestionsList.classList.remove("show");
            return;
        }

        // Si la requête est identique à la précédente, ne fait rien
        if (query === lastQuery) return;
        lastQuery = query; // Met à jour la dernière requête

        // Si les résultats de la requête sont déjà en cache, les utilise
        if (cache[query]) {
            displaySuggestions(cache[query], suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId);
            return;
        }

        // Envoie une requête AJAX pour obtenir les suggestions
        fetch(`../fonctionnalites/suggestions.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(results => {
                cache[query] = results; // Ajoute les résultats au cache
                displaySuggestions(results, suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId);
            })
            .catch(error => console.error("Erreur de récupération des suggestions :", error));
    });

    /**
     * Ajoute un écouteur d'événement pour les clics en dehors des suggestions afin de les masquer.
     */
    document.addEventListener("click", function(e) {
        if (!e.target.closest('#' + suggestionsListId) && !e.target.closest('#' + inputId)) {
            suggestionsList.innerHTML = "";
            suggestionsList.classList.remove("show");
        }
    });
}

/**
 * Affiche les suggestions dans la liste déroulante.
 *
 * @param {Array} results - Les résultats des suggestions.
 * @param {HTMLElement} suggestionsList - L'élément ul pour afficher les suggestions.
 * @param {HTMLElement} inputElement - L'élément input où la saisie est effectuée.
 * @param {string} inputId - L'ID de l'input.
 * @param {string} suggestionsListId - L'ID de la liste de suggestions.
 * @param {string} hiddenInputId - L'ID de l'input caché.
 * @param {string} addButtonId - L'ID du bouton Ajouter.
 */
function displaySuggestions(results, suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId) {
    let suggestionsHtml = "";

    if (results.length > 0) {
        // Parcourt chaque résultat et crée un élément li pour chaque suggestion
        results.forEach(function(result, index) {
            suggestionsHtml += `<li style="--i: ${index}" onclick="selectCity('${result.ville}', '${inputId}', '${suggestionsListId}', '${hiddenInputId}', '${addButtonId}')">${result.ville} (${result.code_postal}, ${result.region})</li>`;
        });
        suggestionsList.classList.add("show");
    } else {
        // Affiche un message si aucune suggestion n'est trouvée
        suggestionsHtml = `<li>Aucune ville trouvée</li>`;
        suggestionsList.classList.add("show");
    }

    suggestionsList.innerHTML = suggestionsHtml; // Met à jour le contenu de la liste de suggestions
}

/**
 * Sélectionne une ville à partir des suggestions et met à jour les champs correspondants.
 *
 * @param {string} city - Le nom de la ville sélectionnée.
 * @param {string} inputId - L'ID de l'input.
 * @param {string} suggestionsListId - L'ID de la liste de suggestions.
 * @param {string} hiddenInputId - L'ID de l'input caché.
 * @param {string} addButtonId - L'ID du bouton Ajouter.
 */
function selectCity(city, inputId, suggestionsListId, hiddenInputId, addButtonId) {
    const inputElement = document.getElementById(inputId);
    const suggestionsList = document.getElementById(suggestionsListId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const addButton = document.getElementById(addButtonId);

    inputElement.value = city;

    lastQuery = ""; // Réinitialise la dernière requête

    // Masque les suggestions
    suggestionsList.innerHTML = "";
    suggestionsList.classList.remove("show");

    // Met à jour le champ caché et active le bouton Ajouter
    if (hiddenInput) {
        hiddenInput.value = city;
    }
    if (addButton) {
        addButton.disabled = false;
    }
}

/**
 * Initialise les suggestions pour le champ de recherche lors du chargement du DOM.
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeSuggestions('search-bar', 'suggestions-list');
});

// Sélectionne les éléments du bouton de recherche
const searchBar = document.getElementById("search-bar");
const searchButton = document.getElementById("search-button");

/**
 * Ajoute un écouteur d'événement au bouton de recherche pour rediriger vers la page de détails de la ville.
 */
searchButton.addEventListener("click", function() {
    let query = searchBar.value.trim();
    if (query !== "") {
        // Met en majuscule la première lettre de chaque mot
        query = query.split(' ').map(function(word) {
            return word.charAt(0).toUpperCase() + word.slice(1);
        }).join(' ');
        // Redirige vers la page de détails de la ville sélectionnée
        window.location.href = `/PUREOXY/fonctionnalites/details.php?ville=${encodeURIComponent(query)}`;
    } else {
        alert("Veuillez entrer le nom d'une ville.");
    }
});
