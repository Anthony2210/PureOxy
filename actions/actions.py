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
            # On envoie la question à Ollama
            response = requests.post(
                "http://localhost:11434/api/chat",
                json={
                    "model": "mistral",
                    "messages": [
                        {"role": "user", "content": question}
                    ],
                    "stream": True
                },
                stream=True
            )

            # On lit les réponses ligne par ligne
            full_response = ""
            for line in response.iter_lines():
                if line:
                    data = json.loads(line.decode("utf-8"))
                    content = data.get("message", {}).get("content", "")
                    full_response += content

            if full_response:
                dispatcher.utter_message(text=full_response)
            else:
                dispatcher.utter_message(text="Désolé, je n'ai pas eu de réponse.")

        except Exception as e:
            dispatcher.utter_message(text=f"Erreur lors de l'appel à Mistral : {e}")

        return []
