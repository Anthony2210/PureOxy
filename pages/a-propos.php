<?php
/**
 * a-propos.php
 *
 * Cette page présente des informations sur PureOxy.
 * Elle détaille également la composition du groupe de projet et propose des liens vers des articles.
 *
 * Références :
 * - ChatGPT pour des conseils sur la structuration et la documentation du code.
 *
 * Utilisation :
 * - Accéder à cette page pour afficher des informations sur PureOxy, la composition du groupe et pour naviguer
 *   vers des articles sur la qualité de l'air.
 *
 *  Fichier placé dans le dossier pages.
 */

session_start(); // Démarrage ou reprise de la session en cours
ob_start();      // Démarrage de la temporisation de sortie

include '../includes/header.php'; // Inclusion de l'en-tête du site
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos de PureOxy</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page à propos -->
    <link rel="stylesheet" href="../styles/apropos.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script>
</head>
<body>
<main>
    <!-- Section présentant les informations sur PureOxy -->
    <section id="apropos">
        <h2>À propos de PureOxy</h2>
        <p>
            PureOxy est une plateforme dédiée à la surveillance et à l'analyse de la qualité de l'air en France.
            Nous fournissons des informations en temps réel sur les niveaux de pollution atmosphérique et proposons des solutions pour améliorer la qualité de l'air.
        </p>
        <p>
            En utilisant des données collectées sur des polluants comme les particules fines (PM2.5, PM10) et les oxydes d'azote (NO₂),
            PureOxy vise à sensibiliser le public aux enjeux environnementaux et à encourager des politiques plus durables.
        </p>
    </section>

    <!-- Section présentant la composition du groupe de projet -->
    <section id="team">
        <h2>Composition du groupe</h2>
        <ul>
            <li>Ayoub Akkouh</li>
            <li>Anthony Combes-Aguéra</li>
            <li>Wassim Harraga</li>
            <li>Rekhis Mohamed Chaouki</li>
        </ul>
    </section>

    <!-- Section avec un appel à l'action vers des articles -->
    <section id="cta">
        <h2>Découvrez nos articles</h2>
        <p>Plongez dans des articles enrichissants qui couvrent des thématiques variées autour de la qualité de l'air.</p>
        <a href="qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
        <a href="lutte_pollution.php" class="button">Lutte contre la pollution de l'air</a>
    </section>
</main>
<?php
include '../includes/footer.php'; // Inclusion du pied de page du site
?>
</body>
</html>
