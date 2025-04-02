/**
 * carte.js
 *
 * Ce script gère l'affichage interactif de la carte Leaflet de PureOxy.
 * Les principales fonctionnalités incluent :
 *  - L'initialisation et le centrage de la carte.
 *  - La gestion des marqueurs avec différentes icônes selon la valeur mesurée par rapport au seuil.
 *  - La prise en compte des filtres (polluant et mois) pour afficher les marqueurs correspondants.
 *
 * Références :
 * - ChatGPT pour la structuration et les commentaires du code.
 *
 * Utilisation :
 * - Ce script est chargé en différé dans "carte.php" et "carte_content.php".
 *
 * Fichier placé dans le dossier script.
 */

function initMap(villes) {
    // 1) Création et centrage de la carte sur la France
    window.map = L.map('map').setView([46.603354, 1.888334], 6);

    // Ajout d'une couche de tuiles OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // 2) Définition des icônes pour les marqueurs
    // Icône par défaut (aucun polluant sélectionné)
    const IconDarkGreen = L.icon({
        iconUrl: '../images/dark-green-icon.png',
        iconSize: [20, 20]
    });
    // Icônes pour les différents états (vert, orange, rouge)
    const IconGreen = L.icon({ iconUrl: '../images/green-icon.png', iconSize: [20, 20] });
    const IconOrange = L.icon({ iconUrl: '../images/orange-icon.png', iconSize: [20, 20] });
    const IconRed = L.icon({ iconUrl: '../images/red-icon.png', iconSize: [20, 20] });

    // 3) Création d'un groupe de couches pour les marqueurs
    const markersLayer = L.layerGroup().addTo(map);

    // 4) Récupération des éléments de filtrage (sélection de polluant et de mois)
    const pollutantSelect = document.getElementById('pollutant-filter');
    const monthSelect     = document.getElementById('month-filter-select');

    // 5) Définition des seuils pour chaque polluant (exemple, à adapter)
    const polluantThresholds = {
        'PM2.5': 10,
        'PM10': 20,
        'NO': 40,
        'NO2': 40,
        'O3': 120,
        'CO': 10
    };

    /**
     * Fonction drawMarkers
     * Efface les marqueurs existants et en dessine de nouveaux en fonction des filtres.
     */
    function drawMarkers() {
        // Suppression de tous les marqueurs existants
        markersLayer.clearLayers();

        // Récupération des filtres sélectionnés
        const selectedPollutant = pollutantSelect ? pollutantSelect.value : '';
        const selectedMonth     = monthSelect ? monthSelect.value : '';

        // Parcours de la liste des villes pour ajouter un marqueur pour chaque ville
        villes.forEach((ville) => {
            if (!ville.lat || !ville.lon) return; // Ignorer les villes sans coordonnées

            // Si aucun polluant n'est sélectionné, afficher le marqueur par défaut
            if (!selectedPollutant) {
                const marker = L.marker([ville.lat, ville.lon], { icon: IconDarkGreen });
                marker.bindPopup(buildPopupContent(ville, null, 0)); // Aucun polluant sélectionné => valeur 0
                markersLayer.addLayer(marker);
                return;
            }

            // Vérifier que la ville possède des données pour le polluant sélectionné
            const pollData = ville.pollutants[selectedPollutant];
            if (!pollData) return; // Ignorer si données absentes

            // Récupération de la valeur mesurée
            let val = 0;
            if (selectedMonth) {
                // Si un mois est sélectionné, utiliser la valeur mensuelle
                val = parseFloat(pollData.monthly[selectedMonth]) || 0;
            } else {
                // Sinon, utiliser la valeur moyenne
                val = parseFloat(pollData.avg_value) || 0;
            }

            // Récupération du seuil pour le polluant sélectionné
            let threshold = polluantThresholds[selectedPollutant] || 50; // Valeur par défaut si non définie
            let ratio = val / threshold;

            // Choix de l'icône en fonction du ratio (comparaison valeur/seuil)
            let icon = IconGreen; // Par défaut vert
            if (ratio < 0.8) {
                icon = IconGreen;
            } else if (ratio < 1) {
                icon = IconOrange;
            } else {
                icon = IconRed;
            }

            // Création du marqueur avec l'icône choisie et liaison d'une popup
            const marker = L.marker([ville.lat, ville.lon], { icon });
            marker.bindPopup(buildPopupContent(ville, selectedPollutant, val));
            markersLayer.addLayer(marker);
        });
    }

    /**
     * Fonction buildPopupContent
     * Construit le contenu HTML de la popup pour un marqueur.
     *
     * @param {Object} ville - Les données de la ville.
     * @param {string|null} polluant - Le polluant sélectionné (ou null).
     * @param {number} val - La valeur mesurée du polluant.
     * @returns {string} Le contenu HTML de la popup.
     */
    function buildPopupContent(ville, polluant, val) {
        if (!polluant) {
            // Si aucun polluant n'est sélectionné, lister tous les polluants disponibles pour la ville
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
            // Si un polluant est sélectionné, afficher uniquement la valeur correspondante
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

    // Ajout d'écouteurs sur les éléments de filtre pour redessiner les marqueurs lors d'un changement
    if (pollutantSelect) {
        pollutantSelect.addEventListener('change', drawMarkers);
    }
    if (monthSelect) {
        monthSelect.addEventListener('change', drawMarkers);
    }

    // Au chargement de la page, dessiner les marqueurs
    drawMarkers();
}

// Initialisation de la carte après chargement complet du DOM
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
