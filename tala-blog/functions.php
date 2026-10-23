<?php
/**
 * Tala Blog theme functions.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'tala_blog_setup' );

/**
 * Theme supports. Block themes get title-tag, post thumbnails, feeds and responsive embeds
 * automatically; this only removes WordPress's stock patterns and styles the editor.
 */
function tala_blog_setup(): void {
	remove_theme_support( 'core-block-patterns' );
	add_editor_style( 'assets/theme.css' );
}

/**
 * A translated string for a block attribute in a pattern: a JSON string, quotes included. Core's
 * serialize_block_attributes() escapes it as the block editor does, so a translation with a quote,
 * backslash, line break or "--" can't break the block markup (esc_attr() only makes text HTML-safe,
 * and the block parser reads JSON).
 *
 * @param string $text Translated text.
 * @return string The text as a JSON string, for example "Search".
 */
function tala_blog_block_text( $text ) {
	return substr( serialize_block_attributes( array( 't' => (string) $text ) ), 5, -1 );
}

add_filter( 'block_type_metadata', 'tala_blog_search_aria_label' );

/**
 * Lets the Search block take a name. Each Search block is a search landmark, and the search results
 * and 404 pages have two (the page's and the footer's): landmarks of the same kind need different
 * names, which the patterns set. The block has no "ariaLabel" support of its own.
 *
 * @param array<string, mixed> $metadata Block type metadata.
 * @return array<string, mixed>
 */
function tala_blog_search_aria_label( $metadata ) {
	if ( isset( $metadata['name'] ) && 'core/search' === $metadata['name'] ) {
		$metadata['supports']              = (array) ( $metadata['supports'] ?? array() );
		$metadata['supports']['ariaLabel'] = true;
	}
	return $metadata;
}

add_filter( 'render_block_core/page-list', 'tala_blog_page_list_in_navigation', 10, 3 );

/**
 * Inside a Navigation block, a Page List gives only its list items: the Navigation block wraps the
 * items of a menu in a list of its own, so the Page List's own list would sit directly inside that
 * one, which isn't valid HTML (screen readers and Lighthouse report it). The header shows a Page List
 * this way until the site has a menu. Navigation passes "showSubmenuIcon" to the blocks inside it,
 * so a Page List anywhere else is left alone.
 *
 * @param string               $html     Rendered Page List.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_page_list_in_navigation( $html, $block, $instance ) {
	if ( ! array_key_exists( 'showSubmenuIcon', $instance->context ) ) {
		return $html;
	}
	$html = trim( $html );
	$list = new WP_HTML_Tag_Processor( $html );
	if ( ! $list->next_tag() || 'UL' !== $list->get_tag() || ! $list->has_class( 'wp-block-page-list' )
		|| ! str_ends_with( $html, '</ul>' ) ) {
		return $html;
	}
	return substr( $html, (int) strpos( $html, '>' ) + 1, -strlen( '</ul>' ) );
}

add_filter( 'render_block_core/site-logo', 'tala_blog_decorative_logo', 10, 2 );

/**
 * The logo beside the site title (patterns/site-brand.php) gets an empty alt text: the title next to
 * it already names the site, so screen readers would otherwise read the name twice. A Site Logo block
 * used anywhere else keeps its alt text.
 *
 * @param string               $html  Rendered Site Logo.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_decorative_logo( $html, $block ) {
	$classes = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! in_array( 'tala-logo', explode( ' ', $classes ), true ) ) {
		return $html;
	}
	$logo = new WP_HTML_Tag_Processor( $html );
	if ( $logo->next_tag( array( 'tag_name' => 'img' ) ) ) {
		$logo->set_attribute( 'alt', '' );
	}
	return $logo->get_updated_html();
}

// Don't fetch pattern directory patterns into the editor.
add_filter( 'should_load_remote_block_patterns', '__return_false' );

// Inline all block CSS rather than only the first 20 KB (core's default budget), so a first visit
// renders without waiting for any stylesheet request.
add_filter( 'styles_inline_size_limit', static fn(): int => 60000 );

add_action( 'wp_enqueue_scripts', 'tala_blog_styles', 20 );

/**
 * Inlines the theme CSS after the global styles: about 3 KB, cheaper than another request.
 */
function tala_blog_styles(): void {
	$css = file_get_contents( get_theme_file_path( 'assets/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	wp_register_style( 'tala-blog', false, array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_style( 'tala-blog' );
	wp_add_inline_style( 'tala-blog', (string) $css );
}

add_action( 'init', 'tala_blog_remove_emoji' );

/**
 * Removes the emoji script and styles: every device already draws emoji itself.
 */
function tala_blog_remove_emoji(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}

add_action( 'wp_head', 'tala_blog_theme_color', 2 );

/**
 * Browser UI colour for light and dark mode: the page background of each, both from theme.json
 * (the "base" palette colour and settings.custom.dark.base), the same values the CSS uses.
 */
function tala_blog_theme_color(): void {
	$base  = array_find(
		(array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ),
		static fn( $color ): bool => 'base' === ( $color['slug'] ?? '' )
	);
	$light = (string) ( $base['color'] ?? '' );
	$dark  = wp_get_global_settings( array( 'custom', 'dark', 'base' ) );
	foreach ( array(
		'light' => $light,
		'dark'  => is_string( $dark ) ? $dark : '',
	) as $scheme => $color ) {
		if ( '' !== $color ) {
			printf( '<meta name="theme-color" content="%s" media="(prefers-color-scheme: %s)">' . "\n", esc_attr( $color ), esc_attr( $scheme ) );
		}
	}
}

add_filter( 'render_block_core/post-date', 'tala_blog_label_dates', 10, 3 );

/**
 * Labels the article dates "Published" and "Updated", as Google recommends, and hides the
 * updated date when the post was only changed on the day it was published.
 * Dates in post lists stay unlabelled.
 *
 * @param string               $html     Block HTML.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_label_dates( $html, $block, $instance ) {
	$post_id = (int) ( $instance->context['postId'] ?? get_the_ID() );
	// Only the article being read gets labels; post lists (Query Loop) don't pass on their context.
	if ( '' === trim( $html ) || ! is_singular() || get_queried_object_id() !== $post_id ) {
		return $html;
	}
	$modified = str_contains( $html, 'wp-block-post-date__modified-date' );
	if ( $modified && get_the_modified_date( 'Y-m-d', $post_id ) <= get_the_date( 'Y-m-d', $post_id ) ) {
		return '';
	}
	$label = $modified ? __( 'Updated', 'tala-blog' ) : __( 'Published', 'tala-blog' );
	return (string) preg_replace( '/<time\b/', '<span class="tala-date-label">' . esc_html( $label ) . '</span><time', $html, 1 );
}

// Priority 20: after Enhanced Responsive Images (10), which doesn't see the CSS width either.
add_filter( 'render_block_core/post-featured-image', 'tala_blog_list_thumb_sizes', 20, 2 );

/**
 * Gives article-list thumbnails their real width in `sizes`: 5.5rem, and 8rem from 40em up, as in
 * assets/theme.css. WordPress says 300px (the "medium" image), so phones with 2× or 3× screens
 * downloaded the 768 or 1024 px file for an 88 px thumbnail. Keeps a leading "auto".
 *
 * @param string               $html  Block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_list_thumb_sizes( $html, $block ) {
	$class = $block['attrs']['className'] ?? '';
	if ( ! is_string( $class ) || ! in_array( 'tala-list-thumb', explode( ' ', $class ), true ) ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'tag_name' => 'img' ) ) ) {
		return $html;
	}
	$sizes = $tags->get_attribute( 'sizes' );
	if ( ! is_string( $sizes ) ) {
		return $html;
	}
	$auto = 'auto' === strtolower( trim( explode( ',', $sizes, 2 )[0] ) ) ? 'auto, ' : '';
	$tags->set_attribute( 'sizes', $auto . '(min-width: 40em) 8rem, 5.5rem' );
	return $tags->get_updated_html();
}
