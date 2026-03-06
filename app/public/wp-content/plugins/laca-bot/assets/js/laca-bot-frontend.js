document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('laca-bot-toggle-btn');
    const closeBtn = document.getElementById('laca-bot-close-btn');
    const chatWindow = document.getElementById('laca-bot-chat-window');
    const inputField = document.getElementById('laca-bot-input');
    const sendBtn = document.getElementById('laca-bot-send-btn');
    const messageList = document.getElementById('laca-bot-message-list');
    
    if(!toggleBtn || !chatWindow) return;

    let sessionId = sessionStorage.getItem('laca_bot_user_session');

    const getSessionId = () => {
        if (!sessionId) {
            sessionId = 'user_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            sessionStorage.setItem('laca_bot_user_session', sessionId);
        }
        return sessionId;
    };

    const loadHistory = () => {
        const formData = new FormData();
        formData.append('action', 'laca_bot_load_history');
        formData.append('nonce', lacaBotObj.nonce);
        formData.append('session_id', getSessionId());

        fetch(lacaBotObj.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                // Clear default greeting if history exists
                messageList.innerHTML = '';
                data.data.forEach(msg => {
                    appendMessage(msg.role === 'assistant' ? 'bot' : 'user', msg.content, false);
                });
            }
        });
    };

    // Load history when initialized
    loadHistory();

    // Toggle chat window
    toggleBtn.addEventListener('click', () => chatWindow.style.display = 'flex');
    closeBtn.addEventListener('click', () => chatWindow.style.display = 'none');

    // Send message logic
    const sendMessage = (customText = null, displayLabel = null) => {
        const text = customText !== null ? customText : inputField.value.trim();
        if(!text) return;
        
        appendMessage('user', displayLabel || text);
        inputField.value = '';
        showTypingIndicator();
        
        const formData = new FormData();
        formData.append('action', 'laca_bot_frontend_chat');
        formData.append('nonce', lacaBotObj.nonce);
        formData.append('message', text);
        formData.append('session_id', getSessionId());

        fetch(lacaBotObj.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            removeTypingIndicator();
            if(data.success) {
                appendMessage('bot', data.data.reply);
            } else {
                appendMessage('bot', 'Lỗi: ' + (data.data || 'Không thể kết nối API.'));
            }
        })
        .catch(err => {
            removeTypingIndicator();
            appendMessage('bot', 'Lỗi mạng, vui lòng thử lại.');
        });
    };

    sendBtn.addEventListener('click', () => sendMessage());
    inputField.addEventListener('keypress', (e) => {
        if(e.key === 'Enter') sendMessage();
    });

    const appendMessage = (sender, text, scroll = true) => {
        removeTypingIndicator();
        const msgDiv = document.createElement('div');
        msgDiv.className = `laca-bot-message ${sender}`;
        
        let contentHtml = text;
        if (typeof marked !== 'undefined') {
            contentHtml = marked.parse(text);
        } else {
            contentHtml = text.replace(/(?:\r\n|\r|\n)/g, '<br>');
        }

        msgDiv.innerHTML = `<div class="laca-bot-message-bubble">${contentHtml}</div>`;
        messageList.appendChild(msgDiv);
        if (scroll) {
            setTimeout(() => {
                messageList.scrollTop = messageList.scrollHeight;
            }, 100);
        }
    };

    const renderChips = (chips) => {
        const container = document.createElement('div');
        container.className = 'laca-bot-chips-container';
        chips.forEach(chip => {
            // Support both old string format and new object format {label, prompt}
            const label = typeof chip === 'string' ? chip : chip.label;
            const prompt = typeof chip === 'string' ? chip : (chip.prompt || chip.label);
            
            const btn = document.createElement('button');
            btn.className = 'laca-bot-chip';
            btn.innerText = label;
            btn.onclick = () => {
                sendMessage(prompt, label);
                container.remove();
            };
            container.appendChild(btn);
        });
        messageList.appendChild(container);
        messageList.scrollTop = messageList.scrollHeight;
    };

    // Initial Frontend Chips
    setTimeout(() => {
        if (lacaBotObj.quick_chips && lacaBotObj.quick_chips.length > 0) {
            renderChips(lacaBotObj.quick_chips);
        }
    }, 1500);

    const showTypingIndicator = () => {
        const msgDiv = document.createElement('div');
        msgDiv.className = `laca-bot-message bot typing`;
        msgDiv.id = 'laca-bot-typing';
        msgDiv.innerHTML = `<div class="laca-bot-message-bubble"><div class="laca-bot-typing"><span></span><span></span><span></span></div></div>`;
        messageList.appendChild(msgDiv);
        messageList.scrollTop = messageList.scrollHeight;
    };

    const removeTypingIndicator = () => {
        const typingIndicator = document.getElementById('laca-bot-typing');
        if(typingIndicator) typingIndicator.remove();
    };
});
