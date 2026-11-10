<?php
/**
 * The filters a site's own code can use: tala_seo_post_types, tala_seo_schema_nodes,
 * tala_seo_term_taxonomies and tala_seo_thin_taxonomies. (tala_seo_publish_requirements and
 * tala_seo_head_comment are tested with the publish check and the head.) A renamed or dropped
 * filter would break such code without a sound, so each has a test of what it promises.
 *
 * @package TalaTests
 */

/**
 * Tala SEO's public filters.
 */
class Test_Tala_SEO_Hooks extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs, as on a real site.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
	}

	/**
	 * The tala_seo_post_types filter chooses the post types with SEO fields: a type it adds gets
	 * the fields (registered for the REST API, so the SEO panel can save them) and its SEO title in
	 * the page's title; a type it takes out loses them.
	 */
	public function test_post_types_filter_chooses_the_types_with_seo_fields(): void {
		register_post_type(
			'guide',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
			)
		);
		$guide = self::factory()->post->create(
			array(
				'post_type'   => 'guide',
				'post_status' => 'publish',
				'post_title'  => 'Getting started',
				'meta_input'  => array( TALA_SEO_TITLE => 'A guide for new teachers' ),
			)
		);
		$page  = $this->publish_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'About',
				'meta_input' => array( TALA_SEO_TITLE => 'About this school' ),
			)
		);
		$this->assertSame( array( 'post', 'page' ), tala_seo_post_types(), 'Posts and pages by default.' );

		$guide_url = add_query_arg(
			array(
				'post_type' => 'guide',
				'p'         => $guide,
			),
			home_url( '/' )
		);
		$this->go_to( $guide_url );
		$this->assertTrue( is_singular( 'guide' ) );
		$this->assertStringStartsWith( 'Getting started', wp_get_document_title(), 'Another type keeps its own title.' );
		$this->assertFalse( registered_meta_key_exists( 'post', TALA_SEO_DESCRIPTION, 'guide' ) );

		add_filter( 'tala_seo_post_types', static fn( array $types ): array => array_merge( array_diff( $types, array( 'page' ) ), array( 'guide' ) ) );
		tala_seo_register_meta();
		tala_seo_guard_setup();
		$this->assertSame( array( 'post', 'guide' ), tala_seo_post_types() );
		foreach ( array( TALA_SEO_TITLE, TALA_SEO_DESCRIPTION, TALA_SEO_NOINDEX, TALA_SEO_CANONICAL, TALA_SEO_REDIRECT, TALA_SEO_MINOR_EDIT ) as $key ) {
			$this->assertTrue( registered_meta_key_exists( 'post', $key, 'guide' ), "$key is registered for the added type." );
		}
		$this->assertStringStartsWith( 'A guide for new teachers', wp_get_document_title(), 'The added type\'s SEO title is used.' );
		$this->assertSame( array(), tala_seo_publish_requirements( 'guide' ), 'An added type has no publish requirements of its own.' );

		$this->go_to( get_permalink( $page ) );
		$this->assertStringStartsWith( 'About |', wp_get_document_title(), 'A type taken out keeps its own title.' );
	}

	/**
	 * The tala_seo_schema_nodes filter has the last word on the page's structured data: a node it
	 * adds is printed, one it takes out is not, and an empty one is dropped.
	 */
	public function test_schema_nodes_filter_changes_the_structured_data(): void {
		$this->go_to( get_permalink( $this->publish_post() ) );
		$this->assertArrayHasKey( 'BlogPosting', $this->schema() );

		add_filter(
			'tala_seo_schema_nodes',
			static function ( array $nodes ): array {
				$nodes   = array_filter( $nodes, static fn( array $node ): bool => 'BlogPosting' !== $node['@type'] );
				$nodes[] = array(
					'@type' => 'Dataset',
					'name'  => 'Enrolment by region',
				);
				$nodes[] = array();
				return $nodes;
			}
		);
		$schema = $this->schema();
		$this->assertArrayNotHasKey( 'BlogPosting', $schema );
		$this->assertSame( 'Enrolment by region', $schema['Dataset']['name'] );
		$this->assertSame( array_values( tala_seo_schema_nodes() ), tala_seo_schema_nodes(), 'A list without gaps or empty nodes.' );
		$this->assertNotContains( array(), tala_seo_schema_nodes() );

		$printed = get_echo( 'tala_seo_print_schema' );
		$this->assertStringContainsString( '"@type":"Dataset"', $printed );
		$this->assertStringNotContainsString( 'BlogPosting', $printed );
	}

	/**
	 * The tala_seo_term_taxonomies filter chooses the taxonomies whose terms get SEO fields: public
	 * ones with an admin screen by default; one it adds gets them, one it takes out doesn't, and
	 * post formats never do.
	 */
	public function test_term_taxonomies_filter_chooses_the_taxonomies_with_seo_fields(): void {
		register_taxonomy(
			'series',
			'post',
			array(
				'public'  => false,
				'show_ui' => true,
			)
		);
		$this->assertContains( 'category', tala_seo_term_taxonomies() );
		$this->assertContains( 'post_tag', tala_seo_term_taxonomies() );
		$this->assertNotContains( 'series', tala_seo_term_taxonomies(), 'Not a public taxonomy.' );
		$this->assertNotContains( 'post_format', tala_seo_term_taxonomies() );

		add_filter(
			'tala_seo_term_taxonomies',
			static fn( array $taxonomies ): array => array_merge( array_diff( $taxonomies, array( 'post_tag' ) ), array( 'series', 'post_format' ) )
		);
		$this->assertContains( 'series', tala_seo_term_taxonomies() );
		$this->assertNotContains( 'post_tag', tala_seo_term_taxonomies() );
		$this->assertNotContains( 'post_format', tala_seo_term_taxonomies(), 'Post formats have no page worth describing.' );

		tala_seo_register_archive_meta();
		$this->assertTrue( registered_meta_key_exists( 'term', TALA_SEO_DESCRIPTION, 'series' ) );
		$this->assertTrue( registered_meta_key_exists( 'term', TALA_SEO_DESCRIPTION, 'category' ) );
		$this->assertFalse( registered_meta_key_exists( 'term', TALA_SEO_DESCRIPTION, 'post_tag' ) );
	}

	/**
	 * The tala_seo_thin_taxonomies filter chooses the taxonomies whose pages need the minimum
	 * number of posts to be indexed: tags by default; with categories added, a category with one
	 * post gets noindex too.
	 */
	public function test_thin_taxonomies_filter_chooses_where_the_minimum_applies(): void {
		$this->set_setting( 'tag_min_posts', 2 );
		$category = self::factory()->category->create( array( 'name' => 'Assessment' ) );
		$tag      = self::factory()->tag->create( array( 'name' => 'rubrics' ) );
		$post     = $this->publish_post( array( 'post_category' => array( $category ) ) );
		wp_set_post_tags( $post, array( 'rubrics' ) );

		$this->go_to( get_term_link( $tag ) );
		$this->assertStringContainsString( 'noindex', $this->robots(), 'A tag with one post, by default.' );
		$this->go_to( get_term_link( $category ) );
		$this->assertStringNotContainsString( 'noindex', $this->robots(), 'A category with one post, by default.' );
		$this->assertSame( 0, tala_seo_min_posts( 'category' ) );

		add_filter( 'tala_seo_thin_taxonomies', static fn( array $taxonomies ): array => array_merge( $taxonomies, array( 'category' ) ) );
		$this->assertSame( 2, tala_seo_min_posts( 'category' ) );
		$this->assertStringContainsString( 'noindex', $this->robots(), 'With categories added.' );

		$this->publish_post( array( 'post_category' => array( $category ) ) );
		$this->go_to( get_term_link( $category ) );
		$this->assertStringNotContainsString( 'noindex', $this->robots(), 'With the minimum reached.' );
	}
}
