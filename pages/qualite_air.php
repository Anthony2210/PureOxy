<?php
/**
 * qualite_air.php
 *
 * Cette page affiche des informations sur la qualité de l'air, les sources de pollution
 * et leurs effets sur la santé.
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
    <title>PureOxy - Qualité de l'air et effets sur la santé</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
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
            <!-- Sources de pollution -->
            <section class="content-section">
                <h2>Qualité de l’air : Sources de pollution et effets sur la santé</h2>
                <p>
                    <strong>La pollution de l’air</strong> est un mélange complexe et en constante évolution de divers composés, dont certains sont d’origine chimique (particules, ozone, oxydes d’azote, dioxyde de soufre, métaux lourds, composés organiques volatils, hydrocarbures aromatiques polycycliques) et d’autres d’origine biologique (pollens, moisissures).
                </p>
                <p>
                    Alors que certaines sources de pollution sont naturelles (vents de sable, érosion, éruptions volcaniques, feux de végétation), la majorité provient des activités humaines.
                </p>
                <div class="image-wrapper">
                    <img src="../images/qualité-air-graph.png" alt="Graphique sur la qualité de l'air">
                </div>
            </section>

            <!-- Effets sur la santé -->
            <section class="content-section">
                <h2>Effets des épisodes de pollution sur la santé</h2>
                <p>
                    L'exposition aiguë à la pollution, même de courte durée, peut provoquer des irritations oculaires, des problèmes respiratoires, des crises d'asthme ainsi qu’une exacerbation de troubles cardio-vasculaires. Dans les cas les plus graves, ces effets peuvent nécessiter une hospitalisation, voire entraîner le décès.
                </p>
                <p>
                    En France, l’exposition prolongée à la pollution de l’air contribue significativement aux impacts sanitaires, principalement en raison des niveaux moyens de pollution rencontrés toute l’année. Santé Publique France estime qu’environ 40 000 décès annuels seraient attribuables aux particules fines (PM2,5) chez les personnes de plus de 30 ans, représentant près de 7% de la mortalité totale.
                </p>
                <div class="image-wrapper">
                    <img src="../images/pollution.png" alt="Image illustrant la pollution">
                </div>
            </section>
        </div>

        <!-- Appel à l'action -->
        <section id="cta" class="mt-5">
            <h2>À lire aussi</h2>
            <a href="lutte_pollution.php" class="button">Lutte contre la pollution de l'air</a>
        </section>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>
