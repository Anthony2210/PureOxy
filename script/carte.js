/**
 * carte.js
 *
 * - Si aucun polluant n’est sélectionné => on affiche l’icône par défaut (dark-green).
 * - Si un polluant est sélectionné => on compare la valeur au seuil correspondant :
 *     ratio = val / threshold
 *     ratio < 0.8  => vert
 *     ratio < 1    => orange
 *     ratio >= 1   => rouge
 *
 * - On applique aussi un filtre mois, si sélectionné (voir la structure "monthly" dans ville.pollutants[polluant]).
 */

function initMap(villes) {
    // 1) Création et centrage de la carte
    window.map = L.map('map').setView([46.603354, 1.888334], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // 2) Icône par défaut (lorsqu’aucun polluant n’est sélectionné)
    const IconDarkGreen = L.icon({
        iconUrl: '../images/dark-green-icon.png',
        iconSize: [20, 20]
    });

    // Icônes pour vert/orange/rouge
    const IconGreen = L.icon({ iconUrl: '../images/green-icon.png', iconSize: [20, 20] });
    const IconOrange = L.icon({ iconUrl: '../images/orange-icon.png', iconSize: [20, 20] });
    const IconRed = L.icon({ iconUrl: '../images/red-icon.png', iconSize: [20, 20] });

    // 3) Création d’un layerGroup pour les marqueurs
    const markersLayer = L.layerGroup().addTo(map);

    // 4) Récupération des <select> polluant + mois
    const pollutantSelect = document.getElementById('pollutant-filter');
    const monthSelect     = document.getElementById('month-filter-select');

    // 5) Définition des seuils fixés pour chaque polluant
    //    (exemple - adapte à tes vraies valeurs)
    const polluantThresholds = {
        'PM2.5': 10,
        'PM10': 20,
        'NO': 40,
        'NO2': 40,
        'O3': 120,
        'CO': 10
    };

    /**
     * Fonction principale pour (ré)afficher tous les marqueurs selon les filtres
     */
    function drawMarkers() {
        markersLayer.clearLayers();

        const selectedPollutant = pollutantSelect ? pollutantSelect.value : '';
        const selectedMonth     = monthSelect ? monthSelect.value : '';

        // On parcourt chaque ville
        villes.forEach((ville) => {
            if (!ville.lat || !ville.lon) return; // skip si coords manquantes

            // Si aucun polluant n’est sélectionné => on affiche le marker "normal"
            if (!selectedPollutant) {
                // Marker par défaut
                const marker = L.marker([ville.lat, ville.lon], { icon: IconDarkGreen });
                marker.bindPopup(buildPopupContent(ville, null, 0)); // polluant=null, val=0
                markersLayer.addLayer(marker);
                return;
            }

            // Sinon, on regarde si la ville possède le polluant
            const pollData = ville.pollutants[selectedPollutant];
            if (!pollData) {
                // ville ne possède pas ce polluant => on n’affiche pas
                return;
            }

            // On récupère la valeur : si un mois est choisi => monthly[mois], sinon => avg_value
            let val = 0;
            if (selectedMonth) {
                val = parseFloat(pollData.monthly[selectedMonth]) || 0;
            } else {
                val = parseFloat(pollData.avg_value) || 0;
            }

            // Récupération du seuil
            let threshold = polluantThresholds[selectedPollutant] || 50; // fallback
            let ratio = val / threshold;

            // Choix de l’icône selon ratio
            let icon = IconGreen; // par défaut
            if (ratio < 0.8) {
                icon = IconGreen;
            } else if (ratio < 1) {
                icon = IconOrange;
            } else {
                icon = IconRed;
            }

            // Création du marker
            const marker = L.marker([ville.lat, ville.lon], { icon });
            marker.bindPopup(buildPopupContent(ville, selectedPollutant, val));
            markersLayer.addLayer(marker);
        });
    }

    /**
     * Construit le contenu du popup
     * - polluant peut être null si aucun polluant sélectionné
     * - val est la concentration (0 si aucun polluant)
     */
    function buildPopupContent(ville, polluant, val) {
        if (!polluant) {
            // Cas "aucun polluant" => on liste tous les polluants
            let pollList = '';
            for (let p in ville.pollutants) {
                let subVal = parseFloat(ville.pollutants[p].avg_value) || 0;
                pollList += `<li><strong>${p} :</strong> ${subVal.toFixed(2)} µg/m³</li>`;
            }
            return `
                <div class="popup-content">
                    <strong>Ville :</strong> ${ville.nom}<br>
                    ${ville.location !== 'Inconnu' ? `<strong>Département :</strong> ${ville.location}<br>` : ''}
                    <ul>${pollList}</ul>
                    <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(ville.nom)}" id="see-more">Voir plus</a>
                </div>
            `;
        } else {
            // Cas "un polluant" => on n’affiche que ce polluant
            return `
                <div class="popup-content">
                    <strong>Ville :</strong> ${ville.nom}<br>
                    ${ville.location !== 'Inconnu' ? `<strong>Département :</strong> ${ville.location}<br>` : ''}
                    <ul>
                        <li><strong>${polluant} :</strong> ${val.toFixed(2)} µg/m³</li>
                    </ul>
                    <a href="../fonctionnalites/details.php?ville=${encodeURIComponent(ville.nom)}" id="see-more">Voir plus</a>
                </div>
            `;
        }
    }

    // Écouteurs sur les deux filtres
    if (pollutantSelect) {
        pollutantSelect.addEventListener('change', () => {
            drawMarkers();
        });
    }
    if (monthSelect) {
        monthSelect.addEventListener('change', () => {
            drawMarkers();
        });
    }

    // Au démarrage, on dessine d'abord les marqueurs
    drawMarkers();
}

document.addEventListener('DOMContentLoaded', function() {
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
