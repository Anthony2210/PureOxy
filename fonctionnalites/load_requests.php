<?php
/**
 * load_requests.php
 *
 * Ce script récupère les demandes de contact envoyées par l'utilisateur connecté
 * depuis la table "messages_contact" et les affiche sous forme de liste HTML.
 *
 * Références :
 * - ChatGPT pour la structuration de la requête et la sécurisation de l'affichage.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis l'espace compte pour afficher les demandes de contact de l'utilisateur.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

session_start();
require_once('../bd/bd.php');
$db = new Database();

if (!isset($_SESSION['id_users'])) {
    echo '<p>Veuillez vous connecter pour voir vos demandes.</p>';
    exit;
}

$id_users = $_SESSION['id_users'];

// Préparation de la requête pour récupérer les demandes de contact
$stmt = $db->prepare("SELECT sujet, message, date_demande FROM messages_contact WHERE id_users = ? ORDER BY date_demande DESC");
$stmt->bind_param("i", $id_users);
$stmt->execute();
$result = $stmt->get_result();

// Affichage des demandes ou d'un message si aucune demande n'est trouvée
if ($result->num_rows > 0) {
    echo '<ul>';
    while ($row = $result->fetch_assoc()) {
        echo '<li>';
        echo '<strong>Objet :</strong> ' . htmlspecialchars($row['sujet'], ENT_QUOTES, 'UTF-8') . '<br>';
        echo '<strong>Date :</strong> ' . htmlspecialchars($row['date_demande'], ENT_QUOTES, 'UTF-8') . '<br>';
        echo '<strong>Message :</strong> ' . nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8')) . '<br>';
        echo '</li><hr>';
    }
    echo '</ul>';
} else {
    echo '<p>Vous n\'avez pas encore envoyé de demandes.</p>';
}
$stmt->close();
?>
