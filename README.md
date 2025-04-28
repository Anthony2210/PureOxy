# PureOxy
## Présentation
PureOxy est une plateforme de visualisation, d'analyse et de prédiction de la qualité de l'air en France.
Le projet a été développé dans le cadre des cours de Gestion de Projet et Sciences des Données 4 du département MIASHS (Université Paul Valéry, Montpellier 3).

Notre objectif est de fournir un outil accessible aux citoyens, chercheurs et décideurs, pour mieux comprendre la pollution atmosphérique, comparer les villes, et anticiper les évolutions grâce aux prédictions basées sur le machine learning.

## Fonctionnalités principales
Carte interactive des niveaux de pollution en France (filtrage par polluant et par mois).

### Système de recherche par ville avec autocomplétion.

### Détail de la pollution par ville (historique et prévisions).

### Système de commentaires et de favoris pour les utilisateurs connectés.

### Page de classement des villes selon différents polluants.

### Page de comparaison avancée entre villes (par densité, superficie, population...).

### Prédictions des niveaux de pollution pour 2025-2026 avec modèles ML (RandomForest).

### Chatbot IA intégré pour répondre aux questions environnementales (basé sur Mistral AI).

### Interface utilisateur épurée avec navigation fluide.

### Gestion sécurisée des comptes utilisateurs.

## Architecture du projet
### Back-end : PHP + MySQL

### Front-end : HTML / CSS / JavaScript

## Bibliothèques utilisées :

### Leaflet (cartes interactives)

### Chart.js (graphiques dynamiques)

### AJAX / Fetch API

### Simple HTML DOM pour l'actualité

### Rasa + Ollama pour l'intégration du chatbot IA

## Données utilisées
### Données environnementales : OpenAQ, DataGouv, LCSQA, Geod'Air.

###Données géographiques : INSEE, geo.api.gouv.fr.

Intégration de 5 ans de données historiques pour 275 villes.

Génération de prévisions pour l'année 2025 via Machine Learning.
