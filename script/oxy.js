document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('chatbot-container');
    const content = document.getElementById('chatbot-content');
    const toggleText = document.getElementById('chatbot-toggle-text');
    const messages = document.getElementById('chatbot-messages');
    const input = document.getElementById('chatbot-input');
    const closeBtn = document.getElementById('chatbot-close');

    if (!container || !content || !toggleText || !messages || !input || !closeBtn) {
        console.error("Certains éléments du chatbot sont manquants dans le DOM.");
        return;
    }

    container.addEventListener('click', (e) => {
        if (!container.classList.contains('expanded')) {
            container.classList.add('expanded');
            e.stopPropagation();
        }
    });

    closeBtn.addEventListener('click', (e) => {
        container.classList.remove('expanded');
        e.stopPropagation();
    });

    content.addEventListener('click', (e) => e.stopPropagation());

    function addMessage(text, isUser = false) {
        const msg = document.createElement('div');
        msg.className = `message ${isUser ? 'user-message' : 'bot-message'}`;
        msg.textContent = text;
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
    }

    async function getBotResponse(message) {
        try {
            const response = await fetch('https://05e8-185-246-98-19.ngrok-free.app/webhooks/rest/webhook', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sender: "user", message })
            });

            const data = await response.json();
            return data[0]?.text || "Désolé, je n’ai pas compris.";
        } catch (err) {
            console.error("Erreur avec la requête Rasa :", err);
            return "Une erreur s’est produite. Réessaie plus tard.";
        }
    }

    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && input.value.trim()) {
            const userMsg = input.value.trim();
            input.value = '';
            addMessage(userMsg, true);

            getBotResponse(userMsg).then(botMsg => {
                setTimeout(() => addMessage(botMsg), 500);
            });
        }
    });

    addMessage("Salut ! Je suis le chatbot PureOxy. Pose-moi une question !");
});
