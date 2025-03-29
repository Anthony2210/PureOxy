<?php
session_start();
require_once('../bd/bd.php');
$db = new Database();

if (isset($_GET['ville']) && !empty($_GET['ville'])) {
    $villeSaisie = trim($_GET['ville']);
    $stmt = $db->prepare("SELECT id_ville FROM donnees_villes WHERE ville = ? LIMIT 1");
    $stmt->bind_param("s", $villeSaisie);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $idVille = $row['id_ville'];
        $userId = $_SESSION['id_users'] ?? 0;
        $stmt2 = $db->prepare("
            INSERT INTO search_history (id_users, search_date, id_ville)
            VALUES (?, NOW(), ?)
        ");
        $stmt2->bind_param("ii", $userId, $idVille);
        $stmt2->execute();
        $stmt2->close();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Villes</title>
    <!-- Liens CSS et JS spécifiques à la recherche -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/recherche.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="../script/suggestions.js" defer></script>
</head>
<body id="recherche-page">
<div class="content-wrapper">
    <?php include('../includes/header.php'); ?>
    <main>
        <!-- Inclusion du contenu de la recherche -->
        <?php include('../fonctionnalites/recherche_content.php'); ?>
    </main>
    <?php include('../includes/footer.php'); ?>
</div>
</body>
</html>
