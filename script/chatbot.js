/**
 * chatbot.js
 *
 * Ce script gère l'interaction avec le chatbot PureOxy.
 * Il contrôle l'ouverture/fermeture du chatbot, l'envoi des messages utilisateur,
 * et l'affichage des réponses du bot.
 *
 * Références :
 * - ChatGPT pour la structuration du code et les commentaires.
 *
 * Utilisation :
 * - Ce fichier est inclus dans toutes les pages appelant le chatbot (via le header) pour initialiser le chatbot.
 *
 * Fichier placé dans le dossier script.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Récupération des éléments du DOM nécessaires au fonctionnement du chatbot
    const chatbotContainer = document.getElementById('chatbot-container');
    const chatbotContent = document.getElementById('chatbot-content');
    const chatbotToggleText = document.getElementById('chatbot-toggle-text');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotClose = document.getElementById('chatbot-close');

    // Vérification que tous les éléments requis sont présents dans le DOM
    if (!chatbotContainer || !chatbotContent || !chatbotToggleText || !chatbotMessages || !chatbotInput || !chatbotClose) {
        console.error('Erreur : Éléments du chatbot non trouvés dans le DOM');
        return;
    }

    // Ouverture du chatbot en mode étendu lorsqu'on clique sur le container en mode minimisé
    chatbotContainer.addEventListener('click', function(e) {
        if (!chatbotContainer.classList.contains('expanded')) {
            chatbotContainer.classList.add('expanded');
            e.stopPropagation(); // Empêche la propagation de l'événement
        }
    });

    // Fermeture du chatbot via le bouton de fermeture dans le header
    chatbotClose.addEventListener('click', function(e) {
        chatbotContainer.classList.remove('expanded');
        e.stopPropagation();
    });

    // Empêche la fermeture accidentelle lorsque l'on clique à l'intérieur du contenu du chatbot
    chatbotContent.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    /**
     * Ajoute un message à la fenêtre de discussion du chatbot.
     *
     * @param {string} message - Le texte du message à afficher.
     * @param {boolean} [isUser=false] - Indique si le message provient de l'utilisateur.
     */
    function addMessage(message, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
        messageDiv.textContent = message;
        chatbotMessages.appendChild(messageDiv);
        // Défilement automatique vers le dernier message
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    /**
     * Envoie le message de l'utilisateur à l'API Rasa et récupère la réponse du bot.
     *
     * @param {string} userMessage - Le message saisi par l'utilisateur.
     * @returns {Promise<string>} - La réponse textuelle du chatbot.
     */
    async function getBotResponse(userMessage) {
        try {
            const response = await fetch('http://localhost:5005/webhooks/rest/webhook', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sender: "user", message: userMessage })
            });
            const data = await response.json();
            // Retourne la réponse du bot ou un message par défaut
            return data[0]?.text || "Désolé, je n’ai pas compris.";
        } catch (error) {
            console.error('Erreur lors de la requête à Rasa :', error);
            return "Désolé, une erreur s’est produite. Réessaie plus tard !";
        }
    }

    // Envoie le message utilisateur lorsque la touche "Enter" est pressée
    chatbotInput.addEventListener('keypress', async function(e) {
        if (e.key === 'Enter' && chatbotInput.value.trim() !== '') {
            const userMessage = chatbotInput.value.trim();
            addMessage(userMessage, true); // Affiche le message de l'utilisateur
            const botResponse = await getBotResponse(userMessage); // Récupère la réponse du bot
            setTimeout(() => addMessage(botResponse), 500); // Ajoute la réponse avec un délai
            chatbotInput.value = ''; // Réinitialise le champ de saisie
        }
    });

    // Message de bienvenue initial dans le chatbot
    addMessage("Salut ! Je suis le chatbot PureOxy. Pose-moi une question !");
});
