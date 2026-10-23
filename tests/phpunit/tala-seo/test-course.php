<?php
/**
 * The Course block: a card with the course's details, a Course node on its post, an ItemList of
 * the courses on a post that has several (each at its card's anchor), and an ItemList on lists of
 * posts that are all courses, as Google's course list asks.
 *
 * @package TalaTests
 */

/**
 * Course block.
 */
class Test_Tala_SEO_Course extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs and a named site.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
		update_option( 'blogname', 'Guro Notes' );
	}

	/**
	 * A Course block as the editor saves it.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	private function course( array $attributes ): string {
		return get_comment_delimited_block_content( 'tala-seo/course', $attributes, '' );
	}

	/**
	 * A complete course: reading instruction for grade 1 teachers.
	 *
	 * @param array<string, mixed> $changes Attributes to change.
	 * @return array<string, mixed>
	 */
	private function reading( array $changes = array() ): array {
		return array_merge(
			array(
				'name'             => 'Teaching early reading',
				'description'      => 'Plan phonics lessons that get every grade 1 learner reading.',
				'educationalLevel' => 'Beginner',
				'courseMode'       => 'Online',
				'workload'         => '6',
				'instructor'       => 'Ana Reyes',
				'price'            => '1500',
				'priceCurrency'    => 'php',
				'enrollUrl'        => 'https://learn.example.com/reading',
			),
			$changes
		);
	}

	/**
	 * A published post with this content, saved as the editor saves it.
	 *
	 * @param string               $content Post content.
	 * @param array<string, mixed> $args    More arguments for the post.
	 * @return int Post ID.
	 */
	private function publish_content( string $content, array $args = array() ): int {
		return $this->publish_post( array_merge( $args, array( 'post_content' => wp_slash( $content ) ) ) );
	}

	/**
	 * The cards the blocks show on the current post.
	 *
	 * @return string
	 */
	private function cards(): string {
		$GLOBALS['post'] = get_queried_object();
		return do_blocks( (string) get_post_field( 'post_content', get_queried_object_id() ) );
	}

	/**
	 * One course: the card shows its details, and the Course node says the same, with the site as
	 * the provider unless another is named.
	 */
	public function test_card_and_course(): void {
		$post = $this->publish_content( $this->course( $this->reading() ) );
		$url  = get_permalink( $post );
		$this->go_to( $url );

		$card = $this->cards();
		$this->assertStringContainsString( 'id="course-teaching-early-reading"', $card );
		$this->assertStringContainsString( '<h2 class="wp-block-tala-seo-course__name">Teaching early reading</h2>', $card );
		$this->assertStringContainsString( '<p>Plan phonics lessons that get every grade 1 learner reading.</p>', $card );
		$this->assertStringContainsString(
			'<dt>Offered by</dt><dd>Guro Notes</dd><dt>Level</dt><dd>Beginner</dd><dt>Format</dt><dd>Online</dd><dt>Time</dt><dd>About 6 hours</dd><dt>Instructor</dt><dd>Ana Reyes</dd><dt>Cost</dt><dd>PHP 1,500 · <a href="https://learn.example.com/reading">Enroll</a></dd>',
			$card
		);

		$this->assertSame(
			array(
				array(
					'@type'             => 'Course',
					'@id'               => $url . '#course',
					'url'               => $url,
					'name'              => 'Teaching early reading',
					'description'       => 'Plan phonics lessons that get every grade 1 learner reading.',
					'provider'          => array(
						'@type'  => 'Organization',
						'name'   => 'Guro Notes',
						'sameAs' => home_url( '/' ),
					),
					'educationalLevel'  => 'Beginner',
					'hasCourseInstance' => array(
						'@type'          => 'CourseInstance',
						'courseMode'     => 'Online',
						'courseWorkload' => 'PT6H',
						'instructor'     => array(
							'@type' => 'Person',
							'name'  => 'Ana Reyes',
						),
					),
					'offers'            => array(
						'@type'         => 'Offer',
						'category'      => 'Paid',
						'price'         => '1500',
						'priceCurrency' => 'PHP',
						'url'           => 'https://learn.example.com/reading',
					),
				),
			),
			$this->nodes( 'Course' )
		);
	}

	/**
	 * Only the essentials: a named provider, a free course, part hours; nothing else is claimed.
	 */
	public function test_essentials_and_free_course(): void {
		$post = $this->publish_content(
			$this->course(
				array(
					'description'   => 'Short lessons on classroom routines.',
					'provider'      => 'Department of Education',
					'providerUrl'   => 'https://www.deped.gov.ph/',
					'workload'      => '1.5',
					'price'         => '0',
					'priceCurrency' => 'PHP',
				)
			),
			array( 'post_title' => 'Classroom routines' )
		);
		$this->go_to( get_permalink( $post ) );

		$course = $this->nodes( 'Course' )[0];
		$this->assertSame( 'Classroom routines', $course['name'], 'The post title names the course.' );
		$this->assertSame(
			array(
				'@type'  => 'Organization',
				'name'   => 'Department of Education',
				'sameAs' => 'https://www.deped.gov.ph/',
			),
			$course['provider']
		);
		$this->assertSame( 'PT1.5H', $course['hasCourseInstance']['courseWorkload'] );
		$this->assertSame( array( '@type', 'courseWorkload' ), array_keys( $course['hasCourseInstance'] ) );
		$this->assertSame(
			array(
				'@type'         => 'Offer',
				'category'      => 'Free',
				'price'         => '0',
				'priceCurrency' => 'PHP',
			),
			$course['offers']
		);
		$this->assertArrayNotHasKey( 'educationalLevel', $course );
		$this->assertStringContainsString( '<dt>Time</dt><dd>About 1.5 hours</dd>', $this->cards() );
		$this->assertStringContainsString( '<dt>Cost</dt><dd>Free</dd>', $this->cards() );
	}

	/**
	 * A site run by a person offers its courses as that person. A course with only what Google
	 * needs claims nothing more.
	 */
	public function test_person_as_provider(): void {
		$this->set_setting( 'publisher_type', 'person' );
		$this->set_setting( 'publisher_name', 'Ana Reyes' );
		$post = $this->publish_content( $this->course( array( 'description' => 'Plan phonics lessons.' ) ) );
		$this->go_to( get_permalink( $post ) );
		$course = $this->nodes( 'Course' )[0];
		$this->assertSame( array( '@type', '@id', 'url', 'name', 'description', 'provider' ), array_keys( $course ) );
		$this->assertSame( 'Person', $course['provider']['@type'] );
		$this->assertSame( 'Ana Reyes', $course['provider']['name'] );
		$this->assertStringContainsString( '<dt>Offered by</dt><dd>Ana Reyes</dd></dl>', $this->cards() );
	}

	/**
	 * Google needs a description: without one the card shows, but there is no Course markup.
	 */
	public function test_no_markup_without_a_description(): void {
		$post = $this->publish_content( $this->course( $this->reading( array( 'description' => '' ) ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'Course' ) );
		$this->assertStringContainsString( 'Teaching early reading', $this->cards() );
	}

	/**
	 * Several courses on one post: an ItemList of them all, each at its card's anchor; none when
	 * one of them is incomplete, as the list must hold every course the page shows.
	 */
	public function test_several_courses_on_one_post(): void {
		$content = $this->course( $this->reading() )
			. $this->course( $this->reading( array( 'name' => 'Teaching numbers' ) ) )
			. $this->course( $this->reading( array( 'name' => 'Teaching science' ) ) );
		$post    = $this->publish_content( $content );
		$url     = get_permalink( $post );
		$this->go_to( $url );

		$this->assertSame( array(), $this->nodes( 'Course' ) );
		$list = $this->nodes( 'ItemList' );
		$this->assertCount( 1, $list );
		$this->assertSame( $url . '#courses', $list[0]['@id'] );
		$this->assertSame( array( 1, 2, 3 ), array_column( $list[0]['itemListElement'], 'position' ) );
		$urls = array_column( array_column( $list[0]['itemListElement'], 'item' ), 'url' );
		$this->assertSame( array( $url . '#course-teaching-early-reading', $url . '#course-teaching-numbers', $url . '#course-teaching-science' ), $urls );
		$cards = $this->cards();
		foreach ( $urls as $item ) {
			$this->assertStringContainsString( 'id="' . substr( $item, strpos( $item, '#' ) + 1 ) . '"', $cards, 'Each item points to its card.' );
		}

		$post = $this->publish_content( $content . $this->course( $this->reading( array( 'description' => '' ) ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( array(), $this->nodes( 'ItemList' ) );
	}

	/**
	 * A category whose posts are all courses, three or more, is their summary page: an ItemList of
	 * the posts in the order shown. Not when it also lists other posts, or has fewer than three.
	 */
	public function test_course_list_on_an_archive(): void {
		$category = self::factory()->category->create( array( 'name' => 'Courses' ) );
		$posts    = array();
		foreach ( array( 'Reading', 'Numbers', 'Science' ) as $i => $subject ) {
			$posts[] = $this->publish_content(
				$this->course( $this->reading( array( 'name' => "Teaching $subject" ) ) ),
				array(
					'post_category' => array( $category ),
					'post_date'     => '2026-10-0' . ( 3 - $i ) . ' 08:00:00',
				)
			);
		}
		$this->go_to( get_category_link( $category ) );
		$this->assertSame(
			array(
				array(
					'@type'           => 'ItemList',
					'itemListElement' => array(
						array(
							'@type'    => 'ListItem',
							'position' => 1,
							'url'      => get_permalink( $posts[0] ),
						),
						array(
							'@type'    => 'ListItem',
							'position' => 2,
							'url'      => get_permalink( $posts[1] ),
						),
						array(
							'@type'    => 'ListItem',
							'position' => 3,
							'url'      => get_permalink( $posts[2] ),
						),
					),
				),
			),
			$this->nodes( 'ItemList' )
		);

		$this->publish_post( array( 'post_category' => array( $category ) ) );
		$this->go_to( get_category_link( $category ) );
		$this->assertSame( array(), $this->nodes( 'ItemList' ), 'Not with a post that isn\'t a course.' );

		$two = self::factory()->category->create( array( 'name' => 'Two courses' ) );
		wp_set_post_categories( $posts[0], array( $two ) );
		wp_set_post_categories( $posts[1], array( $two ) );
		$this->go_to( get_category_link( $two ) );
		$this->assertSame( array(), $this->nodes( 'ItemList' ), 'Google asks for at least three courses.' );
	}

	/**
	 * What the author types is text: markup in it is escaped on the card and stripped in the data.
	 */
	public function test_text_is_escaped(): void {
		$post = $this->publish_content( $this->course( $this->reading( array( 'instructor' => 'Ana <b>Reyes</b><img src=x onerror=alert(1)>' ) ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertStringNotContainsString( '<img', $this->cards() );
		$this->assertStringNotContainsString( '<b>', $this->cards() );
		$this->assertSame( 'Ana Reyes', $this->nodes( 'Course' )[0]['hasCourseInstance']['instructor']['name'] );
	}
}
