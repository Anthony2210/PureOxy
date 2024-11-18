<?php
/**
 * Lutte contre la Pollution de l'Air - PureOxy
 *
 * Cette page présente des informations détaillées sur les efforts de lutte contre la pollution de l'air.
 * Elle aborde des sujets tels que le Plan National de Réduction des Emissions et la Pollution Industrielle.
 * La page inclut également une section de commentaires pour permettre aux utilisateurs de discuter et d'interagir.
 *
 * @package PureOxy
 * @subpackage PollutionControl
 */

session_start(); // Démarre une nouvelle session ou reprend une session existante
ob_start();      // Démarre la temporisation de sortie

require '../bd/bd.php'; // Connexion à la base de données

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Lutte contre la pollution de l'air</title>
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
 * Inclut l'en-tête de la page.
 *
 * L'en-tête contient généralement le logo, le menu de navigation, et d'autres éléments communs
 * à toutes les pages du site.
 *
 * @see ../includes/header.php
 */
include '../includes/header.php';
?>

<div class="content-wrapper">
    <main>
        <div id="main-content" class="container">
            <!-- Section principale de contenu -->
            <section class="content-section">
                <h2>Lutte contre la pollution de l’air</h2>
                <p>
                    La qualité de l’air est un enjeu majeur pour la santé et l’environnement. La politique en faveur de la qualité de l’air
                    nécessite des actions à tous les niveaux, national comme local, et dans tous les secteurs d’activité. L’État, les
                    collectivités territoriales, les opérateurs publics, les entreprises, les citoyens et les organisations non gouvernementales
                    doivent conjuguer leurs efforts pour garantir à chacun le droit de respirer un air qui ne nuise pas à sa santé.
                </p>
            </section>

            <!-- Plan National de Réduction des Emissions -->
            <section class="content-section">
                <h3>Plan national de réduction des émissions de polluants atmosphériques (PREPA)</h3>
                <p>
                    Le plan national de réduction des émissions de polluants atmosphériques (PREPA) fixe la stratégie de l'État pour
                    réduire les émissions de polluants atmosphériques au niveau national et respecter les exigences européennes. C’est
                    l’un des outils de déclinaison de la politique climat air énergie.
                </p>
                <p>
                    Il combine les différents outils de politique publique : réglementations sectorielles, mesures fiscales, initiatives,
                    actions de sensibilisation et de mobilisation des acteurs, action d'amélioration des connaissances. Il regroupe dans
                    un document unique les orientations de l’État en faveur de la qualité de l’air sur le moyen et long termes dans de
                    nombreux secteurs : industrie, transport, résidentiel-tertiaire et agriculture.
                </p>
                <div class="image-wrapper">
                    <img src="../images/pollution-moyen-déplacement.png" alt="Graphique des déplacements exposés à la pollution" style="width: 30%; height: auto;">
                </div>
            </section>

            <!-- Pollution Industrielle -->
            <section class="content-section">
                <h3>Pollution Industrielle</h3>
                <p>
                    La réduction de la pollution de l’air a des conséquences significatives sur les industries, tant
                    positives que négatives, selon la manière dont elles s’adaptent à de nouvelles réglementations et
                    technologies plus écologiques. Voici quelques effets clés :
                </p>
                <ul>
                    <li><strong>Adaptation des industries aux normes environnementales</strong></li>
                    <li><strong>Coûts initiaux élevés</strong> : Les industries doivent investir dans des technologies de réduction
                        des émissions, telles que des systèmes de filtration, des énergies renouvelables, ou des véhicules électriques,
                        ce qui entraîne des coûts initiaux importants.
                    </li>
                    <li><strong>Réorganisation des processus</strong> : Certaines industries sont contraintes de modifier leurs
                        procédés de production pour respecter des limites plus strictes d’émissions de particules fines et de gaz à effet de serre.
                    </li>
                </ul>
            </section>
        </div>

        <!-- Appel à l'action -->
        <section id="cta" class="mt-5">
            <h2>À lire aussi</h2>
            <a href="qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
        </section>
    </main>

    <?php
    /**
     * Inclut la section des commentaires de la page.
     *
     * Permet aux utilisateurs de poster, liker, et interagir avec les commentaires relatifs à cette page.
     *
     * @see ../fonctionnalites/commentaires.php
     */
    include '../fonctionnalites/commentaires.php';

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
</div>
</body>
</html>
