<?php
/**
 * The Course block: a course's description, provider, level, format, time, instructor and cost,
 * shown as a card and described as a Course, following Google Search Central (checked October 2026):
 * - Course list, a carousel of a site's courses in English searches, needs each course's name,
 *   description and provider. A post with one course is its detail page; an archive whose posts
 *   are all courses (three or more) is the summary page that lists them. A post with several
 *   courses lists them itself, each by the anchor on its card. Every list holds all its items.
 * - Google retired the Course info result (level, format, workload, instructor, price) in June
 *   2025. Those details are still valid schema.org that other search engines and AI assistants
 *   can read, and they come only from what the card shows.
 * - A course has lessons or modules, a learning outcome and an instructor; an event or a single
 *   short video is not a course. The block's help says so.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tala_seo_register_course_block' );

/**
 * Registers the Course block (server-rendered).
 */
function tala_seo_register_course_block(): void {
	register_block_type(
		dirname( __DIR__ ) . '/blocks/course',
		array( 'render_callback' => 'tala_seo_render_course' )
	);
}

/**
 * Course formats (schema.org courseMode) and how the card shows them.
 *
 * @return array<string, string>
 */
function tala_seo_course_modes() {
	return array(
		'Online'  => __( 'Online', 'tala-seo' ),
		'Onsite'  => __( 'In person', 'tala-seo' ),
		'Blended' => __( 'Online and in person', 'tala-seo' ),
	);
}

/**
 * A Course block's details, checked and filled in: the name defaults to the post title and the
 * provider to the site (Settings → SEO → site owner).
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param WP_Post|null         $post       The post it is in.
 * @return array<string, mixed>|null Null without a name.
 */
function tala_seo_course_data( array $attributes, $post ) {
	$text = static fn( $key ) => trim( sanitize_text_field( tala_seo_scalar( $attributes[ $key ] ?? '' ) ) );

	$name = $text( 'name' );
	if ( '' === $name && $post ) {
		$name = tala_seo_clean_text( get_the_title( $post ) );
	}
	if ( '' === $name ) {
		return null;
	}

	$provider      = $text( 'provider' );
	$provider_url  = '' !== $provider ? tala_seo_sanitize_url( $attributes['providerUrl'] ?? '' ) : '';
	$provider_type = 'Organization';
	if ( '' === $provider ) {
		// The site itself offers the course: its owner, as on the home page's markup.
		$settings      = tala_seo_settings();
		$provider      = '' !== $settings['publisher_name'] ? $settings['publisher_name'] : tala_seo_site_name();
		$provider_url  = home_url( '/' );
		$provider_type = 'person' === $settings['publisher_type'] ? 'Person' : $settings['organization_type'];
	}

	$hours = $text( 'workload' );
	$hours = is_numeric( $hours ) && (float) $hours > 0 ? (float) $hours : 0.0;
	$mode  = array_key_exists( $text( 'courseMode' ), tala_seo_course_modes() ) ? $text( 'courseMode' ) : '';
	$price = tala_seo_block_price( $attributes['price'] ?? '', $attributes['priceCurrency'] ?? '' );
	$slug  = sanitize_title( $name );

	return array(
		'name'          => $name,
		'description'   => trim( sanitize_textarea_field( tala_seo_scalar( $attributes['description'] ?? '' ) ) ),
		'provider'      => $provider,
		'provider_url'  => $provider_url,
		'provider_type' => $provider_type,
		'level'         => $text( 'educationalLevel' ),
		'mode'          => $mode,
		'hours'         => $hours,
		'instructor'    => $text( 'instructor' ),
		'price'         => $price['price'],
		'currency'      => $price['currency'],
		'enroll'        => tala_seo_sanitize_url( $attributes['enrollUrl'] ?? '' ),
		// The card's id, which a page with several courses points to.
		'anchor'        => 'course' . ( '' !== $slug ? '-' . $slug : '' ),
	);
}

/**
 * Renders the Course block: name, description, then provider, level, format, time, instructor
 * and cost.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function tala_seo_render_course( $attributes ) {
	$course = tala_seo_course_data( (array) $attributes, get_post() );
	if ( ! $course ) {
		return '';
	}
	$level = min( 6, max( 2, (int) ( $attributes['level'] ?? 2 ) ) );
	$html  = sprintf( '<h%1$d class="wp-block-tala-seo-course__name">%2$s</h%1$d>', $level, esc_html( $course['name'] ) );
	if ( '' !== $course['description'] ) {
		$html .= '<div class="wp-block-tala-seo-course__description">' . wpautop( esc_html( $course['description'] ) ) . '</div>';
	}

	$rows = array(
		__( 'Offered by', 'tala-seo' ) => esc_html( $course['provider'] ),
	);
	if ( '' !== $course['level'] ) {
		$rows[ __( 'Level', 'tala-seo' ) ] = esc_html( $course['level'] );
	}
	if ( '' !== $course['mode'] ) {
		$rows[ __( 'Format', 'tala-seo' ) ] = esc_html( tala_seo_course_modes()[ $course['mode'] ] );
	}
	if ( $course['hours'] ) {
		$hours                            = number_format_i18n( $course['hours'], floor( $course['hours'] ) === $course['hours'] ? 0 : 1 );
		$rows[ __( 'Time', 'tala-seo' ) ] = esc_html(
			/* translators: %s: number of hours. */
			sprintf( _n( 'About %s hour', 'About %s hours', (int) ceil( $course['hours'] ), 'tala-seo' ), $hours )
		);
	}
	if ( '' !== $course['instructor'] ) {
		$rows[ __( 'Instructor', 'tala-seo' ) ] = esc_html( $course['instructor'] );
	}
	$cost = array();
	if ( '' !== $course['price'] ) {
		$cost[] = tala_seo_price_html( $course['price'], $course['currency'] );
	}
	if ( '' !== $course['enroll'] ) {
		$cost[] = '<a href="' . esc_url( $course['enroll'] ) . '">' . esc_html__( 'Enroll', 'tala-seo' ) . '</a>';
	}
	if ( $cost ) {
		$rows[ __( 'Cost', 'tala-seo' ) ] = implode( ' · ', $cost );
	}
	$html .= tala_seo_details_html( 'wp-block-tala-seo-course__details', $rows );

	return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes( array( 'id' => $course['anchor'] ) ), $html );
}

/**
 * The courses in a post, when every Course block in it has what Google needs (a name and a
 * description); otherwise none, as a list must hold every course the page shows.
 *
 * @param WP_Post                          $post   Post.
 * @param array<array<string, mixed>>|null $blocks Parsed blocks of the post, if already parsed.
 * @return array<int, array<string, mixed>>
 */
function tala_seo_post_courses( $post, ?array $blocks = null ): array {
	if ( post_password_required( $post ) || ! str_contains( $post->post_content, '<!-- wp:tala-seo/course' ) ) {
		return array();
	}
	$courses = array();
	$found   = tala_seo_find_blocks( $blocks ?? tala_seo_visible_blocks( parse_blocks( $post->post_content ) ), static fn( $block ) => 'tala-seo/course' === $block['blockName'] );
	foreach ( $found as $block ) {
		$course = tala_seo_course_data( (array) ( $block['attrs'] ?? array() ), $post );
		if ( ! $course || '' === $course['description'] ) {
			return array();
		}
		$courses[] = $course;
	}
	return $courses;
}

/**
 * A Course node.
 *
 * @param array<string, mixed> $course Course details (tala_seo_course_data()).
 * @param string               $url    Address of the course: the post, or its card on the post.
 * @return array<string, mixed>
 */
function tala_seo_course_node( array $course, string $url ): array {
	$node = array(
		'@type'       => 'Course',
		'@id'         => $url . ( str_contains( $url, '#' ) ? '' : '#course' ),
		'url'         => $url,
		'name'        => $course['name'],
		'description' => $course['description'],
		'provider'    => array_filter(
			array(
				'@type'  => $course['provider_type'],
				'name'   => $course['provider'],
				'sameAs' => $course['provider_url'],
			)
		),
	);
	if ( '' !== $course['level'] ) {
		$node['educationalLevel'] = $course['level'];
	}

	$instance = array( '@type' => 'CourseInstance' );
	if ( '' !== $course['mode'] ) {
		$instance['courseMode'] = $course['mode'];
	}
	if ( $course['hours'] ) {
		// ISO 8601 duration: "PT6H", "PT1.5H".
		$instance['courseWorkload'] = 'PT' . $course['hours'] . 'H';
	}
	if ( '' !== $course['instructor'] ) {
		$instance['instructor'] = array(
			'@type' => 'Person',
			'name'  => $course['instructor'],
		);
	}
	if ( count( $instance ) > 1 ) {
		$node['hasCourseInstance'] = $instance;
	}

	$offer = array( '@type' => 'Offer' );
	if ( '' !== $course['price'] ) {
		$offer['category']      = 0.0 === (float) $course['price'] ? 'Free' : 'Paid';
		$offer['price']         = $course['price'];
		$offer['priceCurrency'] = $course['currency'];
	}
	if ( '' !== $course['enroll'] ) {
		$offer['url'] = $course['enroll'];
	}
	if ( count( $offer ) > 1 ) {
		$node['offers'] = $offer;
	}
	return $node;
}

/**
 * Structured data for the courses in a post: a Course for its one course, or an ItemList of its
 * courses, each at its card's anchor.
 *
 * @param WP_Post                     $post   Post.
 * @param array<array<string, mixed>> $blocks Parsed blocks of the post.
 * @return array<int, array<string, mixed>>
 */
function tala_seo_post_course_nodes( $post, array $blocks ): array {
	$courses = tala_seo_post_courses( $post, $blocks );
	$url     = (string) get_permalink( $post );
	if ( 1 === count( $courses ) ) {
		return array( tala_seo_course_node( $courses[0], $url ) );
	}
	if ( ! $courses ) {
		return array();
	}
	$items = array();
	foreach ( $courses as $i => $course ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'item'     => tala_seo_course_node( $course, $url . '#' . $course['anchor'] ),
		);
	}
	return array(
		array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#courses',
			'itemListElement' => $items,
		),
	);
}

/**
 * On a list of posts (home page, category, tag, author) whose posts are all courses, three or
 * more, the ItemList that makes it the courses' summary page.
 *
 * @return array<string, mixed>|null
 */
function tala_seo_course_list_node() {
	global $wp_query;
	$posts = $wp_query instanceof WP_Query ? $wp_query->posts : array();
	if ( ! is_array( $posts ) || count( $posts ) < 3 ) {
		return null;
	}
	$items = array();
	foreach ( array_values( $posts ) as $i => $post ) {
		if ( ! $post instanceof WP_Post || 1 !== count( tala_seo_post_courses( $post ) ) ) {
			return null;
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => (string) get_permalink( $post ),
		);
	}
	return array(
		'@type'           => 'ItemList',
		'itemListElement' => $items,
	);
}
