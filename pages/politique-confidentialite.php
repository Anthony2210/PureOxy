<?php
/**
 * politique-confidentialite.php
 *
 * Cette page affiche la politique de confidentialité de PureOxy.
 * Elle structure le contenu principal décrivant les pratiques de gestion des données personnelles.
 *
 */
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureOxy - Politique de confidentialité</title>
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Styles de Base -->
    <link rel="stylesheet" href="../styles/base.css">
    <!-- Styles pour l'En-tête -->
    <link rel="stylesheet" href="../styles/includes.css">
    <!-- Styles pour la page politique de confidentialité -->
    <link rel="stylesheet" href="../styles/p-c.css">
    <!-- Styles pour les Boutons -->
    <link rel="stylesheet" href="../styles/boutons.css">
    <!-- Script de validation de formulaire -->
    <script src="../script/erreur_formulaire.js"></script>
</head>

<?php
include '../includes/header.php';
?>

<section id="politique-confidentialite">
    <h2>Politique de confidentialité</h2>
    <p>Dernière mise à jour : 29 septembre 2024</p>

    <h3>1. Introduction</h3>
    <p>
        Chez PureOxy, nous attachons une grande importance à la protection de vos données personnelles. Cette politique de confidentialité décrit
        les types de données que nous collectons, comment nous les utilisons, et vos droits concernant ces informations.
    </p>

    <h3>2. Données collectées</h3>
    <p>
        Nous collectons différents types de données dans le cadre de votre utilisation du site, notamment :
    </p>
    <ul>
        <li><strong>Données personnelles fournies volontairement :</strong> Lors de l'utilisation de notre formulaire de contact ou d'inscription, nous pouvons collecter votre nom, adresse e-mail, et autres informations de contact.</li>
        <li><strong>Données de navigation :</strong> Nous collectons automatiquement des données liées à votre navigation sur le site, telles que votre adresse IP, le type de navigateur utilisé, les pages visitées, et la durée de votre visite.</li>
        <li><strong>Cookies :</strong> Nous utilisons des cookies pour améliorer votre expérience sur notre site. Vous pouvez configurer votre navigateur pour bloquer les cookies, mais cela pourrait affecter certaines fonctionnalités du site.</li>
    </ul>

    <h3>3. Utilisation des données</h3>
    <p>
        Les données collectées sont utilisées dans les buts suivants :
    </p>
    <ul>
        <li>Améliorer votre expérience sur le site PureOxy.</li>
        <li>Répondre à vos demandes via le formulaire de contact.</li>
        <li>Analyser l'utilisation du site afin d'optimiser son contenu et ses fonctionnalités.</li>
        <li>Envoyer des notifications ou des communications relatives à PureOxy, si vous avez donné votre consentement.</li>
    </ul>

    <h3>4. Partage des données</h3>
    <p>
        Nous ne vendons ni ne louons vos données personnelles à des tiers. Cependant, nous pouvons partager vos informations avec des prestataires de services tiers qui nous assistent dans la gestion du site, tels que des services d'hébergement ou des outils d'analyse.
    </p>

    <h3>5. Sécurité des données</h3>
    <p>
        Nous prenons des mesures raisonnables pour protéger vos données personnelles contre toute perte, accès non autorisé ou divulgation. Toutefois, veuillez noter qu'aucune transmission de données sur Internet n'est totalement sécurisée et que nous ne pouvons garantir la sécurité absolue de vos informations.
    </p>

    <h3>6. Vos droits</h3>
    <p>
        Conformément au règlement général sur la protection des données (RGPD), vous avez le droit de :
    </p>
    <ul>
        <li>Accéder à vos données personnelles.</li>
        <li>Corriger ou mettre à jour vos informations personnelles.</li>
        <li>Demander la suppression de vos données.</li>
        <li>Limiter ou vous opposer au traitement de vos données.</li>
        <li>Retirer votre consentement à tout moment.</li>
    </ul>
    <p>Pour exercer vos droits, veuillez nous contacter via l'adresse e-mail indiquée dans la section "Contactez-nous".</p>

    <h3>7. Durée de conservation des données</h3>
    <p>
        Nous conservons vos données personnelles aussi longtemps que nécessaire pour vous fournir nos services ou selon les exigences légales applicables.
    </p>

    <h3>8. Modifications de la politique de confidentialité</h3>
    <p>
        Nous pouvons mettre à jour cette politique de confidentialité de temps à autre. Toute modification sera publiée sur cette page avec la date de révision mise à jour. Nous vous encourageons à consulter régulièrement cette page pour rester informé de tout changement.
    </p>

    <h3>9. Contactez-nous</h3>
    <p>
        Si vous avez des questions ou des préoccupations concernant notre politique de confidentialité ou la gestion de vos données personnelles, vous pouvez nous contacter à l'adresse suivante :
    </p>
    <p>
        PureOxy<br>
        Adresse email : contact@pureoxy.fr
    </p>
</section>

<?php
include '../includes/footer.php';
?>