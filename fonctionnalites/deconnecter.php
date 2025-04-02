<?php
/**
 * deconnecter.php
 *
 * Ce fichier gère la déconnexion des utilisateurs. Il supprime toutes les variables
 * de session et détruit la session en cours, puis redirige l'utilisateur vers la page
 * d'où il provient ou vers une page par défaut si aucun référent n'est défini.
 *
 * Références :
 * - ChatGPT pour des conseils sur la structuration et la documentation du code.
 *
 * Utilisation :
 * - Inclure ce fichier lorsque vous souhaitez déconnecter un utilisateur.
 *
 * Fichier placé dans le dossier fonctionnalites.
 */

session_start(); // Démarrage ou reprise de la session en cours

// Supprimer toutes les variables de session
session_unset();

// Détruire la session en cours
session_destroy();

// Déterminer la page de redirection : si un référent existe, rediriger vers cette page, sinon vers la page par défaut (index.php)
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';

// Redirection vers la page déterminée
header("Location: " . $redirect);
exit;
?>
