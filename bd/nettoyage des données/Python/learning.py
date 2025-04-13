import pandas as pd
import numpy as np
import datetime
import logging
from sklearn.ensemble import RandomForestRegressor


# --------------------------------------------------------
# Fonction de chargement des données
# --------------------------------------------------------
def load_data(csv_path):
    """
    Charge le fichier CSV et convertit la colonne 'jour' en datetime.
    - csv_path : chemin d'accès vers le fichier CSV.
    """
    # Lecture du fichier CSV avec le séparateur point-virgule et encodage UTF-8.
    df = pd.read_csv(csv_path, sep=";", encoding="utf-8")
    # Conversion de la colonne 'jour' en objet datetime pour faciliter les manipulations temporelles.
    df["jour"] = pd.to_datetime(df["jour"])
    return df


# --------------------------------------------------------
# Fonction de prétraitement des données
# --------------------------------------------------------
def preprocess_data(df):
    """
    Trie les données par ville, polluant et date, crée les variables de retard (lags),
    ajoute les variables calendaires et effectue le one-hot encoding sur 'ville' et 'Polluant'.

    Le prétraitement comprend :
    - Le tri des lignes pour garantir l'ordre chronologique par ville et polluant.
    - La création de deux colonnes de retard (lag_1 et lag_2) pour prévoir à partir des valeurs précédentes.
    - L'extraction d'informations calendaires (jour de la semaine et mois).
    - La conversion des colonnes catégorielles 'ville' et 'Polluant' en variables numériques via one-hot encoding.
    - La suppression des lignes contenant des valeurs NaN résultant de la création des lags.
    """
    # Tri des données par 'ville', 'Polluant' et 'jour'
    df.sort_values(["ville", "Polluant", "jour"], inplace=True)

    # Création de la variable de retard (lag_1) : valeur de la journée précédente
    df["lag_1"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(1)
    # Création de la variable de retard (lag_2) : valeur de deux jours avant
    df["lag_2"] = df.groupby(["ville", "Polluant"])["valeur_journaliere"].shift(2)

    # Extraction du jour de la semaine et du mois depuis la date
    df["dayofweek"] = df["jour"].dt.dayofweek
    df["month"] = df["jour"].dt.month

    # Application du one-hot encoding pour les colonnes 'ville' et 'Polluant'
    # L'argument drop_first permet d'éviter la multicolinéarité en supprimant la première catégorie.
    df = pd.get_dummies(df, columns=["ville", "Polluant"], drop_first=True)

    # Suppression des lignes avec des valeurs manquantes générées par les lags
    df.dropna(inplace=True)
    # Réinitialisation de l'index du DataFrame pour une numérotation continue
    df.reset_index(drop=True, inplace=True)
    return df


# --------------------------------------------------------
# Fonction d'entraînement du modèle RandomForest
# --------------------------------------------------------
def train_random_forest(X, y, random_state=42):
    """
    Entraîne un modèle RandomForestRegressor sur les features X et la cible y.

    Paramètres:
    - X : Variables indépendantes (features)
    - y : Variable dépendante (valeur journalière)
    - random_state : pour assurer la reproductibilité de l'entraînement
    """
    # Création du modèle RandomForest avec 100 estimateurs (arbres)
    model = RandomForestRegressor(n_estimators=100, random_state=random_state)
    # Entraînement du modèle sur les données
    model.fit(X, y)
    return model


# --------------------------------------------------------
# Fonction pour récupérer les derniers lags pour un sous-ensemble
# --------------------------------------------------------
def get_last_lags(sub_df, last_date):
    """
    Pour un sous-dataframe correspondant à une combinaison (ville, Polluant),
    récupère la 'valeur_journaliere' du dernier jour (lag_1) et celle du jour précédent (lag_2).

    Paramètres:
    - sub_df : DataFrame filtré pour une ville et un polluant spécifique.
    - last_date : La dernière date pour laquelle on souhaite obtenir les valeurs.

    Retourne:
    - val_last : La valeur de 'valeur_journaliere' correspondant au dernier jour.
    - val_before : La valeur de 'valeur_journaliere' immédiatement avant last_date.
    """
    sub_df = sub_df.copy()
    # Conversion de la colonne 'jour' en datetime (sécurité au cas où)
    sub_df["jour"] = pd.to_datetime(sub_df["jour"])
    # Tri par ordre chronologique
    sub_df.sort_values("jour", inplace=True)

    # Sélection de la ligne correspondant exactement à la dernière date
    row_last = sub_df[sub_df["jour"] == last_date]
    # Extraction de la valeur si une ligne existe, sinon NaN
    val_last = row_last.iloc[0]["valeur_journaliere"] if len(row_last) == 1 else np.nan

    # Sélection de toutes les lignes antérieures à last_date
    sub_before = sub_df[sub_df["jour"] < last_date]
    # Extraction de la dernière valeur antérieure à last_date, sinon NaN
    val_before = sub_before.iloc[-1]["valeur_journaliere"] if len(sub_before) > 0 else np.nan

    return val_last, val_before


# --------------------------------------------------------
# Fonction pour préparer le mapping des lags et le one-hot encoding
# --------------------------------------------------------
def prepare_lag_and_onehot_mapping(original_df, last_date, model_cols):
    """
    Pour chaque combinaison (ville, Polluant) issue des données originales,
    récupère les derniers lags et prépare un mapping one-hot des variables.

    Paramètres:
    - original_df : DataFrame original contenant les données brutes.
    - last_date : La dernière date des données d'entraînement utilisée pour récupérer les lags.
    - model_cols : Liste des colonnes utilisées dans le modèle (issus du one-hot encoding).

    Retourne:
    - lag_dict : Dictionnaire contenant les lags (lag_1 et lag_2) pour chaque combinaison (ville, Polluant).
    - one_hot_mapping : Dictionnaire avec le mapping des variables one-hot pour chaque combinaison.
    - base_features : Liste des features de base utilisées (lags et variables calendaires).
    """
    # Récupération des villes et polluants uniques
    villes = original_df["ville"].dropna().unique()
    polluants = original_df["Polluant"].dropna().unique()

    lag_dict = {}
    # Pour chaque combinaison de ville et polluant, récupérer les valeurs des lags
    for ville in villes:
        for pol in polluants:
            subset = original_df[(original_df["ville"] == ville) & (original_df["Polluant"] == pol)]
            if subset.empty:
                continue
            val_last, val_before = get_last_lags(subset, last_date)
            lag_dict[(ville, pol)] = {"lag_1": val_last, "lag_2": val_before}

    # Préparer le mapping one-hot en s'appuyant sur les colonnes utilisées lors de l'entraînement
    one_hot_mapping = {}
    for (ville, pol) in lag_dict.keys():
        # Initialiser toutes les colonnes one-hot à 0 pour cette combinaison
        row = {col: 0 for col in model_cols}
        # Construire le nom de la colonne pour la ville en tenant compte du format généré par pd.get_dummies
        col_ville = "ville_" + ville.replace(" ", "_").replace("-", "_")
        # Construire le nom de la colonne pour le polluant
        col_pol = "Polluant_" + pol
        # Si les colonnes existent dans le mapping, mettre la valeur correspondante à 1
        if col_ville in row:
            row[col_ville] = 1
        if col_pol in row:
            row[col_pol] = 1
        one_hot_mapping[(ville, pol)] = row

    # Les features de base utilisées dans le modèle
    base_features = ["lag_1", "lag_2", "dayofweek", "month"]
    return lag_dict, one_hot_mapping, base_features


# --------------------------------------------------------
# Fonction pour réaliser une prédiction multi-step
# --------------------------------------------------------
def multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date, nb_jours):
    """
    Réalise une prédiction multi-step sur 'nb_jours' jours.
    Pour chaque jour, les lags sont mis à jour en utilisant la prédiction précédente.

    Paramètres:
    - model : Modèle entraîné (RandomForestRegressor).
    - lag_dict : Dictionnaire contenant les lags initiaux par (ville, Polluant).
    - one_hot_mapping : Dictionnaire de mapping one-hot pour chaque (ville, Polluant).
    - base_features : Liste des features de base (lags et informations calendaires).
    - model_cols : Liste des colonnes one-hot utilisées dans le modèle.
    - start_date : Date de départ pour la prédiction.
    - nb_jours : Nombre de jours à prédire.

    Retourne:
    - predictions : Liste de dictionnaires, où chaque dictionnaire contient la date,
      la ville, le polluant et la valeur prédite pour ce jour.
    """
    predictions = []
    # Date de début pour la prédiction
    current_date = start_date
    # Date finale de la prédiction = start_date + nb_jours
    end_date = start_date + pd.Timedelta(days=nb_jours)

    # Boucle pour chaque jour à prédire
    while current_date < end_date:
        rows = []
        # Récupération des clés (combinaisons ville, polluant) du dictionnaire des lags
        keys = list(lag_dict.keys())
        for key in keys:
            ville, pol = key
            lags = lag_dict[key]
            # Constitution d'une ligne de features pour la prédiction à la date courante
            row = {
                "lag_1": lags["lag_1"],
                "lag_2": lags["lag_2"],
                "dayofweek": current_date.weekday(),
                "month": current_date.month
            }
            # Ajout du mapping one-hot pour cette combinaison
            row.update(one_hot_mapping[key])
            rows.append(row)
        # Conversion de la liste de dictionnaires en DataFrame
        df_future = pd.DataFrame(rows)

        # Vérification que toutes les colonnes attendues sont présentes dans le DataFrame
        full_features = base_features + model_cols
        for col in full_features:
            if col not in df_future.columns:
                df_future[col] = 0
        # Réorganisation des colonnes pour correspondre à l'ordre du modèle
        df_future = df_future[full_features]

        # Prédiction en batch pour toutes les combinaisons (ville, Polluant) pour la journée courante
        preds = model.predict(df_future)

        # Pour chaque prédiction, enregistrer la valeur prédite et mettre à jour les lags pour le jour suivant
        for i, key in enumerate(keys):
            ville, pol = key
            y_future = preds[i]
            predictions.append({
                "jour": current_date,
                "ville": ville,
                "Polluant": pol,
                "valeur_predite": y_future
            })
            # Mise à jour des lags : le précédent lag_1 devient lag_2 et le nouveau devient lag_1
            lag_dict[key]["lag_2"] = lag_dict[key]["lag_1"]
            lag_dict[key]["lag_1"] = y_future

        # Passer au jour suivant
        current_date += datetime.timedelta(days=1)
    return predictions


# --------------------------------------------------------
# Fonction d'export des prédictions vers un fichier CSV
# --------------------------------------------------------
def export_predictions(predictions, output_csv_path):
    """
    Exporte les prédictions dans un fichier CSV.

    Paramètres:
    - predictions : Liste de dictionnaires contenant les prédictions.
    - output_csv_path : Chemin d'accès où le fichier CSV sera sauvegardé.
    """
    # Conversion de la liste de prédictions en DataFrame
    df_pred = pd.DataFrame(predictions)
    # Tri des prédictions par ville, polluant et date pour l'organisation
    df_pred.sort_values(["ville", "Polluant", "jour"], inplace=True)
    # Sauvegarde du DataFrame en CSV avec le séparateur point-virgule et encodage UTF-8
    df_pred.to_csv(output_csv_path, sep=";", index=False, encoding="utf-8")
    return df_pred


# --------------------------------------------------------
# Fonction principale orchestrant le flux de travail
# --------------------------------------------------------
def main():
    # Configuration du logging pour afficher les informations d'exécution
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

    # Définition des chemins d'accès aux fichiers d'entrée et de sortie
    input_csv = "/Users/akkouh/Desktop/scd3/all_years_cleaned_daily2.csv"
    output_csv = "/Users/akkouh/Desktop/scd3/prediction_1year_all_cities.csv"

    # 1. Chargement et prétraitement des données
    logging.info("Chargement des données...")
    df = load_data(input_csv)
    # On travaille sur une copie des données pour le prétraitement
    df_preprocessed = preprocess_data(df.copy())
    logging.info("Prétraitement terminé, données prêtes pour l'entraînement.")

    # 2. Préparation des features et de la cible
    # Sélection des features de base
    features = ["lag_1", "lag_2", "dayofweek", "month"]
    # Récupération des colonnes générées par le one-hot encoding pour 'ville' et 'Polluant'
    one_hot_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    features += one_hot_cols
    # Construction de X (features) et de y (cible)
    X = df_preprocessed[features]
    y = df_preprocessed["valeur_journaliere"]

    # 3. Entraînement du modèle RandomForest
    logging.info("Entraînement du modèle RandomForest...")
    model = train_random_forest(X, y, random_state=42)
    logging.info("Modèle entraîné sur toutes les villes, tous polluants, toutes dates.")

    # 4. Définir l'horizon de prédiction (365 jours)
    last_date = df_preprocessed["jour"].max()
    logging.info("Dernière date mesurée : %s", last_date)
    # La prédiction commence le lendemain de la dernière date mesurée
    start_date = last_date + pd.Timedelta(days=1)
    nb_jours = 365  # Nombre de jours à prédire

    # 5. Préparation des lags initiaux et du mapping one-hot à partir des données originales
    logging.info("Préparation des lags initiaux et du mapping one-hot...")
    # Rechargement des données d'origine pour préparer les lags (sans modification des lags liés au prétraitement)
    df_orig = load_data(input_csv)
    # Récupération des colonnes one-hot générées lors du prétraitement
    model_cols = [c for c in df_preprocessed.columns if c.startswith("ville_") or c.startswith("Polluant_")]
    # Création des dictionnaires pour les lags et le mapping one-hot
    lag_dict, one_hot_mapping, base_features = prepare_lag_and_onehot_mapping(df_orig, last_date, model_cols)

    # 6. Réalisation de la prédiction multi-step sur l'horizon défini
    logging.info("Début de la prédiction multi-step sur %d jours...", nb_jours)
    predictions = multi_step_prediction(model, lag_dict, one_hot_mapping, base_features, model_cols, start_date,
                                        nb_jours)
    logging.info("Prédiction terminée.")

    # 7. Export des prédictions dans un fichier CSV
    df_pred = export_predictions(predictions, output_csv)
    logging.info("Fichier '%s' créé.", output_csv)
    # Affichage d'un aperçu des 20 premières lignes du fichier généré
    logging.info("Aperçu des 20 premières lignes :\n%s", df_pred.head(20).to_string(index=False))


# Point d'entrée du script
if __name__ == "__main__":
    main()
