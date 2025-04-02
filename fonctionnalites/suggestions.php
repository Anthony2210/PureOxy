<?php
/**
 * suggestions.php
 *
 * Ce fichier renvoie en format JSON les suggestions de villes en fonction de la requête passée en GET.
 * Il recherche dans la base de données les villes dont le nom ou le code postal commence par la chaîne fournie.
 *
 * Références :
 * - ChatGPT pour la formulation de la requête SQL et la gestion du cache.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX par suggestions.js pour afficher les suggestions en temps réel.
 *
 * Fichier placé dans le dossier fonctionnalite.
 */
session_start();
require_once('../bd/bd.php');
$db = new Database();

$query = $_GET['query'] ?? '';
$results = [];

if (!empty($query)) {
    $stmt = $db->prepare("
        SELECT 
            ville AS ville, 
            postal_code AS code_postal, 
            region
        FROM donnees_villes
        WHERE ville LIKE CONCAT(?, '%')
           OR postal_code LIKE CONCAT(?, '%')
        GROUP BY ville, region
        ORDER BY ville
        LIMIT 10
    ");
    $stmt->bind_param("ss", $query, $query);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($results);
?>
