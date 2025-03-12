<?php
// carte.php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Carte interactive</title>
    <!-- Liens CSS et JS spécifiques à la carte -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/carte.css"/>
    <link rel="stylesheet" href="../styles/commentaire.css">
    <link rel="stylesheet" href="../styles/boutons.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="../script/carte.js" defer></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<!-- Inclusion du contenu de la carte -->
<?php include('../fonctionnalites/carte_content.php'); ?>

<?php include '../includes/footer.php'; ?>
</body>
</html>
