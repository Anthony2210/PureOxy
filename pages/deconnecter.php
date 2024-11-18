<?php
/**
 * Déconnexion de l'Utilisateur
 *
 * Ce script gère la déconnexion des utilisateurs en supprimant les données de session
 * et en redirigeant l'utilisateur vers la page de compte.
 *
 * @package PureOxy
 * @subpackage Authentification
 * @version 1.0
 * @since 2024-04-27
 */

session_start();

/**
 * Supprimer toutes les variables de session.
 */
session_unset();

/**
 * Détruire la session en cours.
 */
session_destroy();

/**
 * Rediriger l'utilisateur vers la page de compte après la déconnexion.
 */
header("Location: compte.php");
exit;
?>
