<?php
/**
 * deconnecter.php
 *
 * Ce code gère la déconnexion des utilisateurs en supprimant les données de session
 * et en redirigeant l'utilisateur vers la page de compte.
 *
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
header("Location: ../pages/compte.php");
exit;
?>
