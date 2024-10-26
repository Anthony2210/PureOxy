<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/style.css">
    <title>Résultats de la recherche - PureOxy</title>
    <!-- Inclure Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../script/erreur_formulaire.js"></script>
</head>
<body>
<?php include 'includes/header.php'; ?>

<section id="resultat_recherche">
    <h2>Résultats de la recherche</h2>
    <?php
    include 'bd/bd.php';

    // Récupérer la ville saisie
    if (isset($_POST['ville'])) {
        $ville = $_POST['ville'];

        // Préparer la requête SQL pour éviter les injections
        $stmt = $conn->prepare("SELECT * FROM pollution_villes WHERE city LIKE ?");
        $search = "%$ville%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();

        // Initialiser les variables pour le graphique
        $labels = [];
        $data = [];

        // Vérification des résultats
        if ($result->num_rows > 0) {
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($row['city']) . " - Pollution : " . htmlspecialchars($row['value']) . " µg/m³</li>";

                // Ajouter les données au tableau pour le graphique
                $labels[] = htmlspecialchars($row['city']);
                $data[] = htmlspecialchars($row['value']);
            }
            echo "</ul>";
        } else {
            echo "<p>Aucun résultat trouvé pour la ville : " . htmlspecialchars($ville) . "</p>";
        }

        // Fermer la requête
        $stmt->close();
    } else {
        echo "<p>Veuillez entrer un nom de ville.</p>";
    }
    ?>
</section>

<!-- Section pour le graphique -->
<section id="graphique_resultats">
    <h2>Graphique des résultats</h2>
    <canvas id="pollutionChart" width="400" height="200"></canvas>
</section>

<?php include 'footer.php'; ?>

<script>
    // Vérifier si des données existent pour créer le graphique
    var labels = <?php echo json_encode($labels); ?>;
    var data = <?php echo json_encode($data); ?>;

    if (labels.length > 0 && data.length > 0) {
        // Créer le graphique avec Chart.js
        var ctx = document.getElementById('pollutionChart').getContext('2d');
        var pollutionChart = new Chart(ctx, {
            type: 'bar', // Type de graphique (ici, un graphique à barres)
            data: {
                labels: labels, // Les villes
                datasets: [{
                    label: 'Niveaux de pollution (µg/m³)',
                    data: data, // Les valeurs de pollution
                    backgroundColor: 'rgba(54, 162, 235, 0.2)', // Couleur des barres
                    borderColor: 'rgba(54, 162, 235, 1)', // Couleur des bordures
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true // Le graphique commence à 0
                    }
                }
            }
        });
    }
</script>

</body>
</html>
