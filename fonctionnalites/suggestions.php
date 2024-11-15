<?php
session_start();
require_once('../bd/bd.php');

// Récupérer la requête de l'utilisateur
$query = $_GET['query'] ?? '';

// Initialiser le tableau des résultats
$results = [];

// Si la requête n'est pas vide, lancer la recherche
if (!empty($query)) {
    $stmt = $conn->prepare("
        SELECT City AS ville, MIN(Postal_Code) AS code_postal, Region AS region 
        FROM pollution_villes 
        WHERE City LIKE CONCAT(?, '%') OR Postal_Code LIKE CONCAT(?, '%')
        GROUP BY City, Region 
        ORDER BY City 
        LIMIT 10
    ");
    $stmt->bind_param("ss", $query, $query);
    $stmt->execute();

    // Obtenir les résultats
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    $stmt->close();
}

// Retourner les résultats en format JSON
header('Content-Type: application/json');
echo json_encode($results);
