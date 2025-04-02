<?php
/**
 * lutte_pollution.php
 *
 * Cette page présente des informations détaillées sur les efforts de lutte contre la pollution de l'air.
 *
 * Fichier placé dans le dossier pages.
 */

session_start();
ob_start();

require '../bd/bd.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Lutte contre la pollution de l'air</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <!-- Feuilles de style -->
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/articles.css">
    <link rel="stylesheet" href="../styles/boutons.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<main>
    <div class="content-wrapper">
        <div id="main-content" class="container">
            <!-- Section principale -->
            <section class="content-section">
                <h2>Lutte contre la pollution de l’air</h2>
                <p>
                    La qualité de l’air est un enjeu majeur pour la santé et l’environnement. La politique en faveur de la qualité de l’air
                    nécessite des actions à tous les niveaux – national comme local – et dans tous les secteurs d’activité. L’État, les
                    collectivités territoriales, les opérateurs publics, les entreprises, les citoyens et les organisations non gouvernementales
                    doivent conjuguer leurs efforts pour garantir à chacun le droit de respirer un air sain.
                </p>
            </section>

            <!-- Plan National de Réduction des Emissions -->
            <section class="content-section">
                <h3>Plan national de réduction des émissions de polluants atmosphériques (PREPA)</h3>
                <p>
                    Le PREPA définit la stratégie de l'État pour réduire les émissions de polluants atmosphériques et respecter les exigences européennes.
                    Il constitue un outil déclinaison de la politique climat-air-énergie.
                </p>
                <p>
                    Ce plan combine diverses mesures : réglementations sectorielles, actions fiscales, initiatives de sensibilisation et mobilisation des acteurs,
                    ainsi qu’une amélioration continue des connaissances sur le sujet. Il rassemble en un document unique les orientations de l’État pour la
                    qualité de l’air sur le moyen et long terme dans des secteurs variés comme l’industrie, le transport, le résidentiel-tertiaire et l’agriculture.
                </p>
                <div class="image-wrapper">
                    <img src="../images/pollution-moyen-déplacement.png" alt="Graphique des déplacements exposés à la pollution">
                </div>
            </section>

            <!-- Pollution Industrielle -->
            <section class="content-section">
                <h3>Pollution Industrielle</h3>
                <p>
                    La réduction de la pollution de l’air impacte les industries de plusieurs manières, selon leur capacité d’adaptation aux nouvelles réglementations et technologies écologiques. Voici quelques points clés :
                </p>
                <ul>
                    <li><strong>Adaptation aux normes environnementales</strong></li>
                    <li><strong>Coûts initiaux élevés</strong> : investissements importants dans des technologies de réduction des émissions (systèmes de filtration, énergies renouvelables, véhicules électriques…)</li>
                    <li><strong>Réorganisation des processus</strong> : modification des procédés de production pour se conformer aux limites d’émissions.</li>
                </ul>
            </section>
        </div>

        <!-- Section d'appel à l'action -->
        <section id="cta" class="mt-5">
            <h2>À lire aussi</h2>
            <a href="qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
        </section>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>
