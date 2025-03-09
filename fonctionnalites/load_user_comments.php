<?php
/**
 * load_user_comments.php
 *
 * Ce code récupère et affiche les commentaires postés par l'utilisateur connecté.
 * Il inclut des fonctions pour sécuriser les sorties, formater les dates, et générer des URLs.
 *
 */

session_start();

require '../bd/bd.php';

/**
 * Échappe les sorties HTML pour prévenir les attaques XSS.
 *
 * @param string $string La chaîne à échapper.
 * @return string La chaîne échappée.
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Formate une date au format 'd F Y à H:i'.
 *
 * @param string $date La date à formater.
 * @return string La date formatée.
 */
function formatDate($date) {
    return date('d F Y à H:i', strtotime($date));
}

/**
 * Récupère les commentaires d'un utilisateur.
 *
 * @param mysqli $conn La connexion à la base de données.
 * @param int $user_id L'ID de l'utilisateur.
 * @return mysqli_result|false Le résultat de la requête ou false en cas d'erreur.
 */
function getUserComments($conn, $user_id) {
    $query = "
        SELECT 
            c.id, 
            c.page, 
            c.content, 
            c.created_at, 
            COUNT(DISTINCT l.comment_id) AS like_count,
            COUNT(DISTINCT r.id) AS reply_count
        FROM 
            commentaire c
        LEFT JOIN 
            likes l ON c.id = l.comment_id
        LEFT JOIN 
            commentaire r ON c.id = r.parent_id
        WHERE 
            c.user_id = ? 
        GROUP BY 
            c.id
        ORDER BY 
            c.created_at DESC
    ";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            return $stmt->get_result();
        }
        $stmt->close();
    }
    return false;
}

/**
 * Génère l'URL complète pour un commentaire.
 *
 * @param string $base_path Le chemin de base.
 * @param string $page Le nom de la page.
 * @param int $comment_id L'ID du commentaire.
 * @return string L'URL complète du commentaire.
 */
function generateCommentURL($base_path, $page, $comment_id) {
    return $base_path . escape($page) . '#comment-' . escape($comment_id);
}

/**
 * Récupère le titre de la page à partir du nom du fichier.
 *
 * @param string $page_name Le nom du fichier de la page.
 * @param array $page_titles Tableau associatif des titres des pages.
 * @return string Le titre de la page ou 'Page inconnue'.
 */
function getPageTitle($page_name, $page_titles) {
    return $page_titles[$page_name] ?? 'Page inconnue';
}

/**
 * Ajoute la ville au titre si la page est 'details.php' et que la ville est spécifiée.
 *
 * @param string $page_name Le nom de la page.
 * @param string $page_query La chaîne de requête de la page.
 * @param string $page_title Le titre actuel de la page.
 * @return string Le titre mis à jour de la page.
 */
function appendCityToTitle($page_name, $page_query, $page_title) {
    if ($page_name === 'details.php' && $page_query) {
        parse_str($page_query, $params);
        if (isset($params['ville'])) {
            $page_title .= ' - ' . escape($params['ville']);
        }
    }
    return $page_title;
}

/**
 * Génère le HTML pour une liste de commentaires.
 *
 * @param mysqli_result $comments Les commentaires à afficher.
 * @param array $page_titles Tableau associatif des titres des pages.
 * @param string $base_path Le chemin de base pour les URLs des pages.
 */
function displayComments($comments, $page_titles, $base_path = '/PUREOXY/fonctionnalites/') {
    echo '<ul class="user-comments">';
    while ($comment = $comments->fetch_assoc()) {
        // Extraire le nom de la page et les paramètres
        $page_parts = explode('?', $comment['page'], 2);
        $page_name = $page_parts[0];
        $page_query = $page_parts[1] ?? '';

        // Générer l'URL complète pour le commentaire
        $comment_anchor_url = generateCommentURL($base_path, $comment['page'], $comment['id']);

        // Récupérer et ajuster le titre de la page
        $page_title = getPageTitle($page_name, $page_titles);
        $page_title = appendCityToTitle($page_name, $page_query, $page_title);

        // Formater la date
        $formatted_date = formatDate($comment['created_at']);
        ?>
        <li class="user-comment" id="comment-<?php echo escape($comment['id']); ?>">
            <article class="comment-content">
                <header class="comment-header">
                    <strong>Sur la page :</strong>
                    <a href="<?php echo escape($comment_anchor_url); ?>" class="comment-page-link">
                        <?php echo escape($page_title); ?>
                    </a>
                    <time datetime="<?php echo escape($comment['created_at']); ?>" class="comment-date">
                        <?php echo $formatted_date; ?>
                    </time>
                </header>
                <section class="comment-body">
                    <p class="comment-text">
                        <?php echo nl2br(escape($comment['content'])); ?>
                    </p>
                </section>
                <footer class="comment-footer">
                    <section class="comment-likes">
                        <p class="fas fa-thumbs-up" aria-hidden="true"></p>
                        <?php echo escape($comment['like_count']); ?> Like<?php echo ($comment['like_count'] > 1) ? 's' : ''; ?>
                    </section>
                    <section class="comment-replies">
                        <p class="fas fa-reply" aria-hidden="true"></p>
                        <?php echo escape($comment['reply_count']); ?> Réponse<?php echo ($comment['reply_count'] > 1) ? 's' : ''; ?>
                    </section>
                </footer>
            </article>
        </li>
        <?php
    }
    echo '</ul>';
}

$page_titles = [
    'details.php' => 'Détails de la ville',
    'qualite_air.php' => 'Qualité de l\'air',
    'lutte_pollution.php' => 'Lutte contre la pollution',
];

/**
 * Vérifier si l'utilisateur est connecté et récupérer ses commentaires.
 */
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $comments = getUserComments($conn, $user_id);

    if ($comments && $comments->num_rows > 0) {
        displayComments($comments, $page_titles);
    } else {
        /**
         * Afficher un message si l'utilisateur n'a pas encore posté de commentaires.
         */
        echo '<p class="no-comments">Vous n\'avez pas encore posté de commentaires.</p>';
    }
} else {
    /**
     * Afficher un message si l'utilisateur n'est pas connecté.
     */
    echo '<p class="not-logged-in">Vous devez être connecté pour voir vos commentaires.</p>';
}
?>
