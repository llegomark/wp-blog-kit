<?php
/**
 * Breadcrumbs: one trail used by both the visible "Breadcrumbs" block and the BreadcrumbList
 * structured data, so the markup always describes what visitors see.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * The breadcrumb trail for the current page: Home › (Blog ›) Category › Post. The block and the
 * structured data both ask for it, so it is worked out once per main query (a new query, such as a
 * test's next page, gets its own).
 *
 * @return array<int,array{name:string,url:string}>
 */
function tala_seo_breadcrumb_trail() {
	global $wp_query;
	/**
	 * Trails by main query.
	 *
	 * @var WeakMap<WP_Query, array<int,array{name:string,url:string}>> $trails
	 */
	static $trails = new WeakMap();
	if ( ! $wp_query instanceof WP_Query ) {
		return tala_seo_build_breadcrumb_trail();
	}
	$trails[ $wp_query ] ??= tala_seo_build_breadcrumb_trail();
	return $trails[ $wp_query ];
}

/**
 * What the page being served has done with its breadcrumbs: whether the Breadcrumbs block showed the
 * trail, and whether the trail's structured data is printed. The structured data describes the
 * trail readers see, so a page (or a theme) without the block gets none.
 *
 * @param string $event 'shown' or 'printed' to record it; '' only to read.
 * @return array{shown: bool, printed: bool}
 */
function tala_seo_breadcrumbs_state( string $event = '' ): array {
	global $wp_query;
	/**
	 * States by main query.
	 *
	 * @var WeakMap<WP_Query, array{shown: bool, printed: bool}> $states
	 */
	static $states = new WeakMap();
	if ( ! $wp_query instanceof WP_Query ) {
		return array(
			'shown'   => false,
			'printed' => false,
		);
	}
	$states[ $wp_query ] ??= array(
		'shown'   => false,
		'printed' => false,
	);
	if ( 'shown' === $event || 'printed' === $event ) {
		$state               = $states[ $wp_query ];
		$state[ $event ]     = true;
		$states[ $wp_query ] = $state;
	}
	return $states[ $wp_query ];
}

/**
 * Works out the breadcrumb trail for the current page.
 *
 * @return array<int,array{name:string,url:string}>
 */
function tala_seo_build_breadcrumb_trail() {
	$trail = array();
	if ( is_front_page() || is_404() ) {
		return $trail;
	}

	$trail[]    = array(
		'name' => __( 'Home', 'tala-seo' ),
		'url'  => home_url( '/' ),
	);
	$posts_page = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_for_posts' ) : 0;
	$queried    = get_queried_object();

	if ( is_home() && $posts_page ) {
		$trail[] = tala_seo_crumb( get_the_title( $posts_page ), get_permalink( $posts_page ) );
	} elseif ( is_singular( 'post' ) && $queried instanceof WP_Post ) {
		if ( $posts_page ) {
			$trail[] = tala_seo_crumb( get_the_title( $posts_page ), get_permalink( $posts_page ) );
		}
		$category = tala_seo_primary_category( $queried->ID );
		if ( $category ) {
			$trail   = array_merge( $trail, tala_seo_term_ancestor_crumbs( $category ) );
			$trail[] = tala_seo_crumb( $category->name, get_term_link( $category ) );
		}
		$trail[] = tala_seo_crumb( get_the_title( $queried ), get_permalink( $queried ) );
	} elseif ( is_page() && $queried instanceof WP_Post ) {
		foreach ( array_reverse( get_post_ancestors( $queried ) ) as $ancestor ) {
			// A private or draft parent page isn't shown to visitors, so neither is its title here.
			if ( is_post_publicly_viewable( $ancestor ) ) {
				$trail[] = tala_seo_crumb( get_the_title( $ancestor ), get_permalink( $ancestor ) );
			}
		}
		$trail[] = tala_seo_crumb( get_the_title( $queried ), get_permalink( $queried ) );
	} elseif ( is_singular() ) {
		$trail[] = tala_seo_crumb( get_the_title(), get_permalink() );
	} elseif ( ( is_category() || is_tag() || is_tax() ) && $queried instanceof WP_Term ) {
		if ( is_taxonomy_hierarchical( $queried->taxonomy ) ) {
			$trail = array_merge( $trail, tala_seo_term_ancestor_crumbs( $queried ) );
		}
		$trail[] = tala_seo_crumb( $queried->name, get_term_link( $queried ) );
	} elseif ( is_author() ) {
		$id      = get_queried_object_id();
		$trail[] = tala_seo_crumb( get_the_author_meta( 'display_name', $id ), get_author_posts_url( $id ) );
	} elseif ( is_search() ) {
		/* translators: %s: search terms. */
		$trail[] = tala_seo_crumb( sprintf( __( 'Search results for “%s”', 'tala-seo' ), get_search_query( false ) ), '' );
	}

	return $trail;
}

/**
 * One breadcrumb.
 *
 * @param string                $name Label (may contain HTML entities).
 * @param string|false|WP_Error $url  URL; false or an error (no link available) becomes ''.
 * @return array{name:string,url:string}
 */
function tala_seo_crumb( $name, $url ) {
	return array(
		'name' => tala_seo_clean_text( $name ),
		'url'  => is_string( $url ) ? $url : '',
	);
}

/**
 * Crumbs for the parent terms of a term, top level first.
 *
 * @param WP_Term $term Term.
 * @return array<int, array{name:string,url:string}>
 */
function tala_seo_term_ancestor_crumbs( $term ) {
	$crumbs = array();
	foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor ) {
		$parent = get_term( $ancestor, $term->taxonomy );
		if ( $parent instanceof WP_Term ) {
			$crumbs[] = tala_seo_crumb( $parent->name, get_term_link( $parent ) );
		}
	}
	return $crumbs;
}

/**
 * The category a post belongs to "most": the primary category chosen in the SEO panel, else one
 * chosen in Yoast SEO, Rank Math, The SEO Framework or SEOPress, else the first category that isn't
 * the default "Uncategorized" one. A choice only counts while the post is still in that category.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function tala_seo_primary_category( $post_id ) {
	$chosen = tala_seo_chosen_primary_category( $post_id );
	if ( $chosen ) {
		return $chosen;
	}
	$categories = get_the_category( $post_id );
	$default    = (int) get_option( 'default_category' );
	return array_find( $categories, static fn( $category ): bool => $category->term_id !== $default ) ?? array_first( $categories );
}

/**
 * The primary category someone chose for a post (here or in a previous SEO plugin), or null.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function tala_seo_chosen_primary_category( $post_id ) {
	$keys = array( TALA_SEO_PRIMARY_CATEGORY, '_yoast_wpseo_primary_category', 'rank_math_primary_category', '_primary_term_category', '_seopress_robots_primary_cat' );
	foreach ( $keys as $key ) {
		$id = (int) get_post_meta( $post_id, $key, true );
		if ( $id && has_term( $id, 'category', $post_id ) ) {
			$term = get_term( $id, 'category' );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}
	}
	return null;
}

add_filter( 'post_link_category', 'tala_seo_permalink_category', 10, 3 );

/**
 * On sites whose permalinks contain %category%, a chosen primary category is the one in the address
 * too, so the URL and the breadcrumbs agree. That includes a choice made in Yoast SEO, Rank Math,
 * The SEO Framework or SEOPress, which put it in the address as well, so addresses survive the
 * switch. Without a choice WordPress's own pick stays, so no existing address changes.
 *
 * @param WP_Term   $category The category WordPress picked (the one with the lowest ID).
 * @param WP_Term[] $cats     The post's categories.
 * @param WP_Post   $post     Post.
 * @return WP_Term
 */
function tala_seo_permalink_category( $category, $cats, $post ) {
	return tala_seo_chosen_primary_category( $post->ID ) ?? $category;
}

add_action( 'init', 'tala_seo_register_breadcrumbs_block' );

/**
 * Registers the Breadcrumbs block (server-rendered). A theme shows it by placing it in its templates,
 * as Tala Blog does; with any other theme, add it in Appearance → Editor.
 */
function tala_seo_register_breadcrumbs_block(): void {
	register_block_type(
		dirname( __DIR__ ) . '/blocks/breadcrumbs',
		array( 'render_callback' => 'tala_seo_render_breadcrumbs' )
	);
}

/**
 * Renders the visible breadcrumb trail.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $content    Block content (none).
 * @param WP_Block|null        $block      Block.
 * @return string
 */
function tala_seo_render_breadcrumbs( $attributes = array(), $content = '', $block = null ) {
	// Hidden with block visibility: WordPress drops the output after this runs, so show no trail
	// here either, and the page gets no BreadcrumbList for it.
	$visibility = $block instanceof WP_Block ? ( $block->parsed_block['attrs']['metadata']['blockVisibility'] ?? null ) : null;
	if ( false === $visibility ) {
		return '';
	}
	// In the editor (block preview over REST) show an example trail.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$trail = array(
			array(
				'name' => __( 'Home', 'tala-seo' ),
				'url'  => '#',
			),
			array(
				'name' => __( 'Category', 'tala-seo' ),
				'url'  => '#',
			),
			array(
				'name' => __( 'Current page', 'tala-seo' ),
				'url'  => '',
			),
		);
	} else {
		$trail = tala_seo_breadcrumb_trail();
	}
	if ( count( $trail ) < 2 ) {
		return '';
	}
	if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		tala_seo_breadcrumbs_state( 'shown' );
	}

	$items = '';
	$last  = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		if ( $i === $last || '' === $crumb['url'] ) {
			$items .= sprintf( '<li aria-current="page">%s</li>', esc_html( $crumb['name'] ) );
		} else {
			$items .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( $crumb['url'] ), esc_html( $crumb['name'] ) );
		}
	}

	return sprintf(
		'<nav %s><ol>%s</ol></nav>',
		get_block_wrapper_attributes( array( 'aria-label' => __( 'Breadcrumbs', 'tala-seo' ) ) ),
		$items
	);
}
