<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('../bd/bd.php');

// Récupérer la requête de l'utilisateur
$query = $_GET['query'] ?? '';

// Initialiser le tableau des résultats
$results = [];

// Si la requête n'est pas vide, lancer la recherche
if (!empty($query)) {
    // Préparer la requête SQL pour obtenir des villes uniques avec le premier code postal rencontré
    $stmt = $conn->prepare("
        SELECT City AS ville, MIN(Postal_Code) AS code_postal, Region AS region 
        FROM pollution_villes 
        WHERE City LIKE ? 
        GROUP BY City, Region 
        ORDER BY City 
        LIMIT 10
    ");
    $search = $query . '%';
    $stmt->bind_param("s", $search);
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
