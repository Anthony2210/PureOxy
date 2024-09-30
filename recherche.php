<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>Recherche de ville - PureOxy</title>
</head>
<body>
<?php include 'header.php'; ?>

<section id="recherche">
    <h2>Rechercher une ville</h2>
    <form action="resultat_recherche.php" method="POST">
        <input type="text" name="ville" placeholder="Entrez le nom d'une ville" required>
        <button type="submit">Rechercher</button>
    </form>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
