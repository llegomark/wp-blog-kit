<?php
/**
 * Title: Page not found
 * Slug: tala-blog/hidden-404
 * Categories: text
 * Inserter: no
 * Description: The 404 page's status panel: a large italic "404" (for the eye; the heading says it), the heading, an explanation, a search form and a button back to the home page.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:html -->
<p class="tala-display tala-status-code" aria-hidden="true">404</p>
<!-- /wp:html -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html__( 'Page not found', 'tala-blog' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tala-lead"} -->
<p class="tala-lead"><?php echo esc_html__( 'This page doesn’t exist. The link may be mistyped, or the article may have moved. Search for it, or start again from the home page.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:pattern {"slug":"tala-blog/search"} /-->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Back to the home page', 'tala-blog' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
