import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error, r2_score

# 1) Lire le CSV dans df_original
df_original = pd.read_csv("/Users/akkouh/Desktop/all_years_cleaned_daily.csv", sep=";", encoding="utf-8")

# 2) Créer df_model (copie) pour transformations
df_model = df_original.copy()

# Convertir 'jour' en datetime, trier
df_model["jour"] = pd.to_datetime(df_model["jour"])
df_model.sort_values(["ville", "Polluant", "jour"], inplace=True)

# 3) Créer des lags (par ville, Polluant)
df_model["lag_1"] = df_model.groupby(["ville","Polluant"])["valeur_journaliere"].shift(1)
df_model["lag_2"] = df_model.groupby(["ville","Polluant"])["valeur_journaliere"].shift(2)

# Variables calendaires
df_model["dayofweek"] = df_model["jour"].dt.dayofweek
df_model["month"] = df_model["jour"].dt.month

# One-hot sur 'ville' et 'Polluant'
df_model = pd.get_dummies(df_model, columns=["ville","Polluant"], drop_first=True)

# Supprimer NaN dus aux lags
df_model.dropna(inplace=True)

# Re-index
df_model.reset_index(drop=False, inplace=True)
# 'index' contient l'ancien index, on l'appelle 'old_index' par ex.
df_model.rename(columns={"index": "old_index"}, inplace=True)

# 4) Définir X, y
features = ["lag_1","lag_2","dayofweek","month"]
one_hot_cols = [c for c in df_model.columns if c.startswith("ville_") or c.startswith("Polluant_")]
features += one_hot_cols

X = df_model[features]
y = df_model["valeur_journaliere"]

# 5) Séparer Train / Test (chronologique)
train_size = int(len(df_model)*0.8)
X_train = X.iloc[:train_size]
y_train = y.iloc[:train_size]
X_test = X.iloc[train_size:]
y_test = y.iloc[train_size:]

# 6) Entraîner le RandomForest
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X_train, y_train)

# 7) Prédire
y_pred = model.predict(X_test)

mse = mean_squared_error(y_test, y_pred)
rmse = mse**0.5
r2 = r2_score(y_test, y_pred)
print("RMSE =", rmse, "R² =", r2)

# 8) Construire un DataFrame de test (df_model_test) pour récupérer 'old_index'
df_model_test = df_model.iloc[train_size:].copy()
df_model_test["valeur_predite"] = y_pred

# 9) Mapper les prédictions dans df_original
#    On utilise 'old_index' pour retrouver les lignes correspondantes
df_original_test = df_original.loc[df_model_test["old_index"]].copy()

# Ajouter la colonne 'valeur_predite'
df_original_test["valeur_predite"] = df_model_test["valeur_predite"].values

# 10) Exporter un CSV final lisible
#     -> colonnes : jour, ville, Polluant, valeur_journaliere, valeur_predite, etc.
df_original_test.to_csv("/Users/akkouh/Desktop/predictions_test_readable.csv",
                        sep=";", index=False, encoding="utf-8")

print("Fichier de prédictions créé : predictions_test_readable.csv")
print("Aperçu :")
print(df_original_test.head(20))
