<?php
session_start();
ob_start();

require '../bd/bd.php'; // Connexion à la base de données
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Lutte contre la pollution de l'air</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/includes.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="content-wrapper">
    <main>
        <div id="main-content" class="container">
            <!-- Titre principal de la page -->
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
        <section id="cta" class="mt-5">
            <h2>À lire aussi </h2>
            <a href="../pages/qualite_air.php" class="button">Sources de pollution et effets sur la santé</a>
        </section>
    </main>
    <?php
    include '../fonctionnalites/commentaires.php';
    include '../includes/footer.php'; ?>
</div>
</body>
</html>

