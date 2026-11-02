<?php
/**
 * Title: Closing call to action
 * Slug: tala-blog/call-to-action
 * Categories: call-to-action
 * Block Types: core/template-part/uncategorized
 * Description: A closing call to action for the Call to action template part, shown after the content of every post, page and list: a graph-paper band with a card holding an eyebrow, a heading in display type, a lead, one button and a short line of detail. Replace the text and the button's link with your own.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"section","metadata":{"name":<?php echo tala_blog_block_text( _x( 'Call to action', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-band tala-band--paper","layout":{"type":"default"}} -->
<section class="wp-block-group tala-band tala-band--paper"><!-- wp:group {"className":"tala-rail","layout":{"type":"default"}} -->
<div class="wp-block-group tala-rail"><!-- wp:group {"className":"tala-cta","layout":{"type":"default"}} -->
<div class="wp-block-group tala-cta"><!-- wp:paragraph {"className":"tala-eyebrow"} -->
<p class="tala-eyebrow"><?php echo esc_html__( 'Next step', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"tala-display"} -->
<h2 class="wp-block-heading tala-display"><?php echo esc_html__( 'Ready for the next step?', 'tala-blog' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tala-lead"} -->
<p class="tala-lead"><?php echo esc_html__( 'Tell readers where to go next.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__( 'Get started', 'tala-blog' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"className":"tala-meta"} -->
<p class="tala-meta"><?php echo esc_html__( 'A short line of detail, such as what it costs or how long it takes.', 'tala-blog' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
