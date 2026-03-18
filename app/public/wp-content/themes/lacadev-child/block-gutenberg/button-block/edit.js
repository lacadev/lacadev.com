import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const {
		text,
		url,
		style,
		alignment,
		marginTop,
		marginBottom,
		target,
		fullWidth,
	} = attributes;

	const blockProps = useBlockProps( {
		className: `block-button align-${ alignment } ${
			fullWidth ? 'is-full-width' : ''
		} editor-view`,
		style: {
			marginTop: `${ marginTop }px`,
			marginBottom: `${ marginBottom }px`,
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cấu hình Button', 'laca' ) }>
					<TextControl
						label={ __( 'Nội dung nút', 'laca' ) }
						value={ text }
						onChange={ ( value ) => setAttributes( { text: value } ) }
					/>
					
					<div style={ { marginBottom: '15px' } }>
						<div style={ { display: 'block', marginBottom: '5px' } }>
							{ __( 'Liên kết (URL):', 'laca' ) }
						</div>
						<URLInput
							value={ url }
							onChange={ ( value ) => setAttributes( { url: value } ) }
						/>
					</div>

					<SelectControl
						label={ __( 'Kiểu dáng', 'laca' ) }
						value={ style }
						options={ [
							{ label: __( 'Tiêu chuẩn (Primary)', 'laca' ), value: 'primary' },
							{ label: __( 'Đường nét (Outline)', 'laca' ), value: 'outline' },
							{ label: __( 'Tối giản (Minimal)', 'laca' ), value: 'minimal' },
							{ label: __( 'Gương thần (Glass)', 'laca' ), value: 'glass' },
							{ label: __( 'Gạch chân (Text only)', 'laca' ), value: 'text-only' },
						] }
						onChange={ ( value ) => setAttributes( { style: value } ) }
					/>

					<SelectControl
						label={ __( 'Căn lề', 'laca' ) }
						value={ alignment }
						options={ [
							{ label: __( 'Trái', 'laca' ), value: 'left' },
							{ label: __( 'Giữa', 'laca' ), value: 'center' },
							{ label: __( 'Phải', 'laca' ), value: 'right' },
						] }
						onChange={ ( value ) => setAttributes( { alignment: value } ) }
					/>

					<SelectControl
						label={ __( 'Mở trong', 'laca' ) }
						value={ target }
						options={ [
							{ label: __( 'Trang hiện tại (_self)', 'laca' ), value: '_self' },
							{ label: __( 'Trang mới (_blank)', 'laca' ), value: '_blank' },
						] }
						onChange={ ( value ) => setAttributes( { target: value } ) }
					/>

					<ToggleControl
						label={ __( 'Độ rộng tối đa (Full width)', 'laca' ) }
						checked={ fullWidth }
						onChange={ ( value ) => setAttributes( { fullWidth: value } ) }
					/>

					<RangeControl
						label={ __( 'Khoảng cách trên (px)', 'laca' ) }
						value={ marginTop }
						onChange={ ( value ) => setAttributes( { marginTop: value } ) }
						min={ 0 }
						max={ 200 }
					/>

					<RangeControl
						label={ __( 'Khoảng cách dưới (px)', 'laca' ) }
						value={ marginBottom }
						onChange={ ( value ) => setAttributes( { marginBottom: value } ) }
						min={ 0 }
						max={ 200 }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className={ `btn btn-${ style } ${ fullWidth ? 'is-full-width' : '' }` }>
					{ style === 'minimal' && <span className="btn-icon"></span> }
					<span className="btn-text">{ text || __( 'Thêm tên nút...', 'laca' ) }</span>
				</div>
			</div>
		</>
	);
}
