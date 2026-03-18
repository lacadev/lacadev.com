import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	Button,
	Dashicon,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { title, description, steps } = attributes;

	const updateStep = ( index, key, value ) => {
		const newSteps = [ ...steps ];
		newSteps[ index ] = { ...newSteps[ index ], [ key ]: value };
		setAttributes( { steps: newSteps } );
	};

	const addStep = () => {
		const nextNum = ( steps.length + 1 ).toString().padStart( 2, '0' );
		setAttributes( {
			steps: [
				...steps,
				{ num: nextNum, title: 'New Step', desc: 'Description here' },
			],
		} );
	};

	const removeStep = ( index ) => {
		const newSteps = steps.filter( ( _, i ) => i !== index );
		setAttributes( { steps: newSteps } );
	};

	const moveStep = ( index, direction ) => {
		const newSteps = [ ...steps ];
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if ( newIndex >= 0 && newIndex < newSteps.length ) {
			[ newSteps[ index ], newSteps[ newIndex ] ] = [
				newSteps[ newIndex ],
				newSteps[ index ],
			];
			setAttributes( { steps: newSteps } );
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

		const newSteps = [ ...steps ];
		const draggedItem = newSteps[ dragIndex ];
		newSteps.splice( dragIndex, 1 );
		newSteps.splice( dropIndex, 0, draggedItem );
		setAttributes( { steps: newSteps } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cài đặt Quy trình', 'laca' ) }>
					<TextControl
						label={ __( 'Tiêu đề chính', 'laca' ) }
						value={ title }
						onChange={ ( val ) => setAttributes( { title: val } ) }
					/>
					<TextareaControl
						label={ __( 'Mô tả block', 'laca' ) }
						value={ description }
						onChange={ ( val ) =>
							setAttributes( { description: val } )
						}
						rows={ 3 }
					/>
					<hr style={ { margin: '20px 0' } } />
					<p
						style={ {
							fontSize: '12px',
							color: '#666',
							marginBottom: '15px',
							fontStyle: 'italic',
						} }
					>
						{ __(
							'* Kéo thả các ô bên dưới để đổi thứ tự các bước.',
							'laca'
						) }
					</p>
					{ steps.map( ( step, index ) => (
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
									onClick={ () => moveStep( index, 'up' ) }
									disabled={ index === 0 }
									icon="arrow-up-alt2"
								/>
								<Button
									isSmall
									onClick={ () => moveStep( index, 'down' ) }
									disabled={ index === steps.length - 1 }
									icon="arrow-down-alt2"
								/>
								<Button
									isDestructive
									isSmall
									onClick={ () => removeStep( index ) }
									icon="no-alt"
								/>
							</div>
							<TextControl
								label={ __( 'Số thứ tự', 'laca' ) }
								value={ step.num }
								onChange={ ( val ) =>
									updateStep( index, 'num', val )
								}
							/>
							<TextControl
								label={ __( 'Tiêu đề bước', 'laca' ) }
								value={ step.title }
								onChange={ ( val ) =>
									updateStep( index, 'title', val )
								}
							/>
							<TextareaControl
								label={ __( 'Mô tả bước', 'laca' ) }
								value={ step.desc }
								onChange={ ( val ) =>
									updateStep( index, 'desc', val )
								}
								rows={ 2 }
							/>
						</div>
					) ) }
					<Button
						isPrimary
						onClick={ addStep }
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( 'Thêm bước quy trình', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...useBlockProps( { className: 'block-process' } ) }>
				<div className="container">
					<div className="block-header">
						<RichText
							tagName="h2"
							className="block-title"
							value={ title }
							onChange={ ( val ) =>
								setAttributes( { title: val } )
							}
							placeholder={ __( 'Tiêu đề quy trình…', 'laca' ) }
						/>
						<RichText
							tagName="div"
							className="block-desc"
							value={ description }
							onChange={ ( val ) =>
								setAttributes( { description: val } )
							}
							placeholder={ __( 'Mô tả quy trình…', 'laca' ) }
						/>
					</div>

					<div className="process-grid">
						{ steps.map( ( step, index ) => (
							<div
								key={ index }
								className="process-item staggered-item"
							>
								<RichText
									tagName="div"
									className="process-num"
									value={ step.num }
									onChange={ ( val ) =>
										updateStep( index, 'num', val )
									}
								/>
								<div className="process-info">
									<RichText
										tagName="h3"
										className="process-step-title"
										value={ step.title }
										onChange={ ( val ) =>
											updateStep( index, 'title', val )
										}
										placeholder={ __(
											'Tiêu đề bước…',
											'laca'
										) }
									/>
									<RichText
										tagName="p"
										className="process-step-desc"
										value={ step.desc }
										onChange={ ( val ) =>
											updateStep( index, 'desc', val )
										}
										placeholder={ __(
											'Mô tả bước…',
											'laca'
										) }
									/>
								</div>
							</div>
						) ) }
					</div>
				</div>
			</section>
		</>
	);
}
