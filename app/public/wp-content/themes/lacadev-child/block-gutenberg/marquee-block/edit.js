import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Button,
	Dashicon,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { brands } = attributes;

	const updateBrand = ( index, key, value ) => {
		const newBrands = [ ...brands ];
		newBrands[ index ] = { ...newBrands[ index ], [ key ]: value };
		setAttributes( { brands: newBrands } );
	};

	const addBrand = () => {
		setAttributes( {
			brands: [ ...brands, { name: 'New Brand', url: '' } ],
		} );
	};

	const removeBrand = ( index ) => {
		const newBrands = brands.filter( ( _, i ) => i !== index );
		setAttributes( { brands: newBrands } );
	};

	const moveBrand = ( index, direction ) => {
		const newBrands = [ ...brands ];
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if ( newIndex >= 0 && newIndex < newBrands.length ) {
			[ newBrands[ index ], newBrands[ newIndex ] ] = [
				newBrands[ newIndex ],
				newBrands[ index ],
			];
			setAttributes( { brands: newBrands } );
		}
	};

	const onDragStart = ( e, index ) => {
		e.dataTransfer.setData( 'index', index );
	};

	const onDragOver = ( e ) => {
		e.preventDefault();
	};

	const onDrop = ( e, dropIndex ) => {
		const dragIndex = e.dataTransfer.getData( 'index' );
		if ( dragIndex === '' || dragIndex === String( dropIndex ) ) {
			return;
		}

		const newBrands = [ ...brands ];
		const draggedItem = newBrands[ dragIndex ];
		newBrands.splice( dragIndex, 1 );
		newBrands.splice( dropIndex, 0, draggedItem );
		setAttributes( { brands: newBrands } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cài đặt Marquee Brands', 'laca' ) }>
					<p
						style={ {
							fontSize: '12px',
							color: '#666',
							marginBottom: '15px',
							fontStyle: 'italic',
						} }
					>
						{ __(
							'* Bạn có thể nhấn và kéo các ô để đổi vị trí.',
							'laca'
						) }
					</p>
					{ brands.map( ( brand, index ) => (
						<div
							key={ index }
							draggable
							onDragStart={ ( e ) => onDragStart( e, index ) }
							onDragOver={ onDragOver }
							onDrop={ ( e ) => onDrop( e, index ) }
							style={ {
								padding: '15px',
								border: '1px solid #ddd',
								marginBottom: '15px',
								borderRadius: '8px',
								position: 'relative',
								background: '#f9f9f9',
								cursor: 'grab',
							} }
						>
							<div
								style={ {
									display: 'flex',
									justifyContent: 'flex-end',
									gap: '5px',
									marginBottom: '10px',
								} }
							>
								<Dashicon
									icon="move"
									style={ {
										marginRight: 'auto',
										opacity: 0.3,
									} }
								/>
								<Button
									isSmall
									onClick={ () => moveBrand( index, 'up' ) }
									disabled={ index === 0 }
									icon="arrow-up-alt2"
									label={ __( 'Di chuyển lên', 'laca' ) }
								/>
								<Button
									isSmall
									onClick={ () => moveBrand( index, 'down' ) }
									disabled={ index === brands.length - 1 }
									icon="arrow-down-alt2"
									label={ __( 'Di chuyển xuống', 'laca' ) }
								/>
								<Button
									isDestructive
									isSmall
									onClick={ () => removeBrand( index ) }
									icon="no-alt"
									label={ __( 'Xóa', 'laca' ) }
								/>
							</div>
							<TextControl
								label={ __( 'Tên thương hiệu', 'laca' ) }
								value={ brand.name }
								onChange={ ( val ) =>
									updateBrand( index, 'name', val )
								}
							/>
							<TextControl
								label={ __( 'URL (Tùy chọn)', 'laca' ) }
								value={ brand.url }
								onChange={ ( val ) =>
									updateBrand( index, 'url', val )
								}
							/>
						</div>
					) ) }
					<Button
						isPrimary
						onClick={ addBrand }
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( 'Thêm thương hiệu', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'block-marquee-editor' } ) }>
				<div
					className="marquee-preview-box"
					style={ {
						padding: '2rem',
						background: '#000',
						color: '#fff',
						borderRadius: '0.5rem',
						textAlign: 'center',
					} }
				>
					<div
						style={ {
							fontWeight: 'bold',
							textTransform: 'uppercase',
							marginBottom: '1rem',
							opacity: 0.5,
						} }
					>
						{ __( 'MARQUEE PREVIEW', 'laca' ) }
					</div>
					<div
						style={ {
							display: 'flex',
							flexWrap: 'wrap',
							gap: '1rem',
							justifyContent: 'center',
						} }
					>
						{ brands.map( ( brand, index ) => (
							<span
								key={ index }
								style={ {
									border: '1px solid rgba(255,255,255,0.2)',
									padding: '0.5rem 1rem',
									borderRadius: '2rem',
								} }
							>
								{ brand.name || __( '(Trống)', 'laca' ) }
								{ brand.url && (
									<Dashicon
										icon="admin-links"
										style={ {
											marginLeft: '5px',
											fontSize: '12px',
										} }
									/>
								) }
							</span>
						) ) }
					</div>
				</div>
			</div>
		</>
	);
}
