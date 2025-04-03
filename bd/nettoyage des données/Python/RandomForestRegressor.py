import pandas as pd
import numpy as np
import datetime
from sklearn.ensemble import GradientBoostingRegressor

# ------------------------------------------------------------------
# 1) LIRE LE FICHIER AGRÉGÉ
# ------------------------------------------------------------------
# Charger le fichier CSV contenant les données agrégées. Le séparateur est ";" et l'encodage est UTF-8.
df = pd.read_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily2.csv", sep=";", encoding="utf-8")

# Convertir la colonne "jour" en type datetime pour faciliter les manipulations temporelles.
df["jour"] = pd.to_datetime(df["jour"])

# Trier le DataFrame par ville, polluant et date pour garantir un ordre chronologique par groupe.
df.sort_values(["ville", "Polluant", "jour"], inplace=True)

# ------------------------------------------------------------------
# 2) CRÉER LES LAGS PAR (ville, Polluant)
# ------------------------------------------------------------------
# Pour chaque groupe (ville, Polluant), créer la variable "lag_1" qui représente la valeur journalière décalée d'un jour.
df["lag_1"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(1)
# Créer la variable "lag_2" qui représente la valeur journalière décalée de deux jours.
df["lag_2"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(2)

# Ajouter des variables calendaires extraites de la date :
# "dayofweek" donne le jour de la semaine (0 pour lundi, 6 pour dimanche).
df["dayofweek"] = df["jour"].dt.dayofweek
# "month" donne le mois (1 à 12).
df["month"] = df["jour"].dt.month

# Effectuer un one-hot encoding sur les colonnes "ville" et "Polluant" afin de convertir ces variables catégorielles en variables numériques.
# Le paramètre drop_first=True permet d'éviter la redondance des informations (dummy variable trap).
df = pd.get_dummies(df, columns=["ville", "Polluant"], drop_first=True)

# Les lignes contenant des valeurs manquantes (NaN) sont supprimées.
# Ces NaN proviennent notamment des lags pour les premières observations de chaque groupe.
df.dropna(inplace=True)
# Réinitialiser l'index du DataFrame après suppression des lignes.
df.reset_index(drop=True, inplace=True)

# ------------------------------------------------------------------
# 3) ENTRAÎNER UN GradientBoostingRegressor
# ------------------------------------------------------------------
# Définir la liste de features de base qui comprend les lags et les variables calendaires.
features = ["lag_1", "lag_2", "dayofweek", "month"]

# Récupérer dynamiquement les colonnes issues du one-hot encoding pour "ville" et "Polluant".
one_hot_cols = [c for c in df.columns if c.startswith("ville_") or c.startswith("Polluant_")]
# Ajouter ces colonnes à la liste des features.
features += one_hot_cols

# Séparer les variables explicatives (X) et la cible (y).
X = df[features]
y = df["valeur_journaliere"]

# Instancier et entraîner un modèle GradientBoostingRegressor avec une graine aléatoire pour la reproductibilité.
model = GradientBoostingRegressor(random_state=42)
model.fit(X, y)
print("Modèle GradientBoostingRegressor entraîné sur toutes les villes, tous polluants, toutes dates.")

# ------------------------------------------------------------------
# 4) DÉTERMINER LA DERNIÈRE DATE
# ------------------------------------------------------------------
# Trouver la dernière date présente dans les données.
last_date = df["jour"].max()
print("Dernière date mesurée :", last_date)

# Définir la date de début des prédictions comme le jour suivant la dernière date mesurée.
start_date = last_date + pd.Timedelta(days=1)
# Nombre de jours à prédire (ici 365 jours).
nb_jours = 365
# Définir la date de fin de la période de prédiction (date de début + nb_jours).
end_date = start_date + pd.Timedelta(days=nb_jours)  # date de fin exclue

# ------------------------------------------------------------------
# 5) RÉCUPÉRER LA LISTE DES VILLES ET POLLUANTS (avant get_dummies)
# ------------------------------------------------------------------
# Relire le fichier CSV original pour récupérer les informations catégorielles initiales sur "ville" et "Polluant".
df_orig2 = pd.read_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily2.csv", sep=";", encoding="utf-8")
df_orig2["jour"] = pd.to_datetime(df_orig2["jour"])

# Extraire la liste unique des villes et des polluants en éliminant les valeurs manquantes.
villes = df_orig2["ville"].dropna().unique()
polluants = df_orig2["Polluant"].dropna().unique()

# ------------------------------------------------------------------
# 6) CALCULER LES LAGS INITIAUX POUR CHAQUE (ville, Polluant)
# ------------------------------------------------------------------
def get_last_lags(sub_df, last_date):
    """
    Pour un sous-dataframe correspondant à une ville et un polluant,
    récupère la valeur journalière du dernier jour (lag_1)
    et celle du jour précédent (lag_2).
    """
    # Créer une copie pour éviter de modifier le DataFrame original.
    sub_df = sub_df.copy()
    # S'assurer que la colonne "jour" est bien en format datetime.
    sub_df["jour"] = pd.to_datetime(sub_df["jour"])
    # Trier par date.
    sub_df.sort_values("jour", inplace=True)

    # Récupérer la ligne correspondant à la dernière date.
    row_last = sub_df[sub_df["jour"] == last_date]
    # Si une seule ligne est trouvée pour la dernière date, extraire la valeur journalière ; sinon, attribuer NaN.
    val_last = row_last.iloc[0]["valeur_journaliere"] if len(row_last) == 1 else np.nan

    # Récupérer les données précédant la dernière date pour obtenir le lag_2.
    sub_before = sub_df[sub_df["jour"] < last_date]
    # Extraire la dernière valeur disponible avant la dernière date.
    val_before = sub_before.iloc[-1]["valeur_journaliere"] if len(sub_before) > 0 else np.nan

    return val_last, val_before

# Pour chaque combinaison (ville, Polluant), calculer les valeurs de lag initiales.
lag_dict = {}
for ville in villes:
    for pol in polluants:
        # Sélectionner les données correspondant à la ville et au polluant.
        subset = df_orig2[(df_orig2["ville"] == ville) & (df_orig2["Polluant"] == pol)]
        # Si aucune donnée n'est trouvée pour la combinaison, passer à la suivante.
        if subset.empty:
            continue
        val_last, val_before = get_last_lags(subset, last_date)
        # Enregistrer les lags dans un dictionnaire avec la clé (ville, Polluant).
        lag_dict[(ville, pol)] = {"lag_1": val_last, "lag_2": val_before}

# ------------------------------------------------------------------
# 7) PRÉ-CALCULER LE MAPPING ONE-HOT POUR CHAQUE (ville, Polluant)
# ------------------------------------------------------------------
# Récupérer les colonnes one-hot utilisées dans le modèle.
model_cols = [c for c in df.columns if c.startswith("ville_") or c.startswith("Polluant_")]
# Variables de base (features non one-hot).
base_features = ["lag_1", "lag_2", "dayofweek", "month"]

one_hot_mapping = {}
# Pour chaque couple (ville, Polluant) présent dans lag_dict,
# créer un mapping des colonnes one-hot (initialisé à 0) et activer la colonne correspondante.
for (ville, pol) in lag_dict.keys():
    # Initialiser toutes les colonnes one-hot à 0.
    row = {col: 0 for col in model_cols}
    # Construire le nom de la colonne correspondant à la ville en gérant les espaces et tirets.
    col_ville = "ville_" + ville.replace(" ", "_").replace("-", "_")
    # Construire le nom de la colonne correspondant au polluant.
    col_pol = "Polluant_" + pol
    # Activer la colonne correspondante si elle existe.
    if col_ville in row:
        row[col_ville] = 1
    if col_pol in row:
        row[col_pol] = 1
    one_hot_mapping[(ville, pol)] = row

# ------------------------------------------------------------------
# 8) PRÉDICTION MULTI-STEP SUR 365 JOURS (VECTORISÉ PAR JOUR)
# ------------------------------------------------------------------
predictions = []
current_date = start_date

# Boucle sur chaque jour de la période de prédiction
while current_date < end_date:
    # Pour chaque jour, construire un DataFrame avec toutes les combinaisons (ville, Polluant)
    rows = []
    # Récupérer la liste des clés (couples ville, polluant) présents dans le dictionnaire des lags.
    keys = list(lag_dict.keys())
    for key in keys:
        ville, pol = key
        lags = lag_dict[key]
        # Construire un dictionnaire contenant les lags et les variables calendaires pour le jour courant.
        row = {
            "lag_1": lags["lag_1"],
            "lag_2": lags["lag_2"],
            "dayofweek": current_date.weekday(),
            "month": current_date.month
        }
        # Ajouter les variables one-hot pré-calculées pour la combinaison (ville, Polluant).
        row.update(one_hot_mapping[key])
        rows.append(row)
    # Convertir la liste de dictionnaires en DataFrame.
    df_future = pd.DataFrame(rows)

    # Vérifier que toutes les colonnes nécessaires (features de base + one-hot) sont présentes.
    full_features = base_features + model_cols
    for col in full_features:
        if col not in df_future.columns:
            df_future[col] = 0
    # Réordonner les colonnes selon l'ordre attendu par le modèle.
    df_future = df_future[full_features]

    # Prédire en batch pour le jour courant à partir des features construites.
    preds = model.predict(df_future)

    # Pour chaque combinaison (ville, Polluant), enregistrer la prédiction et mettre à jour les lags.
    for i, key in enumerate(keys):
        ville, pol = key
        y_future = preds[i]
        predictions.append({
            "jour": current_date,
            "ville": ville,
            "Polluant": pol,
            "valeur_predite": y_future
        })
        # Mettre à jour les lags pour la prochaine itération :
        # Le lag précédent devient le lag_2 et la nouvelle prédiction devient le lag_1.
        lag_dict[key]["lag_2"] = lag_dict[key]["lag_1"]
        lag_dict[key]["lag_1"] = y_future

    # Passer au jour suivant.
    current_date += datetime.timedelta(days=1)

# ------------------------------------------------------------------
# 9) EXPORTER LES PRÉDICTIONS
# ------------------------------------------------------------------
# Créer un DataFrame à partir de la liste des prédictions.
df_pred = pd.DataFrame(predictions)
# Trier les prédictions par ville, polluant et date.
df_pred.sort_values(["ville", "Polluant", "jour"], inplace=True)

# Exporter les résultats dans un fichier CSV avec le séparateur ";" et l'encodage UTF-8.
df_pred.to_csv("/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_gradient_boosting.csv", sep=";", index=False,
               encoding="utf-8")

# Afficher un message de confirmation et un aperçu des premières lignes du fichier de prédictions.
print("Fichier 'prediction_1year_all_cities_gradient_boosting.csv' créé.")
print("Aperçu :")
print(df_pred.head(20))