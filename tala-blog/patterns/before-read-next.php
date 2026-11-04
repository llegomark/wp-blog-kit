<?php
/**
 * Title: Call to action before Read next
 * Slug: tala-blog/before-read-next
 * Categories: call-to-action
 * Block Types: core/template-part/uncategorized
 * Description: A call to action for the Before Read next template part, at the end of a post's text, after its topics and before Read next: a card in the text column (a complementary landmark named "Recommended") holding an eyebrow, a heading, a lead, one button and a short line of detail. Replace the text, the landmark's name and the button's link with your own.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"aside","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Call to action before Read next', 'name of a group in the List View', 'tala-blog' ) ); ?>},"ariaLabel":<?php echo tala_blog_block_text( _x( 'Recommended', 'name of the call to action before Read next for screen readers', 'tala-blog' ) ); ?>,"className":"tala-cta-card","layout":{"type":"default"}} -->
<aside class="wp-block-group tala-cta-card" aria-label="<?php echo esc_attr_x( 'Recommended', 'name of the call to action before Read next for screen readers', 'tala-blog' ); ?>"><!-- wp:paragraph {"className":"tala-eyebrow"} -->
<p class="tala-eyebrow"><?php echo esc_html__( 'Recommended', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"tala-subtitle"} -->
<h2 class="wp-block-heading tala-subtitle"><?php echo esc_html__( 'Worth a look next', 'tala-blog' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tala-lead"} -->
<p class="tala-lead"><?php echo esc_html__( 'Tell readers what you recommend and why it helps them.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__( 'Take a look', 'tala-blog' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"className":"tala-meta"} -->
<p class="tala-meta"><?php echo esc_html__( 'A short line of detail, such as who it is for.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph --></aside>
<!-- /wp:group -->
