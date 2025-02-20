/**
 * carte.js
 *
 * Au chargement, on affiche une carte de France avec des marqueurs (markersLayer).
 * Si l'utilisateur sélectionne un polluant dans le <select>, on supprime les marqueurs
 * et on affiche une heat map via heatmap.js.
 * Si l'utilisateur désélectionne (valeur vide ""), on retire la heat map et on remet les marqueurs.
 */

function initMap(villes) {
    // 1) Création et centrage de la carte
    window.map = L.map('map').setView([46.603354, 1.888334], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // 2) Définition d'une icône pour les marqueurs
    const IconDarkGreen = L.icon({
        iconUrl: '../images/dark-green-icon.png',
        iconSize: [20, 20]
    });

    // 3) Création d'un layerGroup pour les marqueurs
    const markersLayer = L.layerGroup().addTo(map);

    /**
     * Affiche les marqueurs pour chaque ville.
     */
    function drawMarkers() {
        markersLayer.clearLayers();
        villes.forEach((ville) => {
            if (ville.lat && ville.lon) {
                const marker = L.marker([ville.lat, ville.lon], { icon: IconDarkGreen });

                // Construire la liste des polluants
                let pollutantList = '';
                for (let pollutant in ville.pollutants) {
                    const values = ville.pollutants[pollutant].map(d => parseFloat(d.value) || 0);
                    const avg = calculateAverage(values);
                    pollutantList += displayPollutantVariation(pollutant, avg);
                }

                // Contenu du popup
                const popupContent = `
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
        const sum = values.reduce((acc, val) => acc + val, 0);
        return sum / values.length;
    }

    /**
     * Construit la chaîne HTML d'un polluant et de sa valeur moyenne.
     */
    function displayPollutantVariation(pollutant, value) {
        return `<li><strong>${pollutant} :</strong> ${value.toFixed(2)} µg/m³</li>`;
    }

    function drawFilteredMarkers(pollutant) {
        // On vide le layer des marqueurs existants
        markersLayer.clearLayers();

        villes.forEach((ville) => {
            if (ville.lat && ville.lon && ville.pollutants[pollutant]) {
                const values = ville.pollutants[pollutant].map(d => parseFloat(d.value) || 0);
                const avg = calculateAverage(values);
                let icon;

                // Choix de l'icône en fonction du niveau moyen
                if (avg < 25) {
                    icon = L.icon({ iconUrl: '../images/green-icon.png', iconSize: [20, 20] });
                } else if (avg < 50) {
                    icon = L.icon({ iconUrl: '../images/orange-icon.png', iconSize: [20, 20] });
                } else {
                    icon = L.icon({ iconUrl: '../images/red-icon.png', iconSize: [20, 20] });
                }

                const marker = L.marker([ville.lat, ville.lon], { icon: icon });

                // Contenu du popup
                const popupContent = `
                <div class="popup-content">
                    <strong>Ville :</strong> ${ville.nom}<br>
                    ${ville.location !== 'Inconnu' ? `<strong>Localisation :</strong> ${ville.location}<br>` : ''}
                    <ul>
                        <li><strong>${pollutant} :</strong> ${avg.toFixed(2)} µg/m³</li>
                    </ul>
                    <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(ville.nom)}" id="see-more">Voir plus</a>
                </div>
            `;
                marker.bindPopup(popupContent);
                markersLayer.addLayer(marker);
            }
        });
    }

    // Gestion du select "pollutant-filter"
    const pollutantSelect = document.getElementById('pollutant-filter');
    if (pollutantSelect) {
        pollutantSelect.addEventListener('change', function() {
            const selectedPollutant = this.value;
            if (selectedPollutant) {
                // On vide les marqueurs existants et on affiche le filtre par niveau
                markersLayer.clearLayers();
                drawFilteredMarkers(selectedPollutant);
            } else {
                // Si aucun polluant n'est sélectionné, on réaffiche tous les marqueurs
                markersLayer.clearLayers();
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
    const villesData = document.getElementById('villes-data');
    if (villesData) {
        try {
            const villes = JSON.parse(villesData.textContent);
            initMap(villes);
        } catch (e) {
            console.error('Erreur lors de l\'analyse des données des villes :', e);
        }
    } else {
        console.error('Aucune donnée des villes trouvée.');
    }
});
