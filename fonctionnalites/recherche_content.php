<!-- recherche_content.php -->
<!-- Ce fichier contient uniquement le contenu de la barre de recherche et des résultats,
     sans structure HTML complète, pour être inclus dans recherche.php -->
<div id="search-container">
    <h1>Rechercher une ville</h1>
    <div class="search-input-wrapper">
        <input type="text" id="search-bar" placeholder="Entrez le nom d'une ville" autocomplete="off">
        <p class="avertissement">Veuillez noter que notre base de données couvre actuellement 443 villes.</p>
        <ul id="suggestions-list"></ul>
    </div>
    <button id="search-button"><i class="fas fa-search"></i> Rechercher</button>
</div>
<div id="search-results"></div>
