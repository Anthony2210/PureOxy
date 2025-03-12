<?php
session_start();
include '../bd/bd.php';

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
    <title>PureOxy - Données nationales</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page d'accueil -->
    <link rel="stylesheet" href="../styles/index.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Styles pour le chatbot -->
    <link rel="stylesheet" href="../styles/chatbot.css">
    <!-- Script du chatbot -->
    <script src="../script/chatbot.js" defer></script>
    <!-- Script du podium -->
    <script src="../script/podium.js" defer></script>

</head>
<body>
<?php include '../includes/header.php'; ?>

<!-- Section Podium, avec un menu déroulant pour choisir le polluant -->
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

    <!-- On passe nos données en JSON au JavaScript -->
    <script>
        var podiumData = <?php echo json_encode($podiumByPollutant, JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
    </script>
</section>
<?php include '../includes/footer.php'; ?>