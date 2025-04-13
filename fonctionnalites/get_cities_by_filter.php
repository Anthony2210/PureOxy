<?php
/**
 * get_cities_by_filter.php
 *
 * Ce script récupère la liste des villes correspondant à un filtre ou un groupe.
 * Si le paramètre group_value est présent, il retourne toutes les villes correspondant au groupe.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */
session_start();
require_once '../bd/bd.php';
$db = new Database();

$filter = $_GET['filter'] ?? '';
$group_value = $_GET['group_value'] ?? '';

if (empty($filter)) {
    echo json_encode(["error" => "Filtre manquant"]);
    exit;
}

if ($group_value !== "") {
    switch ($filter) {
        case "department":
            $query = "SELECT ville FROM donnees_villes WHERE departement = ?";
            $value = $group_value;
            break;
        case "region":
            $query = "SELECT ville FROM donnees_villes WHERE region = ?";
            $value = $group_value;
            break;
        case "densite":
            $query = "SELECT ville FROM donnees_villes WHERE grille_densite_texte = ?";
            $value = $group_value;
            break;
        case "superficie":
            if ($group_value === "moins10") {
                $query = "SELECT ville FROM donnees_villes WHERE superficie_km2 < 10";
            } elseif ($group_value === "10_50") {
                $query = "SELECT ville FROM donnees_villes WHERE superficie_km2 BETWEEN 10 AND 50";
            } elseif ($group_value === "plus50") {
                $query = "SELECT ville FROM donnees_villes WHERE superficie_km2 > 50";
            } else {
                echo json_encode(["error" => "Palier de superficie non valide"]);
                exit;
            }
            break;
        case "population":
            if ($group_value === "moins10k") {
                $query = "SELECT ville FROM donnees_villes WHERE population < 10000";
            } elseif ($group_value === "10k_50k") {
                $query = "SELECT ville FROM donnees_villes WHERE population BETWEEN 10000 AND 50000";
            } elseif ($group_value === "plus50k") {
                $query = "SELECT ville FROM donnees_villes WHERE population > 50000";
            } else {
                echo json_encode(["error" => "Palier de population non valide"]);
                exit;
            }
            break;
        default:
            echo json_encode(["error" => "Filtre non valide"]);
            exit;
    }
    $stmt = $db->prepare($query);
    if (in_array($filter, ["department", "region", "densite"])) {
        $stmt->bind_param("s", $value);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row['ville'];
    }
    $stmt->close();
    echo json_encode($cities);
    exit;
} else {
    echo json_encode(["error" => "Le paramètre group_value est requis pour ce filtre."]);
    exit;
}
?>