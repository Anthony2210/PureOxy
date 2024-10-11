<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <script src="erreur_formulaire.js"></script>

</head>
<?php include 'header.php'; ?>

<section id="contact">
    <h2>Contactez-nous</h2>
    <form action="envoyer_message.php" method="POST">
        <input type="text" name="nom" placeholder="Votre nom" required>
        <input type="email" name="email" placeholder="Votre email" required>
        <textarea name="message" placeholder="Votre message" required></textarea>
        <button type="submit">Envoyer</button>
    </form>
</section>

<?php include 'footer.php'; ?>
