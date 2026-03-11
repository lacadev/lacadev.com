import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
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
	SelectControl,
	CheckboxControl,
	RangeControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit( { attributes, setAttributes } ) {
	const {
		postType,
		taxonomy,
		termIds,
		title,
		description,
		mode,
		orderBy,
		categoryIds,
		postIds,
		count,
		buttonText,
		buttonUrl,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'block-blog editor-view',
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

	// Back-compat: if old categoryIds exists and termIds is empty, reuse it
	const effectiveTermIds =
		termIds && termIds.length > 0 ? termIds : categoryIds || [];

	// Fetch posts based on mode
	const displayPosts = useSelect(
		( select ) => {
			if ( mode === 'manual' && ( ! postIds || postIds.length === 0 ) ) {
				return [];
			}

			const { getEntityRecords } = select( 'core' );
			const query = {
				per_page: mode === 'manual' ? -1 : count,
				status: 'publish',
				_embed: true, // For featured media
			};

			if ( mode === 'manual' ) {
				query.include = postIds;
				query.orderby = 'include';
			} else {
				if ( taxonomy && effectiveTermIds && effectiveTermIds.length > 0 ) {
					query[ taxonomyRestBase ] = effectiveTermIds
						.map( ( id ) => String( id ) )
						.join( ',' );
				}
				query.orderby = orderBy === 'rand' ? 'date' : orderBy;
			}

			return getEntityRecords( 'postType', postType, query );
		},
		[ mode, postIds, count, orderBy, taxonomy, taxonomyRestBase, postType, effectiveTermIds ]
	);

	const manualPosts = useSelect(
		( select ) => {
			return select( 'core' ).getEntityRecords( 'postType', postType, {
				per_page: 50,
				status: 'publish',
			} );
		},
		[ postType ]
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
				<PanelBody title={ __( 'Cấu hình Blog', 'laca' ) }>
					<SelectControl
						label={ __( 'Post Type', 'laca' ) }
						value={ postType }
						options={ postTypes }
						onChange={ ( value ) => {
							setAttributes( {
								postType: value,
								// Reset dependent filters when postType changes
								taxonomy: '',
								termIds: [],
								categoryIds: [],
								postIds: [],
							} );
						} }
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
								categoryIds: [],
							} )
						}
					/>
					<TextControl
						label={ __( 'Tiêu đề block', 'laca' ) }
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
					<hr />
					<SelectControl
						label={ __( 'Chế độ hiển thị', 'laca' ) }
						value={ mode }
						options={ [
							{
								label: __( 'Tự động (Auto)', 'laca' ),
								value: 'auto',
							},
							{
								label: __( 'Thủ công (Manual)', 'laca' ),
								value: 'manual',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { mode: value } )
						}
					/>

					{ mode === 'auto' && (
						<>
							<p>
								<strong>
									{ __( 'Lọc theo taxonomy:', 'laca' ) }
								</strong>
							</p>
							{ ! taxonomy ? (
								<p>{ __( 'Chọn taxonomy để bật bộ lọc.', 'laca' ) }</p>
							) : ! terms ? (
								<Spinner />
							) : (
								<div
									style={ {
										maxHeight: '150px',
										overflowY: 'auto',
										border: '1px solid #ddd',
										padding: '10px',
										marginBottom: '15px',
									} }
								>
									{ terms.map( ( term ) => (
										<CheckboxControl
											key={ term.id }
											label={ term.name }
											checked={ effectiveTermIds.includes(
												term.id
											) }
											onChange={ () =>
												setAttributes( {
													termIds: toggleItem(
														effectiveTermIds,
														term.id
													),
													// Clear legacy field to avoid conflicting sources
													categoryIds: [],
												} )
											}
										/>
									) ) }
								</div>
							) }

							<SelectControl
								label={ __( 'Sắp xếp theo', 'laca' ) }
								value={ orderBy }
								options={ [
									{
										label: __( 'Mới nhất', 'laca' ),
										value: 'date',
									},
									{
										label: __( 'Ngẫu nhiên', 'laca' ),
										value: 'rand',
									},
									{
										label: __(
											'Nhiều bình luận nhất (Proxy Popularity)',
											'laca'
										),
										value: 'comment_count',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { orderBy: value } )
								}
							/>
							<RangeControl
								label={ __( 'Số lượng bài viết', 'laca' ) }
								value={ count }
								onChange={ ( value ) =>
									setAttributes( { count: value } )
								}
								min={ 1 }
								max={ 12 }
							/>
						</>
					) }

					{ mode === 'manual' && (
						<div className="manual-selection">
							<p>
								<strong>
									{ __( 'Chọn nội dung hiển thị:', 'laca' ) }
								</strong>
							</p>
							{ ! manualPosts ? (
								<Spinner />
							) : (
								<div
									style={ {
										maxHeight: '300px',
										overflowY: 'auto',
										border: '1px solid #ddd',
										padding: '10px',
									} }
								>
									{ manualPosts.map( ( post ) => (
										<CheckboxControl
											key={ post.id }
											label={ post.title.rendered }
											checked={ postIds.includes(
												post.id
											) }
											onChange={ () =>
												setAttributes( {
													postIds: toggleItem(
														postIds,
														post.id
													),
												} )
											}
										/>
									) ) }
								</div>
							) }
						</div>
					) }
					<hr />
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
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="container">
					<div
						className="block-header"
						style={ { textAlign: 'center', marginBottom: '40px' } }
					>
						<RichText
							tagName="h2"
							className="block-title"
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

					<div className="blog-list">
						{ ! displayPosts && <Spinner /> }
						{ displayPosts &&
							displayPosts.length > 0 &&
							displayPosts.map( ( post ) => (
								<div key={ post.id } className="blog-card">
									<div
										className="card-link"
										style={ { pointerEvents: 'none' } }
									>
										<div className="card-image-wrap">
											{ post.featured_media ? (
												<PostImage
													id={ post.featured_media }
												/>
											) : (
												<div
													style={ {
														width: '100%',
														height: '220px',
														background: '#f0f0f0',
														borderRadius: '20px',
													} }
												></div>
											) }
										</div>
										<div className="card-body">
											<h3 className="card-title">
												{ decodeEntities(
													post.title.rendered
												) }
											</h3>
											<div className="card-meta">
												<span>
													{ __(
														'By Author',
														'laca'
													) }
												</span>
												<span>
													{ __( 'Just now', 'laca' ) }
												</span>
											</div>
										</div>
									</div>
								</div>
							) ) }
					</div>

					{ buttonText && (
						<div className="block-footer">
							<div className="btn btn-minimal">
								<span className="btn-icon"></span>
								<span className="btn-text">{ buttonText }</span>
							</div>
						</div>
					) }
				</div>
			</section>
		</>
	);
}

function PostImage( { id } ) {
	const media = useSelect(
		( select ) => select( 'core' ).getMedia( id ),
		[ id ]
	);
	if ( ! media ) {
		return <div className="card-image-placeholder"></div>;
	}
	return (
		<img
			src={
				media.media_details?.sizes?.medium?.source_url ||
				media.source_url
			}
			alt={ media.alt_text }
		/>
	);
}
