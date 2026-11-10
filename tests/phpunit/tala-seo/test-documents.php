<?php
/**
 * PDFs and their articles: `wp tala-seo pdf-canonicals` lists each PDF with the article that
 * presents it, for the web server's rel="canonical" header on the file.
 *
 * @package TalaTests
 */

/**
 * PDF canonicals.
 */
class Test_Tala_SEO_Documents extends Tala_SEO_Test_Case {

	/**
	 * Files made for a test, removed after it.
	 *
	 * @var string[]
	 */
	private array $files = array();

	/**
	 * Clears what the command printed before.
	 */
	public function set_up(): void {
		parent::set_up();
		WP_CLI::reset();
		$this->set_pretty_permalinks();
	}

	/**
	 * Removes the test's files.
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			wp_delete_file( $file );
		}
		parent::tear_down();
	}

	/**
	 * A PDF in the uploads folder, under 2021/10.
	 *
	 * @param string $name File name.
	 * @return string The file's address on the site.
	 */
	private function pdf( string $name ): string {
		$file = wp_get_upload_dir()['basedir'] . '/2021/10/' . $name;
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, '%PDF-1.4' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a test file.
		$this->files[] = $file;
		return wp_get_upload_dir()['baseurl'] . '/2021/10/' . rawurlencode( $name );
	}

	/**
	 * A published post or page with this content.
	 *
	 * @param string               $content Post content.
	 * @param array<string, mixed> $args    More arguments.
	 * @return int
	 */
	private function article( string $content, array $args = array() ): int {
		return $this->publish_post( array_merge( array( 'post_content' => $content ), $args ) );
	}

	/**
	 * The article of a PDF: the one it was uploaded to when that one presents it, else the one
	 * that presents the fewest PDFs, posts before pages, the earliest first. Links, embeds (block
	 * attributes with escaped slashes) and encoded file names count; other sites' files, missing
	 * files, and articles that are drafts, hidden from search engines or behind a password don't.
	 */
	public function test_each_pdf_gets_its_article(): void {
		$module = $this->pdf( 'module.pdf' );
		$list_a = $this->pdf( 'list-a.pdf' );
		$list_b = $this->pdf( 'list-b.pdf' );
		$named  = $this->pdf( 'My Module.pdf' );
		$both   = $this->pdf( 'both.pdf' );
		$hidden = $this->pdf( 'hidden.pdf' );
		$locked = $this->pdf( 'locked.pdf' );
		$doc    = $this->pdf( 'page-doc.pdf' );
		$gone   = wp_get_upload_dir()['baseurl'] . '/2021/10/gone.pdf';

		$list = $this->article(
			"<p><a href=\"$module\">Module</a> <a href=\"/wp-content/uploads/2021/10/list-a.pdf\">A</a> <a href=\"$list_b\">B</a></p>",
			array( 'post_date' => '2021-01-01 08:00:00' )
		);
		self::factory()->attachment->create_object(
			array(
				'file'           => '2021/10/module.pdf',
				'post_parent'    => $list,
				'post_mime_type' => 'application/pdf',
			)
		);
		$article = $this->article(
			'<!-- wp:pdfemb/pdf-embedder-viewer {"url":"' . str_replace( '/', '\\/', $module ) . '"} -->'
			. "<p><a href=\"$named\">Download</a> <a href=\"$gone\">Gone</a></p>"
		);
		$single  = $this->article( "<p><a href=\"$list_a\">A</a> <a href=\"https://elsewhere.test/wp-content/uploads/2021/10/list-b.pdf\">elsewhere</a></p>" );
		$page    = $this->article(
			"<p><a href=\"$both\">Both</a></p>",
			array(
				'post_type' => 'page',
				'post_date' => '2020-01-01 08:00:00',
			)
		);
		$post    = $this->article( "<p><a href=\"$both\">Both</a></p>" );
		$doc_id  = $this->article( '<!-- wp:file {"href":"' . str_replace( '/', '\\/', $doc ) . '"} /-->', array( 'post_type' => 'page' ) );
		$this->article( "<p><a href=\"$hidden\">Hidden</a></p>", array( 'meta_input' => array( TALA_SEO_NOINDEX => '1' ) ) );
		$this->article( "<p><a href=\"$locked\">Locked</a></p>", array( 'post_password' => 'secret' ) );
		self::factory()->post->create(
			array(
				'post_status'  => 'draft',
				'post_content' => "<p><a href=\"$list_b\">B</a> <a href=\"$hidden\">Hidden</a></p>",
			)
		);

		$this->assertSame(
			array(
				'/wp-content/uploads/2021/10/My Module.pdf' => get_permalink( $article ),
				'/wp-content/uploads/2021/10/both.pdf'     => get_permalink( $post ),
				'/wp-content/uploads/2021/10/list-a.pdf'   => get_permalink( $single ),
				'/wp-content/uploads/2021/10/list-b.pdf'   => get_permalink( $list ),
				'/wp-content/uploads/2021/10/module.pdf'   => get_permalink( $list ),
				'/wp-content/uploads/2021/10/page-doc.pdf' => get_permalink( $doc_id ),
			),
			tala_seo_pdf_canonicals()
		);
		$this->assertNotEmpty( $page );
		$this->assertSame( array( 'example.org', 'www.example.org' ), tala_seo_site_hosts() );
	}

	/**
	 * The nginx map: one quoted line per PDF, the whole Link header as the value, empty for other
	 * files (nginx then sends none); what nginx can't quote safely is left out.
	 */
	public function test_nginx_map(): void {
		$map = tala_seo_pdf_canonicals_nginx(
			array(
				'/wp-content/uploads/2021/10/a.pdf'   => 'https://example.org/a/',
				'/wp-content/uploads/2021/10/b$c.pdf' => 'https://example.org/b/',
				'/wp-content/uploads/2021/10/d".pdf'  => 'https://example.org/d/',
				'/wp-content/uploads/2021/10/e f.pdf' => 'https://example.org/e/',
			),
			'site_pdf_canonical'
		);
		$this->assertSame(
			"# The canonical article of each PDF, from `wp tala-seo pdf-canonicals` (2 PDFs).\n"
			. "# Use it in the site's PDF location: add_header Link \$site_pdf_canonical always;\n"
			. "# The longest path has 35 bytes: nginx's http block needs \"map_hash_bucket_size 64;\" or more.\n"
			. "map \$uri \$site_pdf_canonical {\n"
			. "    default \"\";\n"
			. "    \"/wp-content/uploads/2021/10/a.pdf\" '<https://example.org/a/>; rel=\"canonical\"';\n"
			. "    \"/wp-content/uploads/2021/10/e f.pdf\" '<https://example.org/e/>; rel=\"canonical\"';\n"
			. "}\n",
			$map
		);
		$this->assertStringEndsWith( "map \$uri \$x {\n    default \"\";\n}\n", tala_seo_pdf_canonicals_nginx( array(), 'x' ) );
	}

	/**
	 * The map holds no path twice: nginx reads a map's paths without regard to letter case and
	 * refuses one that repeats a path, which would stop the server's configuration from loading.
	 * Paths that differ only in case are written once when they share an article, and left out when
	 * they don't (nginx couldn't tell which file is asked for); either way the command says so.
	 */
	public function test_nginx_map_has_no_path_twice(): void {
		$safe = tala_seo_pdf_canonicals_for_nginx(
			array(
				'/wp-content/uploads/2021/10/Report.pdf' => 'https://example.org/first/',
				'/wp-content/uploads/2021/10/report.pdf' => 'https://example.org/second/',
				'/wp-content/uploads/2021/10/Same.pdf'   => 'https://example.org/same/',
				'/wp-content/uploads/2021/10/same.PDF'   => 'https://example.org/same/',
				'/wp-content/uploads/2021/10/alone.pdf'  => 'https://example.org/alone/',
				'/wp-content/uploads/2021/10/b$c.pdf'    => 'https://example.org/b/',
			)
		);
		$this->assertSame(
			array(
				'/wp-content/uploads/2021/10/Same.pdf'  => 'https://example.org/same/',
				'/wp-content/uploads/2021/10/alone.pdf' => 'https://example.org/alone/',
			),
			$safe['map']
		);
		$this->assertSame(
			array(
				'/wp-content/uploads/2021/10/Report.pdf',
				'/wp-content/uploads/2021/10/b$c.pdf',
				'/wp-content/uploads/2021/10/report.pdf',
				'/wp-content/uploads/2021/10/same.PDF',
			),
			array_keys( $safe['skipped'] )
		);
		$this->assertStringContainsString( 'another article', $safe['skipped']['/wp-content/uploads/2021/10/Report.pdf'] );
		$this->assertStringContainsString( 'another article', $safe['skipped']['/wp-content/uploads/2021/10/report.pdf'] );
		$this->assertStringContainsString( '/wp-content/uploads/2021/10/Same.pdf', $safe['skipped']['/wp-content/uploads/2021/10/same.PDF'], 'Names the line that covers it.' );

		$map = tala_seo_pdf_canonicals_nginx(
			array(
				'/wp-content/uploads/2021/10/Report.pdf' => 'https://example.org/first/',
				'/wp-content/uploads/2021/10/report.pdf' => 'https://example.org/second/',
				'/wp-content/uploads/2021/10/alone.pdf'  => 'https://example.org/alone/',
			),
			'x'
		);
		$this->assertStringNotContainsStringIgnoringCase( 'report.pdf', $map );
		$this->assertStringContainsString( '(1 PDFs)', $map );
	}

	/**
	 * The map fits the server: nginx also refuses a map with a path too long for its
	 * map_hash_bucket_size. The map says which size its longest path needs, and with the server's
	 * size given, longer paths are left out.
	 */
	public function test_nginx_map_fits_the_bucket_size(): void {
		$this->assertSame( 46, tala_seo_nginx_map_key_limit( 64 ) );
		$this->assertSame( 110, tala_seo_nginx_map_key_limit( 100 ), 'nginx rounds the size up to its 64-byte lines.' );
		$this->assertSame( 494, tala_seo_nginx_map_key_limit( 512 ) );
		$this->assertSame( 64, tala_seo_nginx_map_bucket_size( 46 ) );
		$this->assertSame( 128, tala_seo_nginx_map_bucket_size( 47 ) );
		$this->assertSame( 256, tala_seo_nginx_map_bucket_size( 204 ) );

		$short = '/wp-content/uploads/2021/10/a.pdf';
		$long  = '/wp-content/uploads/2021/10/' . str_repeat( 'n', 28 ) . '.pdf'; // 60 bytes.
		$both  = array(
			$short => 'https://example.org/a/',
			$long  => 'https://example.org/long/',
		);
		$this->assertSame( 60, strlen( $long ) );

		$this->assertSame( $both, tala_seo_pdf_canonicals_for_nginx( $both )['map'], 'Without the server\'s size, every path is kept.' );
		$this->assertStringContainsString( "# The longest path has 60 bytes: nginx's http block needs \"map_hash_bucket_size 128;\" or more.\n", tala_seo_pdf_canonicals_nginx( $both, 'x' ) );

		$fitted = tala_seo_pdf_canonicals_for_nginx( $both, 64 );
		$this->assertSame( array( $short => 'https://example.org/a/' ), $fitted['map'] );
		$this->assertSame( array( $long ), array_keys( $fitted['skipped'] ) );
		$this->assertStringContainsString( '60 bytes', $fitted['skipped'][ $long ] );
		$this->assertStringNotContainsString( $long, tala_seo_pdf_canonicals_nginx( $both, 'x', 64 ) );
		$this->assertSame( $both, tala_seo_pdf_canonicals_for_nginx( $both, 128 )['map'] );
	}

	/**
	 * `wp tala-seo pdf-canonicals` prints the map (or a table), and turns down a variable name nginx
	 * can't use.
	 */
	public function test_command(): void {
		$url     = $this->pdf( 'module.pdf' );
		$article = $this->article( "<p><a href=\"$url\">Module</a></p>" );

		( new Tala_SEO_CLI() )->pdf_canonicals( array(), array( 'variable' => 'depedtambayan_pdf_canonical' ) );
		$printed = implode( "\n", array_column( WP_CLI::$messages, 1 ) );
		$this->assertStringContainsString( 'map $uri $depedtambayan_pdf_canonical {', $printed );
		$this->assertStringContainsString( "\"/wp-content/uploads/2021/10/module.pdf\" '<" . get_permalink( $article ) . '>; rel="canonical"\';', $printed );

		( new Tala_SEO_CLI() )->pdf_canonicals( array(), array( 'format' => 'table' ) );
		$this->assertSame(
			array(
				array(
					'pdf'     => '/wp-content/uploads/2021/10/module.pdf',
					'article' => get_permalink( $article ),
				),
			),
			WP_CLI::$tables[0]['items']
		);

		// What the map leaves out is reported as warnings (on STDERR, so a map written to a file
		// holds the map only), and stays in the table.
		$upper = $this->pdf( 'Guide.pdf' );
		$lower = $this->pdf( 'guide.pdf' );
		$this->article( "<p><a href=\"$upper\">Guide</a></p>" );
		$this->article( "<p><a href=\"$lower\">Guide</a></p>" );
		WP_CLI::reset();
		( new Tala_SEO_CLI() )->pdf_canonicals( array(), array( 'bucket-size' => '512' ) );
		$lines    = implode( "\n", array_column( array_filter( WP_CLI::$messages, static fn( $message ) => 'line' === $message[0] ), 1 ) );
		$warnings = array_column( array_filter( WP_CLI::$messages, static fn( $message ) => 'warning' === $message[0] ), 1 );
		$this->assertStringNotContainsStringIgnoringCase( 'guide.pdf', $lines );
		$this->assertStringContainsString( 'module.pdf', $lines );
		$this->assertCount( 2, $warnings );
		$this->assertStringContainsString( '/wp-content/uploads/2021/10/Guide.pdf', $warnings[0] );
		$this->assertStringContainsString( '/wp-content/uploads/2021/10/guide.pdf', $warnings[1] );

		WP_CLI::reset();
		( new Tala_SEO_CLI() )->pdf_canonicals( array(), array( 'format' => 'table' ) );
		$this->assertCount( 3, WP_CLI::$tables[0]['items'], 'The table lists every PDF.' );

		foreach ( array( array( 'variable' => 'site-pdf' ), array( 'bucket-size' => 'big' ) ) as $options ) {
			try {
				( new Tala_SEO_CLI() )->pdf_canonicals( array(), $options );
				$this->fail( 'Should have been turned down: ' . wp_json_encode( $options ) );
			} catch ( RuntimeException $error ) {
				$this->assertNotSame( '', $error->getMessage() );
			}
		}
	}
}
