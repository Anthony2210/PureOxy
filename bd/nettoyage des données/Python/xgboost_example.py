import pandas as pd
import numpy as np
import datetime
import logging
from xgboost import XGBRegressor

# ------------------------------------------------------------------
# Fonction load_data
# ------------------------------------------------------------------
def load_data(csv_path):
    """
    Charge le fichier CSV en utilisant sep=";" et encoding="utf-8", et convertit la colonne 'jour' en datetime.
    """
    # Lecture du fichier CSV avec le séparateur ';' et encodage UTF-8
    df = pd.read_csv(csv_path, sep=";", encoding="utf-8")
    # Conversion de la colonne 'jour' en type datetime pour faciliter les opérations temporelles
    df["jour"] = pd.to_datetime(df["jour"])
    return df


# ------------------------------------------------------------------
# Fonction preprocess_data_xgb
# ------------------------------------------------------------------
def preprocess_data_xgb(df):
    """
    Trie les données par 'ville', 'Polluant' et 'jour', crée les lags (lag_1 et lag_2), ajoute
    les variables calendaires (dayofweek, month, dayofyear) et réalise le one-hot encoding sur 'ville' et 'Polluant'.
    """
    # Tri du DataFrame par ville, polluant et date pour assurer un ordre chronologique par groupe
    df.sort_values(["ville", "Polluant", "jour"], inplace=True)

    # Création des lags pour la variable 'valeur_journaliere' pour chaque groupe (ville, Polluant)
    df["lag_1"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(1)  # Décalage d'une journée
    df["lag_2"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(2)  # Décalage de deux jours

    # Extraction des variables calendaires à partir de la date
    df["dayofweek"] = df["jour"].dt.dayofweek  # Jour de la semaine : Lundi = 0, Dimanche = 6
    df["month"] = df["jour"].dt.month          # Mois de l'année : 1 à 12
    df["dayofyear"] = df["jour"].dt.dayofyear    # Jour de l'année : 1 à 365 (ou 366)

    # Réalisation du one-hot encoding sur 'ville' et 'Polluant' pour transformer ces variables en indicatrices
    # Le paramètre drop_first=True permet d'éviter la redondance (dummy variable trap)
    df = pd.get_dummies(df, columns=["ville", "Polluant"], drop_first=True)

    # Suppression des lignes contenant des NaN dues aux valeurs manquantes introduites par le décalage (lags)
    df.dropna(inplace=True)
    # Réinitialisation de l'index après suppression des lignes
    df.reset_index(drop=True, inplace=True)

    return df


# ------------------------------------------------------------------
# Fonction train_xgb
# ------------------------------------------------------------------
def train_xgb(X, y, random_state=42):
    """
    Entraîne un modèle XGBRegressor avec n_estimators=100, random_state et n_jobs=-1.
    """
    # Instanciation du modèle XGBRegressor avec 100 arbres, état aléatoire fixé et utilisation de tous les cœurs
    model = XGBRegressor(n_estimators=100, random_state=random_state, n_jobs=-1)
    # Entraînement du modèle sur les features X et la cible y
    model.fit(X, y)
    return model


# ------------------------------------------------------------------
# Fonction get_last_lags
# ------------------------------------------------------------------
def get_last_lags(sub_df, last_date):
    """
    Pour un sous-dataframe correspondant à une ville et un polluant,
    récupère la valeur du dernier jour (lag_1) et celle du jour précédent (lag_2).
    """
    # Créer une copie pour ne pas altérer les données d'origine
    sub_df = sub_df.copy()
    # S'assurer que la colonne 'jour' est bien en format datetime
    sub_df["jour"] = pd.to_datetime(sub_df["jour"])
    # Tri du sous-DataFrame par date
    sub_df.sort_values("jour", inplace=True)

    # Récupération de la ligne correspondant à la dernière date
    row_last = sub_df[sub_df["jour"] == last_date]
    # Si exactement une ligne est trouvée, récupérer la valeur journalière ; sinon, retourner NaN
    val_last = row_last.iloc[0]["valeur_journaliere"] if len(row_last) == 1 else np.nan

    # Récupération de la dernière valeur avant la dernière date pour obtenir le lag_2
    sub_before = sub_df[sub_df["jour"] < last_date]
    val_before = sub_before.iloc[-1]["valeur_journaliere"] if len(sub_before) > 0 else np.nan

    return val_last, val_before


# ------------------------------------------------------------------
# Fonction prepare_lag_and_onehot_mapping
# ------------------------------------------------------------------
def prepare_lag_and_onehot_mapping(original_df, last_date, model_cols):
    """
    Pour chaque combinaison (ville, Polluant) issue des données originales,
    récupère les derniers lags et prépare un mapping one-hot basé sur les colonnes utilisées
    lors de l'entraînement.
    """
    # Extraction des villes et polluants uniques (en excluant les valeurs manquantes)
    villes = original_df["ville"].dropna().unique()
    polluants = original_df["Polluant"].dropna().unique()

    lag_dict = {}
    # Boucle sur chaque combinaison ville et polluant
    for ville in villes:
        for pol in polluants:
            # Sélectionner les données correspondant à la combinaison actuelle
            subset = original_df[(original_df["ville"] == ville) & (original_df["Polluant"] == pol)]
            if subset.empty:
                continue  # Passer si aucune donnée n'est disponible
            # Récupérer les dernières valeurs pour lag_1 et lag_2
            val_last, val_before = get_last_lags(subset, last_date)
            lag_dict[(ville, pol)] = {"lag_1": val_last, "lag_2": val_before}

    # Préparer le mapping one-hot basé sur les colonnes générées par get_dummies
    one_hot_mapping = {}
    for (ville, pol) in lag_dict.keys():
        # Initialiser toutes les colonnes one-hot à 0
        row = {col: 0 for col in model_cols}
        # Construction du nom de colonne pour la ville en remplaçant espaces et tirets par des underscores
        col_ville = "ville_" + ville.replace(" ", "_").replace("-", "_")
        # Construction du nom de colonne pour le polluant
        col_pol = "Polluant_" + pol
        # Activer la colonne correspondante si elle existe
        if col_ville in row:
            row[col_ville] = 1
        if col_pol in row:
            row[col_pol] = 1
        one_hot_mapping[(ville, pol)] = row

    # Définir les features de base qui ne proviennent pas du one-hot encoding
    base_features = ["lag_1", "lag_2", "dayofweek", "month", "dayofyear"]
    return lag_dict, one_hot_mapping, base_features


# ------------------------------------------------------------------
# Fonction multi_step_prediction
# ------------------------------------------------------------------
def multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date, nb_jours):
    """
    Réalise une prédiction multi-step sur 'nb_jours' jours.
    Pour chaque jour, les lags sont mis à jour avec la prédiction précédente.
    """
    predictions = []  # Liste pour stocker les résultats de prédiction
    current_date = start_date
    # Calcul de la date de fin des prédictions
    end_date = start_date + pd.Timedelta(days=nb_jours)

    # Boucle sur chaque jour de l'horizon de prédiction
    while current_date < end_date:
        rows = []
        # Récupération de toutes les combinaisons (ville, Polluant) présentes dans lag_dict
        keys = list(lag_dict.keys())
        for key in keys:
            ville, pol = key
            lags = lag_dict[key]
            # Calcul du jour de l'année pour la date courante
            day_of_year = current_date.timetuple().tm_yday

            # Construction du dictionnaire de features pour la prédiction du jour courant
            row = {
                "lag_1": lags["lag_1"],
                "lag_2": lags["lag_2"],
                "dayofweek": current_date.weekday(),
                "month": current_date.month,
                "dayofyear": day_of_year
            }
            # Ajout du mapping one-hot pour la combinaison (ville, Polluant)
            row.update(one_hot_mapping[key])
            rows.append(row)
        # Conversion de la liste de dictionnaires en DataFrame
        df_future = pd.DataFrame(rows)

        # Vérification que toutes les colonnes nécessaires sont présentes
        full_features = base_features + model_cols
        for col in full_features:
            if col not in df_future.columns:
                df_future[col] = 0
        # Réorganisation des colonnes dans l'ordre requis par le modèle
        df_future = df_future[full_features]

        # Prédiction en batch pour le jour courant
        preds = model.predict(df_future)

        # Mise à jour des lags et enregistrement des prédictions pour chaque (ville, Polluant)
        for i, key in enumerate(keys):
            ville, pol = key
            y_future = preds[i]
            predictions.append({
                "jour": current_date,
                "ville": ville,
                "Polluant": pol,
                "valeur_predite": y_future
            })
            # Mise à jour des lags pour la prochaine itération :
            # Le lag actuel devient le lag précédent et la nouvelle prédiction devient le nouveau lag_1
            lag_dict[key]["lag_2"] = lag_dict[key]["lag_1"]
            lag_dict[key]["lag_1"] = y_future

        # Passage au jour suivant
        current_date += datetime.timedelta(days=1)
    return predictions


# ------------------------------------------------------------------
# Fonction export_predictions
# ------------------------------------------------------------------
def export_predictions(predictions, output_csv_path):
    """
    Exporte les prédictions dans un fichier CSV avec sep=";", encoding="utf-8" et index=False.
    """
    # Conversion de la liste de prédictions en DataFrame
    df_pred = pd.DataFrame(predictions)
    # Tri du DataFrame par ville, polluant et date pour une meilleure lisibilité
    df_pred.sort_values(["ville", "Polluant", "jour"], inplace=True)
    # Exportation du DataFrame en fichier CSV avec le séparateur ';'
    df_pred.to_csv(output_csv_path, sep=";", index=False, encoding="utf-8")
    return df_pred


# ------------------------------------------------------------------
# Fonction main
# ------------------------------------------------------------------
def main():
    # Configuration du logging pour suivre l'exécution du script
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

    # Définition des chemins d'accès pour les fichiers d'entrée et de sortie
    input_csv = "/Users/akkouh/Desktop/scd3/all_years_cleaned_daily2.csv"
    output_csv = "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_xgboost.csv"

    # 1. Chargement et prétraitement des données pour XGBoost
    logging.info("Chargement et prétraitement des données pour XGBoost...")
    df = load_data(input_csv)  # Chargement du CSV
    df_preprocessed = preprocess_data_xgb(df.copy())  # Prétraitement (tri, création de lags, extraction de variables calendaires, one-hot encoding)
    logging.info("Prétraitement terminé.")

    # 2. Séparation des features et de la cible
    features = ["lag_1", "lag_2", "dayofweek", "month", "dayofyear"]
    # Sélection des colonnes one-hot générées
    one_hot_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    features += one_hot_cols
    X = df_preprocessed[features]  # Features d'entrée
    y = df_preprocessed["valeur_journaliere"]  # Cible

    # 3. Entraînement du modèle XGBRegressor
    logging.info("Entraînement du modèle XGBRegressor...")
    model = train_xgb(X, y, random_state=42)  # Entraînement du modèle sur l'ensemble des données
    logging.info("Modèle XGBRegressor entraîné sur toutes les villes, tous polluants, toutes dates.")

    # 4. Définir l'horizon de prédiction (365 jours)
    last_date = df_preprocessed["jour"].max()  # Récupération de la dernière date mesurée
    logging.info("Dernière date mesurée : %s", last_date)
    start_date = last_date + pd.Timedelta(days=1)  # La prédiction commence le jour suivant la dernière date
    nb_jours = 365  # Nombre de jours à prédire

    # 5. Préparation des lags initiaux et du mapping one-hot à partir des données d'origine
    logging.info("Préparation des lags initiaux et du mapping one-hot...")
    df_orig = load_data(input_csv)  # Rechargement des données d'origine
    model_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    lag_dict, one_hot_mapping, base_features = prepare_lag_and_onehot_mapping(df_orig, last_date, model_cols)

    # 6. Réalisation de la prédiction multi-step sur l'horizon défini
    logging.info("Début de la prédiction multi-step sur %d jours...", nb_jours)
    predictions = multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date, nb_jours)
    logging.info("Prédiction terminée.")

    # 7. Exportation des prédictions dans le fichier final
    df_pred = export_predictions(predictions, output_csv)
    logging.info("Fichier '%s' créé.", output_csv)
    logging.info("Aperçu des 20 premières lignes :\n%s", df_pred.head(20).to_string(index=False))


# ------------------------------------------------------------------
# Point d'entrée du script
# ------------------------------------------------------------------
if __name__ == "__main__":
    main()