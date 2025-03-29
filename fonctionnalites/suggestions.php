<?php
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
