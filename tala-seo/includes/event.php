<?php
/**
 * The Event block: an event's date, place, tickets and organizer shown as a card, and the same
 * details as Event structured data, following Google Search Central (checked October 2026):
 * - name, startDate with the UTC offset (date only for an all-day event) and a Place with a
 *   PostalAddress are required; the markup is left out until the post has them.
 * - one event per page: the block can be used once per post, and a post that has it twice
 *   anyway (code editor) gets no Event markup.
 * - Google shows only events at a physical place that the public can book; the block says so.
 * Times are in the site's timezone (Settings → General).
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tala_seo_register_event_block' );

/**
 * Registers the Event block (server-rendered).
 */
function tala_seo_register_event_block(): void {
	register_block_type(
		dirname( __DIR__ ) . '/blocks/event',
		array( 'render_callback' => 'tala_seo_render_event' )
	);
}

/**
 * Event status values (schema.org) and how the card shows them.
 *
 * @return array<string, string>
 */
function tala_seo_event_statuses() {
	return array(
		'EventScheduled'   => '',
		'EventCancelled'   => __( 'Cancelled', 'tala-seo' ),
		'EventPostponed'   => __( 'Postponed: the new date isn\'t set yet', 'tala-seo' ),
		'EventRescheduled' => __( 'Rescheduled', 'tala-seo' ),
	);
}

/**
 * Ticket availability values (schema.org) and how the card shows them.
 *
 * @return array<string, string>
 */
function tala_seo_ticket_availabilities() {
	return array(
		'InStock'  => __( 'On sale', 'tala-seo' ),
		'SoldOut'  => __( 'Sold out', 'tala-seo' ),
		'PreOrder' => __( 'Pre-order', 'tala-seo' ),
	);
}

/**
 * A date ('2026-11-14') and optional time ('09:00') in the site's timezone, or null when the date
 * isn't a real date.
 *
 * @param mixed $date Date.
 * @param mixed $time Time, or '' for none.
 * @return DateTimeImmutable|null
 */
function tala_seo_event_moment( $date, $time = '' ) {
	$value  = trim( (string) $date );
	$format = 'Y-m-d';
	if ( '' !== (string) $time ) {
		$value  .= ' ' . trim( (string) $time );
		$format .= ' H:i';
	}
	$moment = DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() );
	return $moment && $moment->format( $format ) === $value ? $moment : null;
}

/**
 * An Event block's details, checked and filled in: the name defaults to the post title, an
 * event without a start time is all-day, an end without a time is a date (no made-up midnight),
 * and an end that isn't after the start is dropped.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param WP_Post|null         $post       The post it is in.
 * @return array<string, mixed>|null Null without a name or a valid start date.
 */
function tala_seo_event_data( array $attributes, $post ) {
	$text = static fn( $key ) => trim( sanitize_text_field( (string) ( $attributes[ $key ] ?? '' ) ) );

	$name = $text( 'name' );
	if ( '' === $name && $post ) {
		$name = tala_seo_clean_text( get_the_title( $post ) );
	}
	$all_day = '' === $text( 'startTime' );
	$start   = tala_seo_event_moment( $text( 'startDate' ), $text( 'startTime' ) );
	if ( '' === $name || ! $start ) {
		return null;
	}
	$end         = null;
	$end_all_day = $all_day || '' === $text( 'endTime' );
	if ( '' !== $text( 'endDate' ) || ! $end_all_day ) {
		// An end time without an end date is on the start day.
		$end_date = '' !== $text( 'endDate' ) ? $text( 'endDate' ) : $start->format( 'Y-m-d' );
		$end      = tala_seo_event_moment( $end_date, $end_all_day ? '' : $text( 'endTime' ) );
		$later    = $end_all_day ? $end_date > $start->format( 'Y-m-d' ) : $end > $start;
		if ( ! $end || ! $later ) {
			$end = null;
		}
	}
	$status   = array_key_exists( $text( 'status' ), tala_seo_event_statuses() ) ? $text( 'status' ) : 'EventScheduled';
	$previous = 'EventRescheduled' === $status ? tala_seo_event_moment( $text( 'previousStartDate' ) ) : null;

	$price    = $text( 'price' );
	$currency = strtoupper( $text( 'priceCurrency' ) );
	if ( ! is_numeric( $price ) || (float) $price < 0 || ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
		// Google needs a price with its currency (ISO 4217); one without the other means nothing.
		$price    = '';
		$currency = '';
	}
	$availability = array_key_exists( $text( 'availability' ), tala_seo_ticket_availabilities() ) ? $text( 'availability' ) : '';

	return array(
		'name'         => $name,
		'all_day'      => $all_day,
		'start'        => $start,
		'end'          => $end,
		'end_all_day'  => $end_all_day,
		'status'       => $status,
		'previous'     => $previous,
		'place'        => $text( 'place' ),
		'address'      => array(
			'streetAddress'   => $text( 'streetAddress' ),
			'addressLocality' => $text( 'addressLocality' ),
			'addressRegion'   => $text( 'addressRegion' ),
			'postalCode'      => $text( 'postalCode' ),
			'addressCountry'  => $text( 'addressCountry' ),
		),
		'price'        => $price,
		'currency'     => $currency,
		'availability' => $availability,
		'tickets'      => tala_seo_sanitize_url( $attributes['ticketUrl'] ?? '' ),
		'organizer'    => $text( 'organizer' ),
		'organizerUrl' => tala_seo_sanitize_url( $attributes['organizerUrl'] ?? '' ),
		'description'  => trim( sanitize_textarea_field( (string) ( $attributes['description'] ?? '' ) ) ),
	);
}

/**
 * A date as structured data wants it: date only for all-day events, otherwise ISO 8601 with the
 * UTC offset.
 *
 * @param DateTimeImmutable $moment  Date.
 * @param bool              $all_day All-day event.
 * @return string
 */
function tala_seo_event_iso( DateTimeImmutable $moment, bool $all_day ): string {
	return $moment->format( $all_day ? 'Y-m-d' : 'c' );
}

/**
 * Renders the Event block: name, then when, where, tickets and organizer, then the description.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function tala_seo_render_event( $attributes ) {
	$event = tala_seo_event_data( (array) $attributes, get_post() );
	if ( ! $event ) {
		return '';
	}
	$level = min( 6, max( 2, (int) ( $attributes['level'] ?? 2 ) ) );
	$html  = sprintf( '<h%1$d class="wp-block-tala-seo-event__name">%2$s</h%1$d>', $level, esc_html( $event['name'] ) );

	$status = tala_seo_event_statuses()[ $event['status'] ];
	if ( $event['previous'] ) {
		/* translators: %s: the date the event was first planned for. */
		$status = sprintf( __( 'Rescheduled from %s', 'tala-seo' ), (string) wp_date( (string) get_option( 'date_format' ), $event['previous']->getTimestamp() ) );
	}
	if ( '' !== $status ) {
		$html .= '<p class="wp-block-tala-seo-event__status"><strong>' . esc_html( $status ) . '</strong></p>';
	}

	$rows = array( __( 'When', 'tala-seo' ) => tala_seo_event_when( $event ) );

	$address = implode( ', ', array_filter( $event['address'] ) );
	$where   = implode( '<br>', array_map( 'esc_html', array_filter( array( $event['place'], $address ) ) ) );
	if ( '' !== $where ) {
		$rows[ __( 'Where', 'tala-seo' ) ] = $where;
	}

	$tickets = array();
	if ( '' !== $event['price'] ) {
		$amount    = (float) $event['price'];
		$tickets[] = 0.0 === $amount
			? esc_html__( 'Free', 'tala-seo' )
			: esc_html( $event['currency'] . ' ' . number_format_i18n( $amount, floor( $amount ) === $amount ? 0 : 2 ) );
	}
	if ( '' !== $event['availability'] ) {
		$tickets[] = esc_html( tala_seo_ticket_availabilities()[ $event['availability'] ] );
	}
	if ( '' !== $event['tickets'] && 'SoldOut' !== $event['availability'] ) {
		$tickets[] = '<a href="' . esc_url( $event['tickets'] ) . '">' . esc_html__( 'Get tickets', 'tala-seo' ) . '</a>';
	}
	if ( $tickets ) {
		$rows[ __( 'Tickets', 'tala-seo' ) ] = implode( ' · ', $tickets );
	}

	if ( '' !== $event['organizer'] ) {
		$rows[ __( 'Organizer', 'tala-seo' ) ] = '' !== $event['organizerUrl']
			? '<a href="' . esc_url( $event['organizerUrl'] ) . '">' . esc_html( $event['organizer'] ) . '</a>'
			: esc_html( $event['organizer'] );
	}

	$html .= '<dl class="wp-block-tala-seo-event__details">';
	foreach ( $rows as $label => $value ) {
		$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>';
	}
	$html .= '</dl>';

	if ( '' !== $event['description'] ) {
		$html .= '<div class="wp-block-tala-seo-event__description">' . wpautop( esc_html( $event['description'] ) ) . '</div>';
	}

	return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
}

/**
 * When the event is, as escaped HTML with <time> elements: "14 November 2026, 9:00 am – 5:00 pm",
 * or the dates alone for an all-day event.
 *
 * @param array<string, mixed> $event Event details (tala_seo_event_data()).
 * @return string
 */
function tala_seo_event_when( array $event ): string {
	$date_format = (string) get_option( 'date_format' );
	$time_format = (string) get_option( 'time_format' );
	$time        = static fn( DateTimeImmutable $moment, bool $all_day, string $format ) => sprintf(
		'<time datetime="%s">%s</time>',
		esc_attr( tala_seo_event_iso( $moment, $all_day ) ),
		esc_html( (string) wp_date( $format, $moment->getTimestamp() ) )
	);

	$when = $time( $event['start'], $event['all_day'], $event['all_day'] ? $date_format : $date_format . ', ' . $time_format );
	if ( $event['end'] ) {
		$end_format = $date_format;
		if ( ! $event['end_all_day'] ) {
			$same_day   = $event['end']->format( 'Y-m-d' ) === $event['start']->format( 'Y-m-d' );
			$end_format = $same_day ? $time_format : $date_format . ', ' . $time_format;
		}
		$when .= ' – ' . $time( $event['end'], $event['end_all_day'], $end_format );
	}
	return $when;
}

/**
 * The Event node for a post with one Event block that has a name, a start date and an address.
 *
 * @param WP_Post                     $post   Post.
 * @param array<array<string, mixed>> $blocks Parsed blocks of the post.
 * @return array<string, mixed>|null
 */
function tala_seo_post_event_node( $post, array $blocks ) {
	$found = tala_seo_find_blocks( $blocks, static fn( $block ) => 'tala-seo/event' === $block['blockName'] );
	if ( 1 !== count( $found ) ) {
		return null;
	}
	$event = tala_seo_event_data( (array) ( $found[0]['attrs'] ?? array() ), $post );
	if ( ! $event || ( '' === $event['address']['streetAddress'] && '' === $event['address']['addressLocality'] ) ) {
		return null;
	}

	$url  = (string) get_permalink( $post );
	$node = array(
		'@type'     => 'Event',
		'@id'       => $url . '#event',
		'url'       => $url,
		'name'      => $event['name'],
		'startDate' => tala_seo_event_iso( $event['start'], $event['all_day'] ),
	);
	if ( $event['end'] ) {
		$node['endDate'] = tala_seo_event_iso( $event['end'], $event['end_all_day'] );
	}
	$node['eventStatus'] = 'https://schema.org/' . $event['status'];
	if ( $event['previous'] ) {
		$node['previousStartDate'] = tala_seo_event_iso( $event['previous'], true );
	}

	$location = array( '@type' => 'Place' );
	if ( '' !== $event['place'] ) {
		$location['name'] = $event['place'];
	}
	$location['address'] = array_merge( array( '@type' => 'PostalAddress' ), array_filter( $event['address'] ) );
	$node['location']    = $location;

	if ( '' !== $event['description'] ) {
		$node['description'] = $event['description'];
	}
	$thumbnail = (int) get_post_thumbnail_id( $post );
	if ( $thumbnail ) {
		$images = tala_seo_article_images( $thumbnail );
		if ( $images ) {
			$node['image'] = $images;
		}
	}

	$offer = array( '@type' => 'Offer' );
	if ( '' !== $event['price'] ) {
		$offer['price']         = $event['price'];
		$offer['priceCurrency'] = $event['currency'];
	}
	if ( '' !== $event['availability'] ) {
		$offer['availability'] = 'https://schema.org/' . $event['availability'];
	}
	if ( '' !== $event['tickets'] ) {
		$offer['url'] = $event['tickets'];
	}
	if ( count( $offer ) > 1 ) {
		$node['offers'] = $offer;
	}

	if ( '' !== $event['organizer'] ) {
		$node['organizer'] = array(
			'@type' => 'Organization',
			'name'  => $event['organizer'],
		);
		if ( '' !== $event['organizerUrl'] ) {
			$node['organizer']['url'] = $event['organizerUrl'];
		}
	}
	return $node;
}
