import pandas as pd
import matplotlib.pyplot as plt
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score

# --------------------------------------------------------
# 1) Charger les données réelles de 2024
# --------------------------------------------------------
df_real = pd.read_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily2.csv", sep=";", encoding="utf-8")
df_real["jour"] = pd.to_datetime(df_real["jour"])
# On suppose que df_real contient les colonnes : "jour", "ville", "Polluant", "valeur_reelle"
print("Taille des données réelles :", df_real.shape)

# --------------------------------------------------------
# 2) Définir les chemins vers les fichiers de prédiction pour chaque modèle
# --------------------------------------------------------
models = {
    "Modèle 1": "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities.csv",
    "Modèle 2": "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_linear_regression.csv",
    "Modèle 3": "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_xgboost.csv"
}


# --------------------------------------------------------
# 3) Fonction d'évaluation des prédictions
# --------------------------------------------------------
def evaluate_model(df_compare, prediction_col="valeur_predite"):
    y_true = df_compare["valeur_journaliere"]
    y_pred = df_compare[prediction_col]
    mse = mean_squared_error(y_true, y_pred)
    rmse = mse ** 0.5
    mae = mean_absolute_error(y_true, y_pred)
    r2 = r2_score(y_true, y_pred)
    return mse, rmse, mae, r2


# Dictionnaire pour stocker les résultats
results = {}

# --------------------------------------------------------
# 4) Boucle sur chaque modèle pour charger, fusionner et évaluer
# --------------------------------------------------------
for model_name, filepath in models.items():
    # Charger les prédictions du modèle
    df_pred = pd.read_csv(filepath, sep=";", encoding="utf-8")
    df_pred["jour"] = pd.to_datetime(df_pred["jour"])

    # Fusionner les données réelles et les prédictions sur les colonnes communes
    df_compare = pd.merge(df_real, df_pred, on=["jour", "ville", "Polluant"], how="inner")

    # Calculer les métriques d'évaluation
    mse, rmse, mae, r2 = evaluate_model(df_compare)
    results[model_name] = {"MSE": mse, "RMSE": rmse, "MAE": mae, "R2": r2}

    print(f"{model_name} : MSE = {mse:.2f}, RMSE = {rmse:.2f}, MAE = {mae:.2f}, R² = {r2:.2f}")

    # Tracer la comparaison entre les valeurs réelles et les prédictions
    plt.figure(figsize=(10, 6))
    plt.plot(df_compare["jour"], df_compare["valeur_journaliere"], label="Valeur réelle", color="black")
    plt.plot(df_compare["jour"], df_compare["valeur_predite"], label=f"Prédiction {model_name}", linestyle="--")
    plt.xlabel("Date")
    plt.ylabel("Valeur")
    plt.title(f"Comparaison Réel vs {model_name}")
    plt.legend()
    plt.show()

# --------------------------------------------------------
# 5) Afficher un résumé des résultats sous forme de DataFrame
# --------------------------------------------------------
df_results = pd.DataFrame(results).T
print("Résumé des performances des modèles :")
print(df_results)