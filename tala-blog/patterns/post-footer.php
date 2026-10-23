<?php
/**
 * Title: After the article
 * Slug: tala-blog/post-footer
 * Categories: text
 * Inserter: no
 * Description: The author box with biography, and links to the previous and next articles.
 *
 * @package TalaBlog
 */

?>
<!-- wp:post-author {"avatarSize":64,"showBio":true,"byline":<?php echo tala_blog_block_text( __( 'Written by', 'tala-blog' ) ); ?>,"isLink":true,"className":"tala-author"} /-->

<!-- wp:group {"className":"tala-post-nav","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group tala-post-nav"><!-- wp:post-navigation-link {"type":"previous","label":<?php echo tala_blog_block_text( __( 'Previous article', 'tala-blog' ) ); ?>,"showTitle":true} /-->

<!-- wp:post-navigation-link {"label":<?php echo tala_blog_block_text( __( 'Next article', 'tala-blog' ) ); ?>,"showTitle":true} /--></div>
<!-- /wp:group -->
