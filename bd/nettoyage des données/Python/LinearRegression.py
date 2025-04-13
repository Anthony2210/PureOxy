import pandas as pd
import numpy as np
import datetime
import logging
from sklearn.linear_model import LinearRegression
# aide chatgpt pour l'initialisation des étapes

# --------------------------------------------------------
# Fonction de chargement des données
# --------------------------------------------------------
def load_data(csv_path):
    """
    Charge le fichier CSV avec sep=";" et encoding="utf-8" et convertit la colonne 'jour' en datetime.

    Paramètres :
      - csv_path : chemin vers le fichier CSV.

    Retourne :
      - DataFrame contenant les données du CSV avec la colonne 'jour' convertie.
    """
    # Lecture du fichier CSV en spécifiant le séparateur et l'encodage.
    df = pd.read_csv(csv_path, sep=";", encoding="utf-8")
    # Conversion de la colonne 'jour' en type datetime pour faciliter les opérations temporelles.
    df["jour"] = pd.to_datetime(df["jour"])
    return df


# --------------------------------------------------------
# Fonction de prétraitement des données
# --------------------------------------------------------
def preprocess_data(df):
    """
    Trie les données par 'ville', 'Polluant' et 'jour', crée les lags (lag_1 et lag_2),
    ajoute les variables calendaires (dayofweek et month) et effectue le one-hot encoding sur 'ville' et 'Polluant'.

    Le prétraitement comprend :
      - Un tri des données par ville, polluant et date.
      - La création de colonnes de retard (lag_1 et lag_2) sur 'valeur_journaliere'.
      - L'extraction du jour de la semaine et du mois.
      - La transformation des colonnes catégorielles 'ville' et 'Polluant' en variables numériques via one-hot encoding.
      - La suppression des lignes contenant des NaN générés par le calcul des lags.
    """
    # Tri des données pour garantir l'ordre chronologique par ville, polluant et date
    df.sort_values(["ville", "Polluant", "jour"], inplace=True)

    # Création de la colonne lag_1 : valeur de la journée précédente pour chaque (ville, Polluant)
    df["lag_1"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(1)
    # Création de la colonne lag_2 : valeur de deux jours avant pour chaque (ville, Polluant)
    df["lag_2"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(2)

    # Extraction des informations calendaires : jour de la semaine et mois
    df["dayofweek"] = df["jour"].dt.dayofweek
    df["month"] = df["jour"].dt.month

    # Application du one-hot encoding pour les colonnes 'ville' et 'Polluant'
    # L'option drop_first permet d'éviter la redondance (dummy variable trap)
    df = pd.get_dummies(df, columns=["ville", "Polluant"], drop_first=True)

    # Suppression des lignes contenant des valeurs manquantes dues aux lags
    df.dropna(inplace=True)
    # Réinitialisation de l'index du DataFrame pour conserver un index séquentiel
    df.reset_index(drop=True, inplace=True)

    return df


# --------------------------------------------------------
# Fonction d'entraînement du modèle LinearRegression
# --------------------------------------------------------
def train_linear_regression(X, y):
    """
    Entraîne un modèle LinearRegression sur les données fournies.

    Paramètres :
      - X : DataFrame des features (variables indépendantes)
      - y : Série des valeurs cibles (valeur_journaliere)

    Retourne :
      - Le modèle entraîné.
    """
    # Initialisation du modèle LinearRegression
    model = LinearRegression()
    # Entraînement sur les données d'entrée
    model.fit(X, y)
    return model


# --------------------------------------------------------
# Fonction pour récupérer les derniers lags pour une combinaison (ville, Polluant)
# --------------------------------------------------------
def get_last_lags(sub_df, last_date):
    """
    Récupère la dernière mesure disponible pour une ville et un polluant,
    ainsi que la mesure précédente.

    Si une mesure pour last_date existe, on l'utilise et on cherche la mesure juste antérieure.
    Sinon, on prend les deux dernières mesures disponibles.

    Paramètres :
      - sub_df : DataFrame contenant les données pour une ville et un polluant donnés.
      - last_date : Date de référence pour récupérer le dernier point de mesure.

    Retourne :
      - val_last : dernière valeur de 'valeur_journaliere'.
      - val_before : valeur juste avant la dernière, ou la même valeur si une seule mesure existe.
    """
    # Copie du sous-DataFrame pour éviter de modifier l'original
    sub_df = sub_df.copy()
    # S'assurer que la colonne 'jour' est au format datetime
    sub_df["jour"] = pd.to_datetime(sub_df["jour"])
    # Tri par date pour obtenir l'ordre chronologique
    sub_df.sort_values("jour", inplace=True)

    # Recherche de la ligne correspondant exactement à last_date
    row_last = sub_df[sub_df["jour"] == last_date]
    if len(row_last) == 1:
        # Si une mesure existe pour last_date, on la récupère
        val_last = row_last.iloc[0]["valeur_journaliere"]
        # On récupère la dernière mesure antérieure à last_date
        sub_before = sub_df[sub_df["jour"] < last_date]
        val_before = sub_before.iloc[-1]["valeur_journaliere"] if len(sub_before) > 0 else val_last
    else:
        # Si aucune mesure exacte pour last_date, ou plusieurs mesures, on prend les deux dernières valeurs disponibles
        if len(sub_df) >= 2:
            val_last = sub_df.iloc[-1]["valeur_journaliere"]
            val_before = sub_df.iloc[-2]["valeur_journaliere"]
        elif len(sub_df) == 1:
            # Si seule une mesure est disponible, on la répète
            val_last = sub_df.iloc[0]["valeur_journaliere"]
            val_before = sub_df.iloc[0]["valeur_journaliere"]
        else:
            # En absence de données, retourner NaN pour les deux lags
            val_last, val_before = np.nan, np.nan
    return val_last, val_before


# --------------------------------------------------------
# Fonction pour préparer les lags initiaux et le mapping one-hot
# --------------------------------------------------------
def prepare_lag_and_onehot_mapping(original_df, last_date, model_cols):
    """
    Pour chaque combinaison (ville, Polluant) issue des données originales,
    récupère les derniers lags et prépare un mapping one-hot basé sur les colonnes
    utilisées lors de l'entraînement.

    Paramètres :
      - original_df : DataFrame des données brutes.
      - last_date : Date de référence pour extraire les derniers lags.
      - model_cols : Liste des colonnes issues du one-hot encoding (utilisées dans le modèle).

    Retourne :
      - lag_dict : Dictionnaire contenant les lags (lag_1 et lag_2) pour chaque (ville, Polluant).
      - one_hot_mapping : Dictionnaire associant à chaque (ville, Polluant) un mapping one-hot.
      - base_features : Liste des features de base (lags, dayofweek, month).
    """
    # Obtention des villes et polluants uniques présents dans les données
    villes = original_df["ville"].dropna().unique()
    polluants = original_df["Polluant"].dropna().unique()

    lag_dict = {}
    # Pour chaque combinaison de ville et polluant, récupérer les deux dernières mesures
    for ville in villes:
        for pol in polluants:
            subset = original_df[(original_df["ville"] == ville) & (original_df["Polluant"] == pol)]
            if subset.empty:
                continue
            val_last, val_before = get_last_lags(subset, last_date)
            lag_dict[(ville, pol)] = {"lag_1": val_last, "lag_2": val_before}

    one_hot_mapping = {}
    # Préparation du mapping one-hot pour chaque combinaison
    for (ville, pol) in lag_dict.keys():
        # Initialiser le mapping avec 0 pour chaque colonne du one-hot encoding
        row = {col: 0 for col in model_cols}
        # Formation du nom de la colonne pour 'ville'
        col_ville = "ville_" + ville.replace(" ", "_").replace("-", "_")
        # Formation du nom de la colonne pour 'Polluant'
        col_pol = "Polluant_" + pol
        # Mise à 1 de la colonne correspondante si elle existe
        if col_ville in row:
            row[col_ville] = 1
        if col_pol in row:
            row[col_pol] = 1
        one_hot_mapping[(ville, pol)] = row

    # Liste des features de base utilisées pour la prédiction
    base_features = ["lag_1", "lag_2", "dayofweek", "month"]
    return lag_dict, one_hot_mapping, base_features


# --------------------------------------------------------
# Fonction de prédiction multi-step
# --------------------------------------------------------
def multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date, nb_jours):
    """
    Réalise une prédiction multi-step sur 'nb_jours' jours.

    Pour chaque jour :
      - Le modèle prédit la valeur pour chaque couple (ville, Polluant)
      - Les lags sont mis à jour avec la valeur prédite pour préparer le jour suivant

    Paramètres :
      - model : Le modèle LinearRegression entraîné.
      - lag_dict : Dictionnaire contenant les valeurs initiales des lags pour chaque (ville, Polluant).
      - one_hot_mapping : Mapping one-hot correspondant à chaque combinaison (ville, Polluant).
      - base_features : Liste des features de base (lags, dayofweek, month).
      - model_cols : Liste des colonnes one-hot attendues par le modèle.
      - start_date : Date de début de la prédiction.
      - nb_jours : Nombre de jours à prédire (horizon de prédiction).

    Retourne :
      - predictions : Liste de dictionnaires contenant les prédictions journalières par (ville, Polluant).
    """
    predictions = []
    current_date = start_date
    # Calcul de la date de fin de prédiction
    end_date = start_date + pd.Timedelta(days=nb_jours)

    # Boucle sur chaque jour à prédire jusqu'à la date de fin
    while current_date < end_date:
        rows = []
        # Récupérer toutes les combinaisons (ville, Polluant) pour lesquelles on a des lags
        keys = list(lag_dict.keys())
        for key in keys:
            ville, pol = key
            lags = lag_dict[key]
            # Construction des features pour le jour courant
            row = {
                "lag_1": lags["lag_1"],
                "lag_2": lags["lag_2"],
                "dayofweek": current_date.weekday(),
                "month": current_date.month
            }
            # Ajout du mapping one-hot pour la combinaison courante
            row.update(one_hot_mapping[key])
            rows.append(row)
        # Conversion de la liste des dictionnaires en DataFrame
        df_future = pd.DataFrame(rows)

        # S'assurer que toutes les colonnes nécessaires sont présentes et remplir d'éventuels NaN par 0
        full_features = base_features + model_cols
        for col in full_features:
            if col not in df_future.columns:
                df_future[col] = 0
        df_future = df_future[full_features].fillna(0)

        # Prédiction en batch pour le jour courant pour toutes les combinaisons
        preds = model.predict(df_future)

        # Enregistrer la prédiction pour chaque (ville, Polluant) et mettre à jour les lags
        for i, key in enumerate(keys):
            ville, pol = key
            y_future = preds[i]
            predictions.append({
                "jour": current_date,
                "ville": ville,
                "Polluant": pol,
                "valeur_predite": y_future
            })
            # Mise à jour des lags pour le jour suivant : lag_1 devient la prédiction, ancien lag_1 devient lag_2
            lag_dict[key]["lag_2"] = lag_dict[key]["lag_1"]
            lag_dict[key]["lag_1"] = y_future

        # Passage au jour suivant
        current_date += datetime.timedelta(days=1)
    return predictions


# --------------------------------------------------------
# Fonction d'export des prédictions dans un fichier CSV
# --------------------------------------------------------
def export_predictions(predictions, output_csv_path):
    """
    Exporte les prédictions dans un fichier CSV en utilisant sep=";", encoding="utf-8" et index=False.

    Paramètres :
      - predictions : Liste de dictionnaires contenant les prédictions.
      - output_csv_path : Chemin vers le fichier CSV de sortie.

    Retourne :
      - DataFrame des prédictions exportées.
    """
    # Création d'un DataFrame à partir des prédictions
    df_pred = pd.DataFrame(predictions)
    # Tri des prédictions pour une organisation cohérente par ville, polluant et date
    df_pred.sort_values(["ville", "Polluant", "jour"], inplace=True)
    # Export vers CSV avec le séparateur spécifié et sans index
    df_pred.to_csv(output_csv_path, sep=";", index=False, encoding="utf-8")
    return df_pred


# --------------------------------------------------------
# Fonction principale orchestrant le flux complet
# --------------------------------------------------------
def main():
    # Configuration du logging pour afficher des messages d'information
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

    # Définition des chemins d'accès aux fichiers d'entrée et de sortie
    input_csv = "/Users/akkouh/Desktop/scd3/all_years_cleaned_daily2.csv"
    output_csv = "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities_linear_regression.csv"

    # 1. Chargement et prétraitement des données
    logging.info("Chargement des données...")
    df = load_data(input_csv)
    # Prétraitement des données (création des lags, variables calendaires, one-hot encoding, etc.)
    df_preprocessed = preprocess_data(df.copy())
    logging.info("Prétraitement terminé.")

    # 2. Séparation des features et de la cible pour l'entraînement
    # Définition des features de base
    features = ["lag_1", "lag_2", "dayofweek", "month"]
    # Sélection des colonnes issues du one-hot encoding (commençant par 'ville_' ou 'Polluant_')
    one_hot_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    features += one_hot_cols
    # Construction des features (X) et de la cible (y)
    X = df_preprocessed[features]
    y = df_preprocessed["valeur_journaliere"]

    # 3. Entraînement du modèle LinearRegression
    logging.info("Entraînement du modèle LinearRegression...")
    model = train_linear_regression(X, y)
    logging.info("Modèle LinearRegression entraîné sur toutes les villes, tous polluants, toutes dates.")

    # 4. Définir l'horizon de prédiction (365 jours)
    last_date = df_preprocessed["jour"].max()
    logging.info("Dernière date mesurée : %s", last_date)
    # La prédiction commence le lendemain de la dernière date disponible
    start_date = last_date + pd.Timedelta(days=1)
    nb_jours = 365

    # 5. Préparation des lags initiaux et du mapping one-hot à partir des données d'origine
    logging.info("Préparation des lags initiaux et du mapping one-hot...")
    # Rechargement des données d'origine pour préparer correctement les lags
    df_orig = load_data(input_csv)
    # Récupération des colonnes one-hot générées lors du prétraitement
    model_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    # Création du dictionnaire des lags et du mapping one-hot
    lag_dict, one_hot_mapping, base_features = prepare_lag_and_onehot_mapping(df_orig, last_date, model_cols)

    # 6. Réalisation de la prédiction multi-step sur l'horizon défini
    logging.info("Début de la prédiction multi-step sur %d jours...", nb_jours)
    predictions = multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date,
                                        nb_jours)
    logging.info("Prédiction terminée.")

    # 7. Export des prédictions dans le fichier final CSV
    df_pred = export_predictions(predictions, output_csv)
    logging.info("Fichier '%s' créé.", output_csv)
    # Affichage en log d'un aperçu des 20 premières lignes des prédictions
    logging.info("Aperçu des 20 premières lignes :\n%s", df_pred.head(20).to_string(index=False))


# Point d'entrée du script
if __name__ == "__main__":
    main()
