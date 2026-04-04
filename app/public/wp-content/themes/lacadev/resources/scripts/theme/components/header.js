/**
 * Header
 * Ẩn/hiện header khi scroll, thêm class scrolled khi ra khỏi top.
 */

// Giữ lastScrollTop ngoài closure để resetHeaderState() có thể sync lại.
let lastScrollTop = 0;

export function initHeaderScroll() {
	const header = document.getElementById( 'header' );
	if ( ! header ) return;

	const controller = new AbortController();
	const THRESHOLD = 100;

	window.addEventListener( 'scroll', () => {
		const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

		header.classList.toggle( 'header--scrolled', scrollTop > 50 );

		if ( scrollTop > THRESHOLD ) {
			header.classList.toggle( 'header--hidden', scrollTop > lastScrollTop );
		} else {
			header.classList.remove( 'header--hidden' );
		}

		lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
	}, { passive: true, signal: controller.signal } );

	return () => controller.abort();
}

/**
 * Đồng bộ lại trạng thái header sau Swup navigation.
 * Phải gọi trên content:replace để tránh header bị kẹt ở trạng thái trang cũ.
 */
export function resetHeaderState() {
	const header = document.getElementById( 'header' );
	if ( ! header ) return;

	const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

	// Sync lastScrollTop về vị trí hiện tại để scroll listener hoạt động đúng ngay từ đầu
	lastScrollTop = scrollTop;

	header.classList.toggle( 'header--scrolled', scrollTop > 50 );
	// Luôn show header khi vào trang mới
	header.classList.remove( 'header--hidden' );
}
