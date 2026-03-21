import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	Button,
	Dashicon,
} from '@wordpress/components';

const S = {
	wrap: {
		padding: '8rem 2rem',
		overflow: 'hidden',
	},
	inner: {
		maxWidth: '1536px',
		margin: '0 auto',
	},
	header: {
		textAlign: 'center',
		marginBottom: '6rem',
	},
	subTitle: {
		display: 'block',
		fontSize: '0.75rem',
		fontWeight: '600',
		letterSpacing: '0.2em',
		textTransform: 'uppercase',
		color: 'var(--color-secondary, #6b7280)',
		marginBottom: '1rem',
	},
	title: {
		fontFamily: 'var(--font-headline, inherit)',
		fontSize: 'clamp(2.5rem, 4vw, 3rem)',
		fontWeight: '700',
		margin: '0',
	},
	stepsWrap: {
		position: 'relative',
		display: 'flex',
		flexDirection: 'row',
		gap: '0',
	},
	line: {
		position: 'absolute',
		top: '2.5rem', // tâm badge 5rem / 2
		left: '0',
		width: '100%',
		height: '1px',
		background: 'rgba(0,0,0,0.08)',
		zIndex: 0,
	},
	step: {
		position: 'relative',
		zIndex: 1,
		flex: 1,
		padding: '0 1rem',
	},
	badge: {
		width: '5rem',
		height: '5rem',
		borderRadius: '50%',
		background: '#ffffff',
		border: '1px solid rgba(0,0,0,0.08)',
		boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		marginBottom: '2rem',
	},
	num: {
		fontFamily: 'var(--font-headline, inherit)',
		fontSize: '2.25rem',
		fontWeight: '800',
		opacity: 0.2,
		lineHeight: 1,
	},
	stepTitle: {
		fontFamily: 'var(--font-headline, inherit)',
		fontSize: '1.25rem',
		fontWeight: '700',
		marginBottom: '0.75rem',
		marginTop: 0,
	},
	stepDesc: {
		fontSize: '0.875rem',
		lineHeight: 1.6,
		opacity: 0.65,
		margin: 0,
	},
};

export default function Edit( { attributes, setAttributes } ) {
	const { subTitle, title, steps, backgroundColor } = attributes;

	const formatNum = ( index ) => String( index + 1 ).padStart( 2, '0' );

	const updateStep = ( index, key, value ) => {
		const newSteps = [ ...steps ];
		newSteps[ index ] = { ...newSteps[ index ], [ key ]: value };
		setAttributes( { steps: newSteps } );
	};

	const addStep = () =>
		setAttributes( {
			steps: [ ...steps, { title: 'New Step', desc: 'Step description here.' } ],
		} );

	const removeStep = ( index ) =>
		setAttributes( { steps: steps.filter( ( _, i ) => i !== index ) } );

	const moveStep = ( index, direction ) => {
		const newSteps = [ ...steps ];
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if ( newIndex >= 0 && newIndex < newSteps.length ) {
			[ newSteps[ index ], newSteps[ newIndex ] ] = [ newSteps[ newIndex ], newSteps[ index ] ];
			setAttributes( { steps: newSteps } );
		}
	};

	const onDragStart = ( e, index ) => e.dataTransfer.setData( 'dragIndex', String( index ) );
	const onDragOver  = ( e ) => e.preventDefault();
	const onDrop = ( e, dropIndex ) => {
		const dragIndex = parseInt( e.dataTransfer.getData( 'dragIndex' ), 10 );
		if ( isNaN( dragIndex ) || dragIndex === dropIndex ) return;
		const newSteps = [ ...steps ];
		const [ dragged ] = newSteps.splice( dragIndex, 1 );
		newSteps.splice( dropIndex, 0, dragged );
		setAttributes( { steps: newSteps } );
	};

	const blockProps = useBlockProps( {
		className: 'py-32 px-8 overflow-hidden',
		style: {
			...S.wrap,
			...(backgroundColor ? { backgroundColor } : {} ),
		},
	} );

	return (
		<>
			{ /* ── Inspector Controls ── */ }
			<InspectorControls>
				<PanelColorSettings
					title={ __( 'Màu nền', 'laca' ) }
					initialOpen={ true }
					colorSettings={ [
						{
							value: backgroundColor,
							onChange: ( val ) => setAttributes( { backgroundColor: val || '' } ),
							label: __( 'Background color', 'laca' ),
						},
					] }
				/>
				<PanelBody title={ __( 'Header', 'laca' ) }>
					<TextControl
						label={ __( 'Sub tiêu đề (nhỏ phía trên)', 'laca' ) }
						value={ subTitle }
						onChange={ ( val ) => setAttributes( { subTitle: val } ) }
					/>
					<TextControl
						label={ __( 'Tiêu đề chính', 'laca' ) }
						value={ title }
						onChange={ ( val ) => setAttributes( { title: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Các bước — Steps', 'laca' ) }>
					<p style={ { fontSize: '12px', color: '#666', marginBottom: '12px', fontStyle: 'italic' } }>
						{ __( 'Số thứ tự tự động sinh. Kéo thả để đổi thứ tự.', 'laca' ) }
					</p>
					{ steps.map( ( step, index ) => (
						<div
							key={ index }
							draggable
							onDragStart={ ( e ) => onDragStart( e, index ) }
							onDragOver={ onDragOver }
							onDrop={ ( e ) => onDrop( e, index ) }
							style={ { padding: '14px', border: '1px solid #ddd', marginBottom: '12px', borderRadius: '8px', background: '#fafafa', cursor: 'grab' } }
						>
							<div style={ { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' } }>
								<span style={ { fontSize: '16px', fontWeight: '700', color: '#bbb', fontFamily: 'monospace' } }>
									{ formatNum( index ) }
								</span>
								<div style={ { display: 'flex', gap: '4px' } }>
									<Dashicon icon="move" style={ { opacity: 0.3 } } />
									<Button isSmall icon="arrow-up-alt2"   onClick={ () => moveStep( index, 'up' ) }   disabled={ index === 0 } />
									<Button isSmall icon="arrow-down-alt2" onClick={ () => moveStep( index, 'down' ) } disabled={ index === steps.length - 1 } />
									<Button isSmall isDestructive icon="no-alt" onClick={ () => removeStep( index ) } />
								</div>
							</div>
							<TextControl    label={ __( 'Tiêu đề bước', 'laca' ) } value={ step.title } onChange={ ( v ) => updateStep( index, 'title', v ) } />
							<TextareaControl label={ __( 'Mô tả bước', 'laca' ) }  value={ step.desc }  onChange={ ( v ) => updateStep( index, 'desc',  v ) } rows={ 2 } />
						</div>
					) ) }
					<Button variant="primary" onClick={ addStep } style={ { width: '100%', justifyContent: 'center' } }>
						{ __( '+ Thêm bước', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			{ /* ── Editor Preview ── */ }
			<section { ...blockProps }>
				<div style={ S.inner }>

					<div style={ S.header }>
						<RichText tagName="span" style={ S.subTitle }
							value={ subTitle }
							onChange={ ( v ) => setAttributes( { subTitle: v } ) }
							placeholder={ __( 'Sub tiêu đề…', 'laca' ) }
						/>
						<RichText tagName="h2" style={ S.title }
							value={ title }
							onChange={ ( v ) => setAttributes( { title: v } ) }
							placeholder={ __( 'Tiêu đề…', 'laca' ) }
						/>
					</div>

					<div style={ S.stepsWrap }>
						<div style={ S.line } />

						{ steps.map( ( step, index ) => (
							<div key={ index } style={ S.step }>
								<div style={ S.badge }>
									<span style={ S.num }>{ formatNum( index ) }</span>
								</div>
								<RichText tagName="h4" style={ S.stepTitle }
									value={ step.title }
									onChange={ ( v ) => updateStep( index, 'title', v ) }
									placeholder={ __( 'Tiêu đề bước…', 'laca' ) }
								/>
								<RichText tagName="p" style={ S.stepDesc }
									value={ step.desc }
									onChange={ ( v ) => updateStep( index, 'desc', v ) }
									placeholder={ __( 'Mô tả…', 'laca' ) }
								/>
							</div>
						) ) }
					</div>

				</div>
			</section>
		</>
	);
}
