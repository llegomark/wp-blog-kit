<?php
/**
 * Title: Article
 * Slug: tala-blog/article
 * Categories: posts
 * Inserter: no
 * Description: The article on its own page: breadcrumbs, a header with the title and the "Published" and "Updated" dates, the featured image, the text, and a footer with the categories and tags.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"article","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Article', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-paper","layout":{"type":"default"}} -->
<article class="wp-block-group tala-paper"><!-- wp:pattern {"slug":"tala-blog/breadcrumbs"} /-->

<!-- wp:group {"tagName":"header","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Title and dates', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-article-header","layout":{"type":"default"}} -->
<header class="wp-block-group tala-article-header"><!-- wp:post-title {"level":1} /-->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Dates', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-dates","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group tala-dates"><!-- wp:post-date /-->

<!-- wp:post-date {"displayType":"modified"} /--></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:post-featured-image {"aspectRatio":"16/9","sizeSlug":"large"} /-->

<!-- wp:post-content {"layout":{"type":"default"}} /-->

<!-- wp:pattern {"slug":"tala-blog/post-terms"} /--></article>
<!-- /wp:group -->
