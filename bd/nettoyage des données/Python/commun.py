import pandas as pd
from thefuzz import process

# ---------------------------------------------------------
# MAP FICHIER -> SEP
# (2023 et 2024 : sep=";", 2025 : sep="\t")
# ---------------------------------------------------------
sep_map = {
    "/Users/akkouh/Desktop/scd3/2024_merged.csv": ";"
}

# ---------------------------------------------------------
# LISTE DES FICHIERS :
# ---------------------------------------------------------
fichiers = [
    "/Users/akkouh/Desktop/scd3/2024_merged.csv"
]

# Fichier référentiel de villes (pour fuzzy matching)
fichier_villes = "/Users/akkouh/Desktop/scd3/cities_raw.csv"

# Fichier final unique
fichier_final = "/Users/akkouh/Desktop/scd3/all_years_cleaned_daily3.csv"

# ---------------------------------------------------------
# CHARGER LA LISTE DE VILLES
# ---------------------------------------------------------
df_ref = pd.read_csv(fichier_villes, sep=";", encoding="utf-8")
list_of_cities = df_ref["City"].unique().tolist()
print("Nombre de villes dans df_ref :", len(list_of_cities))


# ---------------------------------------------------------
# FONCTION DE FUZZY MATCHING (pour un seul nom_site)
# ---------------------------------------------------------
def find_best_city(site_name, possible_cities, threshold=80):
    best_match, score = process.extractOne(site_name, possible_cities)
    if score >= threshold:
        return best_match
    else:
        return "Unknown"


# ---------------------------------------------------------
# FONCTION POUR DETECTER ET RENOMMER LA COLONNE "nom_site"
# ---------------------------------------------------------
def rename_nom_site_column(df):
    """
    Cherche une colonne qui correspond à 'nom_site',
    par ex. 'nom site', 'Nom site', etc.,
    et la renomme en 'nom_site'.
    """
    for col in df.columns:
        col_normalise = col.lower().replace(" ", "")
        if col_normalise == "nomsite":
            df.rename(columns={col: "nom_site"}, inplace=True)
            return df
    return df


# ---------------------------------------------------------
# LISTE POUR STOCKER LES DF JOURNALIERS
# ---------------------------------------------------------
all_daily = []

# ---------------------------------------------------------
# TRAITER CHAQUE FICHIER SEPAREMENT, PUIS CONCATENER
# ---------------------------------------------------------
for fichier_entree in fichiers:
    print(f"\n=== TRAITEMENT DE {fichier_entree} ===")

    # 1) Déterminer le séparateur
    sep_f = sep_map[fichier_entree]
    print(f"Utilisation de sep='{sep_f}' pour {fichier_entree}")

    # 2) Lire le fichier CSV
    df = pd.read_csv(fichier_entree, sep=sep_f, encoding="utf-8")
    print("Colonnes détectées :", df.columns.tolist())
    print("Taille du DataFrame :", df.shape)

    # 3) Renommer la colonne 'nom_site' si nécessaire
    df = rename_nom_site_column(df)
    if "nom_site" not in df.columns:
        print("ERREUR : Aucune colonne ne correspond à 'nom_site' !")
        continue

    # 4) Gérer les NaN dans "nom_site" et forcer en string
    df["nom_site"] = df["nom_site"].fillna("inconnu").astype(str)

    # 5) Normaliser la colonne "nom_site"
    df["nom_site_norm"] = df["nom_site"].str.lower().str.strip()

    # ---------------------------------------------------------
    # 6) APPLIQUER LE FUZZY MATCHING UNIQUEMENT AUX NOMS UNIQUES
    # ---------------------------------------------------------
    unique_sites = df["nom_site_norm"].unique()
    print("Nombre de noms de site uniques :", len(unique_sites))

    # Construire un dictionnaire : nom_site_norm -> ville
    mapping = {}
    for site_name in unique_sites:
        mapping[site_name] = find_best_city(site_name, list_of_cities, threshold=80)

    # Appliquer le mapping
    df["ville"] = df["nom_site_norm"].map(mapping)

    print("Aperçu fuzzy matching :")
    print(df[["nom_site", "ville"]].head(10))

    # 7) Supprimer colonnes inutiles et gérer les NaN
    colonnes_a_supprimer = [
        'Organisme', 'code zas', 'Zas', 'code site',
        "type d'implantation", "type d'influence", 'discriminant',
        'Réglementaire', "type d'évaluation", 'procédure de mesure',
        'taux de saisie', 'type de valeur', 'couverture temporelle',
        'couverture de données', 'code qualité', 'validité'
    ]
    df.drop(columns=colonnes_a_supprimer, errors="ignore", inplace=True)

    # Imputer certaines colonnes critiques avant suppression
    df.fillna({"nom_site": "inconnu", "Polluant": "inconnu"}, inplace=True)
    df.dropna(inplace=True)

    # Ne pas conserver les lignes pour lesquelles le fuzzy matching a échoué
    df = df[df["ville"] != "Unknown"]

    # Corriger le polluant si nécessaire
    df["Polluant"] = df["Polluant"].replace('NOX as NO2', 'NO2')

    print("Après nettoyage, taille du DataFrame :", df.shape)

    # 8) Convertir la date en datetime et créer 'year' et 'jour'
    if "Date de début" in df.columns:
        df.rename(columns={"Date de début": "date_heure"}, inplace=True)
    df["date_heure"] = pd.to_datetime(df["date_heure"], format="%Y/%m/%d %H:%M:%S", errors="coerce")
    df.dropna(subset=["date_heure"], inplace=True)
    df["jour"] = df["date_heure"].dt.date
    df["year"] = df["date_heure"].dt.year

    # 9) Agréger par (year, jour, ville, Polluant, unité de mesure)
    colonnes_groupe = ["year", "jour", "ville", "Polluant", "unité de mesure"]
    agregations = {"valeur": "mean", "valeur brute": "mean"}
    df_daily = df.groupby(colonnes_groupe, as_index=False).agg(agregations)
    df_daily.rename(columns={
        "valeur": "valeur_journaliere",
        "valeur brute": "valeur_brute_journaliere"
    }, inplace=True)

    # Imputer les éventuels NaN dans l'agrégation
    df_daily.fillna(0, inplace=True)

    print("Aperçu final agrégé :")
    print(df_daily.head(10))

    # 10) Stocker dans la liste
    all_daily.append(df_daily)

# ---------------------------------------------------------
# CONCATENER TOUTES LES DONNEES JOURNALIÈRES EN UN SEUL FICHIER
# ---------------------------------------------------------
if all_daily:
    df_final = pd.concat(all_daily, ignore_index=True)
    df_final.sort_values(["year", "jour", "ville"], inplace=True)

    # Imputer d'éventuels NaN résiduels dans le DataFrame final
    df_final.fillna(0, inplace=True)

    df_final.to_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily3.csv", sep=";", encoding="utf-8", index=False)
    print("Fichier final unique créé : all_years_cleaned_daily3.csv")
    print("Taille du DataFrame final :", df_final.shape)
else:
    print("Aucun fichier n'a été traité avec succès.")