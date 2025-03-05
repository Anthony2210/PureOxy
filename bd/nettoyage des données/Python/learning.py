import pandas as pd
import numpy as np

# Pour le modèle
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error

# 1) CHARGER LE DATASET
# ------------------------------------------------------------------
# Adaptez le chemin, le séparateur (sep) et l'encodage si besoin
df = pd.read_csv("/Users/akkouh/Desktop/2025_cleaned.csv", sep=";", encoding="utf-8")

# Exemple : renommer la colonne "Date de début" en "date_heure"
df.rename(columns={"Date de début": "date_heure"}, inplace=True)

# Convertir la colonne date_heure en datetime
df["date_heure"] = pd.to_datetime(df["date_heure"], format="%Y/%m/%d %H:%M:%S", errors="coerce")

# 2) NETTOYAGE ET GESTION DES VALEURS MANQUANTES
# ------------------------------------------------------------------
# Par exemple, supprimer les lignes où la colonne "valeur" est NaN :
df.dropna(subset=["valeur"], inplace=True)

# 3) TRIER PAR [ville, date_heure]
# ------------------------------------------------------------------
df.sort_values(["ville", "date_heure"], inplace=True)
df.reset_index(drop=True, inplace=True)

# 4) CREATION DES LAGS PAR VILLE
# ------------------------------------------------------------------
df["lag_1"] = df.groupby("ville")["valeur"].shift(1)
df["lag_2"] = df.groupby("ville")["valeur"].shift(2)

# 5) CREATION DES VARIABLES CALENDAIRES
# ------------------------------------------------------------------
df["hour"] = df["date_heure"].dt.hour
df["dayofweek"] = df["date_heure"].dt.dayofweek

# Supprimer les lignes devenues NaN à cause des lags
df.dropna(inplace=True)
df.reset_index(drop=True, inplace=True)

# 6) ENCODAGE DE LA COLONNE "ville" (ONE-HOT ENCODING)
# ------------------------------------------------------------------
df = pd.get_dummies(df, columns=["ville"], drop_first=True)

# 7) DEFINIR X (FEATURES) ET y (CIBLE)
# ------------------------------------------------------------------
features = ["lag_1", "lag_2", "hour", "dayofweek"]
for col in df.columns:
    if col.startswith("ville_"):
        features.append(col)

X = df[features]
y = df["valeur"]  # la pollution à prédire

# 8) SPLIT CHRONOLOGIQUE EN TRAIN / TEST
# ------------------------------------------------------------------
train_size = int(len(df) * 0.8)  # 80% des données pour le train
X_train, X_test = X.iloc[:train_size], X.iloc[train_size:]
y_train, y_test = y.iloc[:train_size], y.iloc[train_size:]

# 9) ENTRAINER UN MODELE SUPERVISE (RANDOM FOREST)
# ------------------------------------------------------------------
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X_train, y_train)

# 10) EVALUATION SUR LE TEST
# ------------------------------------------------------------------
y_pred = model.predict(X_test)

mse = mean_squared_error(y_test, y_pred)
rmse = mse**0.5
print("RMSE sur le test =", rmse)

# 11) CREER UN FICHIER CSV AVEC LES PREDICTIONS
# ------------------------------------------------------------------
# On récupère l'index du test pour lier à la date_heure ou autres colonnes
df_test = df.iloc[train_size:].copy()

# Ajouter la colonne 'valeur_reelle' et 'valeur_predite'
df_test["valeur_reelle"] = y_test.values
df_test["valeur_predite"] = y_pred

# Sauvegarder dans un CSV
df_test.to_csv("/Users/akkouh/Desktop/predictions.csv", index=False)
print("Fichier 'predictions.csv' créé avec les valeurs réelles et prédites.")
