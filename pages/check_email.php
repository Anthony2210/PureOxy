<?php
/**
 * check_email.php
 *
 * Ce code vérifie si une adresse email existe déjà dans la base de données.
 * Il attend une requête POST contenant le paramètre 'email' et renvoie une réponse JSON.
 *
 */

header('Content-Type: application/json');
require '../bd/bd.php';

/**
 * Vérifie si le paramètre 'email' est présent dans la requête POST.
 */
if (isset($_POST['email'])) {
    /**
     * Récupère et nettoie l'email envoyé via POST pour éviter les espaces inutiles.
     *
     * @var string $email L'email à vérifier.
     */
    $email = trim($_POST['email']);

    /**
     * Prépare une requête SQL pour compter le nombre d'utilisateurs avec l'email donné.
     * Utilisation de requêtes préparées pour prévenir les injections SQL.
     */
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");

    if ($stmt) {
        /**
         * Lie le paramètre 'email' à la requête préparée.
         *
         * @param string $email L'email à lier à la requête SQL.
         */
        $stmt->bind_param('s', $email);

        /**
         * Exécute la requête préparée.
         */
        $stmt->execute();

        /**
         * Lie le résultat de la requête à la variable $count.
         *
         * @var int $count Le nombre d'utilisateurs trouvés avec l'email donné.
         */
        $stmt->bind_result($count);

        /**
         * Récupère le résultat de la requête.
         */
        $stmt->fetch();

        /**
         * Renvoie une réponse JSON indiquant si l'email existe.
         *
         * @var bool $count > 0 Si vrai, l'email existe déjà.
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
     * Si le paramètre 'email' n'est pas présent dans la requête POST, renvoie false.
     */
    echo json_encode(['exists' => false]);
}
?>
