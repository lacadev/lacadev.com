import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
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
