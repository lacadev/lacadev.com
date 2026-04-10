import {
	RichText,
	useBlockProps,
	InspectorControls,
	useBlockEditContext,
} from '@wordpress/block-editor';
import previewImage from './preview.png';
import { PanelBody, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
	const { __unstableIsPreviewMode } = useBlockEditContext();
	if ( ( __unstableIsPreviewMode ?? false ) || ( attributes.__isPreview ?? false ) ) {
		return (
			<div style={ { width: '100%', lineHeight: 0 } }>
				<img src={ previewImage } alt="Block Preview" style={ { width: '100%', height: 'auto', display: 'block' } } />
			</div>
		);
	}


	const { slogan } = attributes;

	const blockProps = useBlockProps( {
		className: 'block-slogan editor-view',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cấu hình Slogan', 'laca' ) }>
					<TextareaControl
						label={ __( 'Nội dung Slogan (Hỗ trợ HTML)', 'laca' ) }
						value={ slogan }
						onChange={ ( value ) =>
							setAttributes( { slogan: value } )
						}
						help={ __(
							'Bạn có thể nhập thẻ <span> để tạo hiệu ứng outline. Ví dụ: La Cà <span>Dev</span>',
							'laca'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<section { ...blockProps }>
				<RichText
					tagName="div"
					className="slogan"
					value={ slogan }
					onChange={ ( value ) => setAttributes( { slogan: value } ) }
					placeholder={ __( 'Nhập slogan của bạn…', 'laca' ) }
					multiline={ false }
				/>
			</section>
		</>
	);
}
