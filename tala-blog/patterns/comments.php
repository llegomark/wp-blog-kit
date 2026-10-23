<?php
/**
 * Title: Comments
 * Slug: tala-blog/comments
 * Categories: text
 * Block Types: core/comments
 * Inserter: no
 * Description: Comments, each an article of its own, with the author's name, date (linked to the comment), text, and reply and edit links; then the comment form. No commenter avatars, so there are no requests to Gravatar.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:comments {"className":"tala-comments"} -->
<div class="wp-block-comments tala-comments"><!-- wp:comments-title {"level":2} /-->

<!-- wp:comment-template -->
<!-- wp:group {"tagName":"article","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Comment', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-comment","layout":{"type":"default"}} -->
<article class="wp-block-group tala-comment"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Name and date', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-comment-meta","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
<div class="wp-block-group tala-comment-meta"><!-- wp:comment-author-name /-->

<!-- wp:comment-date /--></div>
<!-- /wp:group -->

<!-- wp:comment-content /-->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Reply and edit links', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-comment-actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group tala-comment-actions"><!-- wp:comment-reply-link /-->

<!-- wp:comment-edit-link /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination -->
<!-- wp:comments-pagination-previous /-->

<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form /--></div>
<!-- /wp:comments -->
