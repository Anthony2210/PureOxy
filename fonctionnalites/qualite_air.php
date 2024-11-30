<?php
/**
 * Page de Qualité de l'Air
 *
 * Cette page affiche des informations sur la qualité de l'air, les sources de pollution
 * et leurs effets sur la santé. Elle inclut également des sections pour les commentaires
 * des utilisateurs et le pied de page du site.
 *
 * @package PureOxy
 */

session_start();
ob_start();

/**
 * Inclusion du fichier de connexion à la base de données.
 *
 * Ce fichier établit une connexion à la base de données nécessaire pour le fonctionnement de la page.
 */
require '../bd/bd.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Qualité de l'air et effets sur la santé</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Lien Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page à propos -->
    <link rel="stylesheet" href="../styles/articles.css">
    <!-- Styles pour les commentaires -->
    <link rel="stylesheet" href="../styles/commentaire.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script>
</head>
<body>
<?php
/**
 * Inclusion de l'en-tête de la page.
 *
 * Le fichier header.php contient l'en-tête commun à toutes les pages du site, incluant le logo,
 * le menu de navigation et éventuellement d'autres éléments récurrents.
 */
include '../includes/header.php';
?>

<div class="content-wrapper">
    <main>
        <div id="main-content" class="container">
            <!-- Section sur les sources de pollution -->
            <section class="content-section">
                <h2>Qualité de l’air : Sources de pollution et effets sur la santé</h2>
                <p><strong>La pollution de l’air</strong> est un mélange complexe, en évolution constante, de composés qui peuvent être :</p>
                <ul>
                    <li><strong>Chimiques</strong> : comme les particules, l’ozone, le dioxyde et les oxydes d’azote, le dioxyde de soufre, les métaux (arsenic, plomb), certains composés organiques volatils (COV) comme le butane, l’éthanol ou le benzène, ou encore des hydrocarbures (hydrocarbures aromatiques polycycliques – HAP) présents dans le charbon, le pétrole, ou provenant de la combustion des carburants ou du bois.</li>
                    <li><strong>Biologiques</strong> : tels que les pollens et les moisissures.</li>
                </ul>
                <p>Ces polluants de l’air proviennent en minorité de phénomènes d’origine naturelle (vents de sable du Sahara, érosion des sols, éruptions volcaniques, feux de végétation…) et en majorité des activités humaines.</p>
                <div class="image-wrapper">
                    <img src="../images/qualité-air-graph.png" alt="Graphique sur la qualité de l'air">
                </div>
            </section>

            <!-- Section sur les effets de la pollution sur la santé -->
            <section class="content-section">
                <h2>Effet des épisodes de pollution sur la santé</h2>
                <p>Les effets de la pollution de l’air sur la santé observés suite à une exposition de quelques heures à quelques jours (exposition aiguë, dite à court terme) sont les suivants : irritations oculaires ou des voies respiratoires, crises d’asthme, exacerbation de troubles cardio-vasculaires et respiratoires pouvant conduire à une hospitalisation, et dans les cas les plus graves au décès.</p>
                <p>En France, l’exposition à long terme à la pollution de l’air conduit aux impacts les plus importants sur la santé et la part des effets sanitaires attribuables aux épisodes de pollution demeure faible (source : Santé publique France). L’impact prépondérant sur la santé de la pollution de l’air est donc dû à l’exposition tout au long de l’année aux niveaux moyens de pollution et non aux pics.</p>
                <p>Santé Publique France estime que chaque année près de 40 000 décès seraient attribuables à une exposition des personnes âgées de 30 ans et plus aux particules fines (PM2,5). La pollution de l’air ambiant est ainsi un facteur de risque important pour la santé en France puisqu’elle représente 7% de la mortalité totale de la population française attribuable à une exposition aux PM2,5.</p>
                <div class="image-wrapper">
                    <img src="../images/pollution.png" alt="Image de la pollution">
                </div>
            </section>
        </div>
        <section id="cta" class="mt-5">
            <h2>À lire aussi </h2>
            <a href="lutte_pollution.php" class="button">Lutte contre la pollution de l'air</a>
        </section>

    </main>
    <?php
    /**
     * Inclusion de la section des commentaires.
     *
     * Le fichier commentaires.php gère l'affichage et la soumission des commentaires des utilisateurs,
     * permettant ainsi l'interaction et le partage d'expériences concernant la qualité de l'air.
     */
    include '../fonctionnalites/commentaires.php';

    /**
     * Inclusion du pied de page de la page.
     *
     * Le fichier footer.php contient le pied de page commun à toutes les pages du site, incluant
     * des liens utiles, des informations de contact et d'autres éléments récurrents.
     */
    include '../includes/footer.php';
    ?>
</div>
</body>
</html>
