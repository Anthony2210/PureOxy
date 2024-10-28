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
    <?php include('../includes/header.php'); ?>


<main>
    <h1>Recherche de Villes</h1>

    <!-- Barre de recherche -->
    <input type="text" id="search-bar" placeholder="Recherchez une ville...">

    <!-- Liste déroulante pour les suggestions -->
    <ul id="suggestions-list"></ul>

    <!-- Zone de résultats de la recherche -->
    <div id="search-results"></div>
</main>

<footer>
    <!-- Inclusion du fichier footer.php -->
    <?php include('../includes/footer.php'); ?>
</footer>

<script>
    // Variables pour optimiser les requêtes
    let lastQuery = "";
    let cache = {};

    // Ajouter un écouteur d'événement pour l'input de la barre de recherche
    document.getElementById("search-bar").addEventListener("input", function() {
        const query = this.value.trim();

        // Si la saisie est vide, on vide les suggestions
        if (query === "") {
            document.getElementById("suggestions-list").innerHTML = "";
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
        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (this.readyState === 4 && this.status === 200) {
                const results = JSON.parse(this.responseText);

                // Ajouter les résultats au cache
                cache[query] = results;

                // Afficher les suggestions
                displaySuggestions(results);
            }
        };
        xhr.open("GET", `search_suggestions.php?query=${encodeURIComponent(query)}`, true);
        xhr.send();
    });

    // Fonction pour afficher les suggestions
    function displaySuggestions(results) {
        let suggestionsHtml = "";

        if (results.length > 0) {
            results.forEach(function(result) {
                suggestionsHtml += `<li onclick="selectCity('${result.ville}')">${result.ville} (${result.code_postal}, ${result.region})</li>`;
            });
        } else {
            suggestionsHtml = `<li>Aucune ville trouvée</li>`;
        }

        document.getElementById("suggestions-list").innerHTML = suggestionsHtml;
    }

    // Fonction pour sélectionner une ville et rediriger vers details.php
    function selectCity(city) {
        window.location.href = `../fonctionnalites/details.php?ville=${encodeURIComponent(city)}`;
    }

    // Gérer les clics en dehors des suggestions pour les masquer
    document.addEventListener("click", function(e) {
        if (!e.target.closest('#suggestions-list') && !e.target.closest('#search-bar')) {
            document.getElementById("suggestions-list").innerHTML = "";
        }
    });
</script>

</body>
</html>