/**
 * suggestions.js
 *
 * Ce script gère les suggestions de villes en temps réel pour les champs de recherche.
 * Il utilise un système de cache pour éviter des requêtes redondantes et optimiser les performances.
 *
 * Références :
 * - ChatGPT pour la structuration et la gestion des événements.
 *
 * Utilisation :
 * - Appelé au chargement du DOM pour initialiser le comportement du champ de recherche.
 *
 * Fichier placé dans le dossier script.
 */

/**
 * Initialise les suggestions pour un champ de recherche spécifique.
 *
 * @param {string} inputId - L'ID de l'élément input pour la saisie.
 * @param {string} suggestionsListId - L'ID de l'élément ul pour afficher les suggestions.
 * @param {string} [hiddenInputId] - (Optionnel) L'ID de l'input caché pour stocker la ville sélectionnée.
 * @param {string} [addButtonId] - (Optionnel) L'ID du bouton "Ajouter" associé.
 */
function initializeSuggestions(inputId, suggestionsListId, hiddenInputId, addButtonId) {
    const inputElement = document.getElementById(inputId);
    const suggestionsList = document.getElementById(suggestionsListId);
    const hiddenInput = hiddenInputId ? document.getElementById(hiddenInputId) : null;
    const addButton = addButtonId ? document.getElementById(addButtonId) : null;

    // Désactive le bouton Ajouter par défaut
    if (addButton) {
        addButton.disabled = true;
    }

    /**
     * Écoute l'événement 'input' pour récupérer les suggestions en fonction de la saisie
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
        lastQuery = query;

        // Vérifie le cache pour éviter des requêtes redondantes
        if (cache[query]) {
            displaySuggestions(cache[query], suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId);
            return;
        }

        // Requête AJAX pour obtenir les suggestions
        fetch(`../fonctionnalites/suggestions.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(results => {
                cache[query] = results;
                displaySuggestions(results, suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId);
            })
            .catch(error => console.error("Erreur de récupération des suggestions :", error));
    });

    // Masque les suggestions lorsqu'on clique en dehors
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
 * @param {Array} results - Les résultats obtenus.
 * @param {HTMLElement} suggestionsList - L'élément ul où afficher les suggestions.
 * @param {HTMLElement} inputElement - L'élément input concerné.
 * @param {string} inputId - L'ID de l'input.
 * @param {string} suggestionsListId - L'ID de la liste.
 * @param {string} hiddenInputId - L'ID de l'input caché.
 * @param {string} addButtonId - L'ID du bouton Ajouter.
 */
function displaySuggestions(results, suggestionsList, inputElement, inputId, suggestionsListId, hiddenInputId, addButtonId) {
    let suggestionsHtml = "";

    if (results.length > 0) {
        results.forEach(function(result, index) {
            suggestionsHtml += `<li style="--i: ${index}" onclick="selectCity('${result.ville}', '${inputId}', '${suggestionsListId}', '${hiddenInputId}', '${addButtonId}')">${result.ville} (${result.code_postal}, ${result.region})</li>`;
        });
        suggestionsList.classList.add("show");
    } else {
        suggestionsHtml = `<li>Aucune ville trouvée</li>`;
        suggestionsList.classList.add("show");
    }

    suggestionsList.innerHTML = suggestionsHtml;
}

/**
 * Sélectionne une ville dans les suggestions.
 *
 * @param {string} city - Le nom de la ville sélectionnée.
 * @param {string} inputId - L'ID de l'input.
 * @param {string} suggestionsListId - L'ID de la liste.
 * @param {string} hiddenInputId - L'ID de l'input caché.
 * @param {string} addButtonId - L'ID du bouton Ajouter.
 */
function selectCity(city, inputId, suggestionsListId, hiddenInputId, addButtonId) {
    const inputElement = document.getElementById(inputId);
    const suggestionsList = document.getElementById(suggestionsListId);
    const hiddenInput = hiddenInputId ? document.getElementById(hiddenInputId) : null;
    const addButton = addButtonId ? document.getElementById(addButtonId) : null;

    inputElement.value = city;
    lastQuery = ""; // Réinitialise la dernière requête
    suggestionsList.innerHTML = "";
    suggestionsList.classList.remove("show");

    if (hiddenInput) {
        hiddenInput.value = city;
    }
    if (addButton) {
        addButton.disabled = false;
    }
}

// Initialisation des suggestions pour le champ de recherche
document.addEventListener('DOMContentLoaded', function() {
    initializeSuggestions('search-bar', 'suggestions-list');
});

// Gestion du bouton de recherche
const searchBar = document.getElementById("search-bar");
const searchButton = document.getElementById("search-button");

searchButton.addEventListener("click", function() {
    let query = searchBar.value.trim();
    if (query !== "") {
        // Met en majuscule la première lettre de chaque mot
        query = query.split(' ').map(function(word) {
            return word.charAt(0).toUpperCase() + word.slice(1);
        }).join(' ');
        window.location.href = `/PUREOXY/fonctionnalites/details.php?ville=${encodeURIComponent(query)}`;
    } else {
        alert("Veuillez entrer le nom d'une ville.");
    }
});
