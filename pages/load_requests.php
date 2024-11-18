<?php
/**
 * Chargement des Demandes de l'Utilisateur
 *
 * Ce script récupère et affiche les demandes envoyées par l'utilisateur connecté.
 * Si l'utilisateur n'est pas connecté, un message l'invitant à se connecter est affiché.
 *
 * @package PureOxy
 * @subpackage Demandes
 * @version 1.0
 * @since 2024-04-27
 */

session_start();

require_once('../bd/bd.php');

/**
 * Vérifier si l'utilisateur est connecté.
 *
 * Si l'utilisateur n'est pas connecté, afficher un message d'invitation à se connecter et arrêter le script.
 */
if (!isset($_SESSION['user_id'])) {
    echo '<p>Veuillez vous connecter pour voir vos demandes.</p>';
    exit;
}

$user_id = $_SESSION['user_id'];

/**
 * Préparer et exécuter la requête SQL pour récupérer les demandes de l'utilisateur.
 */
$stmt = $conn->prepare("SELECT sujet, message, date_demande FROM messages_contact WHERE user_id = ? ORDER BY date_demande DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

/**
 * Vérifier si des demandes ont été trouvées et les afficher.
 */
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
    /**
     * Afficher un message si l'utilisateur n'a pas encore envoyé de demandes.
     */
    echo '<p>Vous n\'avez pas encore envoyé de demandes.</p>';
}

/**
 * Fermer la requête préparée.
 */
$stmt->close();
?>
