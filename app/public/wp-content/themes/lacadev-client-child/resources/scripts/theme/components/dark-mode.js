/**
 * Dark Mode Toggle
 * Hỗ trợ toggle sáng/tối, lưu preference vào localStorage,
 * và lắng nghe thay đổi từ system prefers-color-scheme.
 */

export function initToggleDarkMode() {
	const toggleInput = document.querySelector( '.header__darkmode-input' );
	const rootElement = document.documentElement;
	const mediaQuery = window.matchMedia( '(prefers-color-scheme: dark)' );
	const prefersDark = mediaQuery.matches;

	const savedTheme = localStorage.getItem( 'theme' );
	const initialTheme = savedTheme || ( prefersDark ? 'dark' : 'light' );
	rootElement.setAttribute( 'data-theme', initialTheme );

	const controller = new AbortController();
	const { signal } = controller;

	if ( toggleInput ) {
		toggleInput.checked = initialTheme === 'dark';
		toggleInput.setAttribute( 'aria-checked', initialTheme === 'dark' );

		toggleInput.addEventListener( 'change', ( event ) => {
			const isDark = event.target.checked;
			const newTheme = isDark ? 'dark' : 'light';
			toggleInput.setAttribute( 'aria-checked', isDark );

			if ( document.startViewTransition ) {
				document.startViewTransition( () => {
					rootElement.setAttribute( 'data-theme', newTheme );
					localStorage.setItem( 'theme', newTheme );
				} );
			} else {
				rootElement.setAttribute( 'data-theme', newTheme );
				localStorage.setItem( 'theme', newTheme );
			}
		}, { signal } );
	}

	mediaQuery.addEventListener( 'change', ( e ) => {
		if ( ! localStorage.getItem( 'theme' ) ) {
			const newTheme = e.matches ? 'dark' : 'light';
			rootElement.setAttribute( 'data-theme', newTheme );
			if ( toggleInput ) toggleInput.checked = e.matches;
		}
	}, { signal } );

	return () => controller.abort();
}
