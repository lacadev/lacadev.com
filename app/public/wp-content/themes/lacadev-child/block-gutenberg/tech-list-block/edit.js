import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { PanelBody, Button, Dashicon } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { technologies } = attributes;

	const updateTech = ( index, key, value ) => {
		const newTech = [ ...technologies ];
		newTech[ index ] = { ...newTech[ index ], [ key ]: value };
		setAttributes( { technologies: newTech } );
	};

	const addTech = () => {
		setAttributes( {
			technologies: [
				...technologies,
				{ name: 'Khác', iconUrl: '', iconId: 0 },
			],
		} );
	};

	const removeTech = ( index ) => {
		const newTech = technologies.filter( ( _, i ) => i !== index );
		setAttributes( { technologies: newTech } );
	};

	const moveTech = ( index, direction ) => {
		const newTech = [ ...technologies ];
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if ( newIndex >= 0 && newIndex < newTech.length ) {
			[ newTech[ index ], newTech[ newIndex ] ] = [ newTech[ newIndex ], newTech[ index ] ];
			setAttributes( { technologies: newTech } );
		}
	};

	const removeMedia = ( index ) => {
		updateTech( index, 'iconId', 0 );
		updateTech( index, 'iconUrl', '' );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cài đặt danh sách công nghệ', 'laca' ) }>
					<p style={ { fontSize: '12px', color: '#666', marginBottom: '15px' } }>
						{ __( 'Thêm, sửa, hoặc xóa các công nghệ sử dụng.', 'laca' ) }
					</p>
					{ technologies.map( ( tech, index ) => (
						<div
							key={ index }
							style={ {
								padding: '15px',
								border: '1px solid #ddd',
								marginBottom: '15px',
								borderRadius: '8px',
								background: '#f9f9f9',
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
								<Button
									isSmall
									onClick={ () => moveTech( index, 'up' ) }
									disabled={ index === 0 }
									icon="arrow-up-alt2"
								/>
								<Button
									isSmall
									onClick={ () => moveTech( index, 'down' ) }
									disabled={ index === technologies.length - 1 }
									icon="arrow-down-alt2"
								/>
								<Button
									isDestructive
									isSmall
									onClick={ () => removeTech( index ) }
									icon="no-alt"
								/>
							</div>
							
							<div style={{ marginBottom: '10px' }}>
								<label style={{ display: 'block', fontSize: '12px', marginBottom: '5px' }}>
									{ __( 'Biểu tượng/Logo', 'laca' ) }
								</label>
								<MediaUploadCheck>
									<MediaUpload
										onSelect={ ( media ) => {
											updateTech( index, 'iconId', media.id );
											updateTech( index, 'iconUrl', media.url );
										} }
										allowedTypes={ [ 'image' ] }
										value={ tech.iconId }
										render={ ( { open } ) => (
											<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
												{ tech.iconUrl ? (
													<>
														<img 
															src={ tech.iconUrl } 
															alt="Preview" 
															style={{ width: '40px', height: '40px', objectFit: 'contain', background: '#e0e0e0', padding: '5px', borderRadius: '4px' }} 
														/>
														<Button isSecondary onClick={ open }>
															{ __( 'Đổi', 'laca' ) }
														</Button>
														<Button isDestructive isLink onClick={ () => removeMedia(index) }>
															{ __( 'Xóa', 'laca' ) }
														</Button>
													</>
												) : (
													<Button isPrimary onClick={ open }>
														{ __( 'Chọn Ảnh/SVG', 'laca' ) }
													</Button>
												) }
											</div>
										) }
									/>
								</MediaUploadCheck>
							</div>
						</div>
					) ) }
					<Button
						isSecondary
						onClick={ addTech }
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( '+ Thêm công nghệ', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...useBlockProps( { className: 'block-tech-list' } ) }>
				<div className="container">
					<div className="tech-list-grid">
						{ technologies.map( ( tech, index ) => (
							<div key={ index } className="tech-item">
								<div className="tech-icon-wrapper">
									{ tech.iconUrl ? (
										<img src={ tech.iconUrl } alt={ tech.name } className="tech-icon" />
									) : (
										<Dashicon icon="desktop" className="tech-placeholder" />
									) }
								</div>
								<RichText
									tagName="div"
									className="tech-name"
									value={ tech.name }
									onChange={ ( val ) => updateTech( index, 'name', val ) }
									placeholder={ __( 'Tên công nghệ…', 'laca' ) }
								/>
							</div>
						) ) }
					</div>
				</div>
			</section>
		</>
	);
}
