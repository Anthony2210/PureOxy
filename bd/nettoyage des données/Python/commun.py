import pandas as pd
from thefuzz import process

# 1) Charger votre DataFrame principal
df = pd.read_csv("/Users/akkouh/Desktop/2025_merged.csv", sep="\t", encoding="utf-8")
# Vérifier les colonnes lues
print("Colonnes dans df :", df.columns.tolist())

# Si la colonne s'appelle déjà 'nom_site', pas besoin de la renommer.
# df.rename(columns={"nom site": "nom_site"}, inplace=True)  # <-- inutile si c'est déjà nom_site

# 2) Charger la liste officielle de communes
df_ref = pd.read_csv("/Users/akkouh/Desktop/cities_raw.csv", sep=";", encoding="utf-8")
print("Colonnes dans df_ref :", df_ref.columns.tolist())

# On suppose que df_ref a une colonne "City" listant les noms de villes
list_of_cities = df_ref["City"].unique().tolist()

# 3) Normaliser la colonne "nom_site"
df["nom_site_norm"] = df["nom_site"].str.lower().str.strip()

# 4) Fonction de fuzzy matching
def find_best_city(site_name, possible_cities, threshold=80):
    best_match, score = process.extractOne(site_name, possible_cities)
    if score >= threshold:
        return best_match
    else:
        return "Unknown"

# 5) Appliquer la fonction
df["ville"] = df["nom_site_norm"].apply(
    lambda x: find_best_city(x, list_of_cities, threshold=80)
)

# 6) Vérifier un aperçu
print(df[["nom_site", "ville"]].head(50))

# 7) Exporter le résultat
df.to_csv("~/Desktop/polluants_ville_matchee.csv", index=False, sep=";", encoding="utf-8")
print("Fichier polluants_ville_matchee.csv créé.")
