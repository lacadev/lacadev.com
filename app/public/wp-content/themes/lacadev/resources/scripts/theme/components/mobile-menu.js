/**
 * Mobile Menu
 * Full-screen overlay menu với accordion submenu cho mobile.
 */

// Closure refs để closeMobileMenu() dùng được từ bên ngoài
let _burgerBtn = null;
let _overlay   = null;

/**
 * Đóng mobile menu từ bên ngoài (dùng trong Swup content:replace hook).
 */
export function closeMobileMenu() {
	if ( _burgerBtn ) _burgerBtn.classList.remove( 'active' );
	if ( _overlay )   _overlay.classList.remove( 'active' );
	document.body.classList.remove( 'menu-open' );
}

export function initMobileMenu() {
	const burgerBtn = document.getElementById( 'btn-hamburger' );
	const overlay = document.querySelector( '.header__overlay' );
	if ( ! burgerBtn || ! overlay ) return;

	_burgerBtn = burgerBtn;
	_overlay   = overlay;

	const controller = new AbortController();
	const { signal } = controller;

	const closeMenu = () => {
		burgerBtn.classList.remove( 'active' );
		overlay.classList.remove( 'active' );
		document.body.classList.remove( 'menu-open' );
	};

	burgerBtn.addEventListener( 'click', () => {
		const isActive = burgerBtn.classList.contains( 'active' );
		if ( isActive ) {
			closeMenu();
		} else {
			burgerBtn.classList.add( 'active' );
			overlay.classList.add( 'active' );
			document.body.classList.add( 'menu-open' );
		}
	}, { signal } );

	overlay.querySelectorAll( 'a' ).forEach( ( link ) => {
		link.addEventListener( 'click', ( e ) => {
			const parentLi = link.parentElement;
			const isParent = parentLi.classList.contains( 'has-children' );

			if ( window.innerWidth < 992 && isParent && ! parentLi.classList.contains( 'open' ) ) {
				e.preventDefault();
				e.stopPropagation();
				overlay.querySelectorAll( '.has-children' ).forEach( ( p ) => {
					if ( p !== parentLi ) p.classList.remove( 'open' );
				} );
				parentLi.classList.add( 'open' );
				return;
			}

			closeMenu();
		}, { signal } );
	} );

	return () => controller.abort();
}
