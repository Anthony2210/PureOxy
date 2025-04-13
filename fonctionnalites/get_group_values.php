<?php
/**
 * get_group_values.php
 *
 * Ce script retourne en JSON la liste distincte des valeurs pour un type de groupe spécifié.
 * Pour "department" : retourne la liste des départements (colonne departement)
 * Pour "region" : retourne la liste des régions (colonne region)
 * Pour "densite" : retourne la liste distincte de grille_densite_texte
 *
 * Le paramètre "group_type" doit être présent dans l'URL.
 */
session_start();
require_once '../bd/bd.php';
$db = new Database();

$group_type = $_GET['group_type'] ?? '';
if (!$group_type) {
    echo json_encode(["error" => "Paramètre group_type manquant."]);
    exit;
}

switch ($group_type) {
    case "department":
        $q = "SELECT departement AS val FROM donnees_villes WHERE departement IS NOT NULL AND departement <> '' GROUP BY departement ORDER BY departement ASC";
        break;
    case "region":
        $q = "SELECT region AS val FROM donnees_villes WHERE region IS NOT NULL AND region <> '' GROUP BY region ORDER BY region ASC";
        break;
    case "densite":
        $q = "SELECT grille_densite_texte AS val FROM donnees_villes WHERE grille_densite_texte IS NOT NULL AND grille_densite_texte <> '' GROUP BY grille_densite_texte ORDER BY grille_densite_texte ASC";
        break;
    default:
        echo json_encode(["error" => "Type de groupe non supporté."]);
        exit;
}

$result = $db->getConnection()->query($q);
if(!$result){
    echo json_encode(["error" => "Erreur lors de l'exécution de la requête."]);
    exit;
}
$values = [];
while ($row = $result->fetch_assoc()){
    $values[] = $row['val'];
}
echo json_encode($values);
exit;
?>
