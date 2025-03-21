<?php
/**
 * suggestions.php
 *
 * Ce code gère les requêtes AJAX pour fournir des suggestions de villes basées sur la saisie de l'utilisateur.
 * Il retourne les résultats au format JSON pour être affichés dynamiquement sur la page de recherche.
 *
 */

session_start();
require_once('../bd/bd.php');

/**
 * Récupération de la requête de l'utilisateur.
 *
 * La requête est obtenue via le paramètre GET 'query'. Si aucun paramètre n'est fourni,
 * une chaîne vide est utilisée par défaut.
 */
$query = $_GET['query'] ?? '';

$results = [];

/**
 * Si la requête n'est pas vide, lancer la recherche dans la base de données.
 */
if (!empty($query)) {
    /**
     * Préparation de la requête SQL pour rechercher des villes correspondantes.
     *
     * La requête sélectionne le nom de la ville, le code postal et la région.
     * Elle filtre les villes dont le nom ou le code postal commence par la requête de l'utilisateur.
     * Les résultats sont groupés par ville et région, triés par nom de ville, et limités à 10 résultats.
     */
    $stmt = $conn->prepare("
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

    // Liaison des paramètres de la requête
    $stmt->bind_param("ss", $query, $query);

    // Exécution de la requête
    $stmt->execute();

    // Obtention des résultats
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    // Fermeture de la requête préparée
    $stmt->close();
}

/**
 * Retourner les résultats au format JSON.
 *
 * Le contenu retourné est au format JSON et est destiné à être traité par le JavaScript côté client.
 */
header('Content-Type: application/json');
echo json_encode($results);