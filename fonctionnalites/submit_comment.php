<?php
/**
 * submit_comment.php
 *
 * Ce script gère la soumission d'un nouveau commentaire ou d'une réponse sur la page "details.php".
 * Il reçoit la requête en AJAX, vérifie que l'utilisateur est connecté, valide le contenu,
 * insère le commentaire dans la base et renvoie un aperçu HTML du commentaire ajouté.
 *
 * Références :
 * - ChatGPT pour la structuration de la requête AJAX et la génération du retour HTML.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis "commentaires.js" lors de la soumission d'un commentaire.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

session_start();
require_once '../bd/bd.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_comment'])) {
    // Vérification de la connexion utilisateur
    if (!isset($_SESSION['id_users'])) {
        echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour poster un commentaire.']);
        exit;
    }
    $id_users = $_SESSION['id_users'];
    // Vérification de la présence de l'identifiant de la ville
    if (!isset($_POST['id_ville']) || empty($_POST['id_ville'])) {
        echo json_encode(['success' => false, 'message' => 'Ville non spécifiée.']);
        exit;
    }
    $id_ville = (int) $_POST['id_ville'];
    // Récupération et validation du contenu du commentaire
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    if(empty($content)){
        echo json_encode(['success' => false, 'message' => 'Contenu vide.']);
        exit;
    }
    // Par défaut, un commentaire sans réponse (parent_id = 0)
    $parent_id = 0;
    if(isset($_POST['parent_id'])){
        $parent_id = (int) $_POST['parent_id'];
    }

    // Insertion du commentaire dans la base
    $stmt = $db->prepare("INSERT INTO commentaires (parent_id, content, created_at, id_users, id_ville) VALUES (?, ?, NOW(), ?, ?)");
    $stmt->bind_param("isii", $parent_id, $content, $id_users, $id_ville);
    if($stmt->execute()){
        $new_id = $stmt->insert_id;
        $stmt->close();
        // Récupération des informations de l'utilisateur pour afficher le commentaire
        $stmtUser = $db->prepare("SELECT username, profile_picture FROM users WHERE id_users = ?");
        $stmtUser->bind_param("i", $id_users);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        $user = $resultUser->fetch_assoc();
        $stmtUser->close();

        // Génération de l'aperçu HTML du commentaire
        ob_start();
        ?>
        <div class="comment" data-id="<?php echo $new_id; ?>">
            <div class="comment-header">
                <img src="../images/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="comment-avatar">
                <span class="comment-username"><?php echo htmlspecialchars($user['username']); ?></span>
                <span class="comment-date"><?php echo date("Y-m-d H:i:s"); ?></span>
            </div>
            <div class="comment-body"><?php echo nl2br(htmlspecialchars($content)); ?></div>
            <?php if(isset($_SESSION['id_users'])): ?>
                <button class="reply-button" data-parent="<?php echo $new_id; ?>">Répondre</button>
                <div class="reply-form" data-parent-form="<?php echo $new_id; ?>" style="display:none;">
                    <textarea placeholder="Votre réponse..."></textarea>
                    <button class="submit-reply" data-parent="<?php echo $new_id; ?>">Envoyer</button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $comment_html = ob_get_clean();
        echo json_encode(['success' => true, 'message' => 'Commentaire ajouté.', 'comment_html' => $comment_html]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du commentaire.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}
?>
