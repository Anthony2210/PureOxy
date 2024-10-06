# Charger les bibliothèques nécessaires
library(dplyr)
library(tidyr)

# Importer le fichier CSV (modifie le chemin du fichier selon ton fichier local)
data <- read.csv("C:\\Users\\antoc\\OneDrive\\Bureau\\Cours sup\\2-MIASHS\\L3\\semestre 5\\Gestion de projet\\données\\qualite-de-lair-france.csv", sep = ";")

# Séparer la colonne "Coordinates" en "Latitude" et "Longitude"
data_separated <- data %>%
  separate(Coordinates, into = c("Latitude", "Longitude"), sep = ", ", convert = TRUE)

# Afficher les noms de colonnes pour vérifier leur exactitude
colnames(data)

# Réorganiser les colonnes pour correspondre à l'ordre attendu dans phpMyAdmin
data_reordered <- data_separated %>%
  select(`Country.Code`, City, Location, Latitude, Longitude, Pollutant, `Source.Name`, Unit, Value, `Last.Updated`, `Country.Label`)

# Afficher le résultat
print(data_reordered)

# Sauvegarder dans un nouveau fichier CSV avec les colonnes séparées
write.csv(data_separated, "C:\\Users\\antoc\\OneDrive\\Bureau\\Cours sup\\2-MIASHS\\L3\\semestre 5\\Gestion de projet\\données\\qualite-de-lair-france-nettoyé.csv", row.names = FALSE)

