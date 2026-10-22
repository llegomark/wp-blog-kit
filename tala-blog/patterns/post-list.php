<?php
/**
 * Title: Post list
 * Slug: tala-blog/post-list
 * Categories: query
 * Inserter: no
 * Description: The list of articles used on the home page, archives and search results.
 *
 * @package TalaBlog
 */

?>
<!-- wp:query {"queryId":1,"query":{"inherit":true},"className":"tala-list"} -->
<div class="wp-block-query tala-list"><!-- wp:post-template -->
<!-- wp:post-title {"level":2,"isLink":true} /-->

<!-- wp:post-excerpt {"excerptLength":40} /-->

<!-- wp:post-date /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous {"label":"<?php echo esc_attr__( 'Newer articles', 'tala-blog' ); ?>"} /-->

<!-- wp:query-pagination-next {"label":"<?php echo esc_attr__( 'Older articles', 'tala-blog' ); ?>"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html__( 'No articles found.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
