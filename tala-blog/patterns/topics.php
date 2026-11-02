<?php
/**
 * Title: Topics
 * Slug: tala-blog/topics
 * Categories: text
 * Inserter: no
 * Description: "Browse by topic" over the site's top-level categories as chips, in the home page's title band. A navigation landmark named by its label; left out when there is no category to link to.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"nav","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Topics', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-topics","layout":{"type":"default"}} -->
<nav class="wp-block-group tala-topics"><!-- wp:paragraph {"className":"tala-meta tala-topics__label"} -->
<p class="tala-meta tala-topics__label"><?php echo esc_html__( 'Browse by topic', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:categories {"showOnlyTopLevel":true,"className":"tala-chips"} /--></nav>
<!-- /wp:group -->
