<?php
// Informations de connexion
$servername = "localhost";  // Adresse du serveur MySQL
$username = "root";          // Nom d'utilisateur MySQL
$password = "";              // Mot de passe MySQL (laisse vide si tu n'en as pas mis)
$dbname = "pureoxy";         // Nom de la base de données

// Créer la connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}
?>
