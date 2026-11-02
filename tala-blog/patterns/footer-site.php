<?php
/**
 * Title: Footer copyright and tagline
 * Slug: tala-blog/footer-site
 * Categories: footer
 * Inserter: no
 * Description: The footer's bottom bar: "© year site title" on the left (the year is always this year, from the tala-blog/year binding), the tagline on the right.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Copyright and tagline', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-footer-bar","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group tala-footer-bar"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Copyright', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-copyright","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group tala-copyright"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tala-blog/year"}}},"className":"tala-year"} -->
<p class="tala-year"></p>
<!-- /wp:paragraph -->

<!-- wp:site-title {"level":0,"isLink":false} /--></div>
<!-- /wp:group -->

<!-- wp:site-tagline /--></div>
<!-- /wp:group -->
