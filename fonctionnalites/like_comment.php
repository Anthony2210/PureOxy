<?php
/**
 * like_comment.php
 *
 * Ce code gère le mécanisme de "like" et "unlike" pour les commentaires sur le site PureOxy.
 * Il permet aux utilisateurs authentifiés de liker ou d'unliker des commentaires tout en
 * assurant la sécurité via la vérification des jetons CSRF et l'utilisation de transactions
 * pour maintenir l'intégrité des données.
 *
 */

require '../bd/bd.php';

/**
 * Démarrer la session si elle n'est pas déjà active.
 * Cela permet de gérer les données utilisateur et les jetons CSRF.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est authentifié.
 * Si non, renvoie une réponse JSON indiquant que l'utilisateur doit se connecter.
 */
if (!isset($_SESSION['id_users'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour liker un commentaire.'
    ]);
    exit;
}

$id_users = $_SESSION['id_users'];

/**
 * Vérification du jeton CSRF pour protéger contre les attaques de type Cross-Site Request Forgery.
 * Si le jeton n'est pas présent ou invalide, renvoie une réponse d'erreur et arrête l'exécution.
 */
if (
    !isset($_POST['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur : Jeton CSRF invalide.'
    ]);
    exit;
}

/**
 * Vérifie si les paramètres 'action' et 'comment_id' sont présents dans la requête POST.
 * 'action' doit être soit 'like' soit 'unlike', et 'comment_id' doit être l'identifiant du commentaire.
 */
if (isset($_POST['action']) && isset($_POST['comment_id'])) {
    $action = $_POST['action'];
    $comment_id = intval($_POST['comment_id']);

    /**
     * Démarrage d'une transaction pour garantir l'intégrité des opérations sur la base de données.
     * Cela permet de s'assurer que toutes les étapes du "like" ou "unlike" sont réalisées avec succès,
     * ou qu'aucune modification n'est appliquée en cas d'erreur.
     */
    $conn->begin_transaction();

    try {
        if ($action == 'like') {
            /**
             * Action de "liker" un commentaire.
             * Vérifie d'abord si l'utilisateur a déjà liké ce commentaire.
             */

            // Préparation de la requête pour vérifier l'existence du like
            $stmt = $conn->prepare("SELECT * FROM likes WHERE id_users = ? AND comment_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête.');
            }
            $stmt->bind_param("ii", $id_users, $comment_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                // L'utilisateur n'a pas encore liké ce commentaire

                $stmt->close();

                /**
                 * Insertion du like dans la table 'likes'.
                 */
                $stmt = $conn->prepare("INSERT INTO likes (id_users, comment_id) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête d\'insertion.');
                }
                $stmt->bind_param("ii", $id_users, $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de l\'insertion du like.');
                }
                $stmt->close();

                /**
                 * Mise à jour du nombre de likes dans la table 'commentaire'.
                 */
                $stmt = $conn->prepare("UPDATE commentaire SET likes = likes + 1 WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de mise à jour.');
                }
                $stmt->bind_param("i", $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de la mise à jour des likes.');
                }
                $stmt->close();

                /**
                 * Récupération du nouveau nombre de likes pour renvoyer au client.
                 */
                $stmt = $conn->prepare("SELECT likes FROM commentaire WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de récupération des likes.');
                }
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comment = $result->fetch_assoc();
                $likes = $comment['likes'];
                $stmt->close();

                /**
                 * Commit de la transaction après réussite de toutes les opérations.
                 */
                $conn->commit();

                /**
                 * Renvoie une réponse JSON indiquant le succès de l'opération et le nouveau nombre de likes.
                 */
                echo json_encode([
                    'success' => true,
                    'message' => 'Commentaire liké.',
                    'likes' => $likes
                ]);
                exit;
            } else {
                // L'utilisateur a déjà liké ce commentaire

                $stmt->close();

                /**
                 * Rollback de la transaction car l'utilisateur a déjà liké ce commentaire.
                 */
                $conn->rollback();

                /**
                 * Renvoie une réponse JSON indiquant que l'utilisateur a déjà liké ce commentaire.
                 */
                echo json_encode([
                    'success' => false,
                    'message' => 'Vous avez déjà liké ce commentaire.'
                ]);
                exit;
            }

        } elseif ($action == 'unlike') {
            /**
             * Action de "unliker" un commentaire.
             * Vérifie d'abord si l'utilisateur a déjà liké ce commentaire.
             */

            // Préparation de la requête pour vérifier l'existence du like
            $stmt = $conn->prepare("SELECT * FROM likes WHERE id_users = ? AND comment_id = ?");
            if (!$stmt) {
                throw new Exception('Erreur de préparation de la requête.');
            }
            $stmt->bind_param("ii", $id_users, $comment_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // L'utilisateur a déjà liké ce commentaire

                $stmt->close();

                /**
                 * Suppression du like de la table 'likes'.
                 */
                $stmt = $conn->prepare("DELETE FROM likes WHERE id_users = ? AND comment_id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de suppression.');
                }
                $stmt->bind_param("ii", $id_users, $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de la suppression du like.');
                }
                $stmt->close();

                /**
                 * Mise à jour du nombre de likes dans la table 'commentaire'.
                 */
                $stmt = $conn->prepare("UPDATE commentaire SET likes = likes - 1 WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de mise à jour.');
                }
                $stmt->bind_param("i", $comment_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'exécution de la mise à jour des likes.');
                }
                $stmt->close();

                /**
                 * Récupération du nouveau nombre de likes pour renvoyer au client.
                 */
                $stmt = $conn->prepare("SELECT likes FROM commentaire WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Erreur de préparation de la requête de récupération des likes.');
                }
                $stmt->bind_param("i", $comment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comment = $result->fetch_assoc();
                $likes = $comment['likes'];
                $stmt->close();

                /**
                 * Commit de la transaction après réussite de toutes les opérations.
                 */
                $conn->commit();

                /**
                 * Renvoie une réponse JSON indiquant le succès de l'opération et le nouveau nombre de likes.
                 */
                echo json_encode([
                    'success' => true,
                    'message' => 'Like retiré.',
                    'likes' => $likes
                ]);
                exit;
            } else {
                // L'utilisateur n'a pas liké ce commentaire

                $stmt->close();

                /**
                 * Rollback de la transaction car l'utilisateur n'a pas liké ce commentaire.
                 */
                $conn->rollback();

                /**
                 * Renvoie une réponse JSON indiquant que l'utilisateur n'a pas liké ce commentaire.
                 */
                echo json_encode([
                    'success' => false,
                    'message' => 'Vous n\'avez pas liké ce commentaire.'
                ]);
                exit;
            }

        } else {
            /**
             * Action non reconnue (ni 'like' ni 'unlike').
             * Rollback de la transaction et renvoie une réponse d'erreur.
             */
            $conn->rollback();

            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue.'
            ]);
            exit;
        }

    } catch (Exception $e) {
        /**
         * En cas d'erreur lors des opérations, effectue un rollback de la transaction
         * et journalise l'erreur côté serveur pour le débogage.
         */
        $conn->rollback();

        // Journalisation de l'erreur côté serveur
        error_log("Erreur dans like_comment.php : " . $e->getMessage());

        /**
         * Renvoie une réponse JSON générique indiquant qu'une erreur s'est produite.
         * Ne divulgue pas les détails de l'erreur pour des raisons de sécurité.
         */
        echo json_encode([
            'success' => false,
            'message' => 'Une erreur s\'est produite. Veuillez réessayer plus tard.'
        ]);
        exit;
    }

} else {
    /**
     * Si les paramètres POST requis ne sont pas présents, renvoie une réponse JSON d'erreur.
     */
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres invalides.'
    ]);
    exit;
}
?>
