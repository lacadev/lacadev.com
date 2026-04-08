/**
 * UI Utilities
 *
 * 3 lightweight frontend features:
 *   1. Back to Top button
 *   2. Copy Code button (auto-inject vào <pre><code>)
 *   3. Image Lightbox (click-to-zoom cho ảnh trong bài viết)
 *
 * @package LacaDev
 */

// ─── 1. Back to Top ──────────────────────────────────────────────────────────

export function initBackToTop() {
	const btn = document.createElement( 'button' );
	btn.id            = 'laca-back-top';
	btn.setAttribute( 'aria-label', 'Về đầu trang' );
	btn.setAttribute( 'title', 'Về đầu trang' );
	btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none"
		stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
		<polyline points="18 15 12 9 6 15"/>
	</svg>`;
	btn.style.cssText = `
		position: fixed;
		bottom: 80px;
		right: 20px;
		width: 42px;
		height: 42px;
		border-radius: 50%;
		border: none;
		background: var(--primary-color, #2271b1);
		color: #fff;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		opacity: 0;
		transform: translateY(10px);
		transition: opacity .25s, transform .25s;
		z-index: 9980;
		box-shadow: 0 2px 12px rgba(0,0,0,.2);
	`;
	document.body.appendChild( btn );

	const toggle = () => {
		const show = window.scrollY > 300;
		btn.style.opacity   = show ? '1' : '0';
		btn.style.transform = show ? 'translateY(0)' : 'translateY(10px)';
		btn.style.pointerEvents = show ? 'auto' : 'none';
	};

	window.addEventListener( 'scroll', toggle, { passive: true } );
	btn.addEventListener( 'click', () => window.scrollTo( { top: 0, behavior: 'smooth' } ) );
	toggle();
}

// ─── 2. Copy Code Button ──────────────────────────────────────────────────────

export function initCopyCode() {
	document.querySelectorAll( 'pre code, .wp-block-code code' ).forEach( ( code ) => {
		const pre = code.closest( 'pre' );
		if ( ! pre || pre.querySelector( '.laca-copy-btn' ) ) return;

		pre.style.position = 'relative';

		const btn = document.createElement( 'button' );
		btn.className = 'laca-copy-btn';
		btn.textContent = 'Copy';
		btn.setAttribute( 'aria-label', 'Copy code' );
		btn.style.cssText = `
			position: absolute;
			top: 8px;
			right: 8px;
			padding: 3px 10px;
			font-size: 11px;
			font-weight: 700;
			background: rgba(255,255,255,.15);
			color: inherit;
			border: 1px solid rgba(255,255,255,.25);
			border-radius: 4px;
			cursor: pointer;
			transition: background .15s;
			backdrop-filter: blur(4px);
		`;

		btn.addEventListener( 'click', () => {
			navigator.clipboard.writeText( code.innerText ).then( () => {
				btn.textContent = '✓ Copied!';
				btn.style.background = 'rgba(76,175,80,.4)';
				setTimeout( () => {
					btn.textContent = 'Copy';
					btn.style.background = 'rgba(255,255,255,.15)';
				}, 1800 );
			} ).catch( () => {
				btn.textContent = 'Lỗi';
				setTimeout( () => { btn.textContent = 'Copy'; }, 1500 );
			} );
		} );

		pre.appendChild( btn );
	} );
}

// ─── 3. Image Lightbox ────────────────────────────────────────────────────────

export function initImageLightbox() {
	// Inject styles once
	if ( ! document.getElementById( 'laca-lightbox-style' ) ) {
		const style = document.createElement( 'style' );
		style.id    = 'laca-lightbox-style';
		style.textContent = `
			#laca-lightbox {
				position: fixed; inset: 0; z-index: 99999;
				background: rgba(0,0,0,.92);
				display: flex; align-items: center; justify-content: center;
				opacity: 0; pointer-events: none;
				transition: opacity .25s;
			}
			#laca-lightbox.is-open { opacity: 1; pointer-events: auto; }
			#laca-lightbox img {
				max-width: 92vw; max-height: 88vh;
				border-radius: 4px;
				box-shadow: 0 8px 40px rgba(0,0,0,.6);
				transform: scale(.94);
				transition: transform .25s;
				object-fit: contain;
			}
			#laca-lightbox.is-open img { transform: scale(1); }
			#laca-lightbox-close {
				position: absolute; top: 16px; right: 20px;
				color: #fff; font-size: 32px; cursor: pointer;
				background: none; border: none; line-height: 1;
				opacity: .7; transition: opacity .15s;
			}
			#laca-lightbox-close:hover { opacity: 1; }
			#laca-lightbox-caption {
				position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
				color: rgba(255,255,255,.7); font-size: 13px; max-width: 80vw;
				text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
			}
		`;
		document.head.appendChild( style );
	}

	// Build lightbox DOM once
	let box = document.getElementById( 'laca-lightbox' );
	if ( ! box ) {
		box = document.createElement( 'div' );
		box.id = 'laca-lightbox';
		box.innerHTML = `
			<button id="laca-lightbox-close" aria-label="Đóng">✕</button>
			<img src="" alt="" id="laca-lightbox-img">
			<p id="laca-lightbox-caption"></p>
		`;
		document.body.appendChild( box );

		const close = () => {
			box.classList.remove( 'is-open' );
			document.body.style.overflow = '';
		};

		document.getElementById( 'laca-lightbox-close' ).addEventListener( 'click', close );
		box.addEventListener( 'click', ( e ) => { if ( e.target === box ) close(); } );
		document.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Escape' ) close(); } );
	}

	const img    = document.getElementById( 'laca-lightbox-img' );
	const caption = document.getElementById( 'laca-lightbox-caption' );

	// Find all linked images in post content: <a href="*.jpg"><img></a>
	const imgExtRe = /\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/i;

	document.querySelectorAll( '.post-body a[href], .entry-content a[href], .single-template a[href]' ).forEach( ( link ) => {
		const href   = link.getAttribute( 'href' ) || '';
		const child  = link.querySelector( 'img' );
		if ( ! child || ! imgExtRe.test( href ) ) return;

		link.style.cursor = 'zoom-in';

		link.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			img.src            = href;
			img.alt            = child.alt || '';
			caption.textContent = child.alt || '';
			box.classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
		} );
	} );

	// Also handle bare <img> not inside a link (in .post-body only)
	document.querySelectorAll( '.post-body img, .entry-content img' ).forEach( ( image ) => {
		if ( image.closest( 'a' ) ) return; // already handled above
		const src = image.getAttribute( 'data-src' ) || image.src;
		if ( ! src || ! imgExtRe.test( src ) ) return;

		image.style.cursor = 'zoom-in';

		image.addEventListener( 'click', () => {
			img.src            = src;
			img.alt            = image.alt || '';
			caption.textContent = image.alt || '';
			box.classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
		} );
	} );
}
