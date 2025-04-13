<?php
/**
 * details.php
 *
 * Page de détails d'une ville de PureOxy.
 * Cette page affiche toutes les informations disponibles sur une ville,
 * notamment ses caractéristiques, son classement par polluant, l'historique
 * des recherches et les commentaires des utilisateurs.
 *
 * Le script gère également, via des requêtes AJAX, l'ajout ou le retrait de la ville
 * dans les favoris, et l'enregistrement de la recherche dans l'historique.
 *
 * Références :
 * - ChatGPT pour la structuration et la documentation du code.
 *
 * Utilisation :
 * - Accéder à cette page via un lien contenant le paramètre GET "ville".
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

session_start();
ob_start();
require_once '../bd/bd.php';
$db = new Database();

// Gestion AJAX du toggling du favori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['favorite_action'])) {
    if (!isset($_SESSION['id_users'])) {
        echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour ajouter aux favoris.']);
        exit;
    }
    $id_users = $_SESSION['id_users'];
    if (!isset($_GET['ville']) || empty($_GET['ville'])) {
        echo json_encode(['success' => false, 'message' => 'Ville non spécifiée.']);
        exit;
    }
    $nomVille = $_GET['ville'];
    // Récupération de l'ID de la ville à partir de son nom
    $stmtVille = $db->prepare("SELECT id_ville, ville FROM donnees_villes WHERE ville = ? LIMIT 1");
    $stmtVille->bind_param("s", $nomVille);
    $stmtVille->execute();
    $resVille = $stmtVille->get_result();
    $infoVilleAjax = $resVille->fetch_assoc();
    $stmtVille->close();
    if (!$infoVilleAjax) {
        echo json_encode(['success' => false, 'message' => 'Ville introuvable.']);
        exit;
    }
    $idVille = (int)$infoVilleAjax['id_ville'];
    $cityName = $infoVilleAjax['ville'];

    // Détermine l'action favorite (ajouter ou retirer)
    $action = $_POST['favorite_action'];
    if ($action == 'add_favorite') {
        // Vérification si la ville est déjà favorite
        $stmtCheck = $db->prepare("SELECT * FROM favorite_cities WHERE id_users = ? AND id_ville = ?");
        $stmtCheck->bind_param("ii", $id_users, $idVille);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Déjà dans les favoris.', 'action' => 'added']);
            exit;
        }
        $stmtCheck->close();
        // Insertion dans les favoris
        $stmtInsert = $db->prepare("INSERT INTO favorite_cities (id_users, id_ville) VALUES (?, ?)");
        $stmtInsert->bind_param("ii", $id_users, $idVille);
        if ($stmtInsert->execute()) {
            echo json_encode(['success' => true, 'message' => 'Ville ajoutée aux favoris.', 'action' => 'added']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout aux favoris.']);
        }
        $stmtInsert->close();
        exit;
    } elseif ($action == 'remove_favorite') {
        // Suppression de la ville des favoris
        $stmtDelete = $db->prepare("DELETE FROM favorite_cities WHERE id_users = ? AND id_ville = ?");
        $stmtDelete->bind_param("ii", $id_users, $idVille);
        if ($stmtDelete->execute()) {
            echo json_encode(['success' => true, 'message' => 'Ville retirée des favoris.', 'action' => 'removed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression des favoris.']);
        }
        $stmtDelete->close();
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        exit;
    }
}

// Vérification du paramètre "ville" dans l'URL
if (!isset($_GET['ville']) || empty($_GET['ville'])) {
    echo "Aucune ville spécifiée !";
    exit;
}
$nomVille = $_GET['ville'];

// Récupération des informations de la ville
$stmtVille = $db->prepare("
    SELECT 
        id_ville, 
        ville, 
        postal_code, 
        latitude, 
        longitude, 
        departement, 
        region, 
        population, 
        superficie_km2, 
        densite, 
        grille_densite_texte
    FROM donnees_villes
    WHERE ville = ?
    LIMIT 1
");
$stmtVille->bind_param("s", $nomVille);
$stmtVille->execute();
$resVille = $stmtVille->get_result();
$infoVille = $resVille->fetch_assoc();
$stmtVille->close();

if (!$infoVille) {
    echo "Ville introuvable dans la base de données.";
    exit;
}
$idVille = (int)$infoVille['id_ville'];

// Ajout de la recherche dans l'historique (si l'utilisateur est connecté)
if (isset($_SESSION['id_users'])) {
    $id_users = $_SESSION['id_users'];

    // Récupérer la dernière recherche de l'utilisateur
    $stmtLast = $db->prepare("SELECT id_ville FROM search_history WHERE id_users = ? ORDER BY search_date DESC LIMIT 1");
    if ($stmtLast) {
        $stmtLast->bind_param("i", $id_users);
        $stmtLast->execute();
        $resultLast = $stmtLast->get_result();
        $lastEntry = $resultLast->fetch_assoc();
        $stmtLast->close();

        // Insertion si la dernière recherche ne correspond pas à la ville en cours
        if (!$lastEntry || $lastEntry['id_ville'] != $idVille) {
            $stmtHistory = $db->prepare("INSERT INTO search_history (id_users, search_date, id_ville) VALUES (?, NOW(), ?)");
            if ($stmtHistory) {
                $stmtHistory->bind_param("ii", $id_users, $idVille);
                $stmtHistory->execute();
                $stmtHistory->close();
            }
        }
    }
}

// Récupération du classement des polluants pour la ville
$stmtPoll = $db->prepare("
    SELECT 
      t1.polluant,
      t1.avg_value,
      (
        SELECT COUNT(*) + 1
        FROM moy_pollution_villes t2
        WHERE t2.polluant = t1.polluant
          AND t2.avg_value < t1.avg_value
      ) AS rang,
      (
        SELECT COUNT(*)
        FROM moy_pollution_villes t3
        WHERE t3.polluant = t1.polluant
      ) AS total
    FROM moy_pollution_villes t1
    WHERE t1.id_ville = ?
");
$stmtPoll->bind_param("i", $idVille);
$stmtPoll->execute();
$resPoll = $stmtPoll->get_result();
$listePolluants = [];
while ($row = $resPoll->fetch_assoc()) {
    $listePolluants[] = $row;
}
$stmtPoll->close();

// Extraction des polluants uniques pour le filtre
$uniquePolluants = [];
foreach ($listePolluants as $poll) {
    if (!in_array($poll['polluant'], $uniquePolluants)) {
        $uniquePolluants[] = $poll['polluant'];
    }
}

// Génération des options pour le filtre des mois pour l'onglet Historique
$historiqueMonths = [];
$months = ["janv", "fev", "mars", "avril", "mai", "juin", "juil", "aout", "sept", "oct", "nov", "dec"];
for ($year = 2023; $year <= 2025; $year++) {
    foreach ($months as $index => $mon) {
        if ($year == 2025 && $index > 0) break;
        $value = $mon . $year;
        $display = ucfirst($mon) . ". " . $year;
        $historiqueMonths[] = ["value" => $value, "display" => $display];
    }
}

// Pour l'onglet Prédictions
$predictionsMonths = [];
for ($year = 2025; $year <= 2026; $year++) {
    foreach ($months as $index => $mon) {
        if ($year == 2026 && $index > 0) break;
        $value = "moy_predic_" . $mon . $year;
        $display = ucfirst($mon) . ". " . $year;
        $predictionsMonths[] = ["value" => $value, "display" => $display];
    }
}

// Fonction de conversion d'une date en temps relatif
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $w = floor($diff->d / 7);
    $d = $diff->d - $w * 7;

    $diffArray = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $w,
        'd' => $d,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s
    ];

    $string = [
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'h',
        'i' => 'min',
        's' => 'sec'
    ];
    foreach ($string as $k => &$v) {
        if ($diffArray[$k]) {
            $v = $diffArray[$k] . ' ' . $v . ($diffArray[$k] > 1 && $k !== 'h' ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PureOxy - Détails de <?php echo htmlspecialchars($infoVille['ville']); ?></title>
    <!-- Polices, Bootstrap, FontAwesome et styles personnalisés -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/includes.css">
    <link rel="stylesheet" href="../styles/details.css">
    <link rel="stylesheet" href="../styles/messages.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<!-- Passage de l'ID de la ville à JavaScript -->
<script>
    var idVille = <?php echo $idVille; ?>;
</script>

<div class="details-container">
    <!-- Colonne gauche : informations sur la ville et classement -->
    <div class="left-column">
        <div class="box-ville">
            <h1 class="ville-title"><?php echo htmlspecialchars($infoVille['ville']); ?></h1>
            <?php
            // Vérifie si la ville est dans les favoris pour l'utilisateur connecté
            $isFavorite = false;
            if (isset($_SESSION['id_users'])) {
                $id_users = $_SESSION['id_users'];
                $stmtFav = $db->prepare("SELECT * FROM favorite_cities WHERE id_users = ? AND id_ville = ?");
                $stmtFav->bind_param("ii", $id_users, $idVille);
                $stmtFav->execute();
                $resultFav = $stmtFav->get_result();
                if ($resultFav->num_rows > 0) {
                    $isFavorite = true;
                }
                $stmtFav->close();
            }
            ?>
            <!-- Bouton pour ajouter ou retirer la ville des favoris -->
            <form id="favorite-form" method="post" style="display:inline;">
                <input type="hidden" name="favorite_action" id="favorite_action" value="<?php echo $isFavorite ? 'remove_favorite' : 'add_favorite'; ?>">
                <button type="submit" class="favorite-icon" data-action="<?php echo $isFavorite ? 'remove_favorite' : 'add_favorite'; ?>">
                    <i class="<?php echo $isFavorite ? 'fas' : 'far'; ?> fa-star"></i>
                </button>
            </form>
            <!-- Texte explicatif sur la densité de pollution -->
            <p class="grille-texte">
                <?php echo nl2br(htmlspecialchars($infoVille['grille_densite_texte'])); ?>
            </p>
            <div class="city-details">
                <div class="city-detail">
                    <i class="fa-solid fa-user-group"></i>
                    Population : <?php echo number_format($infoVille['population'], 0, ',', ' '); ?> habitants
                </div>
                <div class="city-detail">
                    <i class="fa-solid fa-map"></i>
                    Superficie : <?php echo number_format($infoVille['superficie_km2'], 0, ',', ' '); ?> km²
                </div>
                <div class="city-detail">
                    <i class="fa-solid fa-map-pin"></i>
                    Département : <?php echo htmlspecialchars($infoVille['departement']); ?>
                </div>
                <div class="city-detail">
                    <i class="fa-solid fa-location-dot"></i>
                    Région : <?php echo htmlspecialchars($infoVille['region']); ?>
                </div>
            </div>
        </div>

        <!-- Bloc de classement des polluants -->
        <div class="box-classement">
            <h2 class="polluants-title">
                <i class="fa-solid fa-ranking-star"></i> Classement par Polluant
            </h2>
            <?php if (!empty($listePolluants)): ?>
                <table class="table-polluants">
                    <thead>
                    <tr>
                        <th>Polluant</th>
                        <th>Rang</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($listePolluants as $poll):
                        $polluant = $poll['polluant'];
                        $rang    = (int)$poll['rang'];
                        $total   = (int)$poll['total'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($polluant); ?></td>
                            <td>
                                <i class="fa-solid fa-medal" style="color:#f4c542;"></i>
                                <?php echo $rang . ' / ' . $total; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button onclick="window.location.href='../pages/classement.php'" style="margin-top: 10px;">
                    Voir le classement complet
                </button>
            <?php else: ?>
                <p class="aucun-classement">Aucun classement disponible.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonne droite : onglets pour Historique, Prédictions et affichage des graphiques -->
    <div class="right-column">
        <div class="tabs-container">
            <ul class="tabs">
                <li data-tab="historique" class="active">
                    <i class="fa-solid fa-clock"></i> Historique
                </li>
                <li data-tab="predictions">
                    <i class="fa-solid fa-forward"></i> Prédictions
                </li>
            </ul>
            <div class="tab-content">
                <!-- Onglet Historique -->
                <div id="historique" class="tab-panel active">
                    <div class="filter-container">
                        <label for="pollutant-filter-historique">Polluant :</label>
                        <select id="pollutant-filter-historique">
                            <option value="">Tous les polluants</option>
                            <?php foreach ($uniquePolluants as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>">
                                    <?php echo htmlspecialchars($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="month-filter-historique">Mois :</label>
                        <select id="month-filter-historique">
                            <option value="">Tous les mois</option>
                            <?php foreach ($historiqueMonths as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['value']); ?>">
                                    <?php echo htmlspecialchars($m['display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sub-tabs-container">
                        <ul class="sub-tabs">
                            <li data-subtab="bar-historique" class="active">
                                <i class="fa-solid fa-chart-bar"></i> Bar
                            </li>
                            <li data-subtab="line-historique">
                                <i class="fa-solid fa-chart-line"></i> Ligne
                            </li>
                            <li data-subtab="table-historique">
                                <i class="fa-solid fa-table"></i> Tableau
                            </li>
                        </ul>
                        <div class="sub-tab-content">
                            <div id="bar-historique" class="sub-tab-panel active">
                                <canvas id="bar-chart-historique" class="chart"></canvas>
                            </div>
                            <div id="line-historique" class="sub-tab-panel">
                                <canvas id="time-chart-historique" class="chart"></canvas>
                            </div>
                            <div id="table-historique" class="sub-tab-panel">
                                <div class="table-scroll">
                                    <div id="data-table-historique"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Onglet Prédictions -->
                <div id="predictions" class="tab-panel">
                    <div class="filter-container">
                        <label for="pollutant-filter-predictions">Polluant :</label>
                        <select id="pollutant-filter-predictions">
                            <option value="">Tous les polluants</option>
                            <?php foreach ($uniquePolluants as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>">
                                    <?php echo htmlspecialchars($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="month-filter-predictions">Mois :</label>
                        <select id="month-filter-predictions">
                            <option value="">Tous les mois</option>
                            <?php foreach ($predictionsMonths as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['value']); ?>">
                                    <?php echo htmlspecialchars($m['display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sub-tabs-container">
                        <ul class="sub-tabs">
                            <li data-subtab="bar-predictions" class="active">
                                <i class="fa-solid fa-chart-bar"></i> Bar
                            </li>
                            <li data-subtab="line-predictions">
                                <i class="fa-solid fa-chart-line"></i> Ligne
                            </li>
                            <li data-subtab="table-predictions">
                                <i class="fa-solid fa-table"></i> Tableau
                            </li>
                        </ul>
                        <div class="sub-tab-content">
                            <div id="bar-predictions" class="sub-tab-panel active">
                                <canvas id="bar-chart-predictions" class="chart"></canvas>
                            </div>
                            <div id="line-predictions" class="sub-tab-panel">
                                <canvas id="time-chart-predictions" class="chart"></canvas>
                            </div>
                            <div id="table-predictions" class="sub-tab-panel">
                                <div class="table-scroll">
                                    <div id="data-table-predictions"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Fin .tab-content -->
        </div> <!-- Fin .tabs-container -->
    </div> <!-- Fin .right-column -->
</div> <!-- Fin .details-container -->

<!-- Section Commentaires -->
<div id="comments-section" class="comments-section">
    <h2>Commentaires</h2>
    <?php if(isset($_SESSION['id_users'])): ?>
        <!-- Formulaire pour poster un nouveau commentaire -->
        <div id="comment-form">
            <textarea id="new-comment" placeholder="Écrire un commentaire..."></textarea>
            <button id="submit-comment">Envoyer</button>
        </div>
    <?php else: ?>
        <p>Vous devez être connecté pour écrire un commentaire. <a href="../login.php">Se connecter</a></p>
    <?php endif; ?>

    <div id="comments-list">
        <?php
        // Récupération des commentaires pour la ville, affichés par ordre chronologique
        // Modification : jointure avec la table users pour récupérer username et profile_picture
        $stmtComments = $db->prepare("
            SELECT c.*, dv.ville AS ville, u.username, u.profile_picture
            FROM commentaires c 
            JOIN donnees_villes dv ON c.id_ville = dv.id_ville 
            JOIN users u ON c.id_users = u.id_users
            WHERE c.id_ville = ? ORDER BY c.created_at ASC
        ");
        $stmtComments->bind_param("i", $idVille);
        $stmtComments->execute();
        $resultComments = $stmtComments->get_result();
        $comments = [];
        while($row = $resultComments->fetch_assoc()){
            $comments[] = $row;
        }
        $stmtComments->close();

        // Organisation des commentaires en arborescence
        $commentsById = [];
        foreach($comments as $comment){
            $comment['replies'] = [];
            $commentsById[$comment['id_comm']] = $comment;
        }
        $rootComments = [];
        foreach($commentsById as $id => $comment){
            if($comment['parent_id'] == 0){
                $rootComments[] = &$commentsById[$id];
            } else {
                if(isset($commentsById[$comment['parent_id']])){
                    $commentsById[$comment['parent_id']]['replies'][] = &$commentsById[$id];
                } else {
                    $rootComments[] = &$commentsById[$id]; // Cas de sécurité
                }
            }
        }

        // Fonction récursive pour afficher les commentaires (limite d'imbrication 1)
        function displayComments($comments, $depth = 0) {
            global $db;
            foreach ($comments as $comment) {
                // Récupération des compteurs de votes
                $stmtLikes = $db->prepare("SELECT COUNT(*) as like_count FROM likes WHERE id_comm = ? AND vote = 1");
                $stmtLikes->bind_param("i", $comment['id_comm']);
                $stmtLikes->execute();
                $resultLikes = $stmtLikes->get_result();
                $rowLikes = $resultLikes->fetch_assoc();
                $like_count = $rowLikes && $rowLikes['like_count'] !== null ? $rowLikes['like_count'] : 0;
                $stmtLikes->close();

                $stmtDislikes = $db->prepare("SELECT COUNT(*) as dislike_count FROM likes WHERE id_comm = ? AND vote = -1");
                $stmtDislikes->bind_param("i", $comment['id_comm']);
                $stmtDislikes->execute();
                $resultDislikes = $stmtDislikes->get_result();
                $rowDislikes = $resultDislikes->fetch_assoc();
                $dislike_count = $rowDislikes && $rowDislikes['dislike_count'] !== null ? $rowDislikes['dislike_count'] : 0;
                $stmtDislikes->close();

                // Vote de l'utilisateur courant (si connecté)
                $userVote = 0;
                if (isset($_SESSION['id_users'])) {
                    $stmtUserVote = $db->prepare("SELECT vote FROM likes WHERE id_users = ? AND id_comm = ?");
                    $stmtUserVote->bind_param("ii", $_SESSION['id_users'], $comment['id_comm']);
                    $stmtUserVote->execute();
                    $resultUserVote = $stmtUserVote->get_result();
                    if ($resultUserVote->num_rows > 0) {
                        $voteData = $resultUserVote->fetch_assoc();
                        $userVote = (int)$voteData['vote'];
                    }
                    $stmtUserVote->close();
                }
                $likeClass = ($userVote === 1) ? 'voted-like' : '';
                $dislikeClass = ($userVote === -1) ? 'voted-dislike' : '';

                // Utilisation de valeurs par défaut en cas de données manquantes
                $profilePicture = !empty($comment['profile_picture']) ? $comment['profile_picture'] : 'user.png';
                $username = !empty($comment['username']) ? $comment['username'] : 'Utilisateur';

                echo '<div class="comment" data-id="' . $comment['id_comm'] . '">';
                echo '<div class="comment-header">';
                echo '<img src="../images/' . htmlspecialchars($profilePicture) . '" alt="' . htmlspecialchars($username) . '" class="comment-avatar">';
                echo '<span class="comment-username">' . htmlspecialchars($username) . '</span>';
                echo '<span class="comment-date"><i class="fa-solid fa-clock"></i> ' . time_elapsed_string($comment['created_at']) . '</span>';
                echo '</div>';
                echo '<div class="comment-body">' . nl2br(htmlspecialchars($comment['content'])) . '</div>';
                // Bloc de vote
                echo '<div class="comment-actions">';
                echo '<button class="like-button ' . $likeClass . '" data-id="' . $comment['id_comm'] . '" data-vote="1"><i class="fa-solid fa-thumbs-up"></i></button>';
                echo '<span class="like-count">' . $like_count . '</span>';
                echo '<button class="dislike-button ' . $dislikeClass . '" data-id="' . $comment['id_comm'] . '" data-vote="-1"><i class="fa-solid fa-thumbs-down"></i></button>';
                echo '<span class="dislike-count">' . $dislike_count . '</span>';
                echo '</div>';
                // Bouton Répondre et formulaire de réponse
                if (isset($_SESSION['id_users'])) {
                    echo '<button class="reply-button" data-parent="' . $comment['id_comm'] . '"><i class="fa-solid fa-reply"></i> Répondre</button>';
                    echo '<div class="reply-form" data-parent-form="' . $comment['id_comm'] . '" style="display:none;">';
                    echo '<textarea placeholder="Votre réponse..."></textarea>';
                    echo '<button class="submit-reply" data-parent="' . $comment['id_comm'] . '"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>';
                    echo '</div>';
                }
                // Affichage des réponses (limité à 1 niveau)
                if ($depth == 0 && !empty($comment['replies'])) {
                    echo '<div class="replies">';
                    foreach ($comment['replies'] as $reply) {
                        displayComments([$reply], 1);
                    }
                    echo '</div>';
                } else if ($depth > 0 && !empty($comment['replies'])) {
                    foreach ($comment['replies'] as $reply) {
                        displayComments([$reply], 1);
                    }
                }
                echo '</div>';
            }
        }
        displayComments($rootComments);
        ?>
    </div>
</div>

<!-- Inclusion des scripts pour les graphiques et interactions -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../script/details.js"></script>
<script src="../script/favoris.js"></script>
<script src="../script/commentaires.js"></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
