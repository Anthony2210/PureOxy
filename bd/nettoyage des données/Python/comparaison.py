import pandas as pd
import matplotlib.pyplot as plt
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score
# aide de chatgpt
# --------------------------------------------------------
# 1) Charger les données réelles de 2024
# --------------------------------------------------------
df_real = pd.read_csv("/Users/akkouh/Desktop/scd3/all_years_cleaned_daily3.csv", sep=";", encoding="utf-8")
df_real["jour"] = pd.to_datetime(df_real["jour"])
print("Taille des données réelles :", df_real.shape)
print("Dates réelles :", df_real["jour"].min(), df_real["jour"].max())

# Vérifier que df_real couvre bien 2024
# ex: doit aller au moins jusqu'à 2024-12-31

# --------------------------------------------------------
# 2) Définir les chemins vers les fichiers de prédiction
# --------------------------------------------------------
models = {
    "Modèle 1": "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities.csv",
    "Modèle 2": "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_linear_regression.csv",
    "Modèle 3 (XGBoost 2024)": "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_xgboost.csv"
}

# --------------------------------------------------------
# 3) Fonction d'évaluation des prédictions
# --------------------------------------------------------
def evaluate_model(df_compare, prediction_col="valeur_predite"):
    # df_compare doit avoir 'valeur_journaliere' (réelle) et 'valeur_predite'
    y_true = df_compare["valeur_journaliere"]
    y_pred = df_compare[prediction_col]
    mse = mean_squared_error(y_true, y_pred)
    rmse = mse**0.5
    mae = mean_absolute_error(y_true, y_pred)
    r2 = r2_score(y_true, y_pred)
    return mse, rmse, mae, r2

results = {}

# --------------------------------------------------------
# 4) Boucle sur chaque modèle pour charger, fusionner et évaluer
# --------------------------------------------------------
for model_name, filepath in models.items():
    print(f"\n=== Évaluation de {model_name} ===")
    df_pred = pd.read_csv(filepath, sep=";", encoding="utf-8")
    df_pred["jour"] = pd.to_datetime(df_pred["jour"])

    # Fusion sur (jour, ville, Polluant)
    df_compare = pd.merge(df_real, df_pred, on=["jour", "ville", "Polluant"], how="inner")

    if df_compare.empty:
        print("Aucune correspondance dans df_compare : vérifiez la période et les colonnes.")
        continue

    mse, rmse, mae, r2 = evaluate_model(df_compare)
    results[model_name] = {"MSE": mse, "RMSE": rmse, "MAE": mae, "R2": r2}
    print(f"{model_name} : MSE = {mse:.2f}, RMSE = {rmse:.2f}, MAE = {mae:.2f}, R² = {r2:.2f}")

    # Tracer la comparaison
    plt.figure(figsize=(10, 6))
    plt.plot(df_compare["jour"], df_compare["valeur_journaliere"], label="Valeur réelle", color="black")
    plt.plot(df_compare["jour"], df_compare["valeur_predite"], label=f"Prédiction {model_name}", linestyle="--")
    plt.xlabel("Date")
    plt.ylabel("Valeur")
    plt.title(f"Comparaison Réel vs {model_name} (2024)")
    plt.legend()
    plt.show()

# --------------------------------------------------------
# 5) Résumé des performances
# --------------------------------------------------------
df_results = pd.DataFrame(results).T
print("\nRésumé des performances :")
print(df_results)