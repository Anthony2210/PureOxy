<?php
/**
 * À Propos de PureOxy
 *
 * Cette page présente des informations sur PureOxy, une plateforme dédiée à la surveillance et à
 * l'analyse de la qualité de l'air en France. Elle inclut également des appels à l'action vers
 * des articles pertinents.
 *
 * @package PureOxy
 */

session_start(); // Démarre une nouvelle session ou reprend une session existante
ob_start();      // Démarre la temporisation de sortie

/**
 * Inclut l'en-tête de la page.
 *
 * L'en-tête contient généralement le logo, le menu de navigation, et d'autres éléments communs
 * à toutes les pages du site.
 *
 * @see ../includes/header.php
 */
include '../includes/header.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos de PureOxy</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/includes.css">
</head>
<body>
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
    <a href="../fonctionnalites/qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
    <a href="../fonctionnalites/lutte_pollution.php" class="button">Lutte contre la pollution de l'air</a>
</section>

<?php
/**
 * Inclut le pied de page de la page.
 *
 * Le pied de page contient généralement des informations de contact, des liens vers les réseaux sociaux,
 * et d'autres éléments communs à toutes les pages du site.
 *
 * @see ../includes/footer.php
 */
include '../includes/footer.php';
?>
</body>
</html>
