from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

responses = {
    "salut": "Salut ! Bienvenue sur PureOxy. Comment puis-je t’aider ?",
    "c’est quoi pm2.5": "PM2.5, ce sont des particules fines de moins de 2,5 micromètres dans l’air. Elles peuvent affecter tes poumons.",
    "qualité": "Quelle ville veux-tu vérifier ? Dis-moi 'Qualité [ville]' (ex. 'Qualité Paris') !"
}

@app.route('/chat', methods=['POST'])
def chat():
    user_message = request.json.get('message', '').lower().strip()
    if not user_message:
        return jsonify({"response": "Désolé, je n’ai pas compris."})
    if "qualité" in user_message:
        ville = user_message.split('qualité')[-1].strip()
        return jsonify({"response": f"Je vérifie la qualité de l’air à {ville}... (données bientôt disponibles !)"})
    for key, value in responses.items():
        if key in user_message:
            return jsonify({"response": value})
    return jsonify({"response": "Hmm, je ne comprends pas. Essaie 'Salut' ou 'C’est quoi PM2.5 ?' !"})

if __name__ == '__main__':
    app.run(debug=True, port=5000)