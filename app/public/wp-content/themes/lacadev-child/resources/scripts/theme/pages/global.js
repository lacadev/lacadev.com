/* eslint-disable no-console, no-unused-vars, no-alert */
import Swiper from 'swiper/bundle';
// import { Navigation, Pagination, Autoplay } from 'swiper/modules';

// Configure Swiper to use modules
// Swiper.use([Navigation, Pagination, Autoplay]);

window.globalFunctions = {
	init() {
		hidePageLoader();

		const jobSeekerRegisterForm = document.getElementById(
			'job_seeker_register_form'
		);
		if ( jobSeekerRegisterForm ) {
			if (
				window.registerFunction &&
				window.registerFunction.initJobSeekerForm
			) {
				window.registerFunction.initJobSeekerForm(
					jobSeekerRegisterForm
				);
			}
		}

		// Mobile Menu (Mmenu replacement or keep if it's not jQuery dependent - wait, mmenu-js is vanilla?)
		// Checking package.json, "mmenu-js": "^8.5.20" is likely the vanilla version.
		// But the code uses $("#drop_down").mmenu(), which is jQuery syntax.
		// Assuming we need to fix this too, but for now let's focus on Slider as per plan.
		// If mmenu is jQuery plugin, we might need to replace it or adapt it.
		// For now, I will comment it out if it breaks, or try to use vanilla initialization if possible.
		// Let's assume for this step we focus on Sliders and Pjax removal.

		// Job Slider
		const jobSlider = new Swiper( '#js-job-slider', {
			loop: true,
			slidesPerView: 1,
			pagination: {
				el: '.swiper-pagination',
				clickable: true,
			},
			navigation: false,
		} );

		// MV Slider
		const mvSlider = new Swiper( '#js-mv-slider', {
			loop: true,
			autoplay: {
				delay: 3000,
				disableOnInteraction: false,
			},
			slidesPerView: 1,
			pagination: {
				el: '.swiper-pagination',
				clickable: true,
			},
			navigation: false,
		} );

		// Video Modal
		const videoLinks = document.querySelectorAll( '.p-video-item__link' );
		const videoModal = document.getElementById( 'video-js' );

		if ( videoModal ) {
			videoLinks.forEach( ( link ) => {
				link.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					videoModal.setAttribute(
						'src',
						`${ this.getAttribute( 'href' ) }?autoplay=1`
					);
				} );
			} );

			// Bootstrap modal dismiss (if using Bootstrap JS) or custom
			const dismissButtons = document.querySelectorAll(
				'[data-dismiss="modal"]'
			);
			dismissButtons.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					videoModal.setAttribute( 'src', '' );
				} );
			} );
		}

		// File Input
		const fileInputs = document.querySelectorAll( '.c-inputFile' );
		fileInputs.forEach( ( input ) => {
			input.addEventListener( 'change', ( e ) => {
				const fileName = e.target.files[ 0 ].name;
				const urlFile = document.getElementById( 'js-url-file' );
				if ( urlFile ) {
					urlFile.innerHTML = fileName;
				}
			} );
		} );

		// Sticky Header
		window.addEventListener( 'scroll', () => {
			const headerInfo = document.querySelector( '.p-header-info' );
			const headerMenu = document.querySelector( '.p-header-menu' );
			if ( headerInfo && headerMenu ) {
				if ( window.scrollY > headerInfo.offsetHeight ) {
					headerMenu.classList.add( 'is-fixed' );
				} else {
					headerMenu.classList.remove( 'is-fixed' );
				}
			}
		} );

		// Scroll to Apply
		const goApplyBtns = document.querySelectorAll( '.js-goApply' );
		goApplyBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const jobApply = document.getElementById( 'jobApply' );
				if ( jobApply ) {
					window.scrollTo( {
						top: jobApply.offsetTop - 100,
						behavior: 'smooth',
					} );
				}
			} );
		} );

		// Profile Slider
		const profileSlider = new Swiper( '#js-profile-slider', {
			loop: true,
			speed: 300,
			slidesPerView: 5,
			spaceBetween: 10, // Add space between slides if needed
			pagination: {
				el: '.swiper-pagination',
				clickable: true,
			},
			breakpoints: {
				1200: {
					slidesPerView: 4,
				},
				940: {
					slidesPerView: 3,
				},
				730: {
					slidesPerView: 2,
				},
				490: {
					slidesPerView: 1,
				},
			},
		} );
	},
};

// Enable pjax - REPLACED BY SWUP in index.js
// window.suggestTimeout = null;
