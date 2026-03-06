document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('#laca-bot-admin-widget #laca-bot-toggle-btn');
    const closeBtn = document.querySelector('#laca-bot-admin-widget #laca-bot-close-btn');
    const chatWindow = document.querySelector('#laca-bot-admin-widget #laca-bot-chat-window');
    const inputField = document.querySelector('#laca-bot-admin-widget #laca-bot-input');
    const sendBtn = document.querySelector('#laca-bot-admin-widget #laca-bot-send-btn');
    const messageList = document.querySelector('#laca-bot-admin-widget #laca-bot-message-list');
    const newChatBtn = document.querySelector('#laca-bot-admin-widget #laca-bot-new-chat-btn');
    
    if(!toggleBtn || !chatWindow) return;

    let sessionId = localStorage.getItem('laca_bot_admin_session');

    const getSessionId = () => {
        if (!sessionId) {
            sessionId = 'admin_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            localStorage.setItem('laca_bot_admin_session', sessionId);
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

    toggleBtn.addEventListener('click', () => chatWindow.style.display = 'flex');
    closeBtn.addEventListener('click', () => chatWindow.style.display = 'none');

    if (newChatBtn) {
        newChatBtn.addEventListener('click', () => {
            if (confirm('Bắt đầu cuộc trò chuyện mới? Lịch sử cũ vẫn được lưu trong 3 ngày.')) {
                localStorage.removeItem('laca_bot_admin_session');
                sessionId = null;
                messageList.innerHTML = `<div class="laca-bot-message bot"><div class="laca-bot-message-bubble">Chào Admin! Tôi đã sẵn sàng cho cuộc trò chuyện mới.</div></div>`;
                getSessionId();
            }
        });
    }

    const sendMessage = () => {
        const text = inputField.value.trim();
        if(!text) return;
        
        appendMessage('user', text);
        inputField.value = '';
        
        showTypingIndicator();
        
        const formData = new FormData();
        formData.append('action', 'laca_bot_admin_chat');
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

    sendBtn.addEventListener('click', sendMessage);
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
        const existingChips = document.querySelector('.laca-bot-chips-container');
        if (existingChips) existingChips.remove();

        const container = document.createElement('div');
        container.className = 'laca-bot-chips-container';
        chips.forEach(chip => {
            const btn = document.createElement('button');
            btn.className = 'laca-bot-chip';
            btn.innerText = chip;
            btn.onclick = () => {
                inputField.value = chip;
                sendMessage();
                container.remove();
            };
            container.appendChild(btn);
        });
        messageList.appendChild(container);
        messageList.scrollTop = messageList.scrollHeight;
    };

    const showTypingIndicator = () => {
        const msgDiv = document.createElement('div');
        msgDiv.className = `laca-bot-message bot typing`;
        msgDiv.id = 'laca-bot-admin-typing';
        msgDiv.innerHTML = `<div class="laca-bot-message-bubble"><div class="laca-bot-typing"><span></span><span></span><span></span></div></div>`;
        messageList.appendChild(msgDiv);
        messageList.scrollTop = messageList.scrollHeight;
    };

    const removeTypingIndicator = () => {
        const typingIndicator = document.getElementById('laca-bot-admin-typing');
        if(typingIndicator) typingIndicator.remove();
    };
});
