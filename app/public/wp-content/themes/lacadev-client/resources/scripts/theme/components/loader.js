/**
 * Page Loader
 * Quản lý hiệu ứng loader khi vào trang lần đầu (hoặc sau 24h).
 */

import gsap from 'gsap';

const LOADER_KEY = 'laca_loader_shown';
const HOURS_24 = 24 * 60 * 60 * 1000;

let flickerInterval;

const WORDS = [ 'LA CÀ DEV CLIENT', 'WORDPRESS', 'BLOG', 'TRAVELLING', 'MINIMAL', 'CLEAN' ];

function startFlicker() {
	stopFlicker();
	const randoms = document.querySelectorAll( '.randoms' );
	flickerInterval = setInterval( () => {
		randoms.forEach( ( el ) => {
			el.textContent = WORDS[ Math.floor( Math.random() * WORDS.length ) ];
			el.style.opacity = Math.random() > 0.5 ? '1' : '0.1';
		} );
	}, 120 );
}

function stopFlicker() {
	if ( flickerInterval ) {
		clearInterval( flickerInterval );
	}
}

function showPageLoader() {
	const loader = document.querySelector( '.page-loader' );
	const textLoader = document.querySelector( '.text-loader' );
	if ( ! loader || ! textLoader ) return;

	loader.classList.add( 'active' );
	gsap.set( [ loader, textLoader ], { display: 'block', opacity: 1 } );
	document.body.classList.add( 'overflow-hidden' );
	startFlicker();
}

function hidePageLoader() {
	const loader = document.querySelector( '.page-loader' );
	const textLoader = document.querySelector( '.text-loader' );
	if ( ! loader || ! textLoader ) return;

	document.querySelectorAll( '.randoms' ).forEach( ( el ) => ( el.style.opacity = '1' ) );

	gsap.to( textLoader, {
		opacity: 0, duration: 0.5, delay: 0.3, ease: 'power2.inOut',
		onComplete: () => {
			gsap.to( loader, {
				opacity: 0, duration: 0.6, delay: 0.1, ease: 'power2.inOut',
				onComplete: () => {
					loader.style.display = 'none';
					loader.classList.remove( 'active' );
					document.body.classList.remove( 'overflow-hidden' );
					document.documentElement.classList.remove( 'loading' );
					stopFlicker();
				},
			} );
		},
	} );
}

export function shouldShowLoader() {
	const lastShown = localStorage.getItem( LOADER_KEY );
	return ! lastShown || ( Date.now() - parseInt( lastShown ) ) >= HOURS_24;
}

export function initPageLoader( isMobile ) {
	const loader = document.querySelector( '.page-loader' );
	if ( ! loader ) return;

	if ( isMobile ) {
		loader.style.display = 'none';
		loader.classList.remove( 'active' );
		document.body.classList.remove( 'overflow-hidden' );
		document.documentElement.classList.remove( 'loading' );
		return;
	}

	if ( ! shouldShowLoader() ) {
		loader.style.display = 'none';
		loader.classList.remove( 'active' );
		document.body.classList.remove( 'overflow-hidden' );
		document.documentElement.classList.remove( 'loading' );
		return;
	}

	loader.classList.add( 'active' );
	document.body.classList.add( 'overflow-hidden' );
	document.documentElement.classList.add( 'loading' );
	startFlicker();
	localStorage.setItem( LOADER_KEY, Date.now().toString() );

	const startTime = Date.now();
	const MIN_DISPLAY = 1000;

	const handleFinish = () => {
		const remaining = Math.max( 0, MIN_DISPLAY - ( Date.now() - startTime ) );
		setTimeout( hidePageLoader, remaining );
	};

	if ( document.readyState === 'complete' ) {
		handleFinish();
	} else {
		window.addEventListener( 'load', handleFinish );
	}

	// Fallback an toàn sau 5s
	setTimeout( () => {
		if ( loader.style.display !== 'none' ) hidePageLoader();
	}, 5000 );
}
