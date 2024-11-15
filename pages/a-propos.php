<?php
session_start();
ob_start();
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles/style.css">
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles/includes.css">

<main>
<section id="apropos">
    <h2>À propos de PureOxy</h2>
    <p>
        PureOxy est une plateforme dédiée à la surveillance et à l'analyse de la qualité de l'air en France.
        Nous fournissons des informations en temps réel sur les niveaux de pollution atmosphérique et proposons des solutions pour aider à améliorer la qualité de l'air.
    </p>
</section>
</main>

<!-- Appel à l'action -->
<section id="cta" class="mt-5">
    <h2>Nos articles</h2>
    <a href="../pages/qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
    <a href="lutte_pollution.php" class="button">Lutte contre la pollution de l'air</a>
</section>
<?php include '../includes/footer.php'; ?>
