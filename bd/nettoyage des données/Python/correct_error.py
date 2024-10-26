import pandas as pd
from sqlalchemy import create_engine, text

# Connexion à la base de données avec SQLAlchemy
engine = create_engine('mysql+mysqlconnector://root:@localhost/pureoxy')

# Charger le fichier des départements et régions
departments_file = 'C:/Users/antoc/PycharmProjects/pythonProject/departements-region.csv'
df_departments = pd.read_csv(departments_file)

# Fonction pour ajuster la précision des coordonnées et les utiliser dans une requête
def adjust_coordinates_for_query(lat, lon):
    lat = float(lat)  # Conversion en float
    lon = float(lon)  # Conversion en float
    lat_min = lat - 0.00001
    lat_max = lat + 0.00001
    lon_min = lon - 0.00001
    lon_max = lon + 0.00001
    return lat_min, lat_max, lon_min, lon_max

# Fonction pour obtenir le département et la région à partir du code postal
def get_department_and_region_from_postal_code(postal_code):
    if len(postal_code) >= 2:  # Vérification que le code postal a au moins 2 chiffres
        if postal_code.startswith('97'):  # Si le code postal commence par 97 (DOM-TOM)
            dep_code = postal_code[:3]  # Utiliser les trois premiers chiffres pour DOM-TOM
        elif postal_code.startswith('20'):  # Gestion des codes postaux corses
            third_digit = postal_code[2]  # Récupérer le troisième chiffre
            if third_digit in ['0', '1']:  # Corse-du-Sud (2A)
                dep_code = '2A'
            elif third_digit == '2':  # Haute-Corse (2B)
                dep_code = '2B'
            else:
                dep_code = 'Inconnu'
        else:
            dep_code = postal_code[:2]  # Utiliser les deux premiers chiffres pour le reste de la France

        department_info = df_departments[df_departments['num_dep'] == dep_code]
        if not department_info.empty:
            department = department_info['dep_name'].values[0]
            region = department_info['region_name'].values[0]
            return department, region
    return 'Inconnu', 'Inconnu'

# Processus pour compléter les informations manquantes directement depuis la base de données
select_errors_query = text("""
    SELECT Latitude, Longitude, City, Location, Department, Region, Postal_Code 
    FROM pollution_villes
    WHERE Department = 'Inconnu' OR Region = 'Inconnu' OR Postal_Code = 'Inconnu'
""")

with engine.connect() as connection:
    results = connection.execute(select_errors_query).fetchall()

# Parcourir les résultats et compléter les informations
for result in results:
    lat, lon, city_db, location_db, department_db, region_db, postal_code_db = result

    print(f"\n--- Complétion des informations pour la localisation avec coordonnées {lat}, {lon} ---")

    # Si le code postal est manquant ou "Inconnu", utiliser la localisation pour en déduire
    if postal_code_db == 'Inconnu':
        # Récupérer automatiquement les informations basées sur les coordonnées ou via un autre service
        # Ici, nous supposons que le code postal est déjà fourni, sinon un service API externe serait requis.
        postal_code_db = input(f"Entrez le code postal pour {lat}, {lon} (actuel: {postal_code_db}): ")

    # Utiliser le code postal pour compléter les informations manquantes
    if department_db == 'Inconnu' or region_db == 'Inconnu':
        department, region = get_department_and_region_from_postal_code(postal_code_db)
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
            connection.connection.commit()  # Valider les changements (commit)
            print(f"Mise à jour réussie pour {lat}, {lon}.")
        except Exception as e:
            print(f"Erreur lors de la mise à jour pour {lat}, {lon} : {e}")

print("Toutes les entrées manquantes ont été complétées et mises à jour dans la base de données.")
