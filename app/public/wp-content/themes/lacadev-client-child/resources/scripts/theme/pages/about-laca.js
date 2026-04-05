import gsap from 'gsap';

export const initAboutLacaHero = () => {
	const heroSection = document.querySelector( '.block-about-laca' );
	if ( ! heroSection ) {
		return;
	}

	const imgContainer = heroSection.querySelector( '.img-container' );
	const content = heroSection.querySelector( '.content-wrapper' );

	if ( ! imgContainer || ! content ) {
		return;
	}

	const parallaxBg = heroSection.querySelector( '.parallax-bg' );

	// Kill bất kỳ ScrollTrigger/tween cũ nào
	gsap.killTweensOf( [ imgContainer, content, parallaxBg ] );

	// Tính 50rem sang px
	const rootFontSize = parseFloat(
		window.getComputedStyle( document.documentElement ).fontSize
	);
	const initialMaxWidth = 50 * rootFontSize;
	const fullWidth = window.innerWidth;

	// Ép trạng thái ban đầu bằng px
	gsap.set( imgContainer, {
		maxWidth: initialMaxWidth,
		borderRadius: '2rem',
	} );
	gsap.set( content, { opacity: 0, y: 30 } );
	if ( parallaxBg ) {
		gsap.set( parallaxBg, { scale: 1.1 } );
	}

	const tl = gsap.timeline( {
		scrollTrigger: {
			trigger: heroSection,
			start: '50% bottom', // 80% chiều cao element chạm đáy viewport → chưa trigger khi load
			end: '+=70%', // Animation diễn ra trong 80% viewport height
			scrub: 1,
			invalidateOnRefresh: true,
		},
	} );

	// 1. Mở rộng từ 50rem → full viewport width + bỏ bo góc
	tl.fromTo(
		imgContainer,
		{ maxWidth: initialMaxWidth, borderRadius: '2rem' },
		{
			maxWidth: fullWidth,
			borderRadius: '0rem',
			ease: 'none',
			force3D: true,
		},
		0
	);

	// 2. Zoom out ảnh nền
	if ( parallaxBg ) {
		tl.fromTo(
			parallaxBg,
			{ scale: 1.1 },
			{ scale: 1, ease: 'none', force3D: true },
			0
		);
	}

	// 3. Hiện nội dung khi gần full width (~60% tiến trình)
	tl.fromTo(
		content,
		{ opacity: 0, y: 30 },
		{ opacity: 1, y: 0, duration: 0.4, ease: 'power2.out', force3D: true },
		0.3
	);
};
