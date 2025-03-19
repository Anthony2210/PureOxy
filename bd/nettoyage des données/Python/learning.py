import pandas as pd
import numpy as np
import datetime
import logging
from sklearn.ensemble import RandomForestRegressor


def load_data(csv_path):
    """
    Charge le fichier CSV et convertit la colonne 'jour' en datetime.
    """
    df = pd.read_csv(csv_path, sep=";", encoding="utf-8")
    df["jour"] = pd.to_datetime(df["jour"])
    return df


def preprocess_data(df):
    """
    Trie les données par ville, polluant et date, crée les variables de retard (lags),
    ajoute les variables calendaires et effectue le one-hot encoding sur 'ville' et 'Polluant'.
    """
    df.sort_values(["ville", "Polluant", "jour"], inplace=True)

    # Création des lags pour 'valeur_journaliere'
    df["lag_1"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(1)
    df["lag_2"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(2)

    # Variables calendaires
    df["dayofweek"] = df["jour"].dt.dayofweek
    df["month"] = df["jour"].dt.month

    # One-hot encoding pour 'ville' et 'Polluant' (drop_first pour éviter la multicolinéarité)
    df = pd.get_dummies(df, columns=["ville", "Polluant"], drop_first=True)

    # Suppression des lignes avec NaN dus aux lags
    df.dropna(inplace=True)
    df.reset_index(drop=True, inplace=True)
    return df


def train_random_forest(X, y, random_state=42):
    """
    Entraîne un modèle RandomForestRegressor et le retourne.
    """
    model = RandomForestRegressor(n_estimators=100, random_state=random_state)
    model.fit(X, y)
    return model


def get_last_lags(sub_df, last_date):
    """
    Pour un sous-dataframe correspondant à une ville et un polluant,
    récupère la 'valeur_journaliere' du dernier jour (lag_1) et celle du jour précédent (lag_2).
    """
    sub_df = sub_df.copy()
    sub_df["jour"] = pd.to_datetime(sub_df["jour"])
    sub_df.sort_values("jour", inplace=True)

    row_last = sub_df[sub_df["jour"] == last_date]
    val_last = row_last.iloc[0]["valeur_journaliere"] if len(row_last) == 1 else np.nan

    sub_before = sub_df[sub_df["jour"] < last_date]
    val_before = sub_before.iloc[-1]["valeur_journaliere"] if len(sub_before) > 0 else np.nan

    return val_last, val_before


def prepare_lag_and_onehot_mapping(original_df, last_date, model_cols):
    """
    Pour chaque combinaison (ville, Polluant) issue des données originales,
    récupère les derniers lags et prépare un mapping one-hot des variables.
    """
    villes = original_df["ville"].dropna().unique()
    polluants = original_df["Polluant"].dropna().unique()

    lag_dict = {}
    for ville in villes:
        for pol in polluants:
            subset = original_df[(original_df["ville"] == ville) & (original_df["Polluant"] == pol)]
            if subset.empty:
                continue
            val_last, val_before = get_last_lags(subset, last_date)
            lag_dict[(ville, pol)] = {"lag_1": val_last, "lag_2": val_before}

    # Préparer le mapping one-hot en se basant sur les colonnes utilisées lors de l'entraînement
    one_hot_mapping = {}
    for (ville, pol) in lag_dict.keys():
        # Initialiser toutes les colonnes one-hot à 0
        row = {col: 0 for col in model_cols}
        # Adapter le nom de colonne au format produit par get_dummies
        col_ville = "ville_" + ville.replace(" ", "_").replace("-", "_")
        col_pol = "Polluant_" + pol
        if col_ville in row:
            row[col_ville] = 1
        if col_pol in row:
            row[col_pol] = 1
        one_hot_mapping[(ville, pol)] = row

    base_features = ["lag_1", "lag_2", "dayofweek", "month"]
    return lag_dict, one_hot_mapping, base_features


def multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date, nb_jours):
    """
    Réalise une prédiction multi-step sur 'nb_jours' jours.
    Pour chaque jour, les lags sont mis à jour en utilisant la prédiction précédente.
    """
    predictions = []
    current_date = start_date
    end_date = start_date + pd.Timedelta(days=nb_jours)

    while current_date < end_date:
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

        # Enregistrer les prédictions et mettre à jour les lags pour le prochain jour
        for i, key in enumerate(keys):
            ville, pol = key
            y_future = preds[i]
            predictions.append({
                "jour": current_date,
                "ville": ville,
                "Polluant": pol,
                "valeur_predite": y_future
            })
            lag_dict[key]["lag_2"] = lag_dict[key]["lag_1"]
            lag_dict[key]["lag_1"] = y_future

        current_date += datetime.timedelta(days=1)
    return predictions


def export_predictions(predictions, output_csv_path):
    """
    Exporte les prédictions dans un fichier CSV.
    """
    df_pred = pd.DataFrame(predictions)
    df_pred.sort_values(["ville", "Polluant", "jour"], inplace=True)
    df_pred.to_csv(output_csv_path, sep=";", index=False, encoding="utf-8")
    return df_pred


def main():
    # Configuration du logging
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

    # Chemins d'accès des fichiers
    input_csv = "/Users/akkouh/Desktop/scd3/all_years_cleaned_daily.csv"
    output_csv = "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities.csv"

    # 1. Charger et prétraiter les données
    logging.info("Chargement des données...")
    df = load_data(input_csv)
    df_preprocessed = preprocess_data(df.copy())
    logging.info("Prétraitement terminé, données prêtes pour l'entraînement.")

    # 2. Séparation des features et de la cible
    features = ["lag_1", "lag_2", "dayofweek", "month"]
    one_hot_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    features += one_hot_cols
    X = df_preprocessed[features]
    y = df_preprocessed["valeur_journaliere"]

    # 3. Entraîner le modèle RandomForest
    logging.info("Entraînement du modèle RandomForest...")
    model = train_random_forest(X, y, random_state=42)
    logging.info("Modèle entraîné sur toutes les villes, tous polluants, toutes dates.")

    # 4. Définir l'horizon de prédiction (365 jours)
    last_date = df_preprocessed["jour"].max()
    logging.info("Dernière date mesurée : %s", last_date)
    start_date = last_date + pd.Timedelta(days=1)
    nb_jours = 365

    # 5. Préparer les lags initiaux et le mapping one-hot à partir des données d'origine
    logging.info("Préparation des lags initiaux et du mapping one-hot...")
    df_orig = load_data(input_csv)
    model_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    lag_dict, one_hot_mapping, base_features = prepare_lag_and_onehot_mapping(df_orig, last_date, model_cols)

    # 6. Prédiction multi-step sur l'horizon défini
    logging.info("Début de la prédiction multi-step sur %d jours...", nb_jours)
    predictions = multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date,
                                        nb_jours)
    logging.info("Prédiction terminée.")

    # 7. Export des prédictions dans un fichier CSV
    df_pred = export_predictions(predictions, output_csv)
    logging.info("Fichier '%s' créé.", output_csv)
    logging.info("Aperçu des 20 premières lignes :\n%s", df_pred.head(20).to_string(index=False))


if __name__ == "__main__":
    main()