document.addEventListener('DOMContentLoaded', function() {
    const chatbotContainer = document.getElementById('chatbot-container');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotToggle = document.getElementById('chatbot-toggle');

    if (!chatbotToggle || !chatbotContainer || !chatbotMessages || !chatbotInput) {
        console.error('Erreur : Éléments du chatbot non trouvés dans le DOM');
        return;
    } else {
        console.log('Chatbot initialisé avec succès');
    }

    chatbotToggle.addEventListener('click', function() {
        if (chatbotContainer.style.display === 'block') {
            chatbotContainer.style.display = 'none';
            chatbotToggle.textContent = 'Chat';
        } else {
            chatbotContainer.style.display = 'block';
            chatbotToggle.textContent = 'Fermer';
        }
    });

    function addMessage(message, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
        messageDiv.textContent = message;
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

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

    chatbotInput.addEventListener('keypress', async function(e) {
        if (e.key === 'Enter' && chatbotInput.value.trim() !== '') {
            const userMessage = chatbotInput.value.trim();
            addMessage(userMessage, true);
            const botResponse = await getBotResponse(userMessage);
            setTimeout(() => addMessage(botResponse), 500);
            chatbotInput.value = '';
        }
    });

    addMessage("Salut ! Je suis le chatbot PureOxy. Pose-moi une question !");
});