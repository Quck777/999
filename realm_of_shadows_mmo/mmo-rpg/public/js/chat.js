/**
 * Chat Module - Manages chat channels, messages, and polling
 * ФИКС: Не вызывается в демо-режиме (App.init решает)
 */
const Chat = (() => {
    let currentChannel = 'global';
    let lastMessageId = 0;
    let pollInterval = null;
    let pollDelay = 3000;

    function init() {
        if (API.isFileProtocol()) return; // Demo mode handles chat itself

        // Channel switching
        document.querySelectorAll('.btn-chat-channel').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.btn-chat-channel').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentChannel = btn.dataset.channel;
                lastMessageId = 0;
                document.getElementById('chat-messages').innerHTML = '';
                loadMessages();
            });
        });

        // Send message form
        document.getElementById('chat-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (!message) return;

            try {
                await API.chat.send(currentChannel, message);
                input.value = '';
                loadMessages();
            } catch (err) {
                // Silent fail in chat — don't spam toasts
                console.warn('Chat send error:', err.message);
            }
        });
    }

    async function loadChat() {
        if (API.isFileProtocol()) return;
        await loadMessages();
        startPolling();
    }

    async function loadMessages() {
        try {
            const data = await API.chat.messages(currentChannel, 50, lastMessageId);
            const messages = data.data?.messages || [];

            if (messages.length > 0) {
                lastMessageId = Math.max(lastMessageId, ...messages.map(m => parseInt(m.id)));

                const container = document.getElementById('chat-messages');
                if (!container) return;
                const wasAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 50;

                messages.forEach(msg => {
                    container.appendChild(createMessageElement(msg));
                });

                if (wasAtBottom || container.children.length <= 50) {
                    container.scrollTop = container.scrollHeight;
                }

                while (container.children.length > 200) {
                    container.removeChild(container.firstChild);
                }
            }
        } catch (err) {
            // Silent fail for chat
            console.warn('Chat load error:', err.message);
        }
    }

    function createMessageElement(msg) {
        const div = document.createElement('div');
        div.className = `chat-msg chat-msg-${msg.channel || 'global'}`;

        const time = new Date(msg.created_at).toLocaleTimeString('ru-RU', {
            hour: '2-digit', minute: '2-digit'
        });

        const classColors = {
            warrior: '#e74c3c', mage: '#9b59b6', rogue: '#2ecc71',
            paladin: '#f39c12', archer: '#3498db',
        };

        const nameColor = classColors[msg.class] || '#c8a84e';

        div.innerHTML = `
            <span class="chat-time">[${time}]</span>
            <span class="chat-sender" style="color:${nameColor}">${msg.character_name || '???'}</span>
            <span class="chat-level">[${msg.level || 1}]</span>
            ${msg.guild_name ? `<span class="chat-guild">${msg.guild_name}</span>` : ''}
            <span class="chat-text">${msg.message}</span>
        `;

        return div;
    }

    function startPolling() {
        stopPolling();
        pollDelay = 3000;

        const poll = async () => {
            await loadMessages();
            pollInterval = setTimeout(poll, pollDelay);
            pollDelay = Math.min(pollDelay * 1.2, 15000);
        };

        pollInterval = setTimeout(poll, pollDelay);
    }

    function stopPolling() {
        if (pollInterval) {
            clearTimeout(pollInterval);
            pollInterval = null;
        }
    }

    return {
        init,
        loadChat,
        loadMessages,
        stopPolling,
    };
})();
