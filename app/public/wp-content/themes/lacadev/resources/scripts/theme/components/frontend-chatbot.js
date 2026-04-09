/**
 * LacaDev Frontend Chatbot
 * RAG-lite: chỉ trả lời dựa trên nội dung website.
 * Vanilla JS — không phụ thuộc thư viện nào.
 */
(function () {
	'use strict';

	if (typeof lacaChatbot === 'undefined') return;

	const { endpoint, nonce, name, greeting, color, placeholder } = lacaChatbot;

	// ── CSS ─────────────────────────────────────────────────────────────────

	const css = `
	:root {
		--cbot-color: ${color};
		--cbot-radius: 16px;
	}
	#laca-cbot-btn {
		position: fixed;
		bottom: 24px;
		right: 24px;
		z-index: 99990;
		width: 52px;
		height: 52px;
		border-radius: 50%;
		background: var(--cbot-color);
		border: none;
		cursor: pointer;
		box-shadow: 0 4px 16px rgba(0,0,0,.25);
		display: flex;
		align-items: center;
		justify-content: center;
		transition: transform .2s, box-shadow .2s;
		color: #fff;
	}
	#laca-cbot-btn:hover {
		transform: scale(1.08);
		box-shadow: 0 6px 20px rgba(0,0,0,.3);
	}
	#laca-cbot-btn svg { width: 24px; height: 24px; }
	#laca-cbot-btn .cbot-close { display: none; }
	#laca-cbot-btn.open .cbot-open  { display: none; }
	#laca-cbot-btn.open .cbot-close { display: block; }

	/* Unread badge */
	#laca-cbot-badge {
		position: absolute;
		top: -2px; right: -2px;
		width: 12px; height: 12px;
		background: #ef4444;
		border-radius: 50%;
		border: 2px solid #fff;
		animation: cbot-pulse 2s infinite;
	}
	@keyframes cbot-pulse {
		0%,100% { transform: scale(1); opacity:1 }
		50%      { transform: scale(1.3); opacity:.8 }
	}

	#laca-cbot-win {
		position: fixed;
		bottom: 88px;
		right: 24px;
		z-index: 99989;
		width: 360px;
		max-width: calc(100vw - 32px);
		height: 520px;
		max-height: calc(100vh - 120px);
		background: #fff;
		border-radius: var(--cbot-radius);
		box-shadow: 0 12px 48px rgba(0,0,0,.18);
		display: flex;
		flex-direction: column;
		overflow: hidden;
		transform: translateY(12px) scale(.96);
		opacity: 0;
		pointer-events: none;
		transition: transform .25s ease, opacity .25s ease;
	}
	#laca-cbot-win.visible {
		transform: translateY(0) scale(1);
		opacity: 1;
		pointer-events: all;
	}

	/* Header */
	.cbot-header {
		background: var(--cbot-color);
		color: #fff;
		padding: 14px 16px;
		display: flex;
		align-items: center;
		gap: 10px;
		flex-shrink: 0;
	}
	.cbot-avatar {
		width: 32px; height: 32px;
		background: rgba(255,255,255,.2);
		border-radius: 50%;
		display: flex; align-items: center; justify-content: center;
		font-size: 16px;
		flex-shrink: 0;
	}
	.cbot-header-info { flex: 1; min-width: 0; }
	.cbot-title { font-weight: 600; font-size: 14px; }
	.cbot-subtitle {
		font-size: 11px;
		opacity: .8;
		display: flex;
		align-items: center;
		gap: 4px;
		margin-top: 1px;
	}
	.cbot-dot {
		width: 7px; height: 7px;
		background: #4ade80;
		border-radius: 50%;
		display: inline-block;
	}

	/* Messages */
	.cbot-messages {
		flex: 1;
		overflow-y: auto;
		padding: 16px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		scroll-behavior: smooth;
	}
	.cbot-messages::-webkit-scrollbar { width: 4px; }
	.cbot-messages::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

	/* Message bubbles */
	.cbot-msg {
		max-width: 85%;
		font-size: 13.5px;
		line-height: 1.6;
		animation: cbot-fadein .2s ease;
	}
	@keyframes cbot-fadein {
		from { opacity:0; transform: translateY(4px) }
		to   { opacity:1; transform: translateY(0) }
	}
	.cbot-msg--bot {
		align-self: flex-start;
	}
	.cbot-msg--user {
		align-self: flex-end;
	}
	.cbot-bubble {
		padding: 9px 13px;
		border-radius: 16px;
		word-break: break-word;
	}
	.cbot-msg--bot .cbot-bubble {
		background: #f3f4f6;
		color: #111;
		border-radius: 4px 16px 16px 16px;
	}
	.cbot-msg--user .cbot-bubble {
		background: var(--cbot-color);
		color: #fff;
		border-radius: 16px 16px 4px 16px;
	}
	.cbot-bubble p { margin: 0 0 6px; }
	.cbot-bubble p:last-child { margin-bottom: 0; }
	.cbot-bubble strong { font-weight: 600; }
	.cbot-bubble code {
		background: rgba(0,0,0,.07);
		padding: 1px 5px;
		border-radius: 4px;
		font-size: 12px;
	}
	.cbot-bubble pre {
		background: rgba(0,0,0,.07);
		padding: 8px 10px;
		border-radius: 6px;
		font-size: 12px;
		overflow-x: auto;
		margin: 6px 0;
	}
	.cbot-bubble ul, .cbot-bubble ol {
		margin: 4px 0;
		padding-left: 18px;
	}
	.cbot-bubble li { margin: 2px 0; }
	.cbot-bubble a {
		color: var(--cbot-color);
		text-decoration: underline;
		word-break: break-all;
	}
	.cbot-msg--user .cbot-bubble a { color: rgba(255,255,255,.9); }

	/* Sources */
	.cbot-sources {
		margin-top: 6px;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
	.cbot-source-link {
		font-size: 11.5px;
		color: var(--cbot-color);
		text-decoration: none;
		padding: 3px 8px;
		background: rgba(29,78,216,.07);
		border-radius: 12px;
		display: inline-flex;
		align-items: center;
		gap: 4px;
		width: fit-content;
		transition: background .15s;
	}
	.cbot-source-link:hover { background: rgba(29,78,216,.14); }
	.cbot-source-link::before { content: '↗'; font-size: 10px; }

	/* Typing indicator */
	.cbot-typing {
		align-self: flex-start;
		display: flex;
		gap: 4px;
		padding: 10px 14px;
		background: #f3f4f6;
		border-radius: 4px 16px 16px 16px;
	}
	.cbot-typing span {
		width: 7px; height: 7px;
		background: #9ca3af;
		border-radius: 50%;
		animation: cbot-bounce .9s infinite;
	}
	.cbot-typing span:nth-child(2) { animation-delay: .15s; }
	.cbot-typing span:nth-child(3) { animation-delay: .3s; }
	@keyframes cbot-bounce {
		0%,80%,100% { transform: translateY(0) }
		40%          { transform: translateY(-6px) }
	}

	/* Footer */
	.cbot-footer {
		padding: 10px 12px;
		border-top: 1px solid #f0f0f0;
		display: flex;
		gap: 8px;
		align-items: flex-end;
		flex-shrink: 0;
		background: #fff;
	}
	.cbot-input {
		flex: 1;
		border: 1.5px solid #e5e7eb;
		border-radius: 20px;
		padding: 8px 14px;
		font-size: 13px;
		resize: none;
		outline: none;
		max-height: 100px;
		overflow-y: auto;
		line-height: 1.5;
		transition: border-color .2s;
		font-family: inherit;
	}
	.cbot-input:focus { border-color: var(--cbot-color); }
	.cbot-send {
		width: 36px; height: 36px;
		border-radius: 50%;
		background: var(--cbot-color);
		border: none;
		color: #fff;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		transition: opacity .2s, transform .15s;
	}
	.cbot-send:hover { opacity: .9; transform: scale(1.05); }
	.cbot-send:disabled { opacity: .4; cursor: not-allowed; transform: none; }
	.cbot-send svg { width: 16px; height: 16px; }

	.cbot-powered {
		text-align: center;
		font-size: 10px;
		color: #bbb;
		padding: 4px 0 6px;
		flex-shrink: 0;
	}

	/* Mobile */
	@media (max-width: 480px) {
		#laca-cbot-win {
			right: 12px;
			bottom: 80px;
			width: calc(100vw - 24px);
		}
		#laca-cbot-btn { right: 16px; bottom: 20px; }
	}
	`;

	// ── Icons ────────────────────────────────────────────────────────────────

	const ICON_CHAT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
	</svg>`;

	const ICON_X = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
		<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
	</svg>`;

	const ICON_SEND = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<line x1="22" y1="2" x2="11" y2="13"/>
		<polygon points="22 2 15 22 11 13 2 9 22 2"/>
	</svg>`;

	// ── Build UI ─────────────────────────────────────────────────────────────

	function injectCSS() {
		const el = document.createElement('style');
		el.textContent = css;
		document.head.appendChild(el);
	}

	function buildUI() {
		// Floating button
		const btn = document.createElement('button');
		btn.id = 'laca-cbot-btn';
		btn.setAttribute('aria-label', 'Mở chatbot hỗ trợ');
		btn.innerHTML = `
			<span class="cbot-open">${ICON_CHAT}</span>
			<span class="cbot-close">${ICON_X}</span>
			<span id="laca-cbot-badge"></span>
		`;

		// Chat window
		const win = document.createElement('div');
		win.id = 'laca-cbot-win';
		win.setAttribute('role', 'dialog');
		win.setAttribute('aria-label', name + ' Chat');
		win.innerHTML = `
			<div class="cbot-header">
				<div class="cbot-avatar">✦</div>
				<div class="cbot-header-info">
					<div class="cbot-title">${escHtml(name)}</div>
					<div class="cbot-subtitle"><span class="cbot-dot"></span> Dựa trên nội dung website</div>
				</div>
			</div>
			<div class="cbot-messages" id="laca-cbot-msgs"></div>
			<div class="cbot-footer">
				<textarea
					id="laca-cbot-input"
					class="cbot-input"
					placeholder="${escAttr(placeholder)}"
					rows="1"
					aria-label="Nhập câu hỏi"
				></textarea>
				<button id="laca-cbot-send" class="cbot-send" aria-label="Gửi">${ICON_SEND}</button>
			</div>
			<div class="cbot-powered">Powered by LacaDev AI · Chỉ trả lời nội dung website</div>
		`;

		document.body.appendChild(btn);
		document.body.appendChild(win);

		return { btn, win };
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	function escHtml(str) {
		const d = document.createElement('div');
		d.appendChild(document.createTextNode(str));
		return d.innerHTML;
	}

	function escAttr(str) {
		return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	/** Markdown cơ bản → HTML */
	function md(text) {
		return text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
			.replace(/`([^`\n]+)`/g, '<code>$1</code>')
			.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
			.replace(/\*(.+?)\*/g, '<em>$1</em>')
			.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
			.replace(/^- (.+)$/gm, '<li>$1</li>')
			.replace(/(<li>[\s\S]+?<\/li>)(?!\s*<li>)/g, '<ul>$1</ul>')
			.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>')
			.replace(/\n\n+/g, '</p><p>')
			.replace(/\n/g, '<br>');
	}

	function appendMsg(role, content, sources) {
		const msgs  = document.getElementById('laca-cbot-msgs');
		const div   = document.createElement('div');
		div.className = `cbot-msg cbot-msg--${role}`;

		if (role === 'bot') {
			let html = `<div class="cbot-bubble"><p>${md(content)}</p></div>`;
			if (sources && sources.length) {
				html += '<div class="cbot-sources">';
				sources.forEach(s => {
					html += `<a href="${escAttr(s.url)}" class="cbot-source-link" target="_blank" rel="noopener">${escHtml(s.title)}</a>`;
				});
				html += '</div>';
			}
			div.innerHTML = html;
		} else {
			div.innerHTML = `<div class="cbot-bubble">${escHtml(content)}</div>`;
		}

		msgs.appendChild(div);
		msgs.scrollTop = msgs.scrollHeight;
		return div;
	}

	function showTyping() {
		const msgs = document.getElementById('laca-cbot-msgs');
		const el   = document.createElement('div');
		el.className = 'cbot-typing';
		el.id = 'laca-cbot-typing';
		el.innerHTML = '<span></span><span></span><span></span>';
		msgs.appendChild(el);
		msgs.scrollTop = msgs.scrollHeight;
	}

	function hideTyping() {
		const el = document.getElementById('laca-cbot-typing');
		if (el) el.remove();
	}

	// ── Chat Logic ───────────────────────────────────────────────────────────

	let isBusy = false;

	async function send() {
		const input   = document.getElementById('laca-cbot-input');
		const sendBtn = document.getElementById('laca-cbot-send');
		const message = input.value.trim();

		if (!message || isBusy) return;

		isBusy = true;
		sendBtn.disabled = true;
		input.value = '';
		input.style.height = 'auto';

		appendMsg('user', message);
		showTyping();

		try {
			const res = await fetch(endpoint, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body: JSON.stringify({ message }),
			});

			const data = await res.json();
			hideTyping();

			if (res.ok && data.reply) {
				appendMsg('bot', data.reply, data.sources || []);
			} else {
				const errText = data.message || 'Có lỗi xảy ra. Vui lòng thử lại.';
				appendMsg('bot', '⚠ ' + errText, []);
			}
		} catch {
			hideTyping();
			appendMsg('bot', '⚠ Không thể kết nối. Vui lòng thử lại sau.', []);
		}

		isBusy = false;
		sendBtn.disabled = false;
		input.focus();
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	function init() {
		injectCSS();

		const { btn, win } = buildUI();
		const input        = document.getElementById('laca-cbot-input');
		const sendBtn      = document.getElementById('laca-cbot-send');
		const badge        = document.getElementById('laca-cbot-badge');
		let   opened       = false;

		// Toggle window
		btn.addEventListener('click', () => {
			const isOpen = win.classList.toggle('visible');
			btn.classList.toggle('open', isOpen);

			if (isOpen) {
				badge.style.display = 'none';

				// Hiện lời chào lần đầu
				if (!opened) {
					opened = true;
					setTimeout(() => {
						appendMsg('bot', greeting, []);
					}, 300);
				}

				setTimeout(() => input.focus(), 260);
			}
		});

		// Gửi
		sendBtn.addEventListener('click', send);

		// Enter gửi, Shift+Enter xuống dòng
		input.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				send();
			}
			// Auto-resize textarea
			requestAnimationFrame(() => {
				input.style.height = 'auto';
				input.style.height = Math.min(input.scrollHeight, 100) + 'px';
			});
		});

		// Escape đóng
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && win.classList.contains('visible')) {
				win.classList.remove('visible');
				btn.classList.remove('open');
			}
		});

		// Click ngoài đóng
		document.addEventListener('click', (e) => {
			if (win.classList.contains('visible') && !win.contains(e.target) && !btn.contains(e.target)) {
				win.classList.remove('visible');
				btn.classList.remove('open');
			}
		});

		// Hiện badge sau 3 giây để kéo chú ý (lần đầu vào trang)
		if (!sessionStorage.getItem('laca_cbot_seen')) {
			setTimeout(() => {
				if (!win.classList.contains('visible')) {
					badge.style.display = 'block';
				}
			}, 3000);
			sessionStorage.setItem('laca_cbot_seen', '1');
		} else {
			badge.style.display = 'none';
		}
	}

	// Ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
