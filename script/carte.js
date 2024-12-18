/**
 * carte.js
 *
 * Ce script initialise une carte interactive utilisant Leaflet et y ajoute des marqueurs
 * représentant les niveaux de pollution atmosphérique dans différentes villes de France.
 * Les données des villes sont injectées depuis PHP sous forme de JSON.
 */

/**
 * Fonction pour initialiser la carte.
 */
function initMap(villes) {
    // Initialise la carte centrée sur la France.
    var map = L.map('map').setView([46.603354, 1.888334], 6);

    // Ajoute les tuiles OpenStreetMap à la carte.
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Définition d'une icône verte personnalisée pour les marqueurs.
    var greenIcon = L.icon({
        iconUrl: '../images/green-icon.png',
        iconSize: [20, 20],
    });

    /**
     * Génère une liste HTML des variations des polluants.
     *
     * @param {string} pollutant Le nom du polluant.
     * @param {number} value La valeur moyenne du polluant.
     * @returns {string} HTML de la liste.
     */
    function displayPollutantVariation(pollutant, value) {
        return `<li><strong>${pollutant} :</strong> ${value.toFixed(2)} µg/m³</li>`;
    }

    /**
     * Calcule la moyenne d'un tableau de valeurs.
     *
     * @param {Array<number>} values Tableau des valeurs numériques.
     * @returns {number} La moyenne des valeurs.
     */
    function calculateAverage(values) {
        if (values.length === 0) return 0;
        let sum = values.reduce((acc, val) => acc + parseFloat(val), 0);
        return sum / values.length;
    }

    /**
     * Parcourt chaque ville et ajoute un marqueur sur la carte avec un popup détaillé.
     */
    villes.forEach(function(ville) {
        if (ville.lat && ville.lon) {
            var marker = L.marker([ville.lat, ville.lon], { icon: greenIcon }).addTo(map);

            var pollutantList = '';
            for (var pollutant in ville.pollutants) {
                let values = ville.pollutants[pollutant].map(data => data.value);
                let average = calculateAverage(values);
                pollutantList += displayPollutantVariation(pollutant, average);
            }

            var popupContent = `
                <div class="popup-content">
                    <strong>Ville :</strong> ${ville.nom}<br>
                    ${ville.location !== 'Inconnu' ? `<strong>Localisation :</strong> ${ville.location}<br>` : ''}
                    <ul>${pollutantList}</ul>
                    <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(ville.nom)}" id="see-more">Voir plus</a>
                </div>
            `;

            marker.bindPopup(popupContent);

            marker.on('click', function() {
                if (window.currentPopup) {
                    window.currentPopup._close();
                }
                window.currentPopup = marker.getPopup();
                marker.openPopup();
            });
        } else {
            console.warn(`Coordonnées manquantes pour la ville : ${ville.nom}`);
        }
    });
}

/**
 * Attendre que le DOM soit complètement chargé avant d'initialiser la carte.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Récupérer les données des villes injectées dans la page.
    var villesData = document.getElementById('villes-data');
    if (villesData) {
        try {
            var villes = JSON.parse(villesData.textContent);
            initMap(villes);
        } catch (e) {
            console.error('Erreur lors de l\'analyse des données des villes :', e);
        }
    } else {
        console.error('Aucune donnée des villes trouvée.');
    }
});
