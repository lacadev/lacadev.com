import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	MediaUpload,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';

const SCENE_CSS = `
	.alp-scene{position:absolute;inset:0;background:radial-gradient(circle at 50% 40%,#1a2a4e 0%,#0d0d21 60%,#05050a 100%);overflow:hidden;z-index:0;cursor:pointer;}
	.alp-star{position:absolute;border-radius:50%;background:#fff;box-shadow:0 0 3px #fff;animation:alpTwinkle 3s ease-in-out infinite;z-index:2;}
	@keyframes alpTwinkle{0%,100%{opacity:.2;transform:scale(.8)}50%{opacity:1;transform:scale(1.2)}}
	.alp-moon{position:absolute;top:10%;right:15%;width:45px;height:45px;border-radius:50%;box-shadow:8px 8px 0 0 #fef9c3;filter:drop-shadow(0 0 15px rgba(254,249,195,.5));transform:rotate(-10deg);z-index:3;}
	.alp-ground{position:absolute;bottom:0;left:-10%;right:-10%;height:145px;background:#020204;border-radius:50% 50% 0 0;z-index:4;}
	.alp-tent{position:absolute;bottom:125px;left:32%;width:0;height:0;border-left:65px solid transparent;border-right:65px solid transparent;border-bottom:90px solid #1a3a5f;filter:drop-shadow(0 10px 25px rgba(0,0,0,.6));z-index:5;}
	.alp-tent::after{content:'';position:absolute;bottom:-90px;left:-22px;width:0;height:0;border-left:22px solid transparent;border-right:22px solid transparent;border-bottom:45px solid #05080c;}
	.alp-fire-wrap{position:absolute;bottom:130px;left:calc(32% + 140px);width:50px;height:50px;z-index:5;}
	.alp-f-glow{position:absolute;bottom:-20px;left:50%;width:250px;height:100px;margin-left:-125px;background:radial-gradient(ellipse,rgba(255,100,0,.3),transparent 70%);animation:alpPulse 1.2s ease-in-out infinite alternate;}
	@keyframes alpPulse{from{opacity:.4;transform:scale(.9)}to{opacity:.9;transform:scale(1.1)}}
	.alp-flames{position:relative;width:100%;height:100%;display:flex;justify-content:center;align-items:flex-end;}
	.alp-flame{position:absolute;bottom:4px;width:28px;height:50px;background:#ff5e13;border-radius:50% 50% 20% 20%/80% 80% 20% 20%;filter:blur(1.5px);transform-origin:bottom center;animation:alpFM .6s ease-in-out infinite alternate;mix-blend-mode:screen;}
	.alp-flame:nth-child(2){width:22px;height:40px;background:#ffcc33;animation-duration:.5s;animation-delay:.1s;filter:blur(1px);}
	.alp-flame:nth-child(3){width:15px;height:25px;background:#fff;animation-duration:.4s;animation-delay:.2s;filter:blur(.5px);}
	@keyframes alpFM{0%{transform:scale(1) rotate(-3deg) skewX(2deg)}100%{transform:scale(1.1,1.25) rotate(3deg) skewX(-2deg)}}
	.alp-ember{position:absolute;bottom:40px;left:50%;width:3px;height:3px;background:#ffcc33;border-radius:50%;filter:blur(0.5px);animation:alpEmbed var(--e-dur, 2s) linear infinite;}
	@keyframes alpEmbed{0%{transform:translate(var(--x),0) scale(1);opacity:1}100%{transform:translate(var(--tx),-120px) scale(0);opacity:0}}
	.alp-f-pit{position:absolute;bottom:0;left:50%;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;}
	.alp-logs{display:flex;gap:4px;margin-bottom:-2px;}
	.alp-log{width:35px;height:8px;background:#331a0a;border-radius:4px;transform:rotate(var(--r,20deg));}
	.alp-rocks{display:flex;gap:2px;}
	.alp-rock{width:10px;height:6px;background:#222;border-radius:40%;}
	.alp-trees{position:absolute;bottom:135px;right:10%;display:flex;gap:25px;z-index:3;}
	.alp-tree{width:0;height:0;border-left:28px solid transparent;border-right:28px solid transparent;border-bottom:90px solid #080f08;}
	.alp-tree.s{border-left-width:20px;border-right-width:20px;border-bottom-width:60px;margin-top:30px;}
	.alp-hint{position:absolute;bottom:30px;left:0;right:0;text-align:center;color:rgba(255,255,255,.2);font-family:monospace;font-size:9px;letter-spacing:4px;text-transform:uppercase;z-index:5;}
	.alp-click-ring{position:absolute;inset:0;border:1px dashed rgba(255,255,255,.1);pointer-events:none;}
`;

const CampingScene = ( { onOpenMedia } ) => (
	<div
		className="about-laca-placeholder alp-scene"
		onClick={ onOpenMedia }
		title={ __( 'Nhấn để chọn ảnh nền', 'laca' ) }
		role="button"
		tabIndex={ 0 }
		onKeyDown={ ( e ) => e.key === 'Enter' && onOpenMedia() }
	>
		<style>{ SCENE_CSS }</style>

		{ Array.from( { length: 100 }, ( _, i ) => {
			const size = ( i % 4 ) * 0.5 + 1.2;
			return (
				<div
					key={ i }
					className="alp-star"
					style={ {
						left: `${ ( i * 37 + 13 ) % 100 }%`,
						top: `${ ( i * 53 + 7 ) % 85 }%`,
						width: `${ size }px`,
						height: `${ size }px`,
						animationDelay: `${ ( i * 0.4 ) % 5 }s`,
						animationDuration: `${ 2 + ( i % 3 ) }s`,
						boxShadow: `0 0 ${ size }px #fff`,
					} }
				/>
			);
		} ) }

		<div className="alp-moon" />

		<div className="alp-trees">
			<div className="alp-tree" />
			<div className="alp-tree s" />
		</div>

		<div className="alp-tent" />

		<div className="alp-fire-wrap">
			<div className="alp-f-glow" />

			{ Array.from( { length: 8 }, ( _, i ) => (
				<div
					key={ i }
					className="alp-ember"
					style={ {
						'--x': `${ ( ( i * 17 ) % 30 ) - 15 }px`,
						'--tx': `${ ( ( i * 23 ) % 60 ) - 30 }px`,
						'--e-dur': `${ 1.5 + ( ( i * 0.3 ) % 2 ) }s`,
						animationDelay: `${ i * 0.4 }s`,
						left: `${ 40 + ( ( i * 7 ) % 20 ) }%`,
					} }
				/>
			) ) }

			<div className="alp-flames">
				<div className="alp-flame" />
				<div className="alp-flame" />
				<div className="alp-flame" />
			</div>

			<div className="alp-f-pit">
				<div className="alp-logs">
					<div className="alp-log" style={ { '--r': '25deg' } } />
					<div
						className="alp-log"
						style={ { '--r': '-25deg', marginLeft: '-15px' } }
					/>
				</div>
				<div className="alp-rocks">
					<div className="alp-rock" />
					<div className="alp-rock" style={ { marginTop: '2px' } } />
					<div className="alp-rock" />
					<div className="alp-rock" style={ { marginTop: '2px' } } />
				</div>
			</div>
		</div>

		<div className="alp-ground" />
		<div className="alp-hint">
			{ __( '// nhấn để chọn ảnh nền', 'laca' ) }
		</div>
		<div className="alp-click-ring" />
	</div>
);

export default function Edit( { attributes, setAttributes } ) {
	const { content, bgImageId, bgImageUrl } = attributes;

	const onSelectImage = ( media ) => {
		setAttributes( {
			bgImageId: media.id,
			bgImageUrl: media.url,
		} );
	};

	const removeImage = () => {
		setAttributes( {
			bgImageId: undefined,
			bgImageUrl: undefined,
		} );
	};

	const blockProps = useBlockProps( {
		className: 'block-about-laca editor-view',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cấu hình Ảnh nền', 'laca' ) }>
					{ bgImageUrl && (
						<div
							className="image-preview"
							style={ { marginBottom: '10px' } }
						>
							<img
								src={ bgImageUrl }
								alt=""
								style={ { maxWidth: '100%', height: 'auto' } }
							/>
							<Button isDestructive onClick={ removeImage }>
								{ __( 'Xóa ảnh', 'laca' ) }
							</Button>
						</div>
					) }
					<MediaUpload
						onSelect={ onSelectImage }
						allowedTypes={ [ 'image' ] }
						value={ bgImageId }
						render={ ( { open } ) => (
							<Button isPrimary onClick={ open }>
								{ ! bgImageId
									? __( 'Chọn ảnh nền', 'laca' )
									: __( 'Thay đổi ảnh', 'laca' ) }
							</Button>
						) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Nội dung', 'laca' ) }>
					<RichText
						tagName="div"
						className="about-content"
						value={ content }
						onChange={ ( value ) =>
							setAttributes( { content: value } )
						}
						placeholder={ __(
							'Nhập nội dung giới thiệu…',
							'laca'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="img-container">
					{ ! bgImageUrl ? (
						<MediaUpload
							onSelect={ onSelectImage }
							allowedTypes={ [ 'image' ] }
							value={ bgImageId }
							render={ ( { open } ) => (
								<CampingScene onOpenMedia={ open } />
							) }
						/>
					) : (
						<div
							className="bg-image-preview"
							style={ {
								backgroundImage: `url(${ bgImageUrl })`,
							} }
						/>
					) }

					<div className="content-wrapper">
						<RichText
							tagName="div"
							className="about-content"
							value={ content }
							onChange={ ( value ) =>
								setAttributes( { content: value } )
							}
							placeholder={ __(
								'Nhập nội dung giới thiệu…',
								'laca'
							) }
						/>
					</div>
				</div>
			</section>
		</>
	);
}
