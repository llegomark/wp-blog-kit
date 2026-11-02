<?php
/**
 * Title: Topics
 * Slug: tala-blog/post-terms
 * Categories: text
 * Inserter: no
 * Description: "Topics": the article's categories and tags as chips, the footer at the end of its text. Left out when the article has neither.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"footer","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Topics', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-terms","layout":{"type":"default"}} -->
<footer class="wp-block-group tala-terms"><!-- wp:paragraph {"className":"tala-eyebrow"} -->
<p class="tala-eyebrow"><?php echo esc_html_x( 'Topics', 'label over the categories and tags of an article', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Categories and tags', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-chips","layout":{"type":"default"}} -->
<div class="wp-block-group tala-chips"><!-- wp:post-terms {"term":"category"} /-->

<!-- wp:post-terms {"term":"post_tag"} /--></div>
<!-- /wp:group --></footer>
<!-- /wp:group -->
