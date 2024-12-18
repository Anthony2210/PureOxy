import pandas as pd
from sqlalchemy import create_engine, text
import requests
from time import sleep

engine = create_engine('mysql+mysqlconnector://root:@localhost/pureoxy')

query = "SELECT Latitude, Longitude FROM pollution_villes"
df = pd.read_sql(query, engine)

cache = {}

error_log = "error_log.csv"

# Fonction pour obtenir les détails de localisation via l'API Nominatim
def get_city_location(lat, lon):
    lat = round(float(lat), 6)
    lon = round(float(lon), 6)

    # Vérification dans le cache
    if (lat, lon) in cache:
        return cache[(lat, lon)]

    url = f"https://nominatim.openstreetmap.org/reverse?format=json&lat={lat}&lon={lon}&zoom=12&addressdetails=1"

    for attempt in range(5):  # Tentative jusqu'à 5 fois
        response = requests.get(url)

        if response.status_code == 200:
            data = response.json()
            address = data.get('address', {})

            # Debug : afficher la réponse complète de l'API
            print(f"Réponse API pour {lat}, {lon}: {address}")

            # Récupérer la ville (si plusieurs, on garde la première, sinon ville la plus proche)
            city = address.get('city') or address.get('town') or address.get('village') or address.get('hamlet') or "Inconnu"
            postal_code = address.get('postcode', "Inconnu")
            location = address.get('suburb') or address.get('neighbourhood') or address.get('building', "Inconnu")
            department = address.get('county', "Inconnu")
            region = address.get('state', "Inconnu")

            # Stocker dans le cache
            cache[(lat, lon)] = (city, location, department, region, postal_code)
            return city, location, department, region, postal_code

        elif response.status_code == 403:
            print(f"Erreur 403 pour {lat}, {lon}. Attente de 20 secondes avant réessai...")
            sleep(20)
        else:
            print(f"Erreur API pour {lat}, {lon}: {response.status_code}")
            return "Inconnu", "Inconnu", "Inconnu", "Inconnu", "Inconnu"

    # Si après 5 tentatives l'API ne répond toujours pas, on enregistre l'erreur
    with open(error_log, 'a') as file:
        file.write(f"{lat},{lon}\n")
    print(f"Échec après 5 tentatives pour {lat}, {lon}, enregistré dans {error_log}")
    return "Inconnu", "Inconnu", "Inconnu", "Inconnu", "Inconnu"

# Processus pour mettre à jour la base de données
for index, row in df.iterrows():
    lat, lon = float(row['Latitude']), float(row['Longitude'])  # Conversion explicite en float
    city, location, department, region, postal_code = get_city_location(lat, lon)

    print(f"Mise à jour de {lat}, {lon} avec la ville: {city}, lieu de mesure: {location}, département: {department}, région: {region}, code postal: {postal_code}")

    # Requête SQL avec les nouvelles colonnes
    update_query = text("""
    UPDATE pollution_villes
    SET City = :city, Location = :location, Department = :department, Region = :region, Postal_Code = :postal_code
    WHERE Latitude = :lat AND Longitude = :lon
    """)

    # Connexion directe à la base de données via le moteur SQLAlchemy
    with engine.connect() as connection:
        try:
            # Passage explicite des paramètres sous forme de dictionnaire
            connection.execute(update_query, {
                'city': city,
                'location': location,
                'department': department,
                'region': region,
                'postal_code': postal_code,
                'lat': lat,
                'lon': lon
            })
            connection.connection.commit()
        except Exception as e:
            print(f"Erreur lors de la mise à jour pour {lat}, {lon} : {e}")

    # Délai de 20 secondes pour éviter de surcharger l'API
    sleep(20)

print("Mise à jour des villes et localisations terminée.")
