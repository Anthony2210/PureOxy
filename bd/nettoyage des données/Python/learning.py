import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
import datetime

# ------------------------------------------------------------------
# 1) LIRE LE FICHIER AGRÉGÉ
# ------------------------------------------------------------------
df = pd.read_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily.csv", sep=";", encoding="utf-8")

# Convertir 'jour' en datetime et trier
df["jour"] = pd.to_datetime(df["jour"])
df.sort_values(["ville", "Polluant", "jour"], inplace=True)

# ------------------------------------------------------------------
# 2) CRÉER LES LAGS PAR (ville, Polluant)
# ------------------------------------------------------------------
df["lag_1"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(1)
df["lag_2"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(2)

# Variables calendaires
df["dayofweek"] = df["jour"].dt.dayofweek
df["month"] = df["jour"].dt.month

# One-hot sur 'ville' et 'Polluant'
df = pd.get_dummies(df, columns=["ville", "Polluant"], drop_first=True)

# Supprimer les lignes avec NaN (dus aux lags)
df.dropna(inplace=True)
df.reset_index(drop=True, inplace=True)

# ------------------------------------------------------------------
# 3) ENTRAÎNER UN RandomForest SUR TOUTES LES DATES
# ------------------------------------------------------------------
features = ["lag_1", "lag_2", "dayofweek", "month"]
one_hot_cols = [c for c in df.columns if c.startswith("ville_") or c.startswith("Polluant_")]
features += one_hot_cols

X = df[features]
y = df["valeur_journaliere"]

model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X, y)
print("Modèle RandomForest entraîné sur toutes les villes, tous polluants, toutes dates.")

# ------------------------------------------------------------------
# 4) TROUVER LA DERNIÈRE DATE
# ------------------------------------------------------------------
last_date = df["jour"].max()
print("Dernière date mesurée :", last_date)

# Définir l'horizon de prédiction : 365 jours à partir du lendemain de la dernière date
start_date = last_date + pd.Timedelta(days=1)
nb_jours = 365
end_date = start_date + pd.Timedelta(days=nb_jours)  # date de fin exclue

# ------------------------------------------------------------------
# 5) RÉCUPÉRER LA LISTE DES VILLES ET POLLUANTS (avant get_dummies)
# ------------------------------------------------------------------
df_orig2 = pd.read_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily.csv", sep=";", encoding="utf-8")
df_orig2["jour"] = pd.to_datetime(df_orig2["jour"])

villes = df_orig2["ville"].dropna().unique()
polluants = df_orig2["Polluant"].dropna().unique()

# ------------------------------------------------------------------
# 6) CALCULER LES LAGS INITIAUX POUR CHAQUE (ville, Polluant)
# ------------------------------------------------------------------
def get_last_lags(sub_df, last_date):
    """
    Pour un sous-dataframe d'une ville et d'un polluant,
    récupère la 'valeur_journaliere' du dernier jour (lag_1)
    et celle du jour précédent (lag_2).
    """
    sub_df = sub_df.copy()
    sub_df["jour"] = pd.to_datetime(sub_df["jour"])
    sub_df.sort_values("jour", inplace=True)

    row_last = sub_df[sub_df["jour"] == last_date]
    val_last = row_last.iloc[0]["valeur_journaliere"] if len(row_last) == 1 else np.nan

    sub_before = sub_df[sub_df["jour"] < last_date]
    val_before = sub_before.iloc[-1]["valeur_journaliere"] if len(sub_before) > 0 else np.nan

    return val_last, val_before

lag_dict = {}
for ville in villes:
    for pol in polluants:
        subset = df_orig2[(df_orig2["ville"] == ville) & (df_orig2["Polluant"] == pol)]
        if subset.empty:
            continue
        val_last, val_before = get_last_lags(subset, last_date)
        lag_dict[(ville, pol)] = {"lag_1": val_last, "lag_2": val_before}

# ------------------------------------------------------------------
# 7) PRÉ-CALCULER LE MAPPING ONE-HOT POUR CHAQUE (ville, Polluant)
# ------------------------------------------------------------------
# On récupère les colonnes one-hot utilisées lors de l'entraînement (déjà créées dans df)
model_cols = [c for c in df.columns if c.startswith("ville_") or c.startswith("Polluant_")]
base_features = ["lag_1", "lag_2", "dayofweek", "month"]

one_hot_mapping = {}
for (ville, pol) in lag_dict.keys():
    row = {col: 0 for col in model_cols}
    col_ville = "ville_" + ville.replace(" ", "_").replace("-", "_")
    col_pol = "Polluant_" + pol
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

while current_date < end_date:
    # Pour chaque jour, construire un DataFrame avec toutes les combinaisons (ville, Polluant)
    rows = []
    keys = list(lag_dict.keys())
    for key in keys:
        ville, pol = key
        lags = lag_dict[key]
        row = {
            "lag_1": lags["lag_1"],
            "lag_2": lags["lag_2"],
            "dayofweek": current_date.weekday(),
            "month": current_date.month
        }
        # Ajouter les variables one-hot pré-calculées
        row.update(one_hot_mapping[key])
        rows.append(row)
    df_future = pd.DataFrame(rows)

    # S'assurer que toutes les colonnes nécessaires sont présentes
    full_features = base_features + model_cols
    for col in full_features:
        if col not in df_future.columns:
            df_future[col] = 0
    df_future = df_future[full_features]

    # Prédiction en batch pour la journée
    preds = model.predict(df_future)

    # Mettre à jour les prédictions et les lags pour chaque couple (ville, Polluant)
    for i, key in enumerate(keys):
        ville, pol = key
        y_future = preds[i]
        predictions.append({
            "jour": current_date,
            "ville": ville,
            "Polluant": pol,
            "valeur_predite": y_future
        })
        # Mettre à jour les lags : lag_2 <- lag_1, lag_1 <- y_future
        lag_dict[key]["lag_2"] = lag_dict[key]["lag_1"]
        lag_dict[key]["lag_1"] = y_future

    # Passer au jour suivant
    current_date += datetime.timedelta(days=1)

# ------------------------------------------------------------------
# 9) EXPORTER LES PRÉDICTIONS
# ------------------------------------------------------------------
df_pred = pd.DataFrame(predictions)
df_pred.sort_values(["ville", "Polluant", "jour"], inplace=True)
df_pred.to_csv("/Users/akkouh/Desktop/scd3/prediction_1year_all_cities.csv", sep=";", index=False, encoding="utf-8")

print("Fichier 'prediction_1year_all_cities.csv' créé.")
print("Aperçu :")
print(df_pred.head(20))