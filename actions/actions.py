from rasa_sdk import Action
from rasa_sdk.executor import CollectingDispatcher
import requests
import json

class ActionMistralResponse(Action):

    def name(self):
        return "action_mistral_response"

    def run(self, dispatcher, tracker, domain):
        question = tracker.latest_message.get("text")
        try:
            response = requests.post(
                "http://localhost:11434/api/chat",
                json={
                    "model": "mistral",
                    "messages": [
                        {"role": "user", "content": f"Réponds clairement à cette question : {question}"}
                    ],
                    "stream": True
                },
                stream=True
            )

            full_response = ""
            for line in response.iter_lines():
                if line:
                    try:
                        data = json.loads(line.decode("utf-8"))
                        content = data.get("message", {}).get("content", "")
                        full_response += content
                    except Exception as err:
                        print(f"[DEBUG] Ligne ignorée : {line} -- {err}")

            full_response = full_response.strip()

            ### Liste élargie des domaines acceptés :
            sujets_valides = [
                "pollution", "air", "qualité de l'air", "particules fines", "PM2.5", "NO2",
                "dioxyde d'azote", "environnement", "écologie", "gaz à effet de serre",
                "réchauffement climatique", "changement climatique", "biodiversité", "climat",
                "déforestation", "déchets", "énergies renouvelables", "CO2", "empreinte carbone",
                "polluants", "protection de la nature", "ozone", "durabilité", "crise climatique",
                "écosystèmes", "transport durable", "pluie acide", "recyclage", "sols", "océans",
                "développement durable", "gaz polluants", "économie verte", "transition énergétique"
            ]

            if any(mot.lower() in full_response.lower() for mot in sujets_valides):
                dispatcher.utter_message(text=full_response)
            else:
                dispatcher.utter_message(text="Je suis spécialisé dans les questions environnementales 🌱. Pose-moi une question sur la pollution, l’air, le climat ou la biodiversité !")

        except Exception as e:
            dispatcher.utter_message(text=f"Erreur avec le modèle Mistral : {e}")

        return []
