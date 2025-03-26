<?php
session_start();
ob_start();

require_once '../bd/bd.php'; // Connexion BD

// Vérification paramètre GET ?ville
if (!isset($_GET['ville']) || empty($_GET['ville'])) {
    echo "Aucune ville spécifiée !";
    exit;
}
$nomVille = $_GET['ville'];

// ---------------------------------------------------------------------
// 1) Récupération des infos de la ville (table donnees_villes)
// ---------------------------------------------------------------------
$sqlVille = $conn->prepare("
    SELECT 
        id_ville, 
        ville, 
        postal_code, 
        latitude, 
        longitude, 
        departement, 
        region, 
        population, 
        superficie_km2, 
        densite, 
        grille_densite_texte
    FROM donnees_villes
    WHERE ville = ?
    LIMIT 1
");
$sqlVille->bind_param("s", $nomVille);
$sqlVille->execute();
$resVille  = $sqlVille->get_result();
$infoVille = $resVille->fetch_assoc();
$sqlVille->close();

if (!$infoVille) {
    echo "Ville introuvable dans la base de données.";
    exit;
}
$idVille = (int) $infoVille['id_ville'];

// ---------------------------------------------------------------------
// 2) Récupération du rang par polluant (classement global)
// ---------------------------------------------------------------------
$sqlPolluants = $conn->prepare("
    SELECT 
      t1.polluant,
      t1.avg_value,
      (
        SELECT COUNT(*) + 1
        FROM moy_pollution_villes t2
        WHERE t2.polluant = t1.polluant
          AND t2.avg_value < t1.avg_value
      ) AS rang,
      (
        SELECT COUNT(*)
        FROM moy_pollution_villes t3
        WHERE t3.polluant = t1.polluant
      ) AS total
    FROM moy_pollution_villes t1
    WHERE t1.id_ville = ?
");
$sqlPolluants->bind_param("i", $idVille);
$sqlPolluants->execute();
$resPoll   = $sqlPolluants->get_result();
$listePolluants = [];
while ($row = $resPoll->fetch_assoc()) {
    $listePolluants[] = $row;
}
$sqlPolluants->close();

/**
 * Retourne une icône FontAwesome en fonction du polluant
 */
function getPolluantIcon($polluant) {
    switch (strtoupper($polluant)) {
        case 'NO':   return '<i class="fa-solid fa-cloud"></i> NO';
        case 'NO2':  return '<i class="fa-solid fa-cloud-bolt"></i> NO2';
        case 'O3':   return '<i class="fa-solid fa-wind"></i> O3';
        case 'PM10': return '<i class="fa-solid fa-cloud-meatball"></i> PM10';
        case 'PM2.5':return '<i class="fa-solid fa-smog"></i> PM2.5';
        case 'SO2':  return '<i class="fa-solid fa-cloud-showers-water"></i> SO2';
        case 'CO':   return '<i class="fa-solid fa-cloud-rain"></i> CO';
        default:     return '<i class="fa-solid fa-circle-question"></i> ' . htmlspecialchars($polluant);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Détails de <?php echo htmlspecialchars($infoVille['ville']); ?></title>

    <!-- Polices, Bootstrap, FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <!-- Vos styles -->
    <link rel="stylesheet" href="../styles/details.css">
    <link rel="stylesheet" href="../styles/includes.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="details-container">
    <!-- Bloc de gauche : infos sur la ville -->
    <div class="bloc-ville">
        <h1 class="ville-title">
            <?php echo htmlspecialchars($infoVille['ville']); ?>
        </h1>
        <p class="grille-texte">
            <?php echo nl2br(htmlspecialchars($infoVille['grille_densite_texte'])); ?>
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-user-group"></i> Population : </strong>
            <?php echo number_format($infoVille['population'], 0, ',', ' '); ?> habitants
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-map-pin"></i> Département : </strong>
            <?php echo htmlspecialchars($infoVille['departement']); ?>
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-location-dot"></i> Région : </strong>
            <?php echo htmlspecialchars($infoVille['region']); ?>
        </p>
        <p class="info-ligne">
            <strong><i class="fa-solid fa-map"></i> Superficie : </strong>
            <?php echo number_format($infoVille['superficie_km2'], 0, ',', ' '); ?> km²
        </p>
    </div>

    <!-- Bloc de droite : classement par polluant -->
    <div class="bloc-polluants">
        <h2 class="polluants-title">
            <i class="fa-solid fa-ranking-star"></i> Classement par Polluant
        </h2>
        <?php if (!empty($listePolluants)): ?>
            <table class="table-polluants">
                <thead>
                <tr>
                    <th>Polluant</th>
                    <th>Rang</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($listePolluants as $poll):
                    $polluant = $poll['polluant'];
                    $rang     = (int)$poll['rang'];
                    $total    = (int)$poll['total'];
                    ?>
                    <tr>
                        <td><?php echo getPolluantIcon($polluant); ?></td>
                        <td>
                            <i class="fa-solid fa-medal" style="color:#f4c542;"></i>
                            <?php echo $rang . ' / ' . $total; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="aucun-classement">Aucun classement disponible.</p>
        <?php endif; ?>
    </div>
</div>

<hr>

<!-- Onglets -->
<div class="container mt-4">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-hist" data-toggle="tab" href="#historiqueTab" role="tab">
                Historique
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-pred" data-toggle="tab" href="#predictionTab" role="tab">
                Prédictions
            </a>
        </li>
    </ul>
</div>

<div class="container tab-content py-3" id="tabsContent">
    <!-- Historique -->
    <div class="tab-pane fade show active" id="historiqueTab" role="tabpanel" aria-labelledby="tab-hist">
        <h3>Historique (Janv. 2023 à Janv. 2025)</h3>

        <!-- Filtres -->
        <div class="form-inline mb-3">
            <label class="mr-2">Polluant :</label>
            <select id="histPolluant" class="form-control mr-3">
                <option value="">-- Tous --</option>
                <option value="NO">NO</option>
                <option value="NO2">NO2</option>
                <option value="O3">O3</option>
                <option value="PM10">PM10</option>
                <option value="PM2.5">PM2.5</option>
                <option value="SO2">SO2</option>
                <option value="CO">CO</option>
            </select>

            <label class="mr-2">Mois :</label>
            <select id="histMois" class="form-control mr-3">
                <option value="">-- Aucun --</option>
                <option value="moy_janv2023">Janv. 2023</option>
                <option value="moy_fev2023">Févr. 2023</option>
                <option value="moy_mar2023">Mars 2023</option>
                <option value="moy_avril2023">Avril 2023</option>
                <option value="moy_mai2023">Mai 2023</option>
                <option value="moy_juin2023">Juin 2023</option>
                <option value="moy_juil2023">Juil. 2023</option>
                <option value="moy_aout2023">Août 2023</option>
                <option value="moy_sept2023">Sept. 2023</option>
                <option value="moy_oct2023">Oct. 2023</option>
                <option value="moy_nov2023">Nov. 2023</option>
                <option value="moy_dec2023">Déc. 2023</option>
                <option value="moy_janv2024">Janv. 2024</option>
                <option value="moy_fev2024">Févr. 2024</option>
                <option value="moy_mar2024">Mars 2024</option>
                <option value="moy_avril2024">Avril 2024</option>
                <option value="moy_mai2024">Mai 2024</option>
                <option value="moy_juin2024">Juin 2024</option>
                <option value="moy_juil2024">Juil. 2024</option>
                <option value="moy_aout2024">Août 2024</option>
                <option value="moy_sept2024">Sept. 2024</option>
                <option value="moy_oct2024">Oct. 2024</option>
                <option value="moy_nov2024">Nov. 2024</option>
                <option value="moy_dec2024">Déc. 2024</option>
                <option value="moy_janv2025">Janv. 2025</option>
            </select>

            <button id="btnHistFilter" class="btn btn-primary">Filtrer</button>
        </div>

        <!-- Conteneur du tableau de moyennes mensuelles (Historique) -->
        <div id="histMonthlyContainer"></div>

        <!-- Conteneur du tableau de données journalières (Historique) -->
        <div id="histDailyContainer" class="mt-4"></div>
    </div>

    <!-- Prédictions -->
    <div class="tab-pane fade" id="predictionTab" role="tabpanel" aria-labelledby="tab-pred">
        <h3>Prédictions (Janv. 2025 à Janv. 2026)</h3>

        <!-- Filtres -->
        <div class="form-inline mb-3">
            <label class="mr-2">Polluant :</label>
            <select id="predPolluant" class="form-control mr-3">
                <option value="">-- Tous --</option>
                <option value="NO">NO</option>
                <option value="NO2">NO2</option>
                <option value="O3">O3</option>
                <option value="PM10">PM10</option>
                <option value="PM2.5">PM2.5</option>
                <option value="SO2">SO2</option>
                <option value="CO">CO</option>
            </select>

            <label class="mr-2">Mois :</label>
            <label class="mr-2">Mois :</label>
            <select id="predMois" class="form-control mr-3">
                <option value="">-- Aucun --</option>
                <option value="moy_predic_janv2025">Janv. 2025</option>
                <option value="moy_predic_fev2025">Févr. 2025</option>
                <option value="moy_predic_mars2025">Mars 2025</option>
                <option value="moy_predic_avril2025">Avr. 2025</option>
                <option value="moy_predic_mai2025">Mai 2025</option>
                <option value="moy_predic_juin2025">Juin 2025</option>
                <option value="moy_predic_juil2025">Juil. 2025</option>
                <option value="moy_predic_aout2025">Août 2025</option>
                <option value="moy_predic_sept2025">Sept. 2025</option>
                <option value="moy_predic_oct2025">Oct. 2025</option>
                <option value="moy_predic_nov2025">Nov. 2025</option>
                <option value="moy_predic_dec2025">Déc. 2025</option>
                <option value="moy_predic_janv2026">Janv. 2026</option>
            </select>


            <button id="btnPredFilter" class="btn btn-success">Filtrer</button>
        </div>

        <!-- Conteneur du tableau de moyennes mensuelles (Prédictions) -->
        <div id="predMonthlyContainer"></div>

        <!-- Conteneur du tableau de données journalières (Prédictions) -->
        <div id="predDailyContainer" class="mt-4"></div>
    </div>
</div>

<script>
    // On stocke l'idVille pour nos appels AJAX
    const ID_VILLE = <?php echo (int)$infoVille['id_ville']; ?>;
</script>

<!-- jQuery, Popper, Bootstrap -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- Script AJAX -->
<script src="../script/details.js"></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
