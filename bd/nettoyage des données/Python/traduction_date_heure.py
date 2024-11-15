import pandas as pd
from sqlalchemy import create_engine, text
from datetime import datetime
import logging

# Configuration des logs
logging.basicConfig(filename='update_logs.log', level=logging.INFO,
                    format='%(asctime)s - %(levelname)s - %(message)s')

# Connexion à la base de données avec SQLAlchemy
engine = create_engine('mysql+mysqlconnector://root:@localhost/pureoxy')

# Requête SQL pour récupérer toutes les latitudes, longitudes et la date/heure
query = "SELECT Latitude, Longitude, `Last.Updated` FROM pollution_villes"
df = pd.read_sql(query, engine)

# Processus pour mettre à jour la base de données avec DATETIME
total_rows = len(df)
logging.info(f"Début de la mise à jour des {total_rows} lignes en DATETIME.")

for index, row in df.iterrows():
    lat, lon = float(row['Latitude']), float(row['Longitude'])  # Conversion explicite en float
    last_updated = row['Last.Updated']  # Récupérer la date et l'heure d'origine

    # Vérification si la colonne `Last.Updated` n'est pas NULL
    if last_updated and last_updated != "NULL":
        try:
            # Conversion de la chaîne en objet datetime
            datetime_obj = datetime.strptime(last_updated, "%d-%m-%Y %H:%M:%S")

            # Afficher la progression pour chaque ligne
            print(f"Mise à jour {index+1}/{total_rows} : Latitude {lat}, Longitude {lon}, DateTime : {datetime_obj}")

            # Log de la progression
            logging.info(f"Ligne {index+1}/{total_rows} : DateTime généré {datetime_obj}")

            # Requête SQL pour mettre à jour le champ DATETIME
            update_query = text("""
            UPDATE pollution_villes
            SET LastUpdatedDateTime = :datetime_val
            WHERE Latitude = :lat AND Longitude = :lon
            """)

            # Connexion directe à la base de données via le moteur SQLAlchemy
            with engine.connect() as connection:
                try:
                    # Passage explicite des paramètres sous forme de dictionnaire
                    connection.execute(update_query, {
                        'datetime_val': datetime_obj,
                        'lat': lat,
                        'lon': lon
                    })
                    connection.connection.commit()
                    logging.info(f"Succès : Mise à jour effectuée pour Latitude {lat}, Longitude {lon}")
                except Exception as e:
                    logging.error(f"Erreur lors de la mise à jour pour Latitude {lat}, Longitude {lon} : {e}")
        except Exception as e:
            logging.error(f"Erreur de conversion de la date pour {lat}, {lon}: {e}")

print("Mise à jour des DATETIME terminée.")
logging.info("Mise à jour complète des DATETIME terminée.")
