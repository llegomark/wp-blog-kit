<?php
/**
 * Title: Post list
 * Slug: tala-blog/post-list
 * Categories: query
 * Inserter: no
 * Description: The list of articles on the home page, archives and search results, one ruled row each: the date and the first category on the left, the title and summary on the right. Then "Previous page" and "Next page" (numbered pages too, if Appearance → Customize → Article lists asks for them). Posts per page come from Settings → Reading.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:query {"query":{"inherit":true},"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Article list', 'name of a block in the List View', 'tala-blog' ) ); ?>},"className":"tala-rows"} -->
<div class="wp-block-query tala-rows"><!-- wp:post-template -->
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Entry', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-row","layout":{"type":"default"}} -->
<div class="wp-block-group tala-row"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Date and category', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-row__meta","layout":{"type":"default"}} -->
<div class="wp-block-group tala-row__meta"><!-- wp:post-date /-->

<!-- wp:post-terms {"term":"category","className":"tala-first-term"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Title and summary', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-row__text","layout":{"type":"default"}} -->
<div class="wp-block-group tala-row__text"><!-- wp:post-title {"level":2,"isLink":true,"className":"tala-subtitle"} /-->

<!-- wp:post-excerpt {"excerptLength":40} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous {"label":<?php echo tala_blog_block_text( __( 'Previous page', 'tala-blog' ) ); ?>} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":<?php echo tala_blog_block_text( __( 'Next page', 'tala-blog' ) ); ?>} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html__( 'No articles found.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
