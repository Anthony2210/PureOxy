<?php
/**
 * bd.php
 *
 * Ce code établit une connexion à la base de données MySQL et définit
 * la classe Database qui centralise et sécurise l'accès à la base.
 * Vous pouvez instancier cette classe et utiliser sa méthode prepare() pour préparer vos requêtes.
 */

// Informations de connexion à la base de données.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'root');
define('DB_NAME', 'pureoxy');

class Database {
    private $connection;

    public function __construct() {
        $this->connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($this->connection->connect_error) {
            die("Échec de la connexion : " . $this->connection->connect_error);
        }
        // Définit le charset pour éviter les problèmes d'encodage
        $this->connection->set_charset("utf8mb4");
    }

    /**
     * Prépare une requête SQL et renvoie l'objet statement.
     *
     * @param string $query La requête SQL à préparer.
     * @return mysqli_stmt L'objet statement préparé.
     * @throws Exception Si la préparation échoue.
     */
    public function prepare($query) {
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new Exception("La préparation de la requête a échoué : " . $this->connection->error);
        }
        return $stmt;
    }

    /**
     * Retourne l'objet de connexion MySQLi.
     *
     * @return mysqli L'objet de connexion.
     */
    public function getConnection() {
        return $this->connection;
    }
}
?>
