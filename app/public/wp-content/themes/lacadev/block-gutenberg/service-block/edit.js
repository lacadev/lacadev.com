import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	CheckboxControl,
	SelectControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit( { attributes, setAttributes } ) {
	const {
		postType,
		taxonomy,
		termIds,
		mode,
		postIds,
		serviceIds,
		title,
		description,
		buttonText,
		buttonUrl,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'block-service editor-view',
	} );

	const postTypes = useSelect( ( select ) => {
		const types = select( 'core' ).getPostTypes
			? select( 'core' ).getPostTypes( { per_page: -1 } )
			: [];
		return ( types || [] )
			.filter( ( t ) => t.viewable )
			.map( ( t ) => ( {
				label: t.labels?.singular_name || t.name,
				value: t.slug,
			} ) );
	}, [] );

	const taxonomies = useSelect( ( select ) => {
		const list = select( 'core' ).getTaxonomies
			? select( 'core' ).getTaxonomies( { per_page: -1 } )
			: [];
		return ( list || [] )
			.filter(
				( t ) =>
					Array.isArray( t.types ) && t.types.includes( postType )
			)
			.map( ( t ) => ( {
				label: t.labels?.singular_name || t.name,
				value: t.slug,
				restBase: t.rest_base || t.slug,
			} ) );
	}, [ postType ] );

	const selectedTax = taxonomies.find( ( t ) => t.value === taxonomy );
	const taxonomyRestBase = selectedTax?.restBase || taxonomy;

	const terms = useSelect( ( select ) => {
		if ( ! taxonomy ) {
			return [];
		}
		return select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, {
			per_page: -1,
			hide_empty: true,
		} );
	}, [ taxonomy ] );

	// Back-compat: old `serviceIds` maps to new `postIds`
	const effectivePostIds =
		postIds && postIds.length > 0 ? postIds : serviceIds || [];

	const posts = useSelect(
		( select ) => {
			if ( mode === 'manual' ) {
				return select( 'core' ).getEntityRecords( 'postType', postType, {
					per_page: 50,
					status: 'publish',
				} );
			}

			const query = {
				per_page: 50,
				status: 'publish',
			};

			if ( taxonomy && termIds && termIds.length > 0 ) {
				query[ taxonomyRestBase ] = termIds
					.map( ( id ) => String( id ) )
					.join( ',' );
			}

			return select( 'core' ).getEntityRecords( 'postType', postType, query );
		},
		[ postType, mode, taxonomy, termIds, taxonomyRestBase ]
	);

	const toggleItem = ( list, id ) => {
		const newList = [ ...list ];
		if ( newList.includes( id ) ) {
			const index = newList.indexOf( id );
			newList.splice( index, 1 );
		} else {
			newList.push( id );
		}
		return newList;
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cấu hình Dịch vụ', 'laca' ) }>
					<SelectControl
						label={ __( 'Post Type', 'laca' ) }
						value={ postType }
						options={ postTypes }
						onChange={ ( value ) => {
							setAttributes( {
								postType: value,
								taxonomy: '',
								termIds: [],
								mode: 'manual',
								postIds: [],
								serviceIds: [],
							} );
						} }
					/>
					<SelectControl
						label={ __( 'Chế độ hiển thị', 'laca' ) }
						value={ mode }
						options={ [
							{ label: __( 'Thủ công (Manual)', 'laca' ), value: 'manual' },
							{ label: __( 'Tự động (Auto)', 'laca' ), value: 'auto' },
						] }
						onChange={ ( value ) =>
							setAttributes( {
								mode: value,
								postIds: [],
								serviceIds: [],
							} )
						}
					/>
					<SelectControl
						label={ __( 'Taxonomy (lọc)', 'laca' ) }
						value={ taxonomy }
						options={ [
							{ label: __( 'Không lọc', 'laca' ), value: '' },
							...taxonomies.map( ( t ) => ( {
								label: t.label,
								value: t.value,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( {
								taxonomy: value,
								termIds: [],
							} )
						}
					/>
					{ mode === 'auto' && taxonomy && (
						<>
							<p><strong>{ __( 'Lọc theo taxonomy:', 'laca' ) }</strong></p>
							{ ! terms ? (
								<Spinner />
							) : (
								<div style={ { maxHeight: '200px', overflowY: 'auto', border: '1px solid #ddd', padding: '10px', marginBottom: '15px' } }>
									{ terms.map( ( term ) => (
										<CheckboxControl
											key={ term.id }
											label={ term.name }
											checked={ termIds.includes( term.id ) }
											onChange={ () =>
												setAttributes( {
													termIds: toggleItem( termIds, term.id ),
												} )
											}
										/>
									) ) }
								</div>
							) }
						</>
					) }
					<TextControl
						label={ __( 'Tiêu đề', 'laca' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
					/>
					<TextareaControl
						label={ __( 'Mô tả block', 'laca' ) }
						value={ description }
						onChange={ ( value ) =>
							setAttributes( { description: value } )
						}
						rows={ 3 }
					/>
					<hr style={ { margin: '20px 0' } } />
					<p>
						<strong>{ __( 'Cấu hình Button:', 'laca' ) }</strong>
					</p>
					<TextControl
						label={ __( 'Nội dung button', 'laca' ) }
						value={ buttonText }
						onChange={ ( value ) =>
							setAttributes( { buttonText: value } )
						}
					/>
					<div style={ { marginBottom: '15px' } }>
						<div
							style={ { display: 'block', marginBottom: '5px' } }
						>
							{ __(
								'Nhập URL (chọn page hoặc nhập thủ công):',
								'laca'
							) }
						</div>
						<URLInput
							value={ buttonUrl }
							onChange={ ( value ) =>
								setAttributes( { buttonUrl: value } )
							}
						/>
					</div>
					<hr style={ { margin: '20px 0' } } />
					<p>
						<strong>
							{ __( 'Chọn nội dung hiển thị:', 'laca' ) }
						</strong>
					</p>
					{ ! posts ? (
						<Spinner />
					) : (
						<div
							style={ { maxHeight: '300px', overflowY: 'auto' } }
						>
							{ posts.map( ( item ) => (
								<CheckboxControl
									key={ item.id }
									label={ item.title.rendered }
									checked={ effectivePostIds.includes(
										item.id
									) }
									onChange={ () =>
										setAttributes( {
											postIds: toggleItem( effectivePostIds, item.id ),
											serviceIds: [],
										} )
									}
								/>
							) ) }
						</div>
					) }
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="container">
					<div className="block-header">
						<RichText
							tagName="h2"
							className="block-title block-title-scroll"
							value={ title }
							onChange={ ( value ) =>
								setAttributes( { title: value } )
							}
							placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
						/>
						<RichText
							tagName="div"
							className="block-desc"
							value={ description }
							onChange={ ( value ) =>
								setAttributes( { description: value } )
							}
							placeholder={ __( 'Nhập mô tả…', 'laca' ) }
						/>
					</div>

					{ effectivePostIds.length === 0 ? (
						<Placeholder
							icon="megaphone"
							label={ __( 'Laca Service', 'laca' ) }
						>
							{ __(
								'Vui lòng chọn các dịch vụ trong thanh cấu hình bên phải.',
								'laca'
							) }
						</Placeholder>
					) : (
						<div className="block-service__list">
							{ ! posts ? (
								<Spinner />
							) : (
								posts
									.filter( ( s ) =>
										effectivePostIds.includes( s.id )
									)
									.map( ( service ) => (
										<div
											key={ service.id }
											className="block-service__item"
										>
											<div className="item__link">
												<span className="item__icon">
													{ service.title.rendered.charAt(
														0
													) }
												</span>
												<h3 className="item__title">
													{ service.title.rendered }
												</h3>
												<div className="item__desc">
													{ service.excerpt.rendered.replace(
														/<[^>]*>?/gm,
														''
													) ||
														__(
															'Chưa có mô tả…',
															'laca'
														) }
												</div>
											</div>
										</div>
									) )
							) }
						</div>
					) }

					{ buttonText && (
						<div
							className="block-footer"
							style={ { marginTop: '5rem', textAlign: 'center' } }
						>
							<div
								className="btn btn-minimal"
								style={ { display: 'inline-flex' } }
							>
								<span className="btn-icon">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="4"></line><polyline points="10 4 20 4 20 14"></polyline></svg>
								</span>
								<span className="btn-text">{ buttonText }</span>
							</div>
						</div>
					) }
				</div>
			</section>
		</>
	);
}
