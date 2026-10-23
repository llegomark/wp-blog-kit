<?php
/**
 * The Event block: a card with when, where, tickets and organizer, and Event structured data as
 * Google documents it (start date with the UTC offset or date only, a Place with an address, one
 * event per page).
 *
 * @package TalaTests
 */

/**
 * Event block.
 */
class Test_Tala_SEO_Event extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs and the Manila timezone (UTC+8).
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
		update_option( 'timezone_string', 'Asia/Manila' );
		update_option( 'date_format', 'F j, Y' );
		update_option( 'time_format', 'g:i a' );
	}

	/**
	 * An Event block as the editor saves it.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	private function event( array $attributes ): string {
		return get_comment_delimited_block_content( 'tala-seo/event', $attributes, '' );
	}

	/**
	 * A published post with this content, saved as the editor saves it (wp_insert_post() removes
	 * one level of backslashes, as from form data).
	 *
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	private function publish_content( string $content ): int {
		return $this->publish_post( array( 'post_content' => wp_slash( $content ) ) );
	}

	/**
	 * A complete event: the details of a workshop in Quezon City.
	 *
	 * @param array<string, mixed> $changes Attributes to change.
	 * @return array<string, mixed>
	 */
	private function workshop( array $changes = array() ): array {
		return array_merge(
			array(
				'name'            => 'Reading workshop',
				'startDate'       => '2026-11-14',
				'startTime'       => '09:00',
				'endTime'         => '17:00',
				'place'           => 'Quezon City Hall',
				'streetAddress'   => 'Elliptical Road',
				'addressLocality' => 'Quezon City',
				'addressCountry'  => 'PH',
				'price'           => '500',
				'priceCurrency'   => 'php',
				'availability'    => 'InStock',
				'ticketUrl'       => 'https://tickets.example.com/reading',
				'organizer'       => 'Guro Notes',
				'organizerUrl'    => 'https://guronotes.example.com',
				'description'     => 'A day of reading games for grade 1 teachers.',
			),
			$changes
		);
	}

	/**
	 * A published post with one Event block, opened as the current page.
	 *
	 * @param array<string, mixed> $attributes Event attributes.
	 * @return int Post ID.
	 */
	private function open_event( array $attributes ): int {
		$post = $this->publish_content( $this->event( $attributes ) );
		$this->go_to( get_permalink( $post ) );
		return $post;
	}

	/**
	 * The card the block shows on the current post.
	 *
	 * @return string
	 */
	private function card(): string {
		$GLOBALS['post'] = get_queried_object();
		return do_blocks( (string) get_post_field( 'post_content', get_queried_object_id() ) );
	}

	/**
	 * The card shows each detail; the structured data says the same, with the UTC offset, the
	 * currency in capitals and the featured image.
	 */
	public function test_card_and_structured_data(): void {
		$post = $this->open_event( $this->workshop() );
		$card = $this->card();

		$this->assertStringContainsString( '<h2 class="wp-block-tala-seo-event__name">Reading workshop</h2>', $card );
		$this->assertStringContainsString(
			'<dt>When</dt><dd><time datetime="2026-11-14T09:00:00+08:00">November 14, 2026, 9:00 am</time> – <time datetime="2026-11-14T17:00:00+08:00">5:00 pm</time></dd>',
			$card
		);
		$this->assertStringContainsString( '<dt>Where</dt><dd>Quezon City Hall<br>Elliptical Road, Quezon City, PH</dd>', $card );
		$this->assertStringContainsString( '<dt>Tickets</dt><dd>PHP 500 · On sale · <a href="https://tickets.example.com/reading">Get tickets</a></dd>', $card );
		$this->assertStringContainsString( '<dt>Organizer</dt><dd><a href="https://guronotes.example.com">Guro Notes</a></dd>', $card );
		$this->assertStringContainsString( '<p>A day of reading games for grade 1 teachers.</p>', $card );

		$url   = get_permalink( $post );
		$event = $this->nodes( 'Event' );
		$this->assertCount( 1, $event );
		$this->assertSame(
			array(
				'@type'       => 'Event',
				'@id'         => $url . '#event',
				'url'         => $url,
				'name'        => 'Reading workshop',
				'startDate'   => '2026-11-14T09:00:00+08:00',
				'endDate'     => '2026-11-14T17:00:00+08:00',
				'eventStatus' => 'https://schema.org/EventScheduled',
				'location'    => array(
					'@type'   => 'Place',
					'name'    => 'Quezon City Hall',
					'address' => array(
						'@type'           => 'PostalAddress',
						'streetAddress'   => 'Elliptical Road',
						'addressLocality' => 'Quezon City',
						'addressCountry'  => 'PH',
					),
				),
				'description' => 'A day of reading games for grade 1 teachers.',
				'image'       => tala_seo_article_images( self::$image_id ),
				'offers'      => array(
					'@type'         => 'Offer',
					'price'         => '500',
					'priceCurrency' => 'PHP',
					'availability'  => 'https://schema.org/InStock',
					'url'           => 'https://tickets.example.com/reading',
				),
				'organizer'   => array(
					'@type' => 'Organization',
					'name'  => 'Guro Notes',
					'url'   => 'https://guronotes.example.com',
				),
			),
			$event[0]
		);
		$this->assertNotEmpty( $event[0]['image'] );
	}

	/**
	 * Without a start time the event is all-day: dates only, as Google asks (no made-up midnight).
	 * The name defaults to the post title.
	 */
	public function test_all_day_event(): void {
		$post = $this->open_event(
			$this->workshop(
				array(
					'name'      => '',
					'startTime' => '',
					'endDate'   => '2026-11-15',
				)
			)
		);
		$this->assertStringContainsString(
			'<time datetime="2026-11-14">November 14, 2026</time> – <time datetime="2026-11-15">November 15, 2026</time>',
			$this->card()
		);
		$event = $this->nodes( 'Event' )[0];
		$this->assertSame( get_the_title( $post ), $event['name'] );
		$this->assertSame( '2026-11-14', $event['startDate'] );
		$this->assertSame( '2026-11-15', $event['endDate'] );
	}

	/**
	 * An end before the start, or a start that isn't a date, is not used. An end date without a
	 * time stays a date: no made-up midnight.
	 */
	public function test_dates_are_checked(): void {
		$this->open_event( $this->workshop( array( 'endTime' => '08:00' ) ) );
		$this->assertArrayNotHasKey( 'endDate', $this->nodes( 'Event' )[0] );

		$this->open_event(
			$this->workshop(
				array(
					'endDate' => '2026-11-16',
					'endTime' => '',
				)
			)
		);
		$this->assertSame( '2026-11-16', $this->nodes( 'Event' )[0]['endDate'] );
		$this->assertStringContainsString( '9:00 am</time> – <time datetime="2026-11-16">November 16, 2026</time>', $this->card() );

		$this->open_event( $this->workshop( array( 'endTime' => '' ) ) );
		$this->assertArrayNotHasKey( 'endDate', $this->nodes( 'Event' )[0], 'Ends the day it starts, time unknown: nothing to add.' );

		$this->open_event( $this->workshop( array( 'startDate' => '2026-02-30' ) ) );
		$this->assertSame( array(), $this->nodes( 'Event' ) );
		$this->assertSame( '', $this->card(), 'No card without a date.' );
	}

	/**
	 * Google needs the address: without one the card shows, but there is no Event markup.
	 */
	public function test_no_markup_without_an_address(): void {
		$this->open_event(
			$this->workshop(
				array(
					'streetAddress'   => '',
					'addressLocality' => '',
				)
			)
		);
		$this->assertSame( array(), $this->nodes( 'Event' ) );
		$this->assertStringContainsString( 'Reading workshop', $this->card() );
	}

	/**
	 * Google supports pages about a single event: a post that has two gets no Event markup.
	 */
	public function test_one_event_per_page(): void {
		$post = $this->publish_content( $this->event( $this->workshop() ) . $this->event( $this->workshop( array( 'name' => 'Second' ) ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'Event' ) );
	}

	/**
	 * A cancelled or rescheduled event says so on the card and in the markup.
	 */
	public function test_status(): void {
		$this->open_event( $this->workshop( array( 'status' => 'EventCancelled' ) ) );
		$this->assertStringContainsString( '<p class="wp-block-tala-seo-event__status"><strong>Cancelled</strong></p>', $this->card() );
		$this->assertSame( 'https://schema.org/EventCancelled', $this->nodes( 'Event' )[0]['eventStatus'] );

		$this->open_event(
			$this->workshop(
				array(
					'status'            => 'EventRescheduled',
					'previousStartDate' => '2026-10-10',
				)
			)
		);
		$this->assertStringContainsString( '<strong>Rescheduled from October 10, 2026</strong>', $this->card() );
		$event = $this->nodes( 'Event' )[0];
		$this->assertSame( 'https://schema.org/EventRescheduled', $event['eventStatus'] );
		$this->assertSame( '2026-10-10', $event['previousStartDate'] );
	}

	/**
	 * Tickets: free events say "Free"; a price without a currency code is left out, as it means
	 * nothing; a sold-out event has no ticket link on the card.
	 */
	public function test_tickets(): void {
		$this->open_event( $this->workshop( array( 'price' => '0' ) ) );
		$this->assertStringContainsString( '<dd>Free · On sale ·', $this->card() );
		$this->assertSame( '0', $this->nodes( 'Event' )[0]['offers']['price'] );

		$this->open_event(
			$this->workshop(
				array(
					'priceCurrency' => 'pesos',
					'availability'  => 'SoldOut',
				)
			)
		);
		$this->assertStringContainsString( '<dt>Tickets</dt><dd>Sold out</dd>', $this->card() );
		$this->assertSame(
			array(
				'@type'        => 'Offer',
				'availability' => 'https://schema.org/SoldOut',
				'url'          => 'https://tickets.example.com/reading',
			),
			$this->nodes( 'Event' )[0]['offers']
		);
	}

	/**
	 * What the author types is text: markup in it is escaped on the card and stripped in the data.
	 */
	public function test_text_is_escaped(): void {
		$this->open_event( $this->workshop( array( 'place' => 'Hall <b>B</b><img src=x onerror=alert(1)>' ) ) );
		$this->assertStringNotContainsString( '<img', $this->card() );
		$this->assertStringNotContainsString( '<b>', $this->card() );
		$this->assertSame( 'Hall B', $this->nodes( 'Event' )[0]['location']['name'] );
	}
}
