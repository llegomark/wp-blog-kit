<?php
/**
 * Title: Page
 * Slug: tala-blog/page
 * Categories: text
 * Inserter: no
 * Description: A page on its own: breadcrumbs, the title, the featured image and the text.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"article","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Page', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-paper","layout":{"type":"default"}} -->
<article class="wp-block-group tala-paper"><!-- wp:pattern {"slug":"tala-blog/breadcrumbs"} /-->

<!-- wp:post-title {"level":1} /-->

<!-- wp:post-featured-image {"aspectRatio":"16/9","sizeSlug":"large"} /-->

<!-- wp:post-content {"layout":{"type":"default"}} /--></article>
<!-- /wp:group -->
