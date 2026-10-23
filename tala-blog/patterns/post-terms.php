<?php
/**
 * Title: Categories and tags
 * Slug: tala-blog/post-terms
 * Categories: text
 * Inserter: no
 * Description: "Filed under" categories and the tags: the footer at the end of an article.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"footer","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Categories and tags', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-terms","layout":{"type":"default"}} -->
<footer class="wp-block-group tala-terms"><!-- wp:post-terms {"term":"category","prefix":<?php /* translators: Shown before the article's categories; keep the space at the end. */ echo tala_blog_block_text( __( 'Filed under: ', 'tala-blog' ) ); ?>} /-->

<!-- wp:post-terms {"term":"post_tag","prefix":<?php /* translators: Shown before the article's tags; keep the space at the end. */ echo tala_blog_block_text( __( 'Tags: ', 'tala-blog' ) ); ?>} /--></footer>
<!-- /wp:group -->
