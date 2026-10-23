<?php
/**
 * Structured data for what a post shows, read from its blocks (schema.php adds it to the graph):
 * - VideoObject for each YouTube video in an Embed block. YouTube's oEmbed gives the title and
 *   thumbnail, fetched once when the post is saved, never while a page is served. It has no
 *   publish date, which Google requires, so the author enters it in the SEO panel; a video
 *   without one gets no markup.
 * - FAQPage from "FAQ" groups: each Details block is a question (its summary) with its answer.
 * - HowTo from "How-to" groups: the heading names it, paragraphs before the steps describe it,
 *   and each item of the numbered list is a step.
 * - Event from the Event block (event.php), Course from the Course block (course.php).
 *
 * Google stopped showing HowTo results in 2023 and FAQ results in May 2026. The markup is still
 * valid schema.org that other search engines and AI assistants may read, and it repeats only what
 * the page shows. Without the plugin, FAQ and How-to groups are ordinary core blocks.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

// The classes that mark a Group block as an FAQ or a how-to guide (set by the block variations).
const TALA_SEO_FAQ_CLASS   = 'tala-seo-faq';
const TALA_SEO_HOWTO_CLASS = 'tala-seo-howto';

// A YouTube video ID, as a regular expression for the REST schema of the video dates.
const TALA_SEO_YOUTUBE_ID_PATTERN = '^[A-Za-z0-9_-]{11}$';

/**
 * Structured data nodes for a post's videos, FAQ, how-to guides, event and courses.
 *
 * @param WP_Post $post Post.
 * @return array<int, array<string, mixed>>
 */
function tala_seo_content_nodes( $post ) {
	// A password-protected post doesn't show its content until the password is entered.
	if ( post_password_required( $post ) || ! has_blocks( $post ) ) {
		return array();
	}
	$url    = (string) get_permalink( $post );
	$blocks = parse_blocks( $post->post_content );
	return array_merge(
		tala_seo_video_nodes( $post, $blocks, $url ),
		array_filter( array( tala_seo_faq_node( $blocks, $url ) ) ),
		tala_seo_howto_nodes( $blocks, $url ),
		array_filter( array( tala_seo_post_event_node( $post, $blocks ) ) ),
		tala_seo_post_course_nodes( $post, $blocks )
	);
}

/**
 * Blocks anywhere in a tree that $accept accepts, in the order they appear. The blocks inside an
 * accepted block aren't searched.
 *
 * @param array<array<string, mixed>> $blocks Parsed blocks.
 * @param callable                    $accept Whether to take a block.
 * @phpstan-param callable(array<string, mixed>): bool $accept
 * @return array<int, array<string, mixed>>
 */
function tala_seo_find_blocks( array $blocks, callable $accept ): array {
	$found = array();
	foreach ( $blocks as $block ) {
		if ( $accept( $block ) ) {
			$found[] = $block;
		} elseif ( ! empty( $block['innerBlocks'] ) ) {
			array_push( $found, ...tala_seo_find_blocks( $block['innerBlocks'], $accept ) );
		}
	}
	return $found;
}

/**
 * Whether a parsed block is a Group with the given class.
 *
 * @param array<string, mixed> $block      Parsed block.
 * @param string               $class_name Class.
 * @return bool
 */
function tala_seo_is_group( array $block, string $class_name ): bool {
	return 'core/group' === $block['blockName']
		&& in_array( $class_name, explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) ), true );
}

/**
 * The saved HTML of a parsed block, inner blocks included (what the post shows, before dynamic
 * blocks render).
 *
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function tala_seo_block_html( array $block ): string {
	$html  = '';
	$inner = 0;
	foreach ( $block['innerContent'] ?? array() as $chunk ) {
		$html .= is_string( $chunk ) ? $chunk : tala_seo_block_html( $block['innerBlocks'][ $inner++ ] );
	}
	return $html;
}

/**
 * Plain text of block HTML, one line per paragraph, heading or list item.
 *
 * @param string $html HTML.
 * @return string
 */
function tala_seo_html_text( string $html ): string {
	$html  = (string) preg_replace( '/\s+/u', ' ', strip_shortcodes( $html ) );
	$html  = (string) preg_replace( '#<br\s*/?>|</?(?:p|h[1-6]|ul|ol|li|dl|dt|dd|div|figure|figcaption|blockquote|pre|table|tr|details|summary)\b[^>]*>#i', "\n$0\n", $html );
	$text  = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$lines = array_map( static fn( $line ) => trim( (string) preg_replace( '/\s+/u', ' ', $line ) ), explode( "\n", $text ) );
	return implode( "\n", array_filter( $lines, static fn( $line ) => '' !== $line ) );
}

/**
 * FAQPage from the post's FAQ groups: one question for each Details block that has an answer.
 *
 * @param array<array<string, mixed>> $blocks Parsed blocks.
 * @param string                      $url    Post URL.
 * @return array<string, mixed>|null
 */
function tala_seo_faq_node( array $blocks, string $url ) {
	$questions = array();
	$groups    = tala_seo_find_blocks( $blocks, static fn( $block ) => tala_seo_is_group( $block, TALA_SEO_FAQ_CLASS ) );
	foreach ( $groups as $group ) {
		$all_details = tala_seo_find_blocks( $group['innerBlocks'], static fn( $block ) => 'core/details' === $block['blockName'] );
		foreach ( $all_details as $details ) {
			// The question is the <summary>, at the start of the block's own HTML; the answer is the
			// blocks inside it.
			$opening  = (string) ( $details['innerContent'][0] ?? '' );
			$question = preg_match( '#<summary\b[^>]*>(.*?)</summary>#is', $opening, $match ) ? tala_seo_clean_text( $match[1] ) : '';
			$answer   = tala_seo_html_text( implode( '', array_map( 'tala_seo_block_html', $details['innerBlocks'] ) ) );
			if ( '' !== $question && '' !== $answer && ! isset( $questions[ $question ] ) ) {
				$questions[ $question ] = array(
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $answer,
					),
				);
			}
		}
	}
	if ( ! $questions ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => $url . '#faq',
		'url'        => $url,
		'mainEntity' => array_values( $questions ),
	);
}

/**
 * HowTo nodes from the post's How-to groups: a heading and a numbered list of two steps or more.
 *
 * @param array<array<string, mixed>> $blocks Parsed blocks.
 * @param string                      $url    Post URL.
 * @return array<int, array<string, mixed>>
 */
function tala_seo_howto_nodes( array $blocks, string $url ): array {
	$nodes = array();
	foreach ( tala_seo_find_blocks( $blocks, static fn( $block ) => tala_seo_is_group( $block, TALA_SEO_HOWTO_CLASS ) ) as $group ) {
		$name        = '';
		$description = array();
		$steps       = array();
		foreach ( $group['innerBlocks'] as $block ) {
			if ( 'core/heading' === $block['blockName'] && '' === $name ) {
				$name = tala_seo_clean_text( tala_seo_block_html( $block ) );
			} elseif ( 'core/paragraph' === $block['blockName'] ) {
				$description[] = tala_seo_html_text( tala_seo_block_html( $block ) );
			} elseif ( 'core/list' === $block['blockName'] && ! empty( $block['attrs']['ordered'] ) ) {
				foreach ( $block['innerBlocks'] as $item ) {
					$text = 'core/list-item' === $item['blockName'] ? tala_seo_html_text( tala_seo_block_html( $item ) ) : '';
					if ( '' !== $text ) {
						$steps[] = array(
							'@type'    => 'HowToStep',
							'position' => count( $steps ) + 1,
							'text'     => $text,
						);
					}
				}
				break;
			}
		}
		if ( '' === $name || count( $steps ) < 2 ) {
			continue;
		}
		$node        = array(
			'@type' => 'HowTo',
			'@id'   => $url . '#howto-' . ( count( $nodes ) + 1 ),
			'name'  => $name,
		);
		$description = implode( "\n", array_filter( $description ) );
		if ( '' !== $description ) {
			$node['description'] = $description;
		}
		$node['step'] = $steps;
		$nodes[]      = $node;
	}
	return $nodes;
}

/**
 * The video ID in a YouTube address (watch, youtu.be, Shorts, live and embed addresses), or ''.
 *
 * @param string $url Address.
 * @return string
 */
function tala_seo_youtube_id( string $url ): string {
	$pattern = '#^https?://(?:(?:www\.|m\.)?youtube(?:-nocookie)?\.com/(?:watch\?(?:[^\#]*&)?v=|shorts/|live/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])#i';
	return preg_match( $pattern, trim( $url ), $match ) ? $match[1] : '';
}

/**
 * The post's YouTube videos (Embed blocks), each once, in the order they appear.
 *
 * @param array<array<string, mixed>> $blocks Parsed blocks.
 * @return array<string, array<string, mixed>> Video ID => its Embed block.
 */
function tala_seo_youtube_videos( array $blocks ): array {
	$videos = array();
	$embeds = tala_seo_find_blocks( $blocks, static fn( $block ) => 'core/embed' === $block['blockName'] );
	foreach ( $embeds as $embed ) {
		$id = tala_seo_youtube_id( (string) ( $embed['attrs']['url'] ?? '' ) );
		if ( '' !== $id && ! isset( $videos[ $id ] ) ) {
			$videos[ $id ] = $embed;
		}
	}
	return $videos;
}

/**
 * The date a video was published (as the author entered it, '2026-03-14') in ISO 8601 with the
 * site's timezone, as Google recommends, or '' when it isn't a real date.
 *
 * @param mixed $date Date.
 * @return string
 */
function tala_seo_video_upload_date( $date ): string {
	$date = is_string( $date ) ? $date : '';
	$day  = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	return $day && $day->format( 'Y-m-d' ) === $date ? $day->format( 'c' ) : '';
}

/**
 * Keeps the video dates that are real dates of real video IDs.
 *
 * @param mixed $value Video ID => date.
 * @return array<string, string>
 */
function tala_seo_sanitize_video_dates( $value ) {
	$dates = array();
	foreach ( is_array( $value ) ? $value : array() as $id => $date ) {
		$id = (string) $id;
		if ( preg_match( '/' . TALA_SEO_YOUTUBE_ID_PATTERN . '/', $id ) && '' !== tala_seo_video_upload_date( $date ) ) {
			$dates[ $id ] = $date;
		}
	}
	return $dates;
}

/**
 * VideoObject nodes for the post's YouTube videos that have a title and a publish date.
 *
 * @param WP_Post                     $post   Post.
 * @param array<array<string, mixed>> $blocks Parsed blocks.
 * @param string                      $url    Post URL.
 * @return array<int, array<string, mixed>>
 */
function tala_seo_video_nodes( $post, array $blocks, string $url ): array {
	$videos = tala_seo_youtube_videos( $blocks );
	if ( ! $videos ) {
		return array();
	}
	$known = get_post_meta( $post->ID, TALA_SEO_VIDEOS, true );
	$dates = get_post_meta( $post->ID, TALA_SEO_VIDEO_DATES, true );
	$nodes = array();
	foreach ( $videos as $id => $embed ) {
		$video = is_array( $known ) ? ( $known[ $id ] ?? null ) : null;
		$date  = tala_seo_video_upload_date( is_array( $dates ) ? ( $dates[ $id ] ?? '' ) : '' );
		if ( ! is_array( $video ) || '' === $date ) {
			continue;
		}
		$node = array(
			'@type'        => 'VideoObject',
			'@id'          => $url . '#video-' . $id,
			'name'         => $video['name'],
			'thumbnailUrl' => $video['thumbnail'],
			'uploadDate'   => $date,
			'embedUrl'     => 'https://www.youtube.com/embed/' . $id,
		);
		// The caption under the video describes it, when there is one.
		if ( preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#is', tala_seo_block_html( $embed ), $match ) ) {
			$caption = tala_seo_clean_text( $match[1] );
			if ( '' !== $caption ) {
				$node['description'] = $caption;
			}
		}
		$nodes[] = $node;
	}
	return $nodes;
}

add_action( 'wp_after_insert_post', 'tala_seo_remember_videos', 10, 2 );

/**
 * When a post is saved, fetches the title and thumbnail of each YouTube video it doesn't know yet
 * from YouTube's oEmbed, and forgets the videos the post no longer has. A failed fetch is tried
 * again on the next save.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function tala_seo_remember_videos( $post_id, $post ): void {
	if ( ! in_array( $post->post_type, tala_seo_post_types(), true ) ) {
		return;
	}
	$known  = get_post_meta( $post_id, TALA_SEO_VIDEOS, true );
	$known  = is_array( $known ) ? $known : array();
	$videos = array();
	foreach ( array_keys( tala_seo_youtube_videos( parse_blocks( $post->post_content ) ) ) as $id ) {
		$video = $known[ $id ] ?? tala_seo_fetch_video( (string) $id );
		if ( $video ) {
			$videos[ $id ] = $video;
		}
	}
	if ( $videos === $known ) {
		return;
	}
	if ( $videos ) {
		update_post_meta( $post_id, TALA_SEO_VIDEOS, wp_slash( $videos ) );
	} else {
		delete_post_meta( $post_id, TALA_SEO_VIDEOS );
	}
}

/**
 * A YouTube video's title and thumbnail from YouTube's oEmbed, or null when it can't be had.
 *
 * @param string $id Video ID.
 * @return array{name: string, thumbnail: string}|null
 */
function tala_seo_fetch_video( string $id ) {
	$endpoint = add_query_arg(
		array(
			'url'    => rawurlencode( 'https://www.youtube.com/watch?v=' . $id ),
			'format' => 'json',
		),
		'https://www.youtube.com/oembed'
	);
	$response = wp_safe_remote_get( $endpoint, array( 'timeout' => 5 ) );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$name = is_array( $data ) && is_string( $data['title'] ?? null ) ? sanitize_text_field( $data['title'] ) : '';
	if ( '' === $name ) {
		return null;
	}
	$thumbnail = is_string( $data['thumbnail_url'] ?? null ) ? tala_seo_sanitize_url( $data['thumbnail_url'] ) : '';
	return array(
		'name'      => $name,
		'thumbnail' => '' !== $thumbnail ? $thumbnail : 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
	);
}

/**
 * A price for structured data: a number of 0 or more with its currency code (ISO 4217), or '' for
 * both when either is missing or wrong, as one without the other means nothing.
 *
 * @param mixed $price    Price.
 * @param mixed $currency Currency code (any case).
 * @return array{price: string, currency: string}
 */
function tala_seo_block_price( $price, $currency ): array {
	$price    = trim( sanitize_text_field( (string) $price ) );
	$currency = strtoupper( trim( sanitize_text_field( (string) $currency ) ) );
	if ( ! is_numeric( $price ) || (float) $price < 0 || ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
		return array(
			'price'    => '',
			'currency' => '',
		);
	}
	return array(
		'price'    => $price,
		'currency' => $currency,
	);
}

/**
 * A price as a card shows it, escaped: "Free" or "PHP 1,500".
 *
 * @param string $price    Price (tala_seo_block_price()).
 * @param string $currency Currency code.
 * @return string
 */
function tala_seo_price_html( string $price, string $currency ): string {
	$amount = (float) $price;
	if ( 0.0 === $amount ) {
		return esc_html__( 'Free', 'tala-seo' );
	}
	return esc_html( $currency . ' ' . number_format_i18n( $amount, floor( $amount ) === $amount ? 0 : 2 ) );
}

/**
 * A card's details as a description list: labels beside their values.
 *
 * @param string                $class_name Class of the list.
 * @param array<string, string> $rows       Label => value (escaped HTML).
 * @return string
 */
function tala_seo_details_html( string $class_name, array $rows ): string {
	$html = '<dl class="' . esc_attr( $class_name ) . '">';
	foreach ( $rows as $label => $value ) {
		$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>';
	}
	return $html . '</dl>';
}

add_filter( 'get_block_type_variations', 'tala_seo_group_variations', 10, 2 );

/**
 * "FAQ" and "How-to" in the block inserter: Group blocks set up with core blocks, which the
 * structured data above reads. They stay ordinary groups if the plugin is switched off.
 *
 * @param array<int, array<string, mixed>> $variations Variations of the block.
 * @param WP_Block_Type                    $block_type Block type.
 * @return array<int, array<string, mixed>>
 */
function tala_seo_group_variations( $variations, $block_type ) {
	if ( 'core/group' !== $block_type->name ) {
		return $variations;
	}
	$question = array(
		'core/details',
		array( 'placeholder' => __( 'Question', 'tala-seo' ) ),
		array( array( 'core/paragraph', array( 'placeholder' => __( 'Answer', 'tala-seo' ) ) ) ),
	);
	$step     = array( 'core/list-item', array( 'placeholder' => __( 'Step', 'tala-seo' ) ) );

	$variations[] = array(
		'name'        => TALA_SEO_FAQ_CLASS,
		'title'       => __( 'FAQ', 'tala-seo' ),
		'description' => __( 'Questions with answers that open on click, one Details block each. Described as an FAQ for search engines and AI assistants.', 'tala-seo' ),
		'icon'        => 'editor-help',
		'keywords'    => array( 'faq', __( 'questions', 'tala-seo' ), __( 'answers', 'tala-seo' ) ),
		'scope'       => array( 'inserter', 'transform' ),
		'attributes'  => array( 'className' => TALA_SEO_FAQ_CLASS ),
		'isActive'    => array( 'className' ),
		'innerBlocks' => array(
			array( 'core/heading', array( 'content' => __( 'Frequently asked questions', 'tala-seo' ) ) ),
			$question,
			$question,
			$question,
		),
	);
	$variations[] = array(
		'name'        => TALA_SEO_HOWTO_CLASS,
		'title'       => __( 'How-to', 'tala-seo' ),
		'description' => __( 'Steps in a numbered list under a heading. Described as a how-to guide for search engines and AI assistants.', 'tala-seo' ),
		'icon'        => 'editor-ol',
		'keywords'    => array( 'howto', __( 'steps', 'tala-seo' ), __( 'guide', 'tala-seo' ), __( 'tutorial', 'tala-seo' ) ),
		'scope'       => array( 'inserter', 'transform' ),
		'attributes'  => array( 'className' => TALA_SEO_HOWTO_CLASS ),
		'isActive'    => array( 'className' ),
		'innerBlocks' => array(
			array( 'core/heading', array( 'placeholder' => __( 'How to …', 'tala-seo' ) ) ),
			array( 'core/paragraph', array( 'placeholder' => __( 'What the steps achieve, and what the reader needs first.', 'tala-seo' ) ) ),
			array( 'core/list', array( 'ordered' => true ), array( $step, $step, $step ) ),
		),
	);
	return $variations;
}
