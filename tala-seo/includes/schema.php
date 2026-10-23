<?php
/**
 * JSON-LD structured data, following Google Search Central (checked October 2026):
 * - Home page only: WebSite (site name) and Organization/Person (description, logo, profiles).
 * - Posts: BlogPosting with headline, description, image in 16:9/4:3/1:1, ISO 8601 dates with
 *   timezone, and the author as a Person with name and url.
 * - Author archives: ProfilePage whose mainEntity is the Person.
 * - Breadcrumbs: BreadcrumbList matching the visible breadcrumb trail (two items or more), only on
 *   pages that show the Breadcrumbs block. A block theme renders the page before <head>, so the trail
 *   is in the graph there; a trail shown later (a classic theme's content) gets its own script at the
 *   end of the page.
 * - Posts and pages: VideoObject, Event, Course, FAQPage and HowTo for what the content shows
 *   (content-schema.php, event.php, course.php).
 * - Lists of posts that are all courses: the ItemList of Google's course list (course.php).
 * Not used on purpose: SearchAction (Google retired the sitelinks search box).
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'tala_seo_print_schema', 3 );

/**
 * Prints the JSON-LD graph for the current page.
 */
function tala_seo_print_schema(): void {
	$nodes = tala_seo_schema_nodes();
	if ( in_array( 'BreadcrumbList', array_column( $nodes, '@type' ), true ) ) {
		tala_seo_breadcrumbs_state( 'printed' );
	}
	tala_seo_print_json_ld( $nodes );
}

add_action( 'wp_footer', 'tala_seo_print_late_breadcrumbs' );

/**
 * The BreadcrumbList of a trail the page showed after <head> was printed: with a classic theme the
 * Breadcrumbs block renders with the content. (A block theme's trail is already in the graph.)
 */
function tala_seo_print_late_breadcrumbs(): void {
	if ( tala_seo_breadcrumbs_state()['printed'] || is_404() || is_search() || is_preview() ) {
		return;
	}
	$breadcrumbs = tala_seo_breadcrumb_node();
	if ( $breadcrumbs ) {
		tala_seo_breadcrumbs_state( 'printed' );
		tala_seo_print_json_ld( array( $breadcrumbs ) );
	}
}

/**
 * Prints structured data nodes as a JSON-LD script.
 *
 * @param array<int, array<string, mixed>> $nodes Nodes.
 */
function tala_seo_print_json_ld( array $nodes ): void {
	if ( ! $nodes ) {
		return;
	}
	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		),
		// JSON_HEX_TAG turns < and > into < / > so text can't close the script tag.
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
	);
	if ( $json ) {
		echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HTML-significant characters hex-escaped.
	}
}

/**
 * Structured data nodes for the current page.
 *
 * @return array<int, array<string, mixed>>
 */
function tala_seo_schema_nodes() {
	if ( is_404() || is_search() || is_preview() ) {
		return array();
	}
	$nodes = array();

	// The home page is its first page: page 2 of the post list is not where the site describes itself.
	if ( is_front_page() && ! is_paged() ) {
		$nodes[] = tala_seo_website_node();
		$nodes[] = tala_seo_publisher_node();
	}

	$queried = get_queried_object();
	if ( is_singular( 'post' ) && $queried instanceof WP_Post ) {
		$nodes[] = tala_seo_article_node( $queried );
	} elseif ( is_author() ) {
		$nodes[] = tala_seo_profile_page_node( get_queried_object_id() );
	}
	if ( is_singular( tala_seo_post_types() ) && $queried instanceof WP_Post ) {
		array_push( $nodes, ...tala_seo_content_nodes( $queried ) );
	} elseif ( ( is_home() || is_archive() ) && ! is_date() ) {
		$nodes[] = tala_seo_course_list_node();
	}

	$breadcrumbs = tala_seo_breadcrumb_node();
	if ( $breadcrumbs ) {
		$nodes[] = $breadcrumbs;
	}

	return array_values( array_filter( apply_filters( 'tala_seo_schema_nodes', $nodes ) ) );
}

/**
 * WebSite node: tells Google the site name.
 *
 * @return array<string, mixed>
 */
function tala_seo_website_node() {
	return array(
		'@type'      => 'WebSite',
		'@id'        => home_url( '/#website' ),
		'url'        => home_url( '/' ),
		'name'       => tala_seo_site_name(),
		'inLanguage' => get_bloginfo( 'language' ),
		'publisher'  => array( '@id' => home_url( '/#publisher' ) ),
	);
}

/**
 * Organization (or Person) behind the site, with logo and social profiles.
 *
 * @return array<string, mixed>
 */
function tala_seo_publisher_node() {
	$settings    = tala_seo_settings();
	$is_org      = 'person' !== $settings['publisher_type'];
	$node        = array(
		'@type' => $is_org ? 'Organization' : 'Person',
		'@id'   => home_url( '/#publisher' ),
		'name'  => '' !== $settings['publisher_name'] ? $settings['publisher_name'] : tala_seo_site_name(),
		'url'   => home_url( '/' ),
	);
	$description = tala_seo_home_description();
	if ( '' !== $description ) {
		$node['description'] = $description;
	}
	$logo = tala_seo_logo();
	if ( $logo ) {
		$image                              = array(
			'@type'  => 'ImageObject',
			'url'    => $logo['url'],
			'width'  => $logo['width'],
			'height' => $logo['height'],
		);
		$node[ $is_org ? 'logo' : 'image' ] = $image;
	}
	if ( $settings['same_as'] ) {
		$node['sameAs'] = array_values( $settings['same_as'] );
	}
	return $node;
}

/**
 * Person node for an author: name and their author page, plus profiles and a real photo.
 *
 * @param int  $user_id User ID.
 * @param bool $full    Include bio and photo (author pages) or just the essentials (articles).
 * @return array<string, mixed>
 */
function tala_seo_person_node( $user_id, $full = false ) {
	$url      = get_author_posts_url( $user_id );
	$node     = array(
		'@type' => 'Person',
		'@id'   => $url . '#person',
		'name'  => tala_seo_clean_text( get_the_author_meta( 'display_name', $user_id ) ),
		'url'   => $url,
	);
	$profiles = tala_seo_author_profiles( $user_id );
	if ( $profiles ) {
		$node['sameAs'] = $profiles;
	}
	if ( $full ) {
		$bio = tala_seo_clean_text( get_the_author_meta( 'description', $user_id ) );
		if ( '' !== $bio ) {
			$node['description'] = $bio;
		}
		$photo = tala_seo_author_photo( $user_id );
		if ( '' !== $photo ) {
			$node['image'] = $photo;
		}
	}
	return $node;
}

/**
 * BlogPosting node for a post.
 *
 * @param WP_Post $post Post.
 * @return array<string, mixed>
 */
function tala_seo_article_node( $post ) {
	$url  = get_permalink( $post );
	$node = array(
		'@type'            => 'BlogPosting',
		'@id'              => $url . '#article',
		'mainEntityOfPage' => $url,
		'headline'         => tala_seo_clean_text( get_the_title( $post ) ),
		'datePublished'    => get_post_time( 'c', true, $post ),
		'dateModified'     => get_post_modified_time( 'c', true, $post ),
		'inLanguage'       => get_bloginfo( 'language' ),
	);
	if ( get_userdata( (int) $post->post_author ) ) {
		$node['author'] = array( tala_seo_person_node( (int) $post->post_author ) );
	}
	$description = tala_seo_post_description( $post->ID );
	if ( '' !== $description ) {
		$node['description'] = $description;
	}
	$thumbnail = (int) get_post_thumbnail_id( $post );
	if ( $thumbnail ) {
		$images = tala_seo_article_images( $thumbnail );
		if ( $images ) {
			$node['image'] = $images;
		}
	}
	return $node;
}

/**
 * ProfilePage node for an author archive.
 *
 * @param int $user_id User ID.
 * @return array<string, mixed>
 */
function tala_seo_profile_page_node( $user_id ) {
	$url = get_author_posts_url( $user_id );
	return array(
		'@type'      => 'ProfilePage',
		'@id'        => $url,
		'url'        => $url,
		'mainEntity' => tala_seo_person_node( $user_id, true ),
	);
}

/**
 * BreadcrumbList matching the visible trail; Google needs at least two items. None on a page that
 * doesn't show the Breadcrumbs block (so far).
 *
 * @return array<string, mixed>|null
 */
function tala_seo_breadcrumb_node() {
	if ( ! tala_seo_breadcrumbs_state()['shown'] ) {
		return null;
	}
	$trail = tala_seo_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return null;
	}
	$items = array();
	$last  = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $crumb['name'],
		);
		// The last item is the current page; Google allows it without a URL.
		if ( $i < $last && '' !== $crumb['url'] ) {
			$item['item'] = $crumb['url'];
		}
		$items[] = $item;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}
