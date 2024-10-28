<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&display=swap" rel="stylesheet">

<?php
include 'bd/bd.php'; // Connexion à la base de données

if (isset($_GET['query'])) {
    $search = $_GET['query'];
    // Requête pour trouver les villes correspondant à la saisie
    $stmt = $conn->prepare("SELECT ville, code_postal, region FROM table_villes WHERE ville LIKE ? LIMIT 5");
    $stmt->execute(["%$search%"]);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode([]); // Retourne un tableau vide si aucune ville trouvée
    }
}
?>
