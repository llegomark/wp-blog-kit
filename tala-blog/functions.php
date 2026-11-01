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
	// Only HTML that is the list itself: then the first ">" ends its start tag, as WordPress escapes
	// ">" in attribute values.
	if ( ! str_starts_with( $html, '<ul' ) || ! $list->next_tag() || 'UL' !== $list->get_tag()
		|| ! $list->has_class( 'wp-block-page-list' ) || ! str_ends_with( $html, '</ul>' ) ) {
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

/**
 * Whether some HTML holds a link (an <a> with an href).
 *
 * @param string $html HTML.
 * @return bool
 */
function tala_blog_has_link( $html ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( array( 'tag_name' => 'a' ) ) ) {
		if ( null !== $tags->get_attribute( 'href' ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'render_block_core/group', 'tala_blog_empty_navigation', 10, 2 );

/**
 * A Group block that is a navigation landmark, as "Keep reading" under an article is, is left out
 * when it holds no link: a site's only article has no other article to point to, and screen
 * readers would still announce the landmark, heading and all. A navigation landmark is there for
 * its links, as the Navigation block, which renders nothing without items, treats it.
 *
 * @param string               $html  Rendered Group.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_empty_navigation( $html, $block ) {
	if ( 'nav' !== ( $block['attrs']['tagName'] ?? '' ) || tala_blog_has_link( $html ) ) {
		return $html;
	}
	return '';
}

/**
 * Whether a parsed block has a class (its "Additional CSS class(es)").
 *
 * @param array<string, mixed> $block      Parsed block.
 * @param string               $class_name Class.
 * @return bool
 */
function tala_blog_block_has_class( $block, $class_name ) {
	$classes = $block['attrs']['className'] ?? '';
	return is_string( $classes ) && in_array( $class_name, explode( ' ', $classes ), true );
}

add_filter( 'query_loop_block_query_vars', 'tala_blog_related_query', 10, 2 );

/**
 * The "Keep reading" list under an article (patterns/post-footer.php): the Post Template with the
 * class "tala-related" lists the latest articles from the article's own categories, the article
 * itself left out, however many categories it has. Pinned posts take no special place, and the
 * list needs no page count. Away from an article (the pattern placed on another page) it is empty.
 *
 * @param array<string, mixed> $query WP_Query arguments built from the Query Loop block.
 * @param WP_Block             $block The Post Template block.
 * @return array<string, mixed>
 */
function tala_blog_related_query( $query, $block ) {
	if ( ! tala_blog_block_has_class( $block->parsed_block, 'tala-related' ) ) {
		return $query;
	}
	$post_id    = is_singular() ? get_queried_object_id() : 0;
	$categories = $post_id ? wp_get_post_categories( $post_id ) : array();
	if ( ! is_array( $categories ) || ! $categories ) {
		$query['post__in'] = array( 0 ); // Nothing to list.
		return $query;
	}
	$query['category__in']        = array_map( 'intval', $categories );
	$query['post__not_in']        = array_merge( array_map( 'intval', (array) ( $query['post__not_in'] ?? array() ) ), array( $post_id ) );
	$query['ignore_sticky_posts'] = true;
	$query['no_found_rows']       = true;
	return $query;
}

add_filter( 'render_block_core/query', 'tala_blog_empty_related', 10, 2 );

/**
 * The "Keep reading" list is left out when it has no article to list (the Query Loop with the
 * class "tala-related"): an article in a category of its own shows the older and newer articles
 * alone, and a site's only article nothing.
 *
 * @param string               $html  Rendered Query Loop.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_empty_related( $html, $block ) {
	return tala_blog_block_has_class( $block, 'tala-related' ) && ! tala_blog_has_link( $html ) ? '' : $html;
}

// Don't fetch pattern directory patterns into the editor.
add_filter( 'should_load_remote_block_patterns', '__return_false' );

// Inline all block CSS rather than only the first 20 KB (core's default budget), so a first visit
// renders without waiting for any stylesheet request.
add_filter( 'styles_inline_size_limit', static fn(): int => 60000 );

add_action( 'wp_enqueue_scripts', 'tala_blog_styles', 20 );

/**
 * Inlines the theme CSS after the global styles: about 17 KB, under 5 KB once the page is
 * compressed, cheaper than another request.
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
 * Browser UI colour for light and dark mode: the page background of each, from theme.json or the
 * chosen colour preset (the "base" palette colour and settings.custom.color.dark.base), the same
 * values the CSS uses.
 */
function tala_blog_theme_color(): void {
	// Saved global styles can hold anything a site administrator wrote: only text is a colour.
	$base  = array_find(
		(array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ),
		static fn( $color ): bool => is_array( $color ) && 'base' === ( $color['slug'] ?? '' )
	);
	$light = is_string( $base['color'] ?? null ) ? $base['color'] : '';
	$dark  = wp_get_global_settings( array( 'custom', 'color', 'dark', 'base' ) );
	foreach ( array(
		'light' => $light,
		'dark'  => is_string( $dark ) ? $dark : '',
	) as $scheme => $color ) {
		if ( '' !== $color ) {
			printf( '<meta name="theme-color" content="%s" media="(prefers-color-scheme: %s)">' . "\n", esc_attr( $color ), esc_attr( $scheme ) );
		}
	}
}

add_filter( 'wp_theme_json_data_user', 'tala_blog_preset_dark_colors' );

/**
 * The dark-mode colours of the chosen colour preset. A preset (styles/colors) brings them along in
 * settings.custom.color.dark, but when the person who picks it can't save unfiltered HTML (a site
 * administrator on a multisite network, say), WordPress saves only the palette, and the site would
 * show the preset in light mode and the theme's own colours in dark mode. While the site's palette
 * is still a preset's, the dark colours come from that preset.
 *
 * @param WP_Theme_JSON_Data $theme_json The site's own styles (Appearance → Editor → Styles).
 * @return WP_Theme_JSON_Data
 */
function tala_blog_preset_dark_colors( $theme_json ) {
	$site    = $theme_json->get_data();
	$palette = $site['settings']['color']['palette']['theme'] ?? null;
	if ( isset( $site['settings']['custom']['color']['dark'] ) || ! is_array( $palette ) ) {
		return $theme_json;
	}
	// Saved global styles can hold anything a site administrator wrote: an entry that isn't a
	// list, or a colour that isn't text, is no colour (and must never reach strtolower()).
	$colors = static function ( array $entries ): array {
		$by_slug = array();
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['slug'] ?? null ) && is_string( $entry['color'] ?? null ) ) {
				$by_slug[ $entry['slug'] ] = strtolower( $entry['color'] );
			}
		}
		ksort( $by_slug );
		return $by_slug;
	};
	foreach ( WP_Theme_JSON_Resolver::get_style_variations() as $variation ) {
		$dark = $variation['settings']['custom']['color']['dark'] ?? null;
		if ( is_array( $dark ) && $colors( (array) ( $variation['settings']['color']['palette']['theme'] ?? array() ) ) === $colors( $palette ) ) {
			return $theme_json->update_with(
				array(
					'version'  => WP_Theme_JSON::LATEST_SCHEMA,
					'settings' => array( 'custom' => array( 'color' => array( 'dark' => $dark ) ) ),
				)
			);
		}
	}
	return $theme_json;
}

add_filter( 'render_block_core/post-date', 'tala_blog_label_dates', 10, 3 );

/**
 * Labels the article dates "Published" and "Updated", as Google recommends, adds the time to
 * each, and hides the updated date when the post was only changed on the day it was published.
 * Dates in post lists stay unlabelled and without the time.
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
	// A date in the site's own format (the block's default) gets the time too; a format chosen in
	// the block, such as "2 days ago", is left as it is.
	if ( empty( $block['attrs']['format'] ) ) {
		$html = tala_blog_date_with_time( $html );
	}
	$label = $modified ? __( 'Updated', 'tala-blog' ) : __( 'Published', 'tala-blog' );
	return tala_blog_before_time( $html, '<span class="tala-date-label">' . esc_html( $label ) . '</span>' );
}

/**
 * Writes the date of a Date block's <time> element as "date at time", in the site's date and time
 * formats (Settings → General), from the element's own datetime attribute.
 *
 * @param string $html Date block HTML.
 * @return string
 */
function tala_blog_date_with_time( $html ) {
	return (string) preg_replace_callback(
		'#(<time\b[^>]*\bdatetime="([^"]+)"[^>]*>)[^<]*(</time>)#',
		static function ( $parts ) {
			$timestamp = strtotime( $parts[2] );
			if ( false === $timestamp ) {
				return $parts[0];
			}
			$text = sprintf(
				/* translators: 1: a date, 2: a time, as on "Published 3 October 2026 at 8:00 am". */
				_x( '%1$s at %2$s', 'date and time', 'tala-blog' ),
				wp_date( (string) get_option( 'date_format' ), $timestamp ),
				wp_date( (string) get_option( 'time_format' ), $timestamp )
			);
			return $parts[1] . esc_html( $text ) . $parts[3];
		},
		$html,
		1
	);
}

// Priority 20: after Enhanced Responsive Images (10), which doesn't see the CSS width either.
add_filter( 'render_block_core/post-featured-image', 'tala_blog_list_thumb_sizes', 20, 2 );

/**
 * A block's HTML with some HTML inserted just before its first <time> tag. Inserted as it is, so a
 * label's translation holding "$1" or a backslash stays text.
 *
 * @param string $html   Block HTML.
 * @param string $insert HTML to insert.
 * @return string
 */
function tala_blog_before_time( $html, $insert ) {
	if ( ! preg_match( '/<time\b/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	return substr_replace( $html, $insert, $match[0][1], 0 );
}

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

add_filter( 'render_block_core/post-title', 'tala_blog_untitled_post', 10, 3 );

/**
 * A post without a title still gets its heading: the post's date, linked where the title would
 * be, so it can be told apart and opened from a list. (WordPress renders nothing, which leaves the
 * article without a level-one heading and a list entry without a link.)
 *
 * @param string               $html     Rendered Title.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_untitled_post( $html, $block, $instance ) {
	$post_id = (int) ( $instance->context['postId'] ?? get_the_ID() );
	if ( '' !== trim( $html ) || ! $post_id || '' !== trim( get_the_title( $post_id ) ) ) {
		return $html;
	}
	$level = (int) ( $block['attrs']['level'] ?? 2 );
	$tag   = $level >= 1 && $level <= 6 ? 'h' . $level : 'p';
	$text  = esc_html( (string) get_the_date( '', $post_id ) );
	if ( ! empty( $block['attrs']['isLink'] ) ) {
		$text = '<a href="' . esc_url( (string) get_permalink( $post_id ) ) . '">' . $text . '</a>';
	}
	$class = trim( 'wp-block-post-title ' . ( is_string( $block['attrs']['className'] ?? null ) ? $block['attrs']['className'] : '' ) );
	return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr( $class ), $text );
}

add_filter( 'render_block_core/post-date', 'tala_blog_pinned_label', 10, 3 );

/**
 * Marks a sticky post "Pinned" where it is pinned: at the top of the home page's first page.
 *
 * @param string               $html     Block HTML.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_pinned_label( $html, $block, $instance ) {
	$post_id = (int) ( $instance->context['postId'] ?? 0 );
	if ( '' === trim( $html ) || ! $post_id || ! empty( $block['attrs']['displayType'] ) || ! is_home() || is_paged() || ! is_sticky( $post_id ) ) {
		return $html;
	}
	return tala_blog_before_time( $html, '<span class="tala-pinned">' . esc_html__( 'Pinned', 'tala-blog' ) . '</span>' );
}

add_filter( 'render_block_core/comment-author-name', 'tala_blog_author_comment_badge', 10, 3 );

/**
 * Marks the comments of the article's author "Author", the same comments WordPress gives the
 * "bypostauthor" class.
 *
 * @param string               $html     Block HTML.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_author_comment_badge( $html, $block, $instance ) {
	$comment = get_comment( (int) ( $instance->context['commentId'] ?? 0 ) );
	$post    = $comment ? get_post( (int) $comment->comment_post_ID ) : null;
	if ( '' === trim( $html ) || ! $comment || ! $post || ! (int) $comment->user_id || (int) $comment->user_id !== (int) $post->post_author ) {
		return $html;
	}
	$badge = ' <span class="tala-comment-badge">' . esc_html__( 'Author', 'tala-blog' ) . '</span>';
	$html  = trim( $html );
	return str_ends_with( $html, '</div>' ) ? substr( $html, 0, -strlen( '</div>' ) ) . $badge . '</div>' : $html;
}

add_filter( 'comment_form_defaults', 'tala_blog_comment_form_heading' );

/**
 * "Leave a Reply" is a heading of the article's level two, like the comments' title before it.
 * WordPress makes it a level three, which skips a level on an article with no comments yet.
 *
 * @param array<string, mixed> $defaults Comment form arguments.
 * @return array<string, mixed>
 */
function tala_blog_comment_form_heading( $defaults ) {
	$defaults['title_reply_before'] = '<h2 id="reply-title" class="comment-reply-title">';
	$defaults['title_reply_after']  = '</h2>';
	return $defaults;
}

add_filter( 'render_block_core/post-comments-form', 'tala_blog_comments_closed', 10, 3 );

/**
 * Under the comments of a post that no longer takes comments, a note saying so where the form
 * would be, as WordPress's default themes do. Nothing where there are no comments, on pages, or
 * while a password hides the comments.
 *
 * @param string               $html     Rendered Comments Form (empty when comments are closed).
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_comments_closed( $html, $block, $instance ) {
	$post = get_post( (int) ( $instance->context['postId'] ?? 0 ) );
	if ( '' !== trim( (string) $html ) || ! $post || 'page' === $post->post_type || comments_open( $post )
		|| post_password_required( $post ) || ! get_comments_number( $post ) || ! post_type_supports( $post->post_type, 'comments' ) ) {
		return $html;
	}
	return '<p class="tala-comments-closed">' . esc_html__( 'Comments are closed.', 'tala-blog' ) . '</p>';
}

add_action( 'customize_register', 'tala_blog_customize_register' );

/**
 * Appearance → Customize → Article lists: whether lists of articles show numbered pages. Off by
 * default: only links to newer and older articles, so the number of pages isn't on show.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function tala_blog_customize_register( $wp_customize ): void {
	$wp_customize->add_section(
		'tala_blog_lists',
		array(
			'title'    => __( 'Article lists', 'tala-blog' ),
			'priority' => 120,
		)
	);
	$wp_customize->add_setting(
		'tala_blog_page_numbers',
		array(
			'default'           => '',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	$wp_customize->add_control(
		'tala_blog_page_numbers',
		array(
			'section'     => 'tala_blog_lists',
			'type'        => 'checkbox',
			'label'       => __( 'Show numbered pages', 'tala-blog' ),
			'description' => __( 'Under the home page, archives and search results. Off, the lists link only to newer and older articles, and the number of pages isn\'t shown.', 'tala-blog' ),
		)
	);
}

add_filter( 'render_block_core/query-pagination-numbers', 'tala_blog_page_numbers' );

/**
 * The numbered pages under article lists, only when Appearance → Customize → Article lists asks
 * for them.
 *
 * @param string $content The numbers' HTML.
 * @return string
 */
function tala_blog_page_numbers( $content ) {
	return get_theme_mod( 'tala_blog_page_numbers', false ) ? $content : '';
}
