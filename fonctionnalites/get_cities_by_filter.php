<?php
/**
 * get_cities_by_filter.php
 *
 * Ce script récupère la liste des villes correspondant à un filtre (département ou région)
 * basé sur la ville de référence passée en paramètre.
 *
 * Références :
 * - Utilisation de MySQLi et des requêtes préparées pour sécuriser l'accès aux données.
 *
 * Utilisation :
 * - Ce fichier est appelé en AJAX depuis l'interface de comparaison pour ajouter
 *   des villes du même département ou de la même région.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */
session_start();
require_once '../bd/bd.php';
$db = new Database();

$filter = $_GET['filter'] ?? '';
$base_city = $_GET['base_city'] ?? '';

if (empty($filter) || empty($base_city)) {
    echo json_encode(["error" => "Paramètres manquants"]);
    exit;
}

$stmt = $db->prepare("SELECT departement, region FROM donnees_villes WHERE ville = ? LIMIT 1");
$stmt->bind_param("s", $base_city);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if ($filter === "department") {
        $value = $row['departement'];
        $query = "SELECT ville FROM donnees_villes WHERE departement = ?";
    } elseif ($filter === "region") {
        $value = $row['region'];
        $query = "SELECT ville FROM donnees_villes WHERE region = ?";
    } else {
        echo json_encode(["error" => "Filtre non valide"]);
        exit;
    }
    $stmt->close();
    $stmt2 = $db->prepare($query);
    $stmt2->bind_param("s", $value);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $cities = [];
    while ($row2 = $result2->fetch_assoc()) {
        $cities[] = $row2['ville'];
    }
    $stmt2->close();
    echo json_encode($cities);
    exit;
} else {
    echo json_encode(["error" => "Ville non trouvée"]);
    exit;
}
?>