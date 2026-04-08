/**
 * Reading Progress Bar + Estimated Read Time
 *
 * - Progress bar: thanh mỏng ở top page, track vị trí cuộn trong bài viết.
 * - Read time: inject "X phút đọc" vào `.post-hero` hoặc sau `h1` trong single post.
 *
 * Chỉ chạy trên trang single post/page có class `.single-post-template`.
 *
 * @package LacaDev
 */

export function initReadingProgress() {
	// Chỉ chạy trên trang single post
	const article = document.querySelector( '.single-post-template' );
	if ( ! article ) return;

	const postBody = article.querySelector( '.post-body' );
	if ( ! postBody ) return;

	// ── 1. Reading Progress Bar ────────────────────────────────────────────
	const bar = document.createElement( 'div' );
	bar.id = 'laca-reading-bar';
	bar.style.cssText = `
		position: fixed;
		top: 0;
		left: 0;
		width: 0%;
		height: 3px;
		background: var(--primary-color, #2271b1);
		z-index: 9999;
		transition: width 0.1s linear;
		border-radius: 0 2px 2px 0;
		pointer-events: none;
	`;
	document.body.prepend( bar );

	function updateProgressBar() {
		const rect       = postBody.getBoundingClientRect();
		const totalHeight = postBody.offsetHeight;
		const scrolled   = Math.max( 0, -rect.top );
		const pct        = Math.min( 100, ( scrolled / totalHeight ) * 100 );
		bar.style.width  = pct + '%';
	}

	window.addEventListener( 'scroll', updateProgressBar, { passive: true } );
	updateProgressBar();

	// ── 2. Estimated Read Time ─────────────────────────────────────────────
	const text      = postBody.innerText || postBody.textContent || '';
	const wordCount = text.trim().split( /\s+/ ).length;
	const minutes   = Math.max( 1, Math.round( wordCount / 200 ) ); // ~200 words/min

	// Tìm vị trí để inject: sau post-hero hoặc trước .post-body
	const hero = article.querySelector( '.post-hero, .post-hero-content, header.post-header' );
	const target = hero || postBody;
	const insertBefore = hero ? target.nextElementSibling : target;

	const badge = document.createElement( 'div' );
	badge.className = 'laca-read-time';
	badge.style.cssText = `
		display: inline-flex;
		align-items: center;
		gap: 5px;
		font-size: 13px;
		color: #888;
		margin-bottom: 16px;
		font-style: italic;
	`;
	badge.innerHTML = `
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
		</svg>
		${ minutes } phút đọc
	`;

	if ( insertBefore && insertBefore.parentNode ) {
		insertBefore.parentNode.insertBefore( badge, insertBefore );
	} else {
		postBody.parentNode.insertBefore( badge, postBody );
	}
}
