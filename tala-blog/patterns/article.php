<?php
/**
 * Title: Article title band
 * Slug: tala-blog/article
 * Categories: posts
 * Inserter: no
 * Description: The article's title band, on graph paper: breadcrumbs, the first category as eyebrow, the title in display type, the excerpt as lead (only one written for the article, or given by a plugin), "By" the author, the "Published" and "Updated" dates and the reading time. Parts the article doesn't have are left out.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:pattern {"slug":"tala-blog/breadcrumbs"} /-->

<!-- wp:post-terms {"term":"category","className":"tala-first-term tala-eyebrow"} /-->

<!-- wp:post-title {"level":1,"className":"tala-display"} /-->

<!-- wp:post-excerpt {"excerptLength":100,"className":"tala-lead"} /-->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Byline, dates and reading time', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-credits","layout":{"type":"default"}} -->
<div class="wp-block-group tala-credits"><!-- wp:post-author-name {"isLink":true,"className":"tala-byline"} /-->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Dates and reading time', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-meta tala-article-meta","layout":{"type":"default"}} -->
<div class="wp-block-group tala-meta tala-article-meta"><!-- wp:post-date /-->

<!-- wp:post-date {"displayType":"modified"} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tala-blog/reading-time"}}},"className":"tala-reading-time"} -->
<p class="tala-reading-time"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
