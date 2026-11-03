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
 * A Group block that is a navigation landmark, as "Read next" under an article is, is left out
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

add_filter( 'render_block_core/group', 'tala_blog_topics_label', 10, 2 );

/**
 * The home page's topics (the Group with the class "tala-topics") are a navigation landmark named
 * by their visible label, the paragraph with the class "tala-topics__label", through aria-labelledby,
 * so screen readers announce "Browse by topic" once, as the landmark's name. (A Group can save an
 * aria-label, but not a reference to another block.)
 *
 * @param string               $html  Rendered Group.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_topics_label( $html, $block ) {
	if ( ! tala_blog_block_has_class( $block, 'tala-topics' ) ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag() || ! $tags->set_bookmark( 'landmark' ) || ! $tags->next_tag( array( 'class_name' => 'tala-topics__label' ) ) ) {
		return $html;
	}
	$id = $tags->get_attribute( 'id' );
	if ( ! is_string( $id ) || '' === $id ) {
		$id = wp_unique_id( 'tala-topics-label-' );
		$tags->set_attribute( 'id', $id );
	}
	$tags->seek( 'landmark' );
	$tags->remove_attribute( 'aria-label' );
	$tags->set_attribute( 'aria-labelledby', $id );
	return $tags->get_updated_html();
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

add_filter( 'render_block_core/group', 'tala_blog_empty_topics', 10, 2 );

/**
 * The topics at the end of an article (the Group with the class "tala-terms": its categories and
 * tags as chips under "Topics") are left out when the post has neither, label and all.
 *
 * @param string               $html  Rendered Group.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_empty_topics( $html, $block ) {
	return tala_blog_block_has_class( $block, 'tala-terms' ) && ! tala_blog_has_link( $html ) ? '' : $html;
}

add_filter( 'render_block_core/table', 'tala_blog_table_region' );

/**
 * A Table block scrolls sideways in its own box when it is wider than the text column (theme.css),
 * so the page never does. Keyboard users scroll it too: the box is a region they can focus, named
 * by the table's caption, or "Table", as the design system's tables are.
 *
 * @param string $html Rendered Table.
 * @return string
 */
function tala_blog_table_region( $html ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'tag_name' => 'figure' ) ) || ! $tags->has_class( 'wp-block-table' ) ) {
		return $html;
	}
	$caption = preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#s', $html, $match ) ? trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $match[1] ) ) ) : '';
	$tags->set_attribute( 'role', 'region' );
	$tags->set_attribute( 'aria-label', '' !== $caption ? html_entity_decode( $caption, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : _x( 'Table', 'name of a table\'s scrolling box for screen readers', 'tala-blog' ) );
	$tags->set_attribute( 'tabindex', '0' );
	return $tags->get_updated_html();
}

add_filter( 'the_content', 'tala_blog_classic_table_region', 12 );

/**
 * A classic table (from the classic editor or a paste, not a Table block) gets the region a Table
 * block's figure is (tala_blog_table_region()): a box around it that scrolls sideways when the
 * table is wider than the text column, which keyboard users can focus, named by the table's
 * caption or "Table". The table itself is left exactly as it was, so it stays a table at its own
 * width. A table in a Table block, in a region already, or inside another table isn't wrapped.
 * Runs after the blocks, paragraphs and shortcodes of the text are rendered (priority 12); feeds
 * keep their tables as they are.
 *
 * @param string $html Post content HTML.
 * @return string
 */
function tala_blog_classic_table_region( $html ) {
	if ( false === stripos( $html, '<table' ) || is_feed() ) {
		return $html;
	}
	// The Tag Processor, also telling where the current tag starts and ends, so a table can be
	// wrapped without rewriting anything around it.
	$tags    = new class( $html ) extends WP_HTML_Tag_Processor {
		/**
		 * Where the current tag starts (its "<") and ends (just after its ">").
		 *
		 * @return array{int, int} Byte offsets.
		 */
		public function span(): array {
			$this->set_bookmark( 'tala-here' );
			return array( $this->bookmarks['tala-here']->start, $this->bookmarks['tala-here']->start + $this->bookmarks['tala-here']->length );
		}
	};
	$tables  = array(); // [ start, end ] of each table to wrap.
	$depth   = 0;       // Tables open around the current tag.
	$figures = 0;       // Table block figures open around it.
	$after   = false;   // Whether the last tag opened a region box.
	$skip    = false;
	$start   = 0;
	while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag    = $tags->get_tag();
		$closer = $tags->is_tag_closer();
		if ( 'FIGURE' === $tag ) {
			$figures += ! $closer && $tags->has_class( 'wp-block-table' ) ? 1 : ( $closer && $figures > 0 ? -1 : 0 );
		} elseif ( 'TABLE' === $tag && ! $closer ) {
			if ( 0 === $depth ) {
				$skip  = $figures > 0 || $after;
				$start = $tags->span()[0];
			}
			++$depth;
		} elseif ( 'TABLE' === $tag && $depth > 0 ) {
			--$depth;
			if ( 0 === $depth && ! $skip ) {
				$tables[] = array( $start, $tags->span()[1] );
			}
		}
		$after = ! $closer && 'DIV' === $tag && $tags->has_class( 'tala-table-scroll' );
	}
	foreach ( array_reverse( $tables ) as list( $from, $to ) ) {
		$table   = substr( $html, $from, $to - $from );
		$caption = preg_match( '#<caption\b[^>]*>(.*?)</caption>#is', $table, $match ) ? trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $match[1] ) ) ) : '';
		$name    = '' !== $caption ? html_entity_decode( $caption, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : _x( 'Table', 'name of a table\'s scrolling box for screen readers', 'tala-blog' );
		$html    = substr_replace( $html, '<div class="tala-table-scroll" role="region" tabindex="0" aria-label="' . esc_attr( $name ) . '">' . $table . '</div>', $from, $to - $from );
	}
	return $html;
}

add_filter( 'render_block_core/post-author-name', 'tala_blog_byline', 10, 3 );

/**
 * The byline in an article's title band (the Author Name block with the class "tala-byline"):
 * "By {author}", the name linking to the author's page as the post's author (rel="author"). Left
 * out when the author has no name, rather than an empty link. Other Author Name blocks, and markup
 * not of the block's own shape, are left as they are.
 *
 * @param string               $html     Rendered Author Name.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_byline( $html, $block, $instance ) {
	if ( ! tala_blog_block_has_class( $block, 'tala-byline' ) ) {
		return $html;
	}
	$author = (int) get_post_field( 'post_author', (int) ( $instance->context['postId'] ?? get_the_ID() ) );
	$name   = $author ? trim( wp_strip_all_tags( (string) get_the_author_meta( 'display_name', $author ) ) ) : '';
	if ( '' === $name ) {
		return '';
	}
	$wrapper = trim( $html );
	$start   = strpos( $wrapper, '>' ); // The wrapper's start tag ends at the first ">" (WordPress escapes ">" in attributes).
	if ( false === $start || ! str_starts_with( $wrapper, '<div' ) || ! str_ends_with( $wrapper, '</div>' ) ) {
		return $html; // Markup of another shape (a plugin's) is left as it is.
	}
	$html = $wrapper;
	$link = '<a href="' . esc_url( get_author_posts_url( $author ) ) . '" rel="author" class="wp-block-post-author-name__link">' . esc_html( $name ) . '</a>';
	/* translators: %s: the author's name, linked to their page. */
	return substr( $html, 0, $start + 1 ) . sprintf( esc_html__( 'By %s', 'tala-blog' ), $link ) . '</div>';
}

add_filter( 'render_block_core/post-excerpt', 'tala_blog_lead', 10, 3 );

/**
 * The lead in an article's title band (the Excerpt block with the class "tala-lead"): the post's
 * excerpt, written for it (or given by a plugin, as Tala SEO gives the meta description), never
 * one WordPress cuts from the opening of the text, which follows right under the title band. A post
 * without one, or hidden by a password, has no lead.
 *
 * @param string               $html     Rendered Excerpt.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_lead( $html, $block, $instance ) {
	if ( ! tala_blog_block_has_class( $block, 'tala-lead' ) ) {
		return $html;
	}
	return tala_blog_has_lead( get_post( (int) ( $instance->context['postId'] ?? get_the_ID() ) ) ) ? $html : '';
}

add_filter( 'pre_render_block', 'tala_blog_skip_lead', 10, 2 );

/**
 * Leaves out the lead before it renders when the post has none of its own: rendering the Excerpt
 * block would have WordPress cut one from the text, putting the whole text through the_content (and
 * whatever plugins add to it, such as ads) a second time, only for it to be left out.
 *
 * @param string|null          $pre_render   Content to use instead of rendering, or null.
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return string|null
 */
function tala_blog_skip_lead( $pre_render, $parsed_block ) {
	if ( null !== $pre_render || 'core/post-excerpt' !== ( $parsed_block['blockName'] ?? '' )
		|| ! tala_blog_block_has_class( $parsed_block, 'tala-lead' ) ) {
		return $pre_render;
	}
	return tala_blog_has_lead( get_post() ) ? null : '';
}

/**
 * Whether a post has a lead: an excerpt written for it, or given by a plugin, not WordPress's own one
 * made from the text; none when a password hides it.
 *
 * @param WP_Post|null $post Post.
 * @return bool
 */
function tala_blog_has_lead( $post ) {
	if ( ! $post || post_password_required( $post ) ) {
		return false;
	}
	$priority = has_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
	remove_filter( 'get_the_excerpt', 'wp_trim_excerpt', false === $priority ? 10 : $priority );
	try {
		$excerpt = get_the_excerpt( $post );
	} finally {
		if ( false !== $priority ) {
			add_filter( 'get_the_excerpt', 'wp_trim_excerpt', $priority, 2 );
		}
	}
	return '' !== trim( wp_strip_all_tags( $excerpt ) );
}

add_filter( 'query_loop_block_query_vars', 'tala_blog_related_query', 10, 2 );

/**
 * The "Read next" list under an article (patterns/post-footer.php): the Post Template with the
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
 * The "Read next" list is left out when it has no article to list (the Query Loop with the
 * class "tala-related"): an article in a category of its own shows the older and newer articles
 * (as cells) alone, and a site's only article nothing.
 *
 * @param string               $html  Rendered Query Loop.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_empty_related( $html, $block ) {
	return tala_blog_block_has_class( $block, 'tala-related' ) && ! tala_blog_has_link( $html ) ? '' : $html;
}

add_filter( 'render_block_core/template-part', 'tala_blog_empty_part', 10, 2 );

/**
 * A template part with nothing in it prints nothing, not even its wrapper. The theme ships parts
 * that each site fills in the Site Editor (the footer note), and empty they would leave an empty
 * box, with its spacing, on every page.
 *
 * @param string               $html  Rendered Template Part.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_empty_part( $html, $block ) {
	if ( 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	$html = trim( $html );
	// The wrapper's start tag ends at the first ">" (WordPress escapes ">" in attribute values),
	// and its end tag is the last "</".
	$start = strpos( $html, '>' );
	$end   = strrpos( $html, '</' );
	if ( false === $start || false === $end || $end <= $start ) {
		return $html;
	}
	return '' === trim( substr( $html, $start + 1, $end - $start - 1 ) ) ? '' : $html;
}

add_action( 'init', 'tala_blog_register_bindings' );

/**
 * The theme's Block Bindings sources:
 *
 * - "tala-blog/year": this year, in the site's time zone. The footer's copyright line binds a
 *   paragraph to it, so the year is never written into the theme or a site's saved footer, and
 *   changes by itself on 1 January.
 * - "tala-blog/reading-time": "N min read" for the post shown (the article's title band binds a
 *   paragraph to it), from tala_blog_reading_minutes().
 */
function tala_blog_register_bindings(): void {
	register_block_bindings_source(
		'tala-blog/year',
		array(
			'label'              => _x( 'Current year', 'name of a block binding', 'tala-blog' ),
			'get_value_callback' => 'tala_blog_current_year',
		)
	);
	register_block_bindings_source(
		'tala-blog/reading-time',
		array(
			'label'              => _x( 'Reading time', 'name of a block binding', 'tala-blog' ),
			'get_value_callback' => 'tala_blog_reading_time',
			'uses_context'       => array( 'postId' ),
		)
	);
}

add_filter( 'render_block_core/paragraph', 'tala_blog_empty_reading_time', 10, 2 );

/**
 * The title band's reading time (the paragraph with the class "tala-reading-time") is left out
 * when it has nothing to say, as on a post behind a password: no empty paragraph in the dates.
 *
 * @param string               $html  Rendered Paragraph.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_empty_reading_time( $html, $block ) {
	return tala_blog_block_has_class( $block, 'tala-reading-time' ) && '' === trim( wp_strip_all_tags( $html ) ) ? '' : $html;
}

/**
 * "N min read" for the post a block shows (its postId context), or the current post; nothing for a
 * post behind a password.
 *
 * @param array<string, mixed> $source_args Binding arguments (none).
 * @param WP_Block             $block       The bound block.
 * @return string
 */
function tala_blog_reading_time( $source_args, $block ) {
	$post = get_post( (int) ( $block->context['postId'] ?? get_the_ID() ) );
	// Behind a password, nothing about the text shows until the password is entered.
	if ( ! $post || post_password_required( $post ) ) {
		return '';
	}
	$minutes = tala_blog_reading_minutes( $post );
	/* translators: %d: minutes it takes to read the article, as on "5 min read". */
	return sprintf( _n( '%d min read', '%d min read', $minutes, 'tala-blog' ), $minutes );
}

/**
 * Minutes it takes to read a post: the words of its text (the markup, the block delimiters, which
 * are HTML comments, and the shortcodes stripped; split at whitespace, so "niño" is one word), at
 * 220 words a minute, rounded up, and at least one. The post is not rendered for this: rendering it
 * a second time would run its dynamic blocks again (a form would count a second view).
 *
 * @param WP_Post|int $post Post.
 * @return int
 */
function tala_blog_reading_minutes( $post ): int {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}
	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	return max( 1, (int) ceil( count( is_array( $words ) ? $words : array() ) / 220 ) );
}

/**
 * This year, in the site's time zone.
 *
 * @return string
 */
function tala_blog_current_year() {
	return (string) wp_date( 'Y' );
}

add_filter( 'render_block_core/site-title', 'tala_blog_wordmark', 10, 2 );

/**
 * The footer's wordmark (the Site Title block with the class "tala-wordmark"): the site title in
 * giant outlined capitals, sized from its length (--tala-chars, the characters a reader sees,
 * spaces counted) so any title spans the rail. It repeats the title for the eye only: hidden from
 * screen readers, never in the tab order, and without the site title's class, so the brand cell's
 * title stays the site title.
 *
 * @param string               $html  Rendered Site Title.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_wordmark( $html, $block ) {
	if ( '' === trim( $html ) || ! tala_blog_block_has_class( $block, 'tala-wordmark' ) ) {
		return $html;
	}
	$title = html_entity_decode( wp_strip_all_tags( get_bloginfo( 'name' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$chars = max( 1, mb_strlen( $title ) );
	$mark  = new WP_HTML_Tag_Processor( $html );
	if ( ! $mark->next_tag() ) {
		return $html;
	}
	$mark->remove_class( 'wp-block-site-title' );
	$mark->set_attribute( 'aria-hidden', 'true' );
	$style = trim( (string) $mark->get_attribute( 'style' ) );
	$mark->set_attribute( 'style', ( '' === $style ? '' : rtrim( $style, ';' ) . ';' ) . '--tala-chars:' . $chars );
	while ( $mark->next_tag( array( 'tag_name' => 'a' ) ) ) {
		$mark->set_attribute( 'tabindex', '-1' );
	}
	return $mark->get_updated_html();
}

add_filter( 'render_block_core/navigation', 'tala_blog_footer_columns', 10, 2 );

/**
 * The footer menu (the Navigation block with the class "tala-footer-menu") as columns: each
 * top-level item with a submenu is a column under its label, and the top-level items without one
 * (or the pages, while the site has no menu) gather in a last column, in their order. The menu
 * gets the number of columns as --tala-columns, which sets its share of the footer's width.
 * Markup the HTML API can't read is left as it is: then each item is a column of its own.
 *
 * @param string               $html  Rendered Navigation.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_footer_columns( $html, $block ) {
	if ( ! tala_blog_block_has_class( $block, 'tala-footer-menu' ) ) {
		return $html;
	}
	$tags = WP_HTML_Processor::create_fragment( $html );
	if ( null === $tags ) {
		return $html;
	}
	$menu    = '';
	$gather  = '';
	$list    = 0; // Depth of the menu's list, once it opens; -1 once it has closed.
	$columns = 0;
	$leaf    = false;
	while ( $tags->next_token() ) {
		$tag    = $tags->get_tag();
		$closer = $tags->is_tag_closer();
		if ( 0 === $list && 'UL' === $tag && ! $closer && $tags->has_class( 'wp-block-navigation__container' ) ) {
			$list = $tags->get_current_depth();
		} elseif ( $list > 0 && 'LI' === $tag && ! $closer && $tags->get_current_depth() === $list + 1 ) {
			$leaf     = ! $tags->has_class( 'has-child' );
			$columns += $leaf ? 0 : 1;
		} elseif ( $list > 0 && 'UL' === $tag && $closer && $tags->get_current_depth() === $list - 1 ) {
			if ( '' !== $gather ) {
				$menu .= '<li class="wp-block-navigation-item tala-footer-menu__links"><ul class="wp-block-navigation__submenu-container">' . $gather . '</ul></li>';
				++$columns;
			}
			$list = -1;
			$leaf = false;
		}
		if ( $leaf ) {
			$gather .= $tags->serialize_token();
		} else {
			$menu .= $tags->serialize_token();
		}
	}
	if ( null !== $tags->get_last_error() || $list >= 0 ) {
		return $html;
	}
	$nav = new WP_HTML_Tag_Processor( $menu );
	if ( ! $nav->next_tag( array( 'tag_name' => 'nav' ) ) ) {
		return $html;
	}
	$style = trim( (string) $nav->get_attribute( 'style' ) );
	$nav->set_attribute( 'style', ( '' === $style ? '' : rtrim( $style, ';' ) . ';' ) . '--tala-columns:' . max( 1, $columns ) );
	return $nav->get_updated_html();
}

add_filter( 'render_block_core/navigation-submenu', 'tala_blog_submenu_label', 10, 3 );

/**
 * In a menu whose submenus are always open (the footer's columns, the phone menu), a submenu's title
 * is a label, not a link: its items are right under it, and a title that went somewhere else would
 * read as one more item. (In the header its submenu opens on click: core makes the title a button.)
 * Its own page can be one of its items.
 *
 * @param string               $html     Rendered Submenu.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_submenu_label( $html, $block, $instance ) {
	if ( 'always' !== block_core_navigation_submenu_get_submenu_visibility( $instance->context ) ) {
		return $html;
	}
	$title = new WP_HTML_Tag_Processor( $html );
	if ( ! $title->next_tag( array( 'tag_name' => 'li' ) ) || ! $title->next_tag() || 'A' !== $title->get_tag() ) {
		return $html;
	}
	foreach ( array( 'href', 'target', 'rel', 'aria-current' ) as $name ) {
		$title->remove_attribute( $name );
	}
	return $title->get_updated_html();
}

add_filter( 'render_block_data', 'tala_blog_area_menus' );

/**
 * The menus chosen in Appearance → Customize → Header and footer menus: the footer's Navigation block
 * (the class "tala-footer-menu") shows the footer menu, and every other one without links of its own
 * (the header's, the phone menu's) the header menu. Without a choice WordPress picks, as before, its
 * newest menu; but once the footer has a menu of its own the header's pick leaves it out, so a new
 * footer menu never takes the header over.
 *
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return array<string, mixed>
 */
function tala_blog_area_menus( $parsed_block ) {
	if ( 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) || ! empty( $parsed_block['innerBlocks'] )
		|| ! empty( $parsed_block['attrs']['ref'] ) || ! empty( $parsed_block['attrs']['navigationMenuId'] ) ) {
		return $parsed_block;
	}
	$menu = tala_blog_area_menu( tala_blog_block_has_class( $parsed_block, 'tala-footer-menu' ) ? 'footer' : 'header' );
	if ( $menu ) {
		$parsed_block['attrs']['ref'] = $menu;
	}
	return $parsed_block;
}

/**
 * The menu (wp_navigation post) the header or the footer shows: the one chosen for it, or for the
 * header with none chosen while the footer has one, WordPress's pick among the others (its newest);
 * 0 to leave the pick to WordPress.
 *
 * @param string $area 'header' or 'footer'.
 * @return int
 */
function tala_blog_area_menu( $area ) {
	$footer = tala_blog_sanitize_menu( get_theme_mod( 'tala_blog_footer_menu', 0 ) );
	if ( 'footer' === $area ) {
		return $footer;
	}
	$header = tala_blog_sanitize_menu( get_theme_mod( 'tala_blog_header_menu', 0 ) );
	if ( $header || ! $footer ) {
		return $header;
	}
	$others = get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 2,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ( $others as $id ) {
		if ( (int) $id !== $footer ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * A menu choice: the ID of a published menu (wp_navigation post), or 0.
 *
 * @param mixed $value Value to check.
 * @return int
 */
function tala_blog_sanitize_menu( $value ) {
	$post = is_numeric( $value ) ? get_post( (int) $value ) : null;
	return $post && 'wp_navigation' === $post->post_type && 'publish' === $post->post_status ? $post->ID : 0;
}

add_filter( 'render_block_core/group', 'tala_blog_footer_cells', 10, 2 );

/**
 * The footer's cells (the Group with the class "tala-cells") get the number of the menu's columns
 * (--tala-columns, from tala_blog_footer_columns()), so from 48rem of footer they lay out as one
 * row: the brand cell 2fr and each column 1fr. With three columns or more they also get the class
 * tala-cells--stacked: the brand cell then takes a row of its own and the columns share the rail
 * below it, so their words keep room (a class, as Firefox has no style queries on custom properties).
 *
 * @param string               $html  Rendered Group.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_footer_cells( $html, $block ) {
	if ( ! tala_blog_block_has_class( $block, 'tala-cells' ) ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag() ) {
		return $html;
	}
	$tags->set_bookmark( 'cells' );
	while ( $tags->next_tag( array( 'tag_name' => 'nav' ) ) ) {
		if ( $tags->has_class( 'tala-footer-menu' ) && preg_match( '/--tala-columns:(\d+)/', (string) $tags->get_attribute( 'style' ), $columns ) ) {
			$tags->seek( 'cells' );
			$style = trim( (string) $tags->get_attribute( 'style' ) );
			$tags->set_attribute( 'style', ( '' === $style ? '' : rtrim( $style, ';' ) . ';' ) . '--tala-columns:' . $columns[1] );
			if ( (int) $columns[1] >= 3 ) {
				$tags->add_class( 'tala-cells--stacked' );
			}
			break;
		}
	}
	return $tags->get_updated_html();
}

// Don't fetch pattern directory patterns into the editor.
add_filter( 'should_load_remote_block_patterns', '__return_false' );

// Inline all block CSS rather than only the first 20 KB (core's default budget), so a first visit
// renders without waiting for any stylesheet request.
add_filter( 'styles_inline_size_limit', static fn(): int => 60000 );

add_action( 'wp_enqueue_scripts', 'tala_blog_styles', 20 );

/**
 * Inlines the theme CSS after the global styles, cheaper than another request. The comments that
 * explain it in assets/theme.css (a quarter of the file) and the indentation stay out of the page:
 * about 48 KB is printed, under 10 KB once the page is compressed. The editor reads the file as it is.
 *
 * The comments are cut by a plain pattern, from each slash-star to the next star-slash, so
 * theme.css must never have a slash-star inside a string or url(). If PCRE fails (its backtrack
 * limit, say), the file is inlined as it is: a page without it would be unstyled.
 */
function tala_blog_styles(): void {
	$css = (string) file_get_contents( get_theme_file_path( 'assets/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	$css = preg_replace( array( '#/\*.*?\*/#s', '/\n\s+/' ), array( '', "\n" ), $css ) ?? $css;
	wp_register_style( 'tala-blog', false, array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_style( 'tala-blog' );
	wp_add_inline_style( 'tala-blog', trim( $css ) );
}

add_action( 'wp_head', 'tala_blog_theme_switch_script', 0 );

/**
 * The Light/Dark switch's script, first in <head>, so it runs before any CSS is painted:
 *
 * - It applies the reader's choice, kept in localStorage ("tala-theme": "light" or "dark"), as
 *   data-theme on <html>. Until the reader chooses, the page follows the device.
 * - It adds the class tala-js to <html>, which shows the switch. Without JavaScript the switch
 *   can't work, so it stays hidden and the page follows the device.
 * - A click on the switch (.tala-theme-switch, in the header) changes the theme and stores it for
 *   the next pages. The new colors are computed with transitions off (class tala-switching and a
 *   forced style pass), so nothing fades. The switch's state and the browser's color (the
 *   theme-color metas) follow, as they follow the device until a choice.
 * - A choice made elsewhere on the same site also holds on an open page: in another tab (the
 *   storage event), or before the reader came back to a page the browser kept in its back/forward
 *   cache (pageshow). That includes other apps on the same origin that keep the same key, such as
 *   a directory served beside WordPress.
 * - The switch is a toggle button with one name, "Dark theme" (the header pattern): its state is
 *   aria-pressed, true while the page is dark, and the script never changes its name.
 *
 * Storage can be blocked (a private window, site data turned off), so every use of it is in try:
 * the switch then works for the visit. The script is under 1 KB, so its names are short: d the
 * document, h <html>, k its classes, q the device's dark mode, c the paper of each theme, M the
 * theme-color metas, S the class that turns transitions off, t the theme, n the theme shown now,
 * E runs a function on each match of a selector, w shows the state, u reads the stored choice, F
 * changes the theme. Its listeners are on the window, where clicks and DOMContentLoaded arrive
 * too. A browser too old for arrow functions can't run it at all, and keeps the page without
 * JavaScript.
 */
function tala_blog_theme_switch_script(): void {
	wp_print_inline_script_tag(
		'(()=>{' .
		'const d=document,h=d.documentElement,k=h.classList,q=matchMedia("(prefers-color-scheme: dark)"),c={},M="meta[name=theme-color]",S="tala-switching";' .
		'let t;' .
		// u: the stored choice. Only "light" or "dark" becomes the theme; anything else (or blocked
		// storage) leaves the page as it is.
		'const u=()=>{try{t=localStorage.getItem("tala-theme")}catch(e){}if("light"===t||"dark"===t)h.dataset.theme=t};' .
		'u();' .
		'k.add("tala-js");' .
		'const n=()=>h.dataset.theme||(q.matches?"dark":"light"),' .
		'E=(s,f)=>d.querySelectorAll(s).forEach(f),' .
		'w=()=>{' .
		'const on="dark"===n();' .
		'E(".tala-theme-switch",b=>b.setAttribute("aria-pressed",on));' .
		'E(M,m=>m.content=c[h.dataset.theme]||m.content)' .
		'},' .
		// F: a change of theme, its colors computed with transitions off, then the switch's state.
		'F=f=>{k.add(S);f();h.offsetWidth;k.remove(S);w()};' .
		// The paper of each theme, from the metas as printed (one per color scheme), then the switch's state.
		'addEventListener("DOMContentLoaded",()=>{E(M,m=>c[/dark/.test(m.media)?"dark":"light"]=m.content);w()});' .
		'addEventListener("click",e=>{' .
		'if(e.target.closest&&e.target.closest(".tala-theme-switch")){' .
		't="dark"===n()?"light":"dark";' .
		'F(()=>{h.dataset.theme=t});' .
		'try{localStorage.setItem("tala-theme",t)}catch(e){}' .
		'}' .
		'});' .
		// A choice made in another tab, or on another page before this one came back from the
		// back/forward cache.
		'addEventListener("storage",e=>{"tala-theme"===e.key&&F(u)});' .
		'addEventListener("pageshow",e=>{e.persisted&&F(u)});' .
		'q.onchange=w' .
		'})();',
		array( 'id' => 'tala-blog-theme-switch-js' )
	);
}

add_action( 'wp_head', 'tala_blog_font_preloads', 1 );

/**
 * Preloads the two Inter files every page needs, before any CSS: the Latin text face and the light
 * italic display face of the titles. The browser then fetches them alongside the CSS instead of
 * after it, so the text shows in Inter sooner. The Latin Extended and italic files load only on a
 * page with characters or italics they cover (theme.json, unicodeRange).
 */
function tala_blog_font_preloads(): void {
	foreach ( array( 'InterVariable-latin.woff2', 'InterDisplay-LightItalic-latin.woff2' ) as $file ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_theme_file_uri( 'assets/fonts/' . $file ) ) );
	}
}

add_action( 'gform_enqueue_scripts', 'tala_blog_gravity_forms', 1001 );

/**
 * Gravity Forms in the theme's look, light or dark as the page is: the rules for its Orbital theme and
 * for its 2.5 theme go right after Gravity Forms' own stylesheet for that theme. So they load only on
 * pages with such a form, in the order that lets them work. Gravity Forms enqueues its stylesheets on
 * this hook, at priority 1000, for each form on the page.
 */
function tala_blog_gravity_forms(): void {
	$sheets = array(
		'assets/gravity-forms-orbital.css' => array( 'gravity_forms_orbital_theme', 'gravity_forms_theme_framework' ),
		'assets/gravity-forms-2-5.css'     => array( 'gform_theme', 'gform_basic' ),
	);
	foreach ( $sheets as $file => $handles ) {
		$handle = current( array_filter( $handles, static fn( $h ) => wp_style_is( $h, 'enqueued' ) ) );
		if ( ! $handle ) {
			continue;
		}
		$css   = (string) file_get_contents( get_theme_file_path( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$after = wp_styles()->get_data( $handle, 'after' );
		if ( ! is_array( $after ) || ! in_array( $css, $after, true ) ) {
			wp_add_inline_style( $handle, $css );
		}
	}
}

add_action( 'wp_head', 'tala_blog_pdf_viewer', 1 );

/**
 * PDF Embedder's viewer, on pages that have one (block themes have rendered the page by now):
 *
 * - Its scripts (PDF.js, the viewer, and the jQuery they need) load deferred instead of blocking
 *   the page from <head>. An article's text then shows before about 140 KB of JavaScript (490 KB
 *   unpacked) has downloaded and run; the viewer, below the opening paragraphs, starts when the page
 *   is ready, as before. WordPress keeps a script blocking when a blocking script on the page needs
 *   it first (a Gravity Forms form, for one), so nothing runs before what it depends on.
 * - The viewer takes the theme's look, light or dark as the page is, with a readable toolbar
 *   (assets/pdf-embedder.css), right after its own stylesheet.
 */
function tala_blog_pdf_viewer(): void {
	if ( ! wp_script_is( 'pdfemb_embed_pdf', 'enqueued' ) ) {
		return;
	}
	foreach ( array( 'pdfemb_embed_pdf', 'pdfemb_pdfjs', 'jquery-core', 'jquery-migrate' ) as $handle ) { // "jquery" is an alias of these two.
		if ( wp_script_is( $handle, 'registered' ) && ! wp_scripts()->get_data( $handle, 'strategy' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}
	if ( wp_style_is( 'pdfemb_embed_pdf_css', 'enqueued' ) ) {
		$css   = (string) file_get_contents( get_theme_file_path( 'assets/pdf-embedder.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$after = wp_styles()->get_data( 'pdfemb_embed_pdf_css', 'after' );
		if ( ! is_array( $after ) || ! in_array( $css, $after, true ) ) {
			wp_add_inline_style( 'pdfemb_embed_pdf_css', $css );
		}
	}
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
 * Browser UI colour for light and dark mode: the paper of each, from theme.json or a chosen colour
 * preset (the "base" palette colour and settings.custom.color.dark.base), the same values the CSS
 * uses.
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
 * The dark-mode colours of the chosen colour preset. The theme ships none, but a child theme can
 * (styles/colors). A preset brings them along in settings.custom.color.dark, but when the person
 * who picks it can't save unfiltered HTML (a site administrator on a multisite network, say),
 * WordPress saves only the palette, and the site would show the preset in light mode and the
 * theme's own colours in dark mode. While the site's palette is still a preset's, the dark colours
 * come from that preset.
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

add_filter( 'render_block_core/image', 'tala_blog_hide_featured_image', 10, 2 );

/**
 * The templates don't show an article's or page's featured image on its own page, so an Image block
 * in its text that shows the same image is left out there too: many articles open with their
 * featured image as their first block. The text's other images stay, and so does the featured image
 * everywhere else (the text shown in any other place, link previews, structured data).
 *
 * @param string               $html  Image block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_hide_featured_image( $html, $block ) {
	$image = (int) ( $block['attrs']['id'] ?? 0 );
	if ( ! $image || ! doing_filter( 'the_content' ) || ! is_singular() || is_attachment() ) {
		return $html;
	}
	$post_id = (int) get_the_ID();
	if ( get_queried_object_id() !== $post_id || (int) get_post_thumbnail_id( $post_id ) !== $image ) {
		return $html;
	}
	return '';
}

add_filter( 'render_block_core/post-content', 'tala_blog_contents', 10, 3 );

/**
 * "On this page": on an article with enough sections, a table of contents right before its first
 * section, after the opening paragraphs. Closed, one line, until the reader opens it.
 * Appearance → Customize → Table of contents turns it off or sets how many sections it takes.
 *
 * @param string               $html     Post Content block HTML.
 * @param array<string, mixed> $block    Parsed block.
 * @param WP_Block             $instance Block instance.
 * @return string
 */
function tala_blog_contents( $html, $block, $instance ) {
	$post_id = (int) ( $instance->context['postId'] ?? 0 );
	if ( ! is_single() || is_attachment() || get_queried_object_id() !== $post_id || post_password_required( $post_id )
		|| ! get_theme_mod( 'tala_blog_contents', true ) ) {
		return $html;
	}
	return tala_blog_add_contents( $html, tala_blog_sections_needed() );
}

/**
 * How many sections an article needs for its table of contents (2 to 10, 3 by default).
 *
 * @return int
 */
function tala_blog_sections_needed() {
	return tala_blog_sanitize_sections( get_theme_mod( 'tala_blog_contents_sections', 3 ) );
}

/**
 * The number of sections setting, kept between 2 and 10; anything that isn't a number is 3.
 *
 * @param mixed $value Value to check.
 * @return int
 */
function tala_blog_sanitize_sections( $value ) {
	if ( ! is_numeric( $value ) ) {
		return 3;
	}
	return max( 2, min( 10, (int) $value ) );
}

/**
 * Adds the table of contents to an article's HTML (the Post Content block's), when it has at least
 * $needed sections.
 *
 * Its sections are the article's highest heading level (H2, or H3 when it has no H2…), each with
 * the level below as subsections; headings inside an answer (details), a quotation, a figure, a
 * table, a form or another navigation are left out, and so is a heading with the class
 * "tala-no-contents", or every heading in a block with it (Advanced → Additional CSS class). Following Google's guidance for links and
 * "Jump to" links in results, each entry is a plain link to its heading, named by the heading's own
 * words, to an anchor that describes the section: a heading keeps the id it has (links to it keep
 * working), one without gets an id made from its words, and so does one whose id the article uses
 * twice or that the classic editor left (mce_…). The box goes before the article's top-level
 * block that holds the first section, so the opening paragraphs come first. The label is not a
 * heading, so the article's outline stays its own. Read with WordPress's HTML API; HTML it can't
 * read is left as it is, without a table of contents.
 *
 * @param string $html   Article HTML, wrapped in its Post Content element.
 * @param int    $needed Sections needed.
 * @return string
 */
function tala_blog_add_contents( $html, $needed ) {
	// Most articles are short: no need to read one with fewer headings than sections needed.
	if ( preg_match_all( '/<h[234][\s>]/i', $html ) < $needed ) {
		return $html;
	}
	$found = tala_blog_read_headings( $html );
	if ( null === $found ) {
		return $html;
	}
	$usable = array_filter( $found['headings'], static fn( $heading ) => $heading['usable'] );
	if ( ! $usable ) {
		return $html;
	}
	$top      = min( array_column( $usable, 'level' ) );
	$taken    = array_fill_keys( array_merge( $found['ids'], tala_blog_reserved_ids() ), true );
	$uses     = array_count_values( $found['ids'] );
	$sections = array();
	$section  = null; // The section being gathered, with its subsections.
	$new_ids  = array();
	$first    = null;
	foreach ( $usable as $index => $heading ) {
		$is_section = $heading['level'] === $top;
		if ( ! $is_section && ( $heading['level'] !== $top + 1 || null === $section ) ) {
			continue;
		}
		$id = $heading['id'];
		// An id the article uses twice can't be jumped to, and the classic editor's mce_… ids were
		// never anchors: such a heading gets a new one too.
		if ( '' === $id || 1 !== ( $uses[ $id ] ?? 0 ) || preg_match( '/^mce_\d+$/', $id ) ) {
			$id                = tala_blog_unique_id( tala_blog_anchor( $heading['text'] ), $taken );
			$new_ids[ $index ] = $id;
		}
		if ( $is_section ) {
			if ( null !== $section ) {
				$sections[] = $section;
			}
			$section = array(
				'id'       => $id,
				'text'     => $heading['text'],
				'children' => array(),
			);
			$first   = $first ?? $heading['block'];
		} elseif ( null !== $section ) {
			$section['children'][] = array(
				'id'   => $id,
				'text' => $heading['text'],
			);
		}
	}
	if ( null !== $section ) {
		$sections[] = $section;
	}
	if ( count( $sections ) < $needed || null === $first ) {
		return $html;
	}
	return tala_blog_write_contents( $html, $new_ids, $first, tala_blog_contents_box( $sections ) );
}

/**
 * The article's H2 to H4 headings in order (level, text, id, the index of the top-level block they
 * are in, whether they may be a section) and every id in it; null when the HTML API can't read it.
 *
 * @param string $html Article HTML, wrapped in its Post Content element.
 * @return array{headings: list<array{level: int, text: string, id: string, block: int, usable: bool}>, ids: list<string>}|null
 */
function tala_blog_read_headings( $html ) {
	$processor = WP_HTML_Processor::create_fragment( $html );
	if ( null === $processor ) {
		return null;
	}
	$headings = array();
	$ids      = array();
	$base     = null; // The depth of the article's top-level blocks: inside its wrapper.
	$block    = -1;
	$outside  = array( 'DETAILS', 'BLOCKQUOTE', 'FIGURE', 'TABLE', 'FORM', 'NAV', 'ASIDE' );
	$skipped  = null; // The depth of an open element with the class "tala-no-contents".
	$reading  = null; // The heading whose text is being read.
	while ( $processor->next_token() ) {
		$type   = $processor->get_token_type();
		$closer = $processor->is_tag_closer();
		$depth  = $processor->get_current_depth();
		$tag    = (string) $processor->get_tag();
		if ( null !== $skipped && $depth < $skipped ) {
			$skipped = null;
		}
		if ( null !== $reading ) {
			if ( '#text' === $type ) {
				$reading['text'] .= $processor->get_modifiable_text();
			} elseif ( $closer && $reading['tag'] === $tag ) {
				$text       = trim( (string) preg_replace( '/\s+/u', ' ', $reading['text'] ) );
				$headings[] = array(
					'level'  => $reading['level'],
					'text'   => $text,
					'id'     => $reading['id'],
					'block'  => $reading['block'],
					'usable' => $reading['usable'] && '' !== $text,
				);
				$reading    = null;
				continue;
			}
		}
		if ( '#tag' !== $type || $closer ) {
			continue;
		}
		if ( null === $skipped && $processor->has_class( 'tala-no-contents' ) ) {
			$skipped = $depth;
		}
		$id = $processor->get_attribute( 'id' );
		$id = is_string( $id ) ? $id : '';
		if ( '' !== $id ) {
			$ids[] = $id;
		}
		if ( null === $base ) {
			$base = $depth + 1;
			continue;
		}
		if ( $depth === $base ) {
			++$block;
		}
		if ( null === $reading && in_array( $tag, array( 'H2', 'H3', 'H4' ), true ) ) {
			$reading = array(
				'tag'    => $tag,
				'level'  => (int) substr( $tag, 1 ),
				'text'   => '',
				'id'     => $id,
				'block'  => $block,
				'usable' => null === $skipped && ! array_intersect( $processor->get_breadcrumbs(), $outside ),
			);
		}
	}
	if ( null !== $processor->get_last_error() || $processor->paused_at_incomplete_token() ) {
		return null;
	}
	return array(
		'headings' => $headings,
		'ids'      => $ids,
	);
}

/**
 * Writes the new heading ids into the article and puts the table of contents before its top-level
 * block number $first. The HTML API marks that block; the box goes in front of the marked tag.
 *
 * @param string             $html    Article HTML.
 * @param array<int, string> $new_ids New ids by heading index (H2 to H4, in order).
 * @param int                $first   Index of the top-level block that holds the first section.
 * @param string             $box     The table of contents.
 * @return string
 */
function tala_blog_write_contents( $html, $new_ids, $first, $box ) {
	$processor = WP_HTML_Processor::create_fragment( $html );
	if ( null === $processor ) {
		return $html;
	}
	$base    = null;
	$block   = -1;
	$heading = -1;
	while ( $processor->next_tag() ) {
		$depth = $processor->get_current_depth();
		if ( null === $base ) {
			$base = $depth + 1;
			continue;
		}
		if ( $depth === $base && ++$block === $first ) {
			$processor->set_attribute( 'data-tala-contents', 'here' );
		}
		if ( in_array( $processor->get_tag(), array( 'H2', 'H3', 'H4' ), true ) && isset( $new_ids[ ++$heading ] ) ) {
			$processor->set_attribute( 'id', $new_ids[ $heading ] );
		}
	}
	if ( null !== $processor->get_last_error() ) {
		return $html;
	}
	$updated = $processor->get_updated_html();
	$marker  = ' data-tala-contents="here"';
	$at      = strpos( $updated, $marker );
	// A new attribute follows the tag's name, so the tag starts at the last "<" before it.
	$start = false === $at ? false : strrpos( substr( $updated, 0, $at ), '<' );
	if ( false === $at || false === $start ) {
		return $html;
	}
	$updated = substr_replace( $updated, '', $at, strlen( $marker ) );
	return substr_replace( $updated, $box, $start, 0 );
}

/**
 * An anchor that describes a section: its heading's words, lowercase and hyphenated, at most 60
 * characters cut between words ("start-with-the-objective").
 *
 * @param string $text Heading text.
 * @return string
 */
function tala_blog_anchor( $text ) {
	$anchor = sanitize_title( $text );
	if ( strlen( $anchor ) > 60 ) {
		$cut    = strrpos( substr( $anchor, 0, 61 ), '-' );
		$anchor = substr( $anchor, 0, $cut ? $cut : 60 );
	}
	return '' === $anchor ? 'section' : $anchor;
}

/**
 * The anchor, or the anchor with -2, -3… when the page already has it; then taken.
 *
 * @param string              $anchor Anchor.
 * @param array<string, bool> $taken  Ids in use, updated.
 * @return string
 */
function tala_blog_unique_id( $anchor, &$taken ) {
	$id = $anchor;
	for ( $n = 2; isset( $taken[ $id ] ); $n++ ) {
		$id = $anchor . '-' . $n;
	}
	$taken[ $id ] = true;
	return $id;
}

/**
 * Ids the rest of an article page uses (comments and the skip link), which a heading's anchor
 * mustn't take.
 *
 * @return list<string>
 */
function tala_blog_reserved_ids() {
	return array( 'comments', 'respond', 'reply-title', 'commentform', 'comment', 'author', 'email', 'url', 'submit', 'cancel-comment-reply-link', 'wp--skip-link--target' );
}

/**
 * The table of contents, as the design system's: a disclosure that starts closed at every width, its summary
 * "On this page · 4 sections" between a list icon and a chevron, which opens into a navigation
 * landmark named "On this page": the sections numbered 01, 02… (numbers the eye needs, hidden from
 * screen readers, so each link is named by its heading's words alone), each with its subsections.
 *
 * @param list<array{id: string, text: string, children: list<array{id: string, text: string}>}> $sections Sections.
 * @return string
 */
function tala_blog_contents_box( $sections ) {
	$items = '';
	foreach ( $sections as $index => $section ) {
		$children = '';
		foreach ( $section['children'] as $child ) {
			$children .= '<li><a href="#' . esc_attr( $child['id'] ) . '">' . esc_html( $child['text'] ) . '</a></li>';
		}
		$items .= '<li><a href="#' . esc_attr( $section['id'] ) . '"><span class="tala-contents-num" aria-hidden="true">' . sprintf( '%02d', $index + 1 ) . '</span>'
			. esc_html( $section['text'] ) . '</a>' . ( '' !== $children ? '<ol>' . $children . '</ol>' : '' ) . '</li>';
	}
	$title = __( 'On this page', 'tala-blog' );
	$count = sprintf(
		/* translators: %s: number of sections in the article. */
		_n( '%s section', '%s sections', count( $sections ), 'tala-blog' ),
		number_format_i18n( count( $sections ) )
	);
	$svg = '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>';
	return '<details class="tala-contents"><summary>'
		. sprintf( $svg, 'tala-contents-icon', '<path d="M4 7h.5M4 12h.5M4 17h.5M9 7h11M9 12h11M9 17h7"/>' )
		. '<span class="tala-contents-label"><span class="tala-contents-title">' . esc_html( $title ) . '</span> '
		. '<span class="tala-contents-count">' . esc_html( $count ) . '</span></span>'
		. sprintf( $svg, 'tala-contents-chevron', '<polyline points="5 8.5 12 15.5 19 8.5"/>' )
		. '</summary><nav aria-label="' . esc_attr( $title ) . '"><ol>' . $items . '</ol></nav></details>';
}

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

add_filter( 'render_block_core/post-terms', 'tala_blog_first_term', 10, 2 );

/**
 * A Categories or Tags block with the class "tala-first-term" shows only the post's first term,
 * as the category beside a row's date in article lists: its wrapper and first link, without the
 * other links, the separators, or a prefix or suffix. Other term blocks keep every term.
 *
 * @param string               $html  Rendered Post Terms block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_blog_first_term( string $html, array $block ): string {
	if ( ! tala_blog_block_has_class( $block, 'tala-first-term' ) ) {
		return $html;
	}
	$tags = WP_HTML_Processor::create_fragment( $html );
	if ( null === $tags || ! $tags->next_tag() ) {
		return $html;
	}
	// A closing tag reports the depth around it: the links' at the wrapper's, the wrapper's one less.
	$wrapper = $tags->get_current_depth();
	$out     = $tags->serialize_token();
	$link    = 'before';
	while ( $tags->next_token() ) {
		$depth  = $tags->get_current_depth();
		$closer = $tags->is_tag_closer();
		if ( $closer && $depth < $wrapper ) {
			return 'after' === $link && null === $tags->get_last_error() ? $out . $tags->serialize_token() : $html;
		}
		if ( 'before' === $link && ! $closer && 'A' === $tags->get_token_name() && $wrapper + 1 === $depth ) {
			$link = 'in';
		}
		if ( 'in' === $link ) {
			$out .= $tags->serialize_token();
			if ( $closer && 'A' === $tags->get_token_name() && $wrapper === $depth ) {
				$link = 'after';
			}
		}
	}
	return $html;
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
 * Appearance → Customize → Table of contents: whether articles have one (on by default) and how
 * many sections it takes (3).
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

	$wp_customize->add_section(
		'tala_blog_contents',
		array(
			'title'       => __( 'Table of contents', 'tala-blog' ),
			'description' => __( '"On this page": a list of the article\'s sections, after its opening paragraphs, that links to each one. It opens with a tap. To leave a heading out, give it (or a group around it) the class tala-no-contents under Advanced → Additional CSS class(es).', 'tala-blog' ),
			'priority'    => 121,
		)
	);
	$wp_customize->add_setting(
		'tala_blog_contents',
		array(
			'default'           => '1',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	$wp_customize->add_control(
		'tala_blog_contents',
		array(
			'section' => 'tala_blog_contents',
			'type'    => 'checkbox',
			'label'   => __( 'Show a table of contents on articles', 'tala-blog' ),
		)
	);
	$wp_customize->add_setting(
		'tala_blog_contents_sections',
		array(
			'default'           => '3',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => 'tala_blog_sanitize_sections',
		)
	);
	$wp_customize->add_control(
		'tala_blog_contents_sections',
		array(
			'section'     => 'tala_blog_contents',
			'type'        => 'number',
			'label'       => __( 'Sections needed', 'tala-blog' ),
			'description' => __( 'Articles with fewer sections (main headings) than this go without one.', 'tala-blog' ),
			'input_attrs' => array(
				'min'  => 2,
				'max'  => 10,
				'step' => 1,
			),
		)
	);

	$menus = array( 0 => __( 'Automatic', 'tala-blog' ) );
	foreach ( get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	) as $menu ) {
		$menus[ $menu->ID ] = '' === $menu->post_title ? '#' . $menu->ID : $menu->post_title;
	}
	$wp_customize->add_section(
		'tala_blog_menus',
		array(
			'title'       => __( 'Header and footer menus', 'tala-blog' ),
			'description' => __( 'Which menu the header and the footer show. Menus are made in Appearance → Editor → Navigation. "Automatic" leaves the choice to WordPress (its newest menu), but never takes the footer\'s menu for the header.', 'tala-blog' ),
			'priority'    => 119,
		)
	);
	foreach ( array(
		'tala_blog_header_menu' => __( 'Header menu', 'tala-blog' ),
		'tala_blog_footer_menu' => __( 'Footer menu', 'tala-blog' ),
	) as $id => $label ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => '0',
				'type'              => 'theme_mod',
				'capability'        => 'edit_theme_options',
				'sanitize_callback' => 'tala_blog_sanitize_menu',
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'section' => 'tala_blog_menus',
				'type'    => 'select',
				'label'   => $label,
				'choices' => $menus,
			)
		);
	}
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
