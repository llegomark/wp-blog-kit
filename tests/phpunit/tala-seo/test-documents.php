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

		$this->expectException( RuntimeException::class );
		( new Tala_SEO_CLI() )->pdf_canonicals( array(), array( 'variable' => 'site-pdf' ) );
	}
}
