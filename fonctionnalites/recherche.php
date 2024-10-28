<?php

require_once('../bd/bd.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Villes</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">

</head>
<body>
<div class="content-wrapper">
    <?php include('../includes/header.php'); ?>

    <main>
        <div id="search-container">
            <h1>Rechercher une ville</h1>

            <!-- Barre de recherche -->
            <input type="text" id="search-bar" placeholder="Entrez le nom d'une ville">

            <!-- Liste déroulante pour les suggestions -->
            <ul id="suggestions-list"></ul>

            <!-- Bouton de recherche -->
            <button id="search-button">Rechercher</button>
        </div>

        <!-- Zone de résultats de la recherche -->
        <div id="search-results"></div>
    </main>
    <!-- Inclusion du fichier footer.php -->
    <?php include('../includes/footer.php'); ?>
</div>


    <script>
        let lastQuery = "";
        let cache = {};

        // Ajouter un écouteur d'événement pour l'input de la barre de recherche
        document.getElementById("search-bar").addEventListener("input", function() {
            const query = this.value.trim();
            const suggestionsList = document.getElementById("suggestions-list");

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
                displaySuggestions(cache[query]);
                return;
            }

            // Envoyer la requête AJAX pour obtenir les suggestions
            fetch(`suggestions.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(results => {
                    cache[query] = results; // Ajouter les résultats au cache
                    displaySuggestions(results); // Afficher les suggestions
                })
                .catch(error => console.error("Erreur de récupération des suggestions :", error));
        });

        // Fonction pour afficher les suggestions
        function displaySuggestions(results) {
            const suggestionsList = document.getElementById("suggestions-list");
            let suggestionsHtml = "";

            if (results.length > 0) {
                results.forEach(function(result) {
                    suggestionsHtml += `<li onclick="selectCity('${result.ville}')">${result.ville} (${result.code_postal}, ${result.region})</li>`;
                });
                suggestionsList.classList.add("show"); // Ajouter la classe pour l'animation
            } else {
                suggestionsHtml = `<li>Aucune ville trouvée</li>`;
                suggestionsList.classList.remove("show"); // Retirer la classe si aucune suggestion
            }

            suggestionsList.innerHTML = suggestionsHtml;
        }

        // Fonction pour sélectionner une ville et rediriger vers details.php
        function selectCity(city) {
            window.location.href = `../fonctionnalites/details.php?ville=${encodeURIComponent(city)}`;
        }

        // Gérer les clics en dehors des suggestions pour les masquer
        document.addEventListener("click", function(e) {
            if (!e.target.closest('#suggestions-list') && !e.target.closest('#search-bar')) {
                document.getElementById("suggestions-list").innerHTML = "";
                document.getElementById("suggestions-list").classList.remove("show");
            }
        });
    </script>


</body>
</html>