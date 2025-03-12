import pandas as pd

# 1) Lecture du fichier CSV
df = pd.read_csv("/Users/akkouh/Desktop/nouveau.csv", sep=";", encoding="utf-8")

# 2) Affichage des colonnes détectées (pour vérification)
print("Colonnes détectées :", df.columns.tolist())

# 3) Suppression des colonnes inutiles
colonnes_a_supprimer = [
    'Organisme',
    'code zas',
    'Zas',
    'code site',
    "type d'implantation",
    "type d'influence",
    'discriminant',
    'Réglementaire',
    "type d'évaluation",
    'procédure de mesure',
    'taux de saisie',
    'type de valeur',
    'couverture temporelle',
    'couverture de données',
    'code qualité',
    'validité'
]
df.drop(columns=colonnes_a_supprimer, errors="ignore", inplace=True)

# 4) Supprimer les lignes avec des valeurs manquantes (NaN)
df.dropna(inplace=True)

# 5) Supprimer les lignes dont la colonne "ville" vaut "Unknown"
df = df[df["ville"] != "Unknown"]

# Corriger le polluant si besoin
df["Polluant"] = df["Polluant"].replace('NOX as NO2', 'NO2')

# 6) Sauvegarder le DataFrame nettoyé (horaire) sur le Bureau
df.to_csv("~/Desktop/2025_cleaned.csv", index=False, sep=";", encoding="utf-8")
print("Fichier nettoyé (horaire) enregistré sur le Bureau : 2025_cleaned.csv")

# 7) Convertir la colonne "Date de début" en datetime pour agrégation journalière
df.rename(columns={"Date de début": "date_heure"}, inplace=True)
df["date_heure"] = pd.to_datetime(df["date_heure"], format="%Y/%m/%d %H:%M:%S", errors="coerce")

# 8) Créer une colonne "jour" (sans l'heure)
df["jour"] = df["date_heure"].dt.date

# 9) Définir les colonnes pour le groupby
#    - On NE garde plus "nom_site" pour fusionner tous les sites d'une même ville
colonnes_groupe = [
    "jour",
    "ville",
    "Polluant",
    "unité de mesure"
]

# 10) Choisir comment agréger
#    Par exemple : moyenne de "valeur" et "valeur brute"
agregations = {
    "valeur": "mean",
    "valeur brute": "mean",
}

df_daily = df.groupby(colonnes_groupe, as_index=False).agg(agregations)

# 11) Renommer les colonnes agrégées
df_daily.rename(columns={
    "valeur": "valeur_journaliere",
    "valeur brute": "valeur_brute_journaliere"
}, inplace=True)

# 12) Sauvegarder le DataFrame agrégé par jour
df_daily.to_csv("~/Desktop/2025_cleaned_daily.csv", index=False, sep=";", encoding="utf-8")
print("Fichier agrégé par jour enregistré sur le Bureau : 2025_cleaned_daily.csv")
