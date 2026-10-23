<?php
/**
 * Title: Post list
 * Slug: tala-blog/post-list
 * Categories: query
 * Inserter: no
 * Description: The list of articles on the home page, archives and search results: title, summary, date and a thumbnail of the featured image, then numbered pages. Posts per page come from Settings → Reading.
 *
 * @package TalaBlog
 */

?>
<!-- wp:query {"query":{"inherit":true},"className":"tala-list"} -->
<div class="wp-block-query tala-list"><!-- wp:post-template -->
<!-- wp:group {"className":"tala-list-item","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group tala-list-item"><!-- wp:group {"className":"tala-list-text","layout":{"type":"default"}} -->
<div class="wp-block-group tala-list-text"><!-- wp:post-title {"level":2,"isLink":true} /-->

<!-- wp:post-excerpt {"excerptLength":40} /-->

<!-- wp:post-date /--></div>
<!-- /wp:group -->

<!-- wp:post-featured-image {"aspectRatio":"4/3","sizeSlug":"medium","className":"tala-list-thumb"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous {"label":<?php echo tala_blog_block_text( __( 'Newer articles', 'tala-blog' ) ); ?>} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":<?php echo tala_blog_block_text( __( 'Older articles', 'tala-blog' ) ); ?>} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html__( 'No articles found.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
