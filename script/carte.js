/**
 * carte.js
 *
 * - Au chargement, on affiche une carte de France avec des marqueurs (markersLayer).
 * - Si l'utilisateur sélectionne un polluant dans le <select>, on supprime les marqueurs
 *   et on affiche une heat map (heatLayer) en se basant sur les mêmes données.
 * - Si l'utilisateur désélectionne (met la valeur vide ""), on retire la heat map et on remet les marqueurs.
 */

function initMap(villes) {
    // 1) Création et centrage de la carte
    var map = L.map('map').setView([46.603354, 1.888334], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // 2) Définition d'une icône pour les marqueurs
    var greenIcon = L.icon({
        iconUrl: '../images/green-icon.png',
        iconSize: [20, 20]
    });

    // 3) Création de deux "layers" :
    //    - markersLayer : contiendra tous les marqueurs
    //    - heatLayer    : contiendra la heat map
    var markersLayer = L.layerGroup().addTo(map);
    var heatLayer = null; // on l'initialisera plus tard

    /**
     * Affiche les marqueurs pour chaque ville, en se basant sur "villes".
     * On nettoie d'abord markersLayer (au cas où on l'ait déjà rempli).
     */
    function drawMarkers() {
        markersLayer.clearLayers();
        villes.forEach(function(ville) {
            // S'assurer que la ville a bien des coordonnées
            if (ville.lat && ville.lon) {
                var marker = L.marker([ville.lat, ville.lon], { icon: greenIcon });

                // Construire la liste des polluants
                var pollutantList = '';
                for (var pollutant in ville.pollutants) {
                    let values = ville.pollutants[pollutant].map(function(d) {
                        return parseFloat(d.value) || 0;
                    });
                    let avg = calculateAverage(values);
                    pollutantList += displayPollutantVariation(pollutant, avg);
                }

                // Contenu du popup
                var popupContent = `
                    <div class="popup-content">
                        <strong>Ville :</strong> ${ville.nom}<br>
                        ${ville.location !== 'Inconnu' ? `<strong>Localisation :</strong> ${ville.location}<br>` : ''}
                        <ul>${pollutantList}</ul>
                        <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(ville.nom)}" id="see-more">Voir plus</a>
                    </div>
                `;

                marker.bindPopup(popupContent);
                markersLayer.addLayer(marker);
            }
        });
    }

    /**
     * Calcule la moyenne d'un tableau de nombres.
     */
    function calculateAverage(values) {
        if (!values.length) return 0;
        let sum = values.reduce((acc, val) => acc + val, 0);
        return sum / values.length;
    }

    /**
     * Construit la chaîne HTML d'un polluant et de sa valeur moyenne.
     */
    function displayPollutantVariation(pollutant, value) {
        return `<li><strong>${pollutant} :</strong> ${value.toFixed(2)} µg/m³</li>`;
    }

    /**
     * Construit et affiche la heat map pour un polluant donné (sans normalisation).
     * - On supprime d'abord la heat map précédente (si elle existe).
     * - On parcourt les données "villes" pour calculer la moyenne du polluant et construire heatData.
     * - On crée la heat map avec L.heatLayer(heatData, ...).
     */
    function drawHeatMap(pollutant) {
        // On supprime l'ancienne heat map si elle existe
        if (heatLayer) {
            map.removeLayer(heatLayer);
            heatLayer = null;
        }

        // Si aucun polluant sélectionné, on ne fait rien
        if (!pollutant) return;

        var heatData = [];
        var maxValue = 0;

        // Parcours des villes pour construire le tableau heatData
        villes.forEach(function(ville) {
            if (ville.lat && ville.lon && ville.pollutants[pollutant]) {
                let arr = ville.pollutants[pollutant].map(d => parseFloat(d.value) || 0);
                let avg = calculateAverage(arr);

                if (avg > maxValue) {
                    maxValue = avg;
                }

                heatData.push([parseFloat(ville.lat), parseFloat(ville.lon), avg]);
            }
        });

        // Debug : vérifier le nombre de points et la valeur max
        console.log("Polluant sélectionné :", pollutant);
        console.log("Nombre de points dans heatData :", heatData.length);
        console.log("maxValue :", maxValue);

        // Normaliser les intensités pour qu'elles soient comprises entre 0 et 1
        if (maxValue > 0) {
            heatData = heatData.map(function(point) {
                return [point[0], point[1], point[2] / maxValue];
            });
        }

        // Ajuster les paramètres pour améliorer la visibilité
        heatLayer = L.heatLayer(heatData, {
            // Valeurs raisonnables pour tester
            radius: 40,
            blur: 20,
            gradient: {0.0: 'blue', 0.5: 'lime', 1.0: 'red'}
        }).addTo(map);

        // Facultatif : recadrer la carte pour voir tous les points
        // (si beaucoup de points sont hors métropole, vous verrez la carte dézoomée)
        if (heatData.length > 0) {
            var latLngs = heatData.map(pt => [pt[0], pt[1]]);
            var bounds = L.latLngBounds(latLngs);
            map.fitBounds(bounds);
        }
    }

    // Le <select> "pollutant-filter"
    var pollutantSelect = document.getElementById('pollutant-filter');
    if (pollutantSelect) {
        pollutantSelect.addEventListener('change', function() {
            var selectedPollutant = this.value;
            if (selectedPollutant) {
                // On enlève les marqueurs pour afficher la heat map
                markersLayer.clearLayers();
                drawHeatMap(selectedPollutant);
            } else {
                // Si polluant = vide, on retire la heat map et on remet les marqueurs
                if (heatLayer) {
                    map.removeLayer(heatLayer);
                    heatLayer = null;
                }
                drawMarkers();
            }
            console.log("Changement polluant ->", selectedPollutant);
        });
    }

    // Au démarrage, on dessine d'abord les marqueurs
    drawMarkers();
}

document.addEventListener('DOMContentLoaded', function() {
    // Récupère les données des villes injectées dans la page (en JSON)
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
