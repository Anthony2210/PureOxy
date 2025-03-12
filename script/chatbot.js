document.addEventListener('DOMContentLoaded', function() {
    const chatbotContainer = document.getElementById('chatbot-container');
    const chatbotContent = document.getElementById('chatbot-content');
    const chatbotToggleText = document.getElementById('chatbot-toggle-text');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotClose = document.getElementById('chatbot-close');

    if (!chatbotContainer || !chatbotContent || !chatbotToggleText || !chatbotMessages || !chatbotInput || !chatbotClose) {
        console.error('Erreur : Éléments du chatbot non trouvés dans le DOM');
        return;
    }

    // Lorsque le container est cliqué en mode minimisé, il s'agrandit
    chatbotContainer.addEventListener('click', function(e) {
        if (!chatbotContainer.classList.contains('expanded')) {
            chatbotContainer.classList.add('expanded');
            // On empêche la propagation pour éviter d'éventuels conflits
            e.stopPropagation();
        }
    });

    // Bouton de fermeture (la croix) dans le header
    chatbotClose.addEventListener('click', function(e) {
        chatbotContainer.classList.remove('expanded');
        // On empêche la propagation pour éviter de réouvrir immédiatement
        e.stopPropagation();
    });

    // Empêcher la propagation des clics à l'intérieur du contenu pour ne pas fermer la chatbox accidentellement
    chatbotContent.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Fonction pour ajouter un message dans le chat
    function addMessage(message, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
        messageDiv.textContent = message;
        chatbotMessages.appendChild(messageDiv);
        // Faire défiler vers le bas pour afficher le nouveau message
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    // Fonction asynchrone pour récupérer la réponse du bot
    async function getBotResponse(userMessage) {
        try {
            const response = await fetch('http://localhost:5005/webhooks/rest/webhook', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sender: "user", message: userMessage })
            });
            const data = await response.json();
            return data[0]?.text || "Désolé, je n’ai pas compris.";
        } catch (error) {
            console.error('Erreur lors de la requête à Rasa :', error);
            return "Désolé, une erreur s’est produite. Réessaie plus tard !";
        }
    }

    // Envoi du message lorsque l'utilisateur appuie sur la touche "Enter"
    chatbotInput.addEventListener('keypress', async function(e) {
        if (e.key === 'Enter' && chatbotInput.value.trim() !== '') {
            const userMessage = chatbotInput.value.trim();
            addMessage(userMessage, true);
            const botResponse = await getBotResponse(userMessage);
            setTimeout(() => addMessage(botResponse), 500);
            chatbotInput.value = '';
        }
    });

    // Message de bienvenue initial
    addMessage("Salut ! Je suis le chatbot PureOxy. Pose-moi une question !");
});
