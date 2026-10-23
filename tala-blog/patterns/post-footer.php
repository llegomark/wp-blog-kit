<?php
/**
 * Title: After the article
 * Slug: tala-blog/post-footer
 * Categories: text
 * Inserter: no
 * Description: The author box with biography, and links to the previous and next articles: a navigation landmark named "Previous and next articles".
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:post-author {"avatarSize":64,"showBio":true,"byline":<?php echo tala_blog_block_text( __( 'Written by', 'tala-blog' ) ); ?>,"isLink":true,"className":"tala-author"} /-->

<!-- wp:group {"tagName":"nav","ariaLabel":<?php echo tala_blog_block_text( _x( 'Previous and next articles', 'name of the article navigation for screen readers', 'tala-blog' ) ); ?>,"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Previous and next articles', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-post-nav","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<nav class="wp-block-group tala-post-nav" aria-label="<?php echo esc_attr( _x( 'Previous and next articles', 'name of the article navigation for screen readers', 'tala-blog' ) ); ?>"><!-- wp:post-navigation-link {"type":"previous","label":<?php echo tala_blog_block_text( __( 'Previous article', 'tala-blog' ) ); ?>,"showTitle":true} /-->

<!-- wp:post-navigation-link {"label":<?php echo tala_blog_block_text( __( 'Next article', 'tala-blog' ) ); ?>,"showTitle":true} /--></nav>
<!-- /wp:group -->
