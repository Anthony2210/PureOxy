# PureOxy
## Présentation

PureOxy est une plateforme de visualisation, d'analyse et de prédiction de la qualité de l'air en France.
Le projet a été développé dans le cadre des cours de Gestion de Projet et Sciences des Données 4 de la licence MIASHS (Université Paul Valéry, Montpellier 3).

Le site est accessible librement en ligne à l'adresse suivante : http://pureoxy.rf.gd/ (à noter que seul le chatbot IA ne fonctionne pas en continu, car il est hébergé sur notre propre serveur que nous lançons ponctuellement)

Notre objectif est de fournir un outil accessible aux citoyens, chercheurs et décideurs, pour mieux comprendre la pollution atmosphérique, comparer les villes, et anticiper les évolutions grâce aux prédictions basées sur le machine learning.

## Fonctionnalités principales
- Carte interactive des niveaux de pollution en France (filtrage par polluant et par mois).
- Système de recherche par ville avec autocomplétion.
- Détail de la pollution par ville (historique et prévisions).
- Système de commentaires et de favoris pour les utilisateurs connectés.
- Page de classement des villes selon différents polluants.
- Page de comparaison avancée entre villes (densité, superficie, population...).
- Prédictions des niveaux de pollution pour 2025-2026 avec modèles de Machine Learning (RandomForest).
- Chatbot IA intégré pour répondre aux questions environnementales (basé sur Mistral AI).
- Interface utilisateur épurée avec navigation fluide.
- Gestion sécurisée des comptes utilisateurs.

## Architecture du projet
- Back-end : PHP + MySQL
- Front-end : HTML / CSS / JavaScript

## Bibliothèques et technologies utilisées
- Leaflet : pour l'affichage de la carte interactive.
- Chart.js : pour la création de graphiques dynamiques.
- AJAX / Fetch API : pour les communications asynchrones avec le serveur.
- Simple HTML DOM : pour la récupération d'actualités environnementales.
- Rasa + Ollama : pour l'intégration du chatbot IA Mistral.

## Données utilisées
- Données environnementales : OpenAQ, DataGouv, LCSQA, Geod'Air.
- Données géographiques : INSEE, geo.api.gouv.fr.

Intégration de 5 ans de données historiques pour plus de 275 villes.
Génération de prévisions de pollution pour l'année 2025 via des modèles de machine learning.

## Notes supplémentaires
Le chatbot IA n'est pas actif en permanence sur la version en ligne, car son serveur doit être lancé manuellement par notre équipe.

### Équipe
- Anthony Combes-Aguera
- Mohamed Rekhis Chaouki
- Ayoub Akkouh
- Wassim Harraga
