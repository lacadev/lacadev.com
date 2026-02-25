/* eslint-disable no-unused-vars */
import '@images/favicon.ico';
import '@styles/theme';
import './pages/*.js';
import './ajax-search.js';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Swup from 'swup';
import Swiper from 'swiper';
import { initAboutLacaHero } from './pages/about-laca';

gsap.registerPlugin( ScrollTrigger );

let flickerInterval;

document.addEventListener( 'DOMContentLoaded', () => {
	const swup = new Swup();
	initializePageFeatures();
	initPageLoader();

	// Hiển thị loader khi chuyển trang qua Swup
	swup.hooks.on( 'visit:start', () => {
		showPageLoader();
	} );

	swup.hooks.on( 'content:replace', () => {
		initializePageFeatures();
		hidePageLoader();
	} );
} );

function initializePageFeatures() {
	initCustomCursor();
	initHoverService();
	setupGsap404();
	initToggleDarkMode();
	initAboutLacaHero();
	initAnimations();
	initHeaderScroll();
	initMobileMenu();
	animateText();
	
	// Refresh ScrollTrigger after items are initialized
	setTimeout(() => {
		ScrollTrigger.refresh();
	}, 500);
}

/**
 * Hiển thị Page Loader
 */
function showPageLoader() {
	const loader = document.querySelector( '.page-loader' );
	const textLoader = document.querySelector( '.text-loader' );
	if ( ! loader || ! textLoader ) return;

	gsap.set( [ loader, textLoader ], { display: 'block', opacity: 1 } );
	document.body.classList.add( 'overflow-hidden' );
	startFlicker();
}

/**
 * Ẩn Page Loader
 */
function hidePageLoader() {
	const loader = document.querySelector( '.page-loader' );
	const textLoader = document.querySelector( '.text-loader' );
	if ( ! loader || ! textLoader ) return;

	const randoms = document.querySelectorAll( '.randoms' );
	randoms.forEach( ( el ) => ( el.style.opacity = '1' ) );

	gsap.to( textLoader, {
		opacity: 0,
		duration: 0.5,
		delay: 0.3,
		ease: 'power2.inOut',
		onComplete: () => {
			gsap.to( loader, {
				opacity: 0,
				duration: 0.6,
				delay: 0.1,
				ease: 'power2.inOut',
				onComplete: () => {
					loader.style.display = 'none';
					document.body.classList.remove( 'overflow-hidden' );
					stopFlicker();
				},
			} );
		},
	} );
}

/**
 * Hiệu ứng nhấp nháy chữ ngẫu nhiên
 */
function startFlicker() {
	stopFlicker();
	const randoms = document.querySelectorAll( '.randoms' );
	const words = [
		'LA CÀ DEV',
		'WORDPRESS',
		'BLOG',
		'TRAVELLING',
		'MINIMAL',
		'CLEAN',
	];

	flickerInterval = setInterval( () => {
		randoms.forEach( ( el ) => {
			const randomWord = words[ Math.floor( Math.random() * words.length ) ];
			el.textContent = randomWord;
			el.style.opacity = Math.random() > 0.5 ? '1' : '0.1';
		} );
	}, 120 );
}

function stopFlicker() {
	if ( flickerInterval ) {
		clearInterval( flickerInterval );
	}
}

/**
 * Khởi tạo Page Loader lần đầu
 */
function initPageLoader() {
	const loader = document.querySelector( '.page-loader' );
	if ( ! loader ) return;

	document.body.classList.add( 'overflow-hidden' );
	startFlicker();

	// Hiển thị trong 1s (1000ms)
	const startTime = Date.now();
	const minDisplayTime = 1000;

	const handleFinish = () => {
		const elapsedTime = Date.now() - startTime;
		const remainingTime = Math.max( 0, minDisplayTime - elapsedTime );
		setTimeout( hidePageLoader, remainingTime );
	};

	if ( document.readyState === 'complete' ) {
		handleFinish();
	} else {
		window.addEventListener( 'load', handleFinish );
	}

	// Fallback an toàn sau 5s
	setTimeout( () => {
		if ( loader.style.display !== 'none' ) {
			hidePageLoader();
		}
	}, 5000 );
}

/**
 * Khởi tạo hoạt ảnh GSAP và AOS
 */
function initAnimations() {
	// GSAP
	gsap.registerPlugin( ScrollTrigger );
	gsap.from( '.block-title-scroll', {
		x: '50%',
		duration: 2,
		opacity: 0.3,
		scrollTrigger: {
			trigger: '.block-title-scroll',
			start: 'top 80%',
			end: 'bottom 20%',
			scrub: true,
		},
	} );

	//   // AOS
	//   AOS.init({
	//     duration: 400,
	//   });
}

function initHoverService() {}

function initToggleDarkMode() {
	const toggleInput = document.querySelector( '.darkmode-icon input' );
	const rootElement = document.documentElement;
	const prefersDark = window.matchMedia(
		'(prefers-color-scheme: dark)'
	).matches;

	// Set initial theme based on system preference or saved preference
	const savedTheme = localStorage.getItem( 'theme' );
	const initialTheme = savedTheme || ( prefersDark ? 'dark' : 'light' );
	rootElement.setAttribute( 'data-theme', initialTheme );
	if ( toggleInput ) {
		toggleInput.checked = initialTheme === 'dark';
	}

	// Handle theme toggle
	if ( toggleInput ) {
		// Set initial ARIA state
		toggleInput.setAttribute( 'aria-checked', initialTheme === 'dark' );

		toggleInput.addEventListener( 'change', ( event ) => {
			const isDark = event.target.checked;
			const newTheme = isDark ? 'dark' : 'light';

			// Update ARIA state
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
		} );
	}

	// Listen for system theme changes
	window
		.matchMedia( '(prefers-color-scheme: dark)' )
		.addEventListener( 'change', ( e ) => {
			if ( ! localStorage.getItem( 'theme' ) ) {
				const newTheme = e.matches ? 'dark' : 'light';
				rootElement.setAttribute( 'data-theme', newTheme );
				if ( toggleInput ) {
					toggleInput.checked = e.matches;
				}
			}
		} );
}

/**
 * Header Scroll logic (Hide on scroll down, Show on scroll up)
 */
function initHeaderScroll() {
	const header = document.getElementById( 'header' );
	if ( ! header ) return;

	let lastScrollTop = 0;
	const threshold = 100;

	window.addEventListener( 'scroll', () => {
		const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

		// Background / Scrolled state
		if ( scrollTop > 50 ) {
			header.classList.add( 'header--scrolled' );
		} else {
			header.classList.remove( 'header--scrolled' );
		}

		// Hide / Show logic
		if ( scrollTop > threshold ) {
			if ( scrollTop > lastScrollTop ) {
				// Scroll Down
				header.classList.add( 'header--hidden' );
			} else {
				// Scroll Up
				header.classList.remove( 'header--hidden' );
			}
		} else {
			header.classList.remove( 'header--hidden' );
		}

		lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
	}, { passive: true } );
}

/**
 * Mobile Menu logic (Full screen overlay)
 */
function initMobileMenu() {
	const burgerBtn = document.getElementById( 'btn-hamburger' );
	const overlay = document.querySelector( '.mobile-overlay' );
	
	if ( ! burgerBtn || ! overlay ) return;

	// Toggle mobile overlay
	burgerBtn.addEventListener( 'click', () => {
		const isActive = burgerBtn.classList.contains( 'active' );
		
		if ( isActive ) {
			burgerBtn.classList.remove( 'active' );
			overlay.classList.remove( 'active' );
			document.body.classList.remove( 'menu-open' );
		} else {
			burgerBtn.classList.add( 'active' );
			overlay.classList.add( 'active' );
			document.body.classList.add( 'menu-open' );
		}
	} );

	// Combined logic for mobile submenus and navigation
	const menuLinks = overlay.querySelectorAll( 'a' );
	menuLinks.forEach( link => {
		link.addEventListener( 'click', ( e ) => {
			const parentLi = link.parentElement;
			const isParent = parentLi.classList.contains( 'has-children' );

			if ( window.innerWidth < 992 && isParent ) {
				const isOpen = parentLi.classList.contains( 'open' );
				
				if ( ! isOpen ) {
					// First click on parent: prevent navigation & open submenu
					e.preventDefault();
					e.stopPropagation();

					// Close other open submenus (Accordion style)
					const allParents = overlay.querySelectorAll( '.has-children' );
					allParents.forEach( p => {
						if ( p !== parentLi ) p.classList.remove( 'open' );
					} );

					parentLi.classList.add( 'open' );
					return; // Stop here, keep overlay open
				}
			}

			// If it's a regular link, or second click on parent, or desktop link:
			// Close the whole overlay and let navigation happen
			burgerBtn.classList.remove( 'active' );
			overlay.classList.remove( 'active' );
			document.body.classList.remove( 'menu-open' );
		} );
	} );
}

function setupGsap404() {
	gsap.set( 'svg', { visibility: 'visible' } );

	gsap.to( '#spaceman', {
		y: 5,
		rotation: 2,
		yoyo: true,
		repeat: -1,
		ease: 'sine.inOut',
		duration: 1,
	} );

	gsap.to( '#starsBig line', {
		rotation: 'random(-30,30)',
		transformOrigin: '50% 50%',
		yoyo: true,
		repeat: -1,
		ease: 'sine.inOut',
	} );

	gsap.fromTo(
		'#starsSmall g',
		{ scale: 0 },
		{
			scale: 1,
			transformOrigin: '50% 50%',
			yoyo: true,
			repeat: -1,
			stagger: 0.1,
		}
	);

	gsap.to( '#circlesSmall circle', {
		y: -4,
		yoyo: true,
		duration: 1,
		ease: 'sine.inOut',
		repeat: -1,
	} );

	gsap.to( '#circlesBig circle', {
		y: -2,
		yoyo: true,
		duration: 1,
		ease: 'sine.inOut',
		repeat: -1,
	} );

	gsap.set( '#glassShine', { x: -68 } );
	gsap.to( '#glassShine', {
		x: 80,
		duration: 2,
		rotation: -30,
		ease: 'expo.inOut',
		transformOrigin: '50% 50%',
		repeat: -1,
		repeatDelay: 8,
		delay: 2,
	} );
}

function animateText( selector ) {
	const hasAnim = document.querySelectorAll( '.slogan p' );
	hasAnim.forEach( ( element ) => {
		const splitType = 'lines, chars';
		const splitto = new SplitText( element, {
			type: splitType,
			linesClass: 'anim_line',
			charsClass: 'anim_char',
			wordsClass: 'anim_word',
		} );
		const chars = element.querySelectorAll( '.anim_char' );
		gsap.fromTo(
			chars,
			{ y: '100%', autoAlpha: 0 },
			{
				y: '0%',
				autoAlpha: 1,
				duration: 0.8,
				stagger: 0.01,
				ease: 'power2.out',
			}
		);
	} );
}

/**
 * Custom Mouse Cursor
 */
function initCustomCursor() {
	const cursorOuter = document.querySelector( '.cursor-outer' );
	const cursorInner = document.querySelector( '.cursor-inner' );

	if ( ! cursorOuter || ! cursorInner ) return;

	let mouseX = 0;
	let mouseY = 0;

	window.addEventListener( 'mousemove', ( e ) => {
		mouseX = e.clientX;
		mouseY = e.clientY;

		gsap.to( cursorInner, {
			x: mouseX,
			y: mouseY,
			duration: 0.1,
			ease: 'power2.out',
		} );

		gsap.to( cursorOuter, {
			x: mouseX,
			y: mouseY,
			duration: 0.5,
			ease: 'power2.out',
		} );
	} );

	// Handle hover states
	const handleMouseEnter = ( e ) => {
		const target = e.currentTarget;
		if ( target.hasAttribute( 'data-cursor-arrow' ) ) {
			cursorInner.classList.add( 'is-hover', 'is-visible' );
			cursorOuter.classList.add( 'is-hover', 'is-visible' );
		}
	};

	const handleMouseLeave = () => {
		cursorInner.classList.remove( 'is-hover', 'is-visible' );
		cursorOuter.classList.remove( 'is-hover', 'is-visible' );
	};

	const addCursorEvents = () => {
		const interactiveElements = document.querySelectorAll(
			'[data-cursor-arrow]'
		);
		interactiveElements.forEach( ( el ) => {
			el.removeEventListener( 'mouseenter', handleMouseEnter );
			el.removeEventListener( 'mouseleave', handleMouseLeave );
			el.addEventListener( 'mouseenter', handleMouseEnter );
			el.addEventListener( 'mouseleave', handleMouseLeave );
		} );
	};

	addCursorEvents();

	// Support for dynamically added elements (like after Swup content replacement)
	const observer = new MutationObserver( () => {
		addCursorEvents();
	} );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );
}