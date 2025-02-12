<?php
/**
 * bd.php
 *
 * Ce code établit une connexion à la base de données MySQL en utilisant les informations de configuration fournies.
 * Il initialise la variable `$conn` qui sera utilisée pour interagir avec la base de données dans d'autres scripts.
 *
 */

/**
 * Informations de connexion à la base de données.
 *
 * @var string $servername Adresse du serveur MySQL.
 * @var string $username   Nom d'utilisateur MySQL.
 * @var string $password   Mot de passe MySQL.
 * @var string $dbname     Nom de la base de données.
 */
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "pureoxy";

/**
 * Établit une connexion à la base de données MySQL.
 *
 * Utilise l'extension mysqli pour se connecter à la base de données.
 *
 * @var mysqli $conn Objet de connexion à la base de données.
 */
$conn = new mysqli($servername, $username, $password, $dbname);

/**
 * Vérifie la connexion à la base de données.
 *
 * Si la connexion échoue, le script s'arrête et affiche un message d'erreur.
 */
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}