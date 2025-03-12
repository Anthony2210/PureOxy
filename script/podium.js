// Fonction d'échappement
function escapeHTML(str) {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

// Fonction pour construire le podium HTML
function buildPodiumHTML(pollutant) {
    var rows = podiumData[pollutant] || [];
    if (rows.length === 0) {
        return '<p>Aucune donnée pour ce polluant.</p>';
    }

    // On récupère 1er, 2e, 3e
    var first  = rows[0] || null;
    var second = rows[1] || null;
    var third  = rows[2] || null;


    var html = '<div class="podium-container">';

    // Place #2
    if (second) {
        html += `
        <div class="place place-2" title="2ème place">
            <div class="rank">2</div>
            <div class="city">
                ${escapeHTML(second.city)}
            </div>
            <div class="val">${parseFloat(second.avg_val).toFixed(2)} µg/m³</div>
        </div>
        `;
    }

    // Place #1
    if (first) {
        html += `
        <div class="place place-1" title="1ère place">
            <div class="rank">1</div>
            <div class="city">
                ${escapeHTML(first.city)}
                <span class="medal-icon medal-gold" title="Or"></span>
            </div>
            <div class="val">${parseFloat(first.avg_val).toFixed(2)} µg/m³</div>
        </div>
        `;
    }

    // Place #3
    if (third) {
        html += `
        <div class="place place-3" title="3ème place">
            <div class="rank">3</div>
            <div class="city">
                ${escapeHTML(third.city)}
            </div>
            <div class="val">${parseFloat(third.avg_val).toFixed(2)} µg/m³</div>
        </div>
        `;
    }

    html += '</div>';
    return html;
}

document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('pollutant-select');
    var container = document.getElementById('podiumContainer');

    function updatePodium() {
        var selectedPollutant = select.value;
        container.innerHTML = buildPodiumHTML(selectedPollutant);
    }

    // Au changement
    select.addEventListener('change', updatePodium);

    // Afficher le premier polluant par défaut
    if (select.value) {
        updatePodium();
    }
});