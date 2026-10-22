<?php
/**
 * Title: Page not found
 * Slug: tala-blog/hidden-404
 * Categories: text
 * Inserter: no
 * Description: Heading, explanation and search form for the 404 template.
 *
 * @package TalaBlog
 */

?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html__( 'Page not found', 'tala-blog' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'This page doesn’t exist. The link may be mistyped, or the article may have moved. Search for it instead:', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:pattern {"slug":"tala-blog/search"} /-->
