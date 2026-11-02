<?php
/**
 * Title: After the article
 * Slug: tala-blog/post-footer
 * Categories: text
 * Inserter: no
 * Description: "Read next": a navigation landmark with up to three recent articles from the same categories as ruled rows (date, title and summary, no pictures), then the older and newer articles as two hairline cells; then the author box with biography.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"nav","ariaLabel":<?php echo tala_blog_block_text( _x( 'Read next', 'name of the navigation to other articles for screen readers', 'tala-blog' ) ); ?>,"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Read next', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-post-nav","layout":{"type":"default"}} -->
<nav class="wp-block-group tala-post-nav" aria-label="<?php echo esc_attr( _x( 'Read next', 'name of the navigation to other articles for screen readers', 'tala-blog' ) ); ?>"><!-- wp:heading {"level":2,"className":"tala-eyebrow tala-post-nav__title"} -->
<h2 class="wp-block-heading tala-eyebrow tala-post-nav__title"><?php echo esc_html__( 'Read next', 'tala-blog' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Articles from the same categories', 'name of a block in the List View', 'tala-blog' ) ); ?>},"className":"tala-rows tala-related"} -->
<div class="wp-block-query tala-rows tala-related"><!-- wp:post-template {"className":"tala-related"} -->
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Entry', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-row","layout":{"type":"default"}} -->
<div class="wp-block-group tala-row"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Date', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-row__meta","layout":{"type":"default"}} -->
<div class="wp-block-group tala-row__meta"><!-- wp:post-date /--></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Title and summary', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-row__text","layout":{"type":"default"}} -->
<div class="wp-block-group tala-row__text"><!-- wp:post-title {"level":3,"isLink":true,"className":"tala-subtitle"} /-->

<!-- wp:post-excerpt {"excerptLength":40} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Older and newer articles', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-post-nav__links","layout":{"type":"default"}} -->
<div class="wp-block-group tala-post-nav__links"><!-- wp:post-navigation-link {"type":"previous","label":<?php echo tala_blog_block_text( __( 'Older article', 'tala-blog' ) ); ?>,"showTitle":true,"linkLabel":true,"arrow":"arrow"} /-->

<!-- wp:post-navigation-link {"label":<?php echo tala_blog_block_text( __( 'Newer article', 'tala-blog' ) ); ?>,"showTitle":true,"linkLabel":true,"arrow":"arrow"} /--></div>
<!-- /wp:group --></nav>
<!-- /wp:group -->

<!-- wp:post-author {"avatarSize":96,"showBio":true,"byline":<?php echo tala_blog_block_text( __( 'About the author', 'tala-blog' ) ); ?>,"isLink":true,"className":"tala-author"} /-->
