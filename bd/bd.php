<?php

// Infos de connexion à la base en ligne 
define('DB_SERVER', 'sql109.infinityfree.com'); // Host MySQL
define('DB_USERNAME', 'if0_38652396');           // Utilisateur MySQL
define('DB_PASSWORD', 'LSEM9GsOGplX');           // Mot de passe MySQL
define('DB_NAME', 'if0_38652396_pureoxy');       // Nom de la base

class Database
{
    private $connection;

    public function __construct()
    {
        // Connexion à la base
        $this->connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($this->connection->connect_error) {
            die("Échec de la connexion : " . $this->connection->connect_error);
        }

        $this->connection->set_charset("utf8mb4");
    }

    // Préparation sécurisée de requêtes SQL
    public function prepare($query)
    {
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new Exception("Erreur lors de la préparation : " . $this->connection->error);
        }
        return $stmt;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
?>
