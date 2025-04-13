<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Infos de connexion à la base locale (MAMP)
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Vide par défaut dans MAMP
define('DB_NAME', 'pureoxy');

// Tester la connexion
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $error_message = "Problème : Impossible de se connecter à la base de données 'pureoxy'. Erreur : " . $conn->connect_error;
    echo json_encode([
        "status" => "error",
        "message" => $error_message
    ]);
    exit;
}

// Confirmer que la connexion fonctionne
$conn->set_charset("utf8mb4");

// Vérifier les paramètres
if (!isset($_GET['ville']) || !isset($_GET['date'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Problème : Il manque la ville ou la date dans l'URL"
    ]);
    exit;
}

$ville = $_GET['ville'];
$date = $_GET['date'];

// Vérifier si la table 'all_years_cleaned_daily' existe
if (!$conn->query("SHOW TABLES LIKE 'all_years_cleaned_daily'")->num_rows) {
    echo json_encode([
        "status" => "error",
        "message" => "Problème : La table 'all_years_cleaned_daily' n'existe pas dans la base 'pureoxy'"
    ]);
    $conn->close();
    exit;
}

// Requête pour données réelles
$stmt = $conn->prepare("SELECT polluant, valeur_journaliere AS valeur, unite_de_mesure FROM all_years_cleaned_daily WHERE ville = ? AND jour = ?");
if ($stmt === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Problème : Erreur lors de la préparation de la requête pour 'all_years_cleaned_daily' : " . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("ss", $ville, $date);
$stmt->execute();
$result = $stmt->get_result();

$pollution = [];

while ($row = $result->fetch_assoc()) {
    $pollution[] = $row;
}

$stmt->close();

// Si vide, vérifier la table 'prediction_cities'
if (empty($pollution)) {
    if (!$conn->query("SHOW TABLES LIKE 'prediction_cities'")->num_rows) {
        echo json_encode([
            "status" => "error",
            "message" => "Problème : La table 'prediction_cities' n'existe pas dans la base 'pureoxy'"
        ]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("SELECT polluant, valeur_predite AS valeur, '-' AS unite_de_mesure FROM prediction_cities WHERE ville = ? AND jour = ?");
    if ($stmt === false) {
        echo json_encode([
            "status" => "error",
            "message" => "Problème : Erreur lors de la préparation de la requête pour 'prediction_cities' : " . $conn->error
        ]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("ss", $ville, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pollution[] = $row;
    }
    $stmt->close();
}

// Fermer la connexion
$conn->close();

// Retourner les données ou un message
if (empty($pollution)) {
    echo json_encode([
        "status" => "success",
        "message" => "Aucune donnée trouvée pour $ville le $date"
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "message" => "Données récupérées avec succès",
        "data" => $pollution
    ]);
}
?>