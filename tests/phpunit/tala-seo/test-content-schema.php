<?php
/**
 * Structured data read from what a post shows: FAQPage from FAQ groups, HowTo from How-to groups,
 * and VideoObject for YouTube embeds, whose title comes from YouTube's oEmbed when the post is
 * saved and whose publish date the author enters.
 *
 * @package TalaTests
 */

/**
 * Structured data from the content.
 */
class Test_Tala_SEO_Content_Schema extends Tala_SEO_Test_Case {

	/**
	 * The addresses requested from YouTube's oEmbed during a test.
	 *
	 * @var string[]
	 */
	private array $fetched = array();

	/**
	 * The status YouTube's oEmbed answers with.
	 *
	 * @var int
	 */
	private int $oembed_status = 200;

	/**
	 * Readable URLs, the Manila timezone, and a stand-in for YouTube's oEmbed.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
		update_option( 'timezone_string', 'Asia/Manila' );
		add_filter( 'pre_http_request', array( $this, 'fake_oembed' ), 10, 3 );
	}

	/**
	 * Answers requests to YouTube's oEmbed as YouTube does; lets nothing else out.
	 *
	 * @param false|array<string, mixed> $response Response.
	 * @param array<string, mixed>       $args     Request arguments.
	 * @param string                     $url      Address.
	 * @return array<string, mixed>
	 */
	public function fake_oembed( $response, $args, $url ): array {
		$this->assertStringStartsWith( 'https://www.youtube.com/oembed?', $url, 'Only YouTube\'s oEmbed is asked.' );
		$this->fetched[] = $url;
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$id   = substr( (string) $query['url'], -11 );
		$body = array(
			'title'         => 'Video ' . $id . ' \\ "quoted"',
			'author_name'   => 'A channel',
			'thumbnail_url' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
		);
		return array(
			'headers'  => array(),
			'body'     => (string) wp_json_encode( $body ),
			'response' => array(
				'code'    => $this->oembed_status,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * A Details block.
	 *
	 * @param string $summary Question (HTML).
	 * @param string $answer  Blocks inside.
	 * @return string
	 */
	private function details( string $summary, string $answer ): string {
		return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>$summary</summary>$answer</details>\n<!-- /wp:details -->";
	}

	/**
	 * A Paragraph block.
	 *
	 * @param string $html Text (HTML).
	 * @return string
	 */
	private function paragraph( string $html ): string {
		return "<!-- wp:paragraph -->\n<p>$html</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * A List block.
	 *
	 * @param string[] $items   Items (HTML).
	 * @param bool     $ordered Numbered.
	 * @return string
	 */
	private function items( array $items, bool $ordered = true ): string {
		$tag   = $ordered ? 'ol' : 'ul';
		$html  = $ordered ? "<!-- wp:list {\"ordered\":true} -->\n" : "<!-- wp:list -->\n";
		$html .= "<$tag class=\"wp-block-list\">";
		foreach ( $items as $item ) {
			$html .= "<!-- wp:list-item -->\n<li>$item</li>\n<!-- /wp:list-item -->";
		}
		return $html . "</$tag>\n<!-- /wp:list -->";
	}

	/**
	 * A Group block with a class, as the FAQ and How-to variations insert it.
	 *
	 * @param string $class_name Class.
	 * @param string $inner      Blocks inside.
	 * @return string
	 */
	private function group( string $class_name, string $inner ): string {
		return "<!-- wp:group {\"className\":\"$class_name\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group $class_name\">$inner</div>\n<!-- /wp:group -->";
	}

	/**
	 * A YouTube Embed block, as the editor saves it.
	 *
	 * @param string $url     Video address.
	 * @param string $caption Caption (HTML).
	 * @return string
	 */
	private function embed( string $url, string $caption = '' ): string {
		$attributes = wp_json_encode(
			array(
				'url'              => $url,
				'type'             => 'video',
				'providerNameSlug' => 'youtube',
				'responsive'       => true,
			),
			JSON_UNESCAPED_SLASHES
		);
		$caption    = '' !== $caption ? "<figcaption class=\"wp-element-caption\">$caption</figcaption>" : '';
		return "<!-- wp:embed $attributes -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube\"><div class=\"wp-block-embed__wrapper\">\n$url\n</div>$caption</figure>\n<!-- /wp:embed -->";
	}

	/**
	 * Each Details block in an FAQ group is a question; the answer is everything inside it, one
	 * line per paragraph or list item. Details outside the group, and questions without an
	 * answer, are not part of the FAQ.
	 */
	public function test_faq_from_details_blocks(): void {
		$faq  = $this->group(
			'tala-seo-faq',
			"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Questions</h2>\n<!-- /wp:heading -->"
			. $this->details( 'Do I need to <strong>register</strong>?', $this->paragraph( 'Yes. It is free &amp; quick.' ) . $this->items( array( 'Bring an ID', 'Bring a pen' ), false ) )
			. $this->details( 'Is there parking?', $this->paragraph( 'Only on the street.' ) )
			. $this->details( 'Not answered yet', $this->paragraph( '' ) )
		);
		$post = $this->publish_post( array( 'post_content' => $this->details( 'Outside', $this->paragraph( 'Not in the FAQ.' ) ) . $faq ) );
		$this->go_to( get_permalink( $post ) );

		$faqs = $this->nodes( 'FAQPage' );
		$this->assertCount( 1, $faqs );
		$this->assertSame( get_permalink( $post ) . '#faq', $faqs[0]['@id'] );
		$this->assertSame(
			array(
				array(
					'@type'          => 'Question',
					'name'           => 'Do I need to register?',
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => "Yes. It is free & quick.\nBring an ID\nBring a pen",
					),
				),
				array(
					'@type'          => 'Question',
					'name'           => 'Is there parking?',
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => 'Only on the street.',
					),
				),
			),
			$faqs[0]['mainEntity']
		);
	}

	/**
	 * Details blocks alone, or a group without the FAQ class, are not an FAQ.
	 */
	public function test_no_faq_without_the_faq_group(): void {
		$details = $this->details( 'A question?', $this->paragraph( 'An answer.' ) );
		$post    = $this->publish_post( array( 'post_content' => $details . $this->group( 'is-style-card', $details ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'FAQPage' ) );
	}

	/**
	 * A How-to group: the heading names the guide, the paragraphs before the numbered list describe
	 * it, and each list item is a step.
	 */
	public function test_howto_from_a_numbered_list(): void {
		$howto = $this->group(
			'tala-seo-howto',
			"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">How to <em>label</em> forms</h2>\n<!-- /wp:heading -->"
			. $this->paragraph( 'You need a marker.' )
			. $this->items( array( 'Sort the forms by grade.', 'Write the date on each folder.' ) )
			. $this->paragraph( 'After the steps: not part of the description.' )
		);
		$post  = $this->publish_post( array( 'post_content' => $howto ) );
		$this->go_to( get_permalink( $post ) );

		$this->assertSame(
			array(
				array(
					'@type'       => 'HowTo',
					'@id'         => get_permalink( $post ) . '#howto-1',
					'name'        => 'How to label forms',
					'description' => 'You need a marker.',
					'step'        => array(
						array(
							'@type'    => 'HowToStep',
							'position' => 1,
							'text'     => 'Sort the forms by grade.',
						),
						array(
							'@type'    => 'HowToStep',
							'position' => 2,
							'text'     => 'Write the date on each folder.',
						),
					),
				),
			),
			$this->nodes( 'HowTo' )
		);
	}

	/**
	 * Not a how-to guide: a bulleted list, a single step, or no heading.
	 */
	public function test_no_howto_without_a_heading_and_steps(): void {
		$heading = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">How to do it</h2>\n<!-- /wp:heading -->";
		$content = $this->group( 'tala-seo-howto', $heading . $this->items( array( 'One', 'Two' ), false ) )
			. $this->group( 'tala-seo-howto', $heading . $this->items( array( 'Only one step' ) ) )
			. $this->group( 'tala-seo-howto', $this->items( array( 'One', 'Two' ) ) );
		$post    = $this->publish_post( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'HowTo' ) );
	}

	/**
	 * A password-protected post doesn't show its content, so its markup doesn't describe it.
	 */
	public function test_nothing_from_the_content_of_a_protected_post(): void {
		$faq  = $this->group( 'tala-seo-faq', $this->details( 'A question?', $this->paragraph( 'An answer.' ) ) );
		$post = $this->publish_post(
			array(
				'post_content'  => $faq,
				'post_password' => 'secret',
			)
		);
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'FAQPage' ) );
		$this->assertCount( 1, $this->nodes( 'BlogPosting' ), 'The article itself is still described.' );
	}

	/**
	 * A block hidden on the front end (block visibility) isn't on the page, so the markup leaves it
	 * out and saving asks YouTube nothing about it; a block hidden only on some screens is still on
	 * the page, and still described.
	 */
	public function test_hidden_blocks_are_not_described(): void {
		$hidden   = '"metadata":{"blockVisibility":false}';
		$viewport = '"metadata":{"blockVisibility":{"viewport":{"mobile":false}}}';
		$faq      = $this->group(
			'tala-seo-faq',
			$this->details( 'Shown question?', $this->paragraph( 'Shown answer.' ) )
			. "<!-- wp:details {{$hidden}} -->\n<details class=\"wp-block-details\"><summary>Hidden question?</summary>" . $this->paragraph( 'Hidden answer.' ) . "</details>\n<!-- /wp:details -->"
			. "<!-- wp:details {{$viewport}} -->\n<details class=\"wp-block-details\"><summary>Desktop question?</summary>" . $this->paragraph( 'Desktop answer.' ) . "</details>\n<!-- /wp:details -->"
		);
		$video    = str_replace( '"responsive":true}', "\"responsive\":true,$hidden}", $this->embed( 'https://youtu.be/aqz-KE-bpKQ' ) );
		$event    = "<!-- wp:tala-seo/event {\"name\":\"Hidden event\",\"startDate\":\"2026-11-14\",\"streetAddress\":\"1 Hidden St\",\"addressLocality\":\"Quezon City\",$hidden} /-->";
		$course   = "<!-- wp:tala-seo/course {\"name\":\"Hidden course\",\"description\":\"Not on the page.\",$hidden} /-->";
		$post     = $this->publish_post(
			array(
				'post_content' => $faq . $video . $event . $course,
				'meta_input'   => array( TALA_SEO_VIDEO_DATES => array( 'aqz-KE-bpKQ' => '2014-11-10' ) ),
			)
		);
		$this->assertSame( array(), $this->fetched, 'Nothing asked about a hidden video.' );

		$this->go_to( get_permalink( $post ) );
		$questions = array_column( $this->nodes( 'FAQPage' )[0]['mainEntity'], 'name' );
		$this->assertSame( array( 'Shown question?', 'Desktop question?' ), $questions );
		$this->assertSame( array(), $this->nodes( 'VideoObject' ) );
		$this->assertSame( array(), $this->nodes( 'Event' ) );
		$this->assertSame( array(), $this->nodes( 'Course' ) );
	}

	/**
	 * Addresses of YouTube videos, and addresses that aren't.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function youtube_addresses(): array {
		return array(
			'watch'          => array( 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'aqz-KE-bpKQ' ),
			'watch, more'    => array( 'https://m.youtube.com/watch?feature=share&v=aqz-KE-bpKQ&t=10', 'aqz-KE-bpKQ' ),
			'short link'     => array( 'https://youtu.be/aqz-KE-bpKQ?si=abc', 'aqz-KE-bpKQ' ),
			'Shorts'         => array( 'https://www.youtube.com/shorts/aqz-KE-bpKQ', 'aqz-KE-bpKQ' ),
			'live'           => array( 'https://youtube.com/live/aqz-KE-bpKQ', 'aqz-KE-bpKQ' ),
			'embed'          => array( 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ', 'aqz-KE-bpKQ' ),
			'ID too long'    => array( 'https://www.youtube.com/watch?v=aqz-KE-bpKQx', '' ),
			'channel'        => array( 'https://www.youtube.com/@blender', '' ),
			'another site'   => array( 'https://vimeo.com/76979871', '' ),
			'look-alike'     => array( 'https://notyoutube.com/watch?v=aqz-KE-bpKQ', '' ),
			'in another URL' => array( 'https://example.com/?u=https://youtu.be/aqz-KE-bpKQ', '' ),
		);
	}

	/**
	 * The video ID is read from every kind of YouTube address.
	 *
	 * @dataProvider youtube_addresses
	 *
	 * @param string $url Address.
	 * @param string $id  Video ID, or '' for none.
	 */
	public function test_youtube_id( string $url, string $id ): void {
		$this->assertSame( $id, tala_seo_youtube_id( $url ) );
	}

	/**
	 * Saving fetches each video's title and thumbnail once. The video is described when the author
	 * has entered its publish date (in the site's timezone), with the caption as its description;
	 * serving the page asks YouTube nothing.
	 */
	public function test_video_described_once_it_has_a_date(): void {
		$content = $this->embed( 'https://youtu.be/aqz-KE-bpKQ', 'An open movie from <strong>Blender</strong>.' )
			. $this->embed( 'https://www.youtube.com/watch?v=aqz-KE-bpKQ' )
			. $this->embed( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
		$post    = $this->publish_post( array( 'post_content' => $content ) );
		$this->assertCount( 2, $this->fetched, 'One request per video, however often it is embedded.' );
		$this->assertSame(
			array(
				'name'      => 'Video aqz-KE-bpKQ \\ "quoted"',
				'thumbnail' => 'https://i.ytimg.com/vi/aqz-KE-bpKQ/hqdefault.jpg',
			),
			get_post_meta( $post, TALA_SEO_VIDEOS, true )['aqz-KE-bpKQ'],
			'Backslashes survive saving.'
		);

		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'VideoObject' ), 'No markup without the publish date Google requires.' );

		update_post_meta( $post, TALA_SEO_VIDEO_DATES, array( 'aqz-KE-bpKQ' => '2014-11-10' ) );
		wp_update_post( array( 'ID' => $post ) );
		$this->assertCount( 2, $this->fetched, 'Saving again fetches nothing it knows.' );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame(
			array(
				array(
					'@type'        => 'VideoObject',
					'@id'          => get_permalink( $post ) . '#video-aqz-KE-bpKQ',
					'name'         => 'Video aqz-KE-bpKQ \\ "quoted"',
					'thumbnailUrl' => 'https://i.ytimg.com/vi/aqz-KE-bpKQ/hqdefault.jpg',
					'uploadDate'   => '2014-11-10T00:00:00+08:00',
					'embedUrl'     => 'https://www.youtube.com/embed/aqz-KE-bpKQ',
					'description'  => 'An open movie from Blender.',
				),
			),
			$this->nodes( 'VideoObject' )
		);
		$this->assertCount( 2, $this->fetched, 'Serving the page asks YouTube nothing.' );
	}

	/**
	 * A video removed from the post is forgotten; a failed fetch is tried again on the next save.
	 */
	public function test_videos_follow_the_content(): void {
		$this->oembed_status = 500;
		$post                = $this->publish_post( array( 'post_content' => $this->embed( 'https://youtu.be/aqz-KE-bpKQ' ) ) );
		$this->assertSame( '', get_post_meta( $post, TALA_SEO_VIDEOS, true ), 'Nothing kept from a failed fetch.' );

		$this->oembed_status = 200;
		wp_update_post( array( 'ID' => $post ) );
		$this->assertCount( 1, $this->fetched, 'Not asked again right away.' );
		update_post_meta( $post, TALA_SEO_VIDEO_FAILURES, array( 'aqz-KE-bpKQ' => time() - HOUR_IN_SECONDS - 1 ) );
		wp_update_post( array( 'ID' => $post ) );
		$this->assertArrayHasKey( 'aqz-KE-bpKQ', get_post_meta( $post, TALA_SEO_VIDEOS, true ), 'Tried again on a save an hour later.' );
		$this->assertSame( '', get_post_meta( $post, TALA_SEO_VIDEO_FAILURES, true ), 'Nothing left to try again.' );

		wp_update_post(
			array(
				'ID'           => $post,
				'post_content' => $this->paragraph( 'The video is gone.' ),
			)
		);
		$this->assertSame( '', get_post_meta( $post, TALA_SEO_VIDEOS, true ) );
	}

	/**
	 * However many videos a post holds, one save asks YouTube about a few at most, and videos it
	 * couldn't describe wait an hour: saving a post never turns into a long string of requests.
	 */
	public function test_video_fetches_per_save_are_limited(): void {
		$this->oembed_status = 404;
		$embeds              = array_map( fn( $i ) => $this->embed( sprintf( 'https://youtu.be/v%010d', $i ) ), range( 1, TALA_SEO_VIDEO_FETCHES + 5 ) );
		$post                = $this->publish_post( array( 'post_content' => implode( '', $embeds ) ) );
		$this->assertCount( TALA_SEO_VIDEO_FETCHES, $this->fetched );

		wp_update_post( array( 'ID' => $post ) );
		$this->assertCount( TALA_SEO_VIDEO_FETCHES + 5, $this->fetched, 'The rest on the next save' );
		wp_update_post( array( 'ID' => $post ) );
		$this->assertCount( TALA_SEO_VIDEO_FETCHES + 5, $this->fetched, 'None asked again within the hour' );
	}

	/**
	 * The publish dates are saved over the REST API (the SEO panel) only as real dates of video IDs.
	 */
	public function test_video_dates_over_rest(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post = $this->publish_post();
		$save = static function ( array $dates ) use ( $post ): WP_REST_Response {
			$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
			$request->set_body_params( array( 'meta' => array( TALA_SEO_VIDEO_DATES => $dates ) ) );
			return rest_do_request( $request );
		};

		$this->assertSame( 400, $save( array( 'aqz-KE-bpKQ' => '10 November 2014' ) )->get_status() );
		$this->assertSame( 400, $save( array( 'not-an-id' => '2014-11-10' ) )->get_status() );

		$response = $save(
			array(
				'aqz-KE-bpKQ' => '2014-11-10',
				'dQw4w9WgXcQ' => '',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'aqz-KE-bpKQ' => '2014-11-10' ), get_post_meta( $post, TALA_SEO_VIDEO_DATES, true ), 'An emptied date is dropped.' );
		$this->assertSame( array(), tala_seo_sanitize_video_dates( array( 'aqz-KE-bpKQ' => '2014-02-30' ) ), 'Not a real date.' );
	}

	/**
	 * The video dates name the videos in a post's content, which a password may hide: only people who
	 * may edit the post read them over REST (as the editor does); visitors see none.
	 */
	public function test_video_dates_are_for_editors(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post   = $this->publish_post(
			array(
				'post_password' => 'secret',
				'post_content'  => $this->embed( 'https://youtu.be/aqz-KE-bpKQ' ),
				'meta_input'    => array( TALA_SEO_VIDEO_DATES => array( 'aqz-KE-bpKQ' => '2014-11-10' ) ),
			)
		);
		$read   = static function ( string $context ) use ( $post ): array {
			$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post );
			$request->set_param( 'context', $context );
			return (array) rest_do_request( $request )->get_data();
		};

		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( TALA_SEO_VIDEO_DATES, (array) ( $read( 'view' )['meta'] ?? array() ) );
		wp_set_current_user( $editor );
		$this->assertSame( array( 'aqz-KE-bpKQ' => '2014-11-10' ), $read( 'edit' )['meta'][ TALA_SEO_VIDEO_DATES ] );
	}

	/**
	 * FAQ and How-to are in the inserter as Group variations, recognised by their class.
	 */
	public function test_group_variations(): void {
		$variations = array_column( (array) WP_Block_Type_Registry::get_instance()->get_registered( 'core/group' )->variations, null, 'name' );
		foreach ( array( 'tala-seo-faq', 'tala-seo-howto' ) as $name ) {
			$this->assertArrayHasKey( $name, $variations );
			$this->assertSame( array( 'className' => $name ), $variations[ $name ]['attributes'] );
			$this->assertSame( array( 'className' ), $variations[ $name ]['isActive'] );
		}
		$this->assertSame( 'core/details', $variations['tala-seo-faq']['innerBlocks'][1][0] );
		$this->assertSame( array( 'ordered' => true ), $variations['tala-seo-howto']['innerBlocks'][2][1] );
	}

	/**
	 * Requests to YouTube's oEmbed accept a small answer and follow no redirect.
	 */
	public function test_youtube_requests_are_small(): void {
		$seen = array();
		add_filter(
			'pre_http_request',
			static function ( $response, $args ) use ( &$seen ) {
				$seen[] = $args;
				return $response;
			},
			9,
			2
		);
		$this->publish_post( array( 'post_content' => $this->embed( 'https://youtu.be/aqz-KE-bpKQ' ) ) );
		$this->assertSame( 65536, $seen[0]['limit_response_size'] ?? null );
		$this->assertSame( 0, $seen[0]['redirection'] ?? null );
	}

	/**
	 * Block attributes of the wrong type (lists where text belongs) are ignored without a warning:
	 * a page never fills the error log because of what a block was saved with.
	 */
	public function test_odd_block_attributes_are_ignored(): void {
		$content = '<!-- wp:group {"className":["tala-seo-faq"]} --><div class="wp-block-group"></div><!-- /wp:group -->'
			. '<!-- wp:embed {"url":["https://youtu.be/aqz-KE-bpKQ"]} /-->'
			. '<!-- wp:tala-seo/event {"name":["Event"],"startDate":{"day":1}} /-->'
			. '<!-- wp:tala-seo/course {"name":["Course"],"description":["About"],"price":["1"],"priceCurrency":["PHP"],"providerUrl":["https://example.com/"]} /-->';
		$post    = $this->publish_post( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertCount( 1, $this->nodes( 'BlogPosting' ) );
	}
}
