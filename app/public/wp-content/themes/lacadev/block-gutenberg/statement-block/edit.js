import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	useBlockEditContext,
} from '@wordpress/block-editor';
import previewImage from './preview.png';
import { PanelBody, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { __unstableIsPreviewMode } = useBlockEditContext();
	if ( ( __unstableIsPreviewMode ?? false ) || ( attributes.__isPreview ?? false ) ) {
		return (
			<div style={ { width: '100%', lineHeight: 0 } }>
				<img src={ previewImage } alt="Block Preview" style={ { width: '100%', height: 'auto', display: 'block' } } />
			</div>
		);
	}


	const { title, subtitle } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cài đặt Statement', 'laca' ) }>
					<TextControl
						label={ __( 'Phụ đề', 'laca' ) }
						value={ subtitle }
						onChange={ ( val ) =>
							setAttributes( { subtitle: val } )
						}
					/>
					<TextControl
						label={ __( 'Tuyên ngôn', 'laca' ) }
						value={ title }
						onChange={ ( val ) => setAttributes( { title: val } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'block-statement' } ) }>
				<div className="container">
					<RichText
						tagName="p"
						className="statement-subtitle"
						value={ subtitle }
						onChange={ ( val ) =>
							setAttributes( { subtitle: val } )
						}
						placeholder={ __( 'Nhập phụ đề…', 'laca' ) }
					/>
					<RichText
						tagName="h2"
						className="statement-title"
						value={ title }
						onChange={ ( val ) => setAttributes( { title: val } ) }
						placeholder={ __( 'Nhập tuyên ngôn…', 'laca' ) }
					/>
				</div>
			</div>
		</>
	);
}
