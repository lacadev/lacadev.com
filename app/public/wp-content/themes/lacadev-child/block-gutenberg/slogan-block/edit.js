import {
	RichText,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
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
