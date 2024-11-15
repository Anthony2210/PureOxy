-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 15 nov. 2024 à 08:41
-- Version du serveur : 8.2.0
-- Version de PHP : 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `pureoxy`
--

-- --------------------------------------------------------

--
-- Structure de la table `commentaire`
--

DROP TABLE IF EXISTS `commentaire`;
CREATE TABLE IF NOT EXISTS `commentaire` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `page` varchar(255) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `content` text NOT NULL,
  `likes` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commentaire`
--

INSERT INTO `commentaire` (`id`, `user_id`, `page`, `parent_id`, `content`, `likes`, `created_at`) VALUES
(1, 6, 'details.php?ville=Paris', 0, 'C\'est incroyable', 0, '2024-11-14 18:58:00'),
(2, 6, 'details.php?ville=Paris', 0, 'C\'est incroyable', 0, '2024-11-14 18:58:08'),
(3, 6, 'details.php?ville=Paris', 0, 'je suis choqué', 0, '2024-11-14 18:58:14'),
(4, 6, 'qualite_air.php', 0, 'incroyable cet article !', 0, '2024-11-15 09:41:10');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
