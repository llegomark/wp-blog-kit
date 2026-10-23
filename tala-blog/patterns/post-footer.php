<?php
/**
 * Title: After the article
 * Slug: tala-blog/post-footer
 * Categories: text
 * Inserter: no
 * Description: "Keep reading": a navigation landmark with up to three recent articles from the same categories, then the older and newer articles as cards; then the author box with biography.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"nav","ariaLabel":<?php echo tala_blog_block_text( _x( 'Keep reading', 'name of the navigation to other articles for screen readers', 'tala-blog' ) ); ?>,"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Keep reading', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-post-nav","layout":{"type":"default"}} -->
<nav class="wp-block-group tala-post-nav" aria-label="<?php echo esc_attr( _x( 'Keep reading', 'name of the navigation to other articles for screen readers', 'tala-blog' ) ); ?>"><!-- wp:heading {"level":2,"className":"tala-post-nav__title"} -->
<h2 class="wp-block-heading tala-post-nav__title"><?php echo esc_html__( 'Keep reading', 'tala-blog' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Articles from the same categories', 'name of a block in the List View', 'tala-blog' ) ); ?>},"className":"tala-list tala-related"} -->
<div class="wp-block-query tala-list tala-related"><!-- wp:post-template {"className":"tala-related"} -->
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Entry', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-list-item","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group tala-list-item"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Title and date', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-list-text","layout":{"type":"default"}} -->
<div class="wp-block-group tala-list-text"><!-- wp:post-title {"level":3,"isLink":true} /-->

<!-- wp:post-date /--></div>
<!-- /wp:group -->

<!-- wp:post-featured-image {"aspectRatio":"4/3","sizeSlug":"medium","className":"tala-list-thumb"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Older and newer articles', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-post-nav__links","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"stretch"}} -->
<div class="wp-block-group tala-post-nav__links"><!-- wp:post-navigation-link {"type":"previous","label":<?php echo tala_blog_block_text( __( 'Older article', 'tala-blog' ) ); ?>,"showTitle":true,"linkLabel":true,"arrow":"arrow"} /-->

<!-- wp:post-navigation-link {"label":<?php echo tala_blog_block_text( __( 'Newer article', 'tala-blog' ) ); ?>,"showTitle":true,"linkLabel":true,"arrow":"arrow"} /--></div>
<!-- /wp:group --></nav>
<!-- /wp:group -->

<!-- wp:post-author {"avatarSize":64,"showBio":true,"byline":<?php echo tala_blog_block_text( __( 'Written by', 'tala-blog' ) ); ?>,"isLink":true,"className":"tala-author"} /-->
