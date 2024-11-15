import pandas as pd
from sqlalchemy import create_engine, text
from geopy.geocoders import Nominatim
import time

# Connexion à la base de données avec SQLAlchemy
engine = create_engine('mysql+mysqlconnector://root:@localhost/pureoxy')

# Charger le fichier des départements et régions
departments_file = 'C:/Users/antoc/PycharmProjects/pythonProject/departements-region.csv'
df_departments = pd.read_csv(departments_file)

# Initialiser le géocodeur OpenStreetMap (Nominatim)
geolocator = Nominatim(user_agent="pureoxy_app")

# Fonction pour ajuster la précision des coordonnées et les utiliser dans une requête
def adjust_coordinates_for_query(lat, lon):
    lat = float(lat)
    lon = float(lon)
    lat_min = lat - 0.00001
    lat_max = lat + 0.00001
    lon_min = lon - 0.00001
    lon_max = lon + 0.00001
    return lat_min, lat_max, lon_min, lon_max

# Fonction pour obtenir le département et la région à partir du code postal
def get_department_and_region_from_postal_code(postal_code):
    if len(postal_code) >= 2:
        if postal_code.startswith('97'):  # DOM-TOM
            dep_code = postal_code[:3]
        elif postal_code.startswith('20'):  # Corse
            third_digit = postal_code[2]
            if third_digit in ['0', '1']:
                dep_code = '2A'
            elif third_digit == '2':
                dep_code = '2B'
            else:
                dep_code = 'Inconnu'
        else:
            dep_code = postal_code[:2]  # Rest of France

        department_info = df_departments[df_departments['num_dep'] == dep_code]
        if not department_info.empty:
            department = department_info['dep_name'].values[0]
            region = department_info['region_name'].values[0]
            return department, region
    return 'Inconnu', 'Inconnu'

# Fonction pour obtenir le code postal via OpenStreetMap
def get_postal_code_from_coordinates(lat, lon):
    try:
        location = geolocator.reverse((lat, lon), exactly_one=True, language='fr')
        if location and 'postcode' in location.raw['address']:
            return location.raw['address']['postcode']
    except Exception as e:
        print(f"Erreur lors de la géocodification avec OSM : {e}")
    return 'Inconnu'

# Fonction pour générer un code postal par défaut en fonction du département
def generate_postal_code_from_department(department_db):
    department_info = df_departments[df_departments['dep_name'] == department_db]
    if not department_info.empty:
        dep_code = department_info['num_dep'].values[0]
        return dep_code + "000"
    return 'Inconnu'

# Processus pour compléter les informations manquantes
select_errors_query = text("""
    SELECT Latitude, Longitude, City, Location, Department, Region, Postal_Code 
    FROM pollution_villes
    WHERE Department = 'Inconnu' OR Region = 'Inconnu' OR Postal_Code = 'Inconnu'
""")

with engine.connect() as connection:
    results = connection.execute(select_errors_query).fetchall()

total_rows = len(results)
print(f"Nombre total de localisations à compléter : {total_rows}")

# Parcourir les résultats et compléter les informations
for index, result in enumerate(results):
    lat, lon, city_db, location_db, department_db, region_db, postal_code_db = result

    print(f"\n--- Traitement de la localisation {index + 1}/{total_rows} (coordonnées : {lat}, {lon}) ---")

    # Si le code postal est manquant ou "Inconnu", utiliser OpenStreetMap pour le trouver
    if postal_code_db == 'Inconnu':
        postal_code_db = get_postal_code_from_coordinates(lat, lon)
        if postal_code_db == 'Inconnu' and department_db != 'Inconnu':
            postal_code_db = generate_postal_code_from_department(department_db)
            print(f"Code postal généré à partir du département : {postal_code_db}")

    # Utiliser le code postal pour compléter les informations manquantes
    if department_db == 'Inconnu' or region_db == 'Inconnu':
        department, region = get_department_and_region_from_postal_code(postal_code_db)
        if department == 'Inconnu':
            print(f"Impossible de trouver le département et la région pour le code postal {postal_code_db}.")
    else:
        department, region = department_db, region_db

    print(f"DEBUG - Valeurs à mettre à jour : Department={department}, Region={region}, Postal_Code={postal_code_db}")

    # Requête SQL pour mettre à jour les informations
    update_query = text("""
        UPDATE pollution_villes
        SET Department = :department, Region = :region, Postal_Code = :postal_code
        WHERE Latitude = :lat AND Longitude = :lon
    """)

    with engine.connect() as connection:
        try:
            connection.execute(update_query, {
                'department': department,
                'region': region,
                'postal_code': postal_code_db,
                'lat': lat,
                'lon': lon
            })
            connection.connection.commit()
            print(f"Mise à jour réussie pour {lat}, {lon}.")
        except Exception as e:
            print(f"Erreur lors de la mise à jour pour {lat}, {lon} : {e}")

    # Log de progression
    remaining_rows = total_rows - (index + 1)
    print(f"Lignes restantes à mettre à jour : {remaining_rows}")

    # Pause pour éviter de surcharger le service OpenStreetMap
    time.sleep(1)  # Attente de 1 seconde entre chaque requête

print("Toutes les entrées manquantes ont été complétées et mises à jour dans la base de données.")
