<?php
/**
 * bd.php
 *
 * Ce fichier contient la classe Database qui permet d'établir et de gérer la connexion
 * à une base de données MySQL via l'extension MySQLi. Il centralise l'accès à la base
 * de données et sécurise l'exécution des requêtes SQL en utilisant la méthode prepare().
 *
 * Ce fichier a été conçu en respectant les bonnes pratiques de développement, comme
 * la séparation des responsabilités et la sécurisation contre les injections SQL.
 *
 * Références :
 * - ChatGPT pour des conseils sur la structuration et la documentation du code.
 *
 * Utilisation :
 * - Inclure ce fichier dans votre projet.
 * - Instancier la classe Database pour établir une connexion à la base de données.
 * - Utiliser la méthode prepare() pour préparer et exécuter les requêtes SQL de façon sécurisée.
 * - Utiliser la méthode getConnection() pour obtenir directement l'objet de connexion MySQLi si besoin.
 *
 * Fichier placé dans le dossier bd.
 *
 */

// Informations de connexion à la base de données
define('DB_SERVER', 'localhost');  // Adresse du serveur de base de données (souvent 'localhost' en environnement local)
define('DB_USERNAME', 'root');       // Nom d'utilisateur pour se connecter à MySQL
define('DB_PASSWORD', '');           // Mot de passe associé (laisser vide dans certains environnements de développement)
define('DB_NAME', 'pureoxy');        // Nom de la base de données à laquelle se connecter

/**
 * Classe Database
 *
 * Cette classe encapsule la gestion de la connexion à la base de données.
 * Elle établit une connexion via MySQLi dans le constructeur, configure le charset
 * pour éviter les problèmes d'encodage, et fournit des méthodes pour préparer des requêtes
 * SQL ainsi que pour accéder directement à l'objet de connexion.
 */
class Database {
    /**
     * @var mysqli $connection Instance de connexion MySQLi utilisée pour interagir avec la base de données.
     */
    private $connection;

    /**
     * Constructeur de la classe Database.
     *
     * Lors de l'instanciation, ce constructeur établit une connexion à la base de données
     * en utilisant les constantes définies (DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME).
     * En cas d'échec de la connexion, le script s'arrête et un message d'erreur est affiché.
     *
     * Il configure également le jeu de caractères à "utf8mb4" afin d'assurer une bonne gestion de l'encodage.
     *
     * @throws Exception En cas d'échec de la connexion, le script affiche un message d'erreur et s'arrête.
     */
    public function __construct() {
        // Création de la connexion à la base de données
        $this->connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Vérification de la connexion et gestion d'erreur
        if ($this->connection->connect_error) {
            // Arrêt du script avec un message d'erreur si la connexion échoue
            die("Échec de la connexion : " . $this->connection->connect_error);
        }

        // Définition du charset à utf8mb4 pour garantir la compatibilité des caractères spéciaux
        $this->connection->set_charset("utf8mb4");
    }

    /**
     * Prépare une requête SQL et renvoie l'objet statement.
     *
     * Cette méthode prépare une requête SQL de manière sécurisée, évitant ainsi les injections SQL.
     * Elle retourne un objet statement prêt à être utilisé pour l'exécution de la requête.
     *
     * Si la préparation échoue, une exception est levée avec le message d'erreur correspondant.
     *
     * @param string $query La requête SQL à préparer.
     * @return mysqli_stmt L'objet statement préparé.
     * @throws Exception Si la préparation de la requête échoue.
     */
    public function prepare($query) {
        // Préparation de la requête SQL via l'objet de connexion
        $stmt = $this->connection->prepare($query);

        // Vérification de la préparation de la requête
        if (!$stmt) {
            // Levée d'une exception en cas d'erreur et affichage du message d'erreur
            throw new Exception("La préparation de la requête a échoué : " . $this->connection->error);
        }

        // Retour de l'objet statement préparé
        return $stmt;
    }

    /**
     * Retourne l'objet de connexion MySQLi.
     *
     * Cette méthode permet d'accéder directement à l'objet de connexion MySQLi pour des opérations
     * spécifiques ou un contrôle plus précis sur la connexion, si nécessaire.
     *
     * @return mysqli L'objet de connexion MySQLi.
     */
    public function getConnection() {
        // Retour de l'objet de connexion à la base de données
        return $this->connection;
    }
}
?>
