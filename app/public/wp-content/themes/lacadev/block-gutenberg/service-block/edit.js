import { __ } from '@wordpress/i18n';
import { 
	useBlockProps, 
	RichText, 
	InspectorControls 
} from '@wordpress/block-editor';
import { 
	PanelBody, 
	TextControl, 
	TextareaControl,
	CheckboxControl,
	Placeholder,
	Spinner
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit( { attributes, setAttributes } ) {
	const { title, description, serviceIds } = attributes;

	const blockProps = useBlockProps( {
		className: 'block-service editor-view',
	} );

	// Fetch services posts
	const services = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords( 'postType', 'service', {
			per_page: -1,
			status: 'publish',
		} );
	}, [] );

	const toggleService = ( id ) => {
		const newIds = [ ...serviceIds ];
		if ( newIds.includes( id ) ) {
			const index = newIds.indexOf( id );
			newIds.splice( index, 1 );
		} else {
			newIds.push( id );
		}
		setAttributes( { serviceIds: newIds } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cấu hình Dịch vụ', 'laca' ) }>
					<TextControl
						label={ __( 'Tiêu đề', 'laca' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
					<TextareaControl
						label={ __( 'Mô tả ngắn', 'laca' ) }
						value={ description }
						onChange={ ( value ) => setAttributes( { description: value } ) }
						rows={ 4 }
					/>
					<hr style={{ margin: '20px 0' }} />
					<p><strong>{ __( 'Chọn dịch vụ hiển thị:', 'laca' ) }</strong></p>
					{ ! services ? (
						<Spinner />
					) : (
						<div style={{ maxHeight: '300px', overflowY: 'auto' }}>
							{ services.map( ( service ) => (
								<CheckboxControl
									key={ service.id }
									label={ service.title.rendered }
									checked={ serviceIds.includes( service.id ) }
									onChange={ () => toggleService( service.id ) }
								/>
							) ) }
						</div>
					) }
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="container">
					<RichText
						tagName="h2"
						className="block-title block-title-scroll"
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						placeholder={ __( 'Nhập tiêu đề...', 'laca' ) }
					/>
					<RichText
						tagName="div"
						className="block-desc"
						value={ description }
						multiline="p"
						onChange={ ( value ) => setAttributes( { description: value } ) }
						placeholder={ __( 'Nhập mô tả dịch vụ...', 'laca' ) }
					/>
					
					{ serviceIds.length === 0 ? (
						<Placeholder 
							icon="megaphone" 
							label={ __( 'Laca Service', 'laca' ) }
						>
							{ __( 'Vui lòng chọn các dịch vụ trong thanh cấu hình bên phải.', 'laca' ) }
						</Placeholder>
					) : (
						<div className="block-service__list">
							{ ! services ? (
								<Spinner />
							) : (
								services
									.filter( s => serviceIds.includes( s.id ) )
									.map( ( service ) => (
										<div key={ service.id } className="block-service__item">
											<div className="item__link">
												<span className="item__icon">{ service.title.rendered.charAt(0) }</span>
												<h3 className="item__title">{ service.title.rendered }</h3>
												<div className="item__desc">
													{ service.excerpt.rendered.replace(/<[^>]*>?/gm, '') || __( 'Chưa có mô tả...', 'laca' ) }
												</div>
											</div>
										</div>
									) )
							) }
						</div>
					) }
				</div>
			</section>
		</>
	);
}
