<?php
session_start();
include 'bd/bd.php';

// 1) Récupérer la moyenne pour chaque polluant/ville
$sql = "
    SELECT Pollutant, City, AVG(value) AS avg_val
    FROM pollution_villes
    GROUP BY Pollutant, City
    ORDER BY Pollutant, avg_val DESC
";
$result = $conn->query($sql);

$podiumByPollutant = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pollutant = $row['Pollutant'];
        $city      = $row['City'];
        $avgVal    = (float) $row['avg_val'];

        if (!isset($podiumByPollutant[$pollutant])) {
            $podiumByPollutant[$pollutant] = [];
        }
        $podiumByPollutant[$pollutant][] = [
            'city'    => $city,
            'avg_val' => $avgVal
        ];
    }
}

// 2) Garder le top 3 pour chaque polluant
foreach ($podiumByPollutant as $poll => &$rows) {
    $rows = array_slice($rows, 0, 3);
}
unset($rows);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Respirez mieux, vivez mieux</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="styles/includes.css">
    <!-- Styles pour la page d'accueil -->
    <link rel="stylesheet" href="styles/index.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="styles/boutons.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<!-- Section d'introduction à PureOxy -->
<section id="introduction">
    <h2>Bienvenue sur PureOxy</h2>
    <p>
        PureOxy est une plateforme interactive dédiée à l'analyse de la qualité de l'air en France.
        Consultez en temps réel les niveaux de pollution dans votre ville, obtenez des prévisions grâce à nos algorithmes de machine learning,
        et recevez des recommandations personnalisées pour protéger votre santé.
    </p>
</section>

<!-- Section présentant les fonctionnalités principales -->
<section id="features">
    <h2>Nos fonctionnalités</h2>
    <ul>
        <li><strong>Carte interactive</strong> : Visualisez les niveaux de pollution dans toute la France.</li>
        <li><strong>Recherche par ville</strong> : Trouvez rapidement les données de pollution pour votre ville.</li>
        <li><strong>Prédictions</strong> : Obtenez des prévisions sur la qualité de l'air grâce au machine learning.</li>
        <li><strong>Recommandations personnalisées</strong> : Recevez des conseils pour limiter l'impact de la pollution sur votre santé.</li>
    </ul>
</section>

<!-- Section Podium unique, avec un menu déroulant pour choisir le polluant -->
<section id="podium">
    <h2>Podium des villes les plus polluées</h2>
    <p>Sélectionnez un polluant pour afficher le Top 3 (moyenne la plus élevée) :</p>

    <!-- Menu déroulant -->
    <select id="pollutant-select">
        <?php
        // Lister les polluants
        $pollutantsList = array_keys($podiumByPollutant);
        foreach ($pollutantsList as $poll) {
            echo '<option value="'.htmlspecialchars($poll).'">'.htmlspecialchars($poll).'</option>';
        }
        ?>
    </select>

    <!-- Conteneur vide : le JS va générer le podium ici -->
    <div id="podiumContainer"></div>
</section>

<!-- Section d'appel à l'action pour commencer -->
<section id="cta">
    <h2>Commencez dès maintenant</h2>
    <a href="fonctionnalites/recherche.php" class="button">Cliquez ici pour rechercher une ville</a>
</section>

<?php include 'includes/footer.php'; ?>

<!-- On passe nos données en JSON au JavaScript -->
<script>
    var podiumData = <?php echo json_encode($podiumByPollutant, JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
</script>

<script>
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

        var html = escapeHTML(pollutant) +'</h3>';
        html += '<div class="podium-container">';

        // Place #2
        if (second) {
            html += `
        <div class="place place-2" title="2ème place">
            <div class="rank">2</div>
            <div class="city">
                ${escapeHTML(second.city)}
                <!-- On place la "médaille" à droite du nom -->
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
</script>
</body>
</html>
