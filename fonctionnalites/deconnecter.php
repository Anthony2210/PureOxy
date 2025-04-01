<?php
/**
 * deconnecter.php
 *
 * Ce code gère la déconnexion des utilisateurs en supprimant les données de session
 * et en redirigeant l'utilisateur vers la page d'où il provient.
 */

session_start();

// Supprimer toutes les variables de session.
session_unset();

// Détruire la session en cours.
session_destroy();

// Rediriger l'utilisateur vers la page d'origine s'il y a un référent, sinon vers compte.php
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
header("Location: " . $redirect);
exit;
?>
