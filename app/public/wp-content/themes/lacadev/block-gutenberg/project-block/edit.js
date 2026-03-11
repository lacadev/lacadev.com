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
	SelectControl,
	RangeControl,
	CheckboxControl,
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
		categoryIds,
		orderBy,
		countDesktop,
		countMobile,
		buttonText,
		buttonUrl,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'laca-project-block editor-view',
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

	// Back-compat: old `categoryIds`
	const effectiveTermIds =
		termIds && termIds.length > 0 ? termIds : categoryIds || [];

	const maxCount = Math.max( countDesktop, countMobile );

	const { displayPosts, isLoading } = useSelect(
		( select ) => {
			const { getEntityRecords, isResolving: isResolvingSelector } = select( 'core' );
			const query = {
				per_page: maxCount,
				status: 'publish',
				_embed: true,
			};

			if ( taxonomy && effectiveTermIds && effectiveTermIds.length > 0 ) {
				query[ taxonomyRestBase ] = effectiveTermIds.map(id => String(id)).join(',');
			}

			const records = getEntityRecords( 'postType', postType, query );
			const resolving = isResolvingSelector( 'getEntityRecords', [ 'postType', postType, query ] );
			
			return {
				displayPosts: records || [],
				isLoading: resolving,
			};
		},
		[ maxCount, effectiveTermIds, taxonomy, taxonomyRestBase, postType, orderBy ]
	);

	const toggleCategory = ( id ) => {
		const newList = [ ...effectiveTermIds ];
		if ( newList.includes( id ) ) {
			const index = newList.indexOf( id );
			newList.splice( index, 1 );
		} else {
			newList.push( id );
		}
		setAttributes( { termIds: newList, categoryIds: [] } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cấu hình Project Block', 'laca' ) }>
					<SelectControl
						label={ __( 'Post Type', 'laca' ) }
						value={ postType }
						options={ postTypes }
						onChange={ ( value ) => {
							setAttributes( {
								postType: value,
								taxonomy: '',
								termIds: [],
								categoryIds: [],
							} );
						} }
					/>
					<SelectControl
						label={ __( 'Taxonomy (Tab)', 'laca' ) }
						value={ taxonomy }
						options={ [
							{ label: __( 'Không dùng tab', 'laca' ), value: '' },
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
					
					<p><strong>{ __( 'Chọn danh mục hiển thị (Tab)', 'laca' ) }</strong></p>
					{ ! taxonomy ? (
						<p>{ __( 'Chọn taxonomy để bật tab.', 'laca' ) }</p>
					) : ! terms ? (
						<Spinner />
					) : (
						<div style={ { maxHeight: '200px', overflowY: 'auto', border: '1px solid #ddd', padding: '10px', marginBottom: '15px' } }>
							{ terms.map( ( cat ) => (
								<CheckboxControl
									key={ cat.id }
									label={ cat.name }
									checked={ effectiveTermIds.includes( cat.id ) }
									onChange={ () => toggleCategory( cat.id ) }
								/>
							) ) }
						</div>
					) }

					<SelectControl
						label={ __( 'Sắp xếp theo', 'laca' ) }
						value={ orderBy }
						options={ [
							{ label: __( 'Mới nhất', 'laca' ), value: 'date' },
							{ label: __( 'Ngẫu nhiên', 'laca' ), value: 'rand' },
							{ label: __( 'Dự án thực tế', 'laca' ), value: 'hand_made' },
						] }
						onChange={ ( value ) => setAttributes( { orderBy: value } ) }
					/>

					<RangeControl
						label={ __( 'Số lượng hiển thị (Desktop)', 'laca' ) }
						value={ countDesktop }
						onChange={ ( value ) =>
							setAttributes( { countDesktop: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
                    
					<RangeControl
						label={ __( 'Số lượng hiển thị (Mobile)', 'laca' ) }
						value={ countMobile }
						onChange={ ( value ) =>
							setAttributes( { countMobile: value } )
						}
						min={ 1 }
						max={ 20 }
					/>

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
						<div style={ { display: 'block', marginBottom: '5px' } }>
							{ __( 'Nhập URL:', 'laca' ) }
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
				<div className="laca-project-block__container container">
					{ ( title || description ) && (
						<div className="laca-project-block__header">
							{ title && (
								<RichText
									tagName="h2"
									className="laca-project-block__title"
									value={ title }
									onChange={ ( value ) =>
										setAttributes( { title: value } )
									}
									placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
								/>
							) }
							{ description && (
								<RichText
									tagName="div"
									className="laca-project-block__desc"
									value={ description }
									onChange={ ( value ) =>
										setAttributes( { description: value } )
									}
									placeholder={ __( 'Nhập mô tả…', 'laca' ) }
								/>
							) }
						</div>
					) }

					{ taxonomy && effectiveTermIds && effectiveTermIds.length > 0 && (
						<div className="laca-project-block__tabs">
							<div className="tab-item is-active">{ __( 'All', 'laca' ) }</div>
							{ terms && terms.filter(c => effectiveTermIds.includes(c.id)).map(cat => (
								<div key={cat.id} className="tab-item">{cat.name}</div>
							))}
						</div>
					)}

					<div className="laca-project-block__grid">
						{ isLoading ? (
							<div className="laca-editor-placeholder">
								<Spinner />
								<p>{ __( 'Đang tải dự án...', 'laca' ) }</p>
							</div>
						) : (
							<>
								{ displayPosts.length === 0 && (
									<div className="laca-editor-placeholder">
										<p>{ __( 'Không tìm thấy dự án nào. Vui lòng kiểm tra lại danh mục hoặc trạng thái bài viết.', 'laca' ) }</p>
									</div>
								) }
								
								{ displayPosts.map( ( post, index ) => {
									let itemClass = 'laca-project-block__item';
									if ( index + 1 > countDesktop ) {
										itemClass += ' hidden-on-desktop';
									}
									if ( index + 1 > countMobile ) {
										itemClass += ' hidden-on-mobile';
									}

									const quickViewImgId = post.meta?.quick_view_img;

									return (
										<div key={ post.id } className={ itemClass }>
											<div className="laca-project-block__card-link" style={ { pointerEvents: 'none' } }>
												<div className="laca-project-block__image-wrap">
													{ post.featured_media ? (
														<PostImage id={ post.featured_media } />
													) : (
														<div className="laca-project-block__image-placeholder"></div>
													) }
													
													{ quickViewImgId && (
														<div className="laca-project-block__hover-img-wrap">
															<PostImage id={ quickViewImgId } isHover={true} />
														</div>
													) }
												</div>
											</div>
										</div>
									);
								} ) }
							</>
						) }
					</div>

					{ buttonText && (
						<div className="block-footer laca-project-block__footer">
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

function PostImage( { id, isHover = false } ) {
	const media = useSelect(
		( select ) => {
			// Carbon Fields might return a URL instead of ID if configured that way
			// We check if id is a number
			return isNaN(parseInt(id)) ? null : select( 'core' ).getMedia( id );
		},
		[ id ]
	);

	const className = isHover ? 'laca-project-block__hover-img' : 'laca-project-block__img';

	if ( ! media ) {
		// If id is a URL, use it directly
		if ( typeof id === 'string' && id.startsWith('http') ) {
			return <img src={ id } alt="" className={ className } />;
		}
		return <div className="laca-project-block__image-placeholder"></div>;
	}

	return (
		<img
			src={
				media.media_details?.sizes?.medium?.source_url ||
				media.source_url
			}
			alt={ media.alt_text }
			className={ className }
		/>
	);
}
