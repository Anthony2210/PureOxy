<?php
/**
 * check_username.php
 *
 * Ce code vérifie si un nom d'utilisateur existe déjà dans la base de données.
 * Il attend une requête POST contenant le paramètre 'username' et renvoie une réponse JSON.
 *
 */

header('Content-Type: application/json');
require '../bd/bd.php';

/**
 * Vérifie si le paramètre 'username' est présent dans la requête POST.
 */
if (isset($_POST['username'])) {
    /**
     * Récupère et nettoie le nom d'utilisateur envoyé via POST pour éviter les espaces inutiles.
     *
     * @var string $username Le nom d'utilisateur à vérifier.
     */
    $username = trim($_POST['username']);

    /**
     * Prépare une requête SQL pour compter le nombre d'utilisateurs avec le nom d'utilisateur donné.
     * Utilisation de requêtes préparées pour prévenir les injections SQL.
     */
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");

    if ($stmt) {
        /**
         * Lie le paramètre 'username' à la requête préparée.
         *
         * @param string $username Le nom d'utilisateur à lier à la requête SQL.
         */
        $stmt->bind_param('s', $username);

        /**
         * Exécute la requête préparée.
         */
        $stmt->execute();

        /**
         * Lie le résultat de la requête à la variable $count.
         *
         * @var int $count Le nombre d'utilisateurs trouvés avec le nom d'utilisateur donné.
         */
        $stmt->bind_result($count);

        /**
         * Récupère le résultat de la requête.
         */
        $stmt->fetch();

        /**
         * Renvoie une réponse JSON indiquant si le nom d'utilisateur existe.
         *
         * @var bool $count > 0 Si vrai, le nom d'utilisateur existe déjà.
         */
        echo json_encode(['exists' => $count > 0]);

        /**
         * Ferme la requête préparée pour libérer les ressources.
         */
        $stmt->close();
    } else {
        /**
         * En cas d'échec de la préparation de la requête, renvoie false.
         */
        echo json_encode(['exists' => false]);
    }
} else {
    /**
     * Si le paramètre 'username' n'est pas présent dans la requête POST, renvoie false.
     */
    echo json_encode(['exists' => false]);
}
?>
