import pandas as pd

# Charger les données
file_path = '/Users/akkouh/Desktop/indices_QA_commune_IDF_filtre.csv'
data = pd.read_csv(file_path)

# Sélection des codes INSEE des départements d'Île-de-France
idf_insee_codes = ['75', '77', '78', '91', '92', '93', '94', '95']

# Filtrer les données pour les départements d'Île-de-France
idf_data = data[data['ninsee'].astype(str).str[:2].isin(idf_insee_codes)]

# Ajouter une colonne 'id_qualité' au début, avec des nombres séquentiels
idf_data.insert(0, 'id_qualité', range(1, len(idf_data) + 1))

# Afficher les premières lignes des données filtrées
print(idf_data.head())

# Sauvegarder le nouveau fichier CSV avec la colonne id_qualité
idf_data.to_csv('/Users/akkouh/Desktop/indices_QA_commune_IDF_filtree_id.csv', index=False)
