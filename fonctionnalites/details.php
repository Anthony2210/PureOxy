<?php
include '../includes/header.php';
include '../bd/bd.php';

// Récupérer le nom de la ville depuis l'URL
$ville = isset($_GET['ville']) ? $_GET['ville'] : '';

if ($ville) {
    // Requête SQL pour obtenir les données spécifiques à la ville
    $sql = "SELECT * FROM pollution_villes WHERE City = ? ORDER BY `LastUpdated`";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ville);
    $stmt->execute();
    $result = $stmt->get_result();

    // Préparer les données à afficher dans le tableau
    $pollutants_data = [];
    $dates = [];
    $arrondissements = [];

    while ($row = $result->fetch_assoc()) {
        $pollutant = $row['Pollutant'];
        $date = $row['LastUpdated'];
        $location = $row['Location'];

        // Formater la date en "Mois AAAA"
        $formattedDate = date('F Y', strtotime($date));

        // Ajouter l'arrondissement dans le tableau s'il n'est pas encore présent (pour Paris, Lyon, Marseille)
        if (in_array($ville, ['Paris', 'Lyon', 'Marseille']) && !in_array($location, $arrondissements)) {
            $arrondissements[] = $location;
        }

        // Identifier l'entrée par la date et la localisation
        $columnIdentifier = $formattedDate;
        if ($location !== 'Inconnu') {
            $columnIdentifier .= ' - ' . $location;
        }

        // Éviter les doublons dans les colonnes
        if (!in_array($columnIdentifier, array_column($dates, 'identifier'))) {
            $dates[] = ['date' => $formattedDate, 'location' => $location, 'identifier' => $columnIdentifier];
        }

        // Ajouter les données du polluant
        if (!isset($pollutants_data[$pollutant])) {
            $pollutants_data[$pollutant] = [];
        }
        $pollutants_data[$pollutant][$columnIdentifier] = $row['value'];
    }

    // Afficher les détails de la ville
    echo "<h1 class='centered-title'>" . htmlspecialchars($ville) . "</h1>";

    // Afficher le sélecteur d'arrondissement si la ville est Paris, Lyon ou Marseille
    if (in_array($ville, ['Paris', 'Lyon', 'Marseille'])) {
        echo '<select id="arrondissement-select">';
        echo '<option value="all">Tous les arrondissements</option>';
        foreach ($arrondissements as $arrondissement) {
            echo '<option value="' . htmlspecialchars($arrondissement) . '">' . htmlspecialchars($arrondissement) . '</option>';
        }
        echo '</select>';
    }

    // Afficher le tableau
    echo '<div class="table-container">';
    echo '<table id="details-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Polluant</th>';

    // Afficher les dates dans les colonnes avec la localisation en dessous si présente
    foreach ($dates as $entry) {
        echo '<th data-location="' . htmlspecialchars($entry['location']) . '">';
        echo htmlspecialchars($entry['date']);
        if ($entry['location'] !== 'Inconnu') {
            echo '<br><small>' . htmlspecialchars($entry['location']) . '</small>';
        }
        echo '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Afficher les données des polluants
    foreach ($pollutants_data as $pollutant => $data) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($pollutant) . '</td>';

        // Afficher les valeurs par date
        foreach ($dates as $entry) {
            $identifier = $entry['identifier'];
            if (isset($data[$identifier])) {
                echo '<td data-location="' . htmlspecialchars($entry['location']) . '">' . htmlspecialchars($data[$identifier]) . ' µg/m³</td>';
            } else {
                echo '<td data-location="' . htmlspecialchars($entry['location']) . '">/</td>'; // Pas de données pour cette date
            }
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo "<h1>Erreur : Ville non spécifiée</h1>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Données détaillées de <?php echo htmlspecialchars($ville); ?></title>
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/details.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <script src="../script/erreur_formulaire.js"></script>

</head>
<body>

<script>
    // Filtrer les colonnes du tableau par arrondissement
    document.getElementById('arrondissement-select').addEventListener('change', function() {
        var selectedArrondissement = this.value;
        var columns = document.querySelectorAll('#details-table th, #details-table td');

        columns.forEach(function(column) {
            var location = column.getAttribute('data-location');

            // Si c'est la colonne des polluants, on la montre toujours
            if (column.cellIndex === 0) {
                column.style.display = '';  // Toujours afficher la première colonne (polluants)
            } else if (selectedArrondissement === 'all' || location === selectedArrondissement) {
                column.style.display = '';  // Afficher les colonnes correspondant à l'arrondissement sélectionné
            } else {
                column.style.display = 'none';  // Masquer les colonnes qui ne correspondent pas
            }
        });
    });


    // Pagination du tableau avec JS
    var currentStart = 0;
    var columnsToShow = 5;  // Nombre de colonnes (dates) à afficher à la fois

    function updatePageInfo() {
        var totalColumns = document.querySelectorAll("#details-table th:not(:first-child)").length;
        var currentPage = Math.floor(currentStart / columnsToShow) + 1;
        var totalPages = Math.ceil(totalColumns / columnsToShow);
        document.getElementById("page-info").innerText = `Page ${currentPage} sur ${totalPages}`;

        // Activer ou désactiver les boutons
        document.getElementById("prev-button").disabled = currentStart <= 0;
        document.getElementById("next-button").disabled = currentStart + columnsToShow >= totalColumns;
    }

    function paginateTable() {
        var allColumns = document.querySelectorAll("#details-table th:not(:first-child), #details-table td:not(:first-child)");

        allColumns.forEach(function(column, index) {
            if (index % allColumns.length >= currentStart && index % allColumns.length < currentStart + columnsToShow) {
                column.style.display = '';
            } else {
                column.style.display = 'none';
            }
        });

        updatePageInfo();
    }

    document.getElementById("prev-button").addEventListener("click", function() {
        if (currentStart > 0) {
            currentStart -= columnsToShow;
            paginateTable();
        }
    });

    document.getElementById("next-button").addEventListener("click", function() {
        var totalColumns = document.querySelectorAll("#details-table th:not(:first-child)").length;
        if (currentStart + columnsToShow < totalColumns) {
            currentStart += columnsToShow;
            paginateTable();
        }
    });

    // Initialiser la pagination au chargement de la page
    paginateTable();
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
