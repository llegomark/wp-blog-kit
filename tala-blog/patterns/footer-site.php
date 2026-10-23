<?php
/**
 * Title: Footer site title and tagline
 * Slug: tala-blog/footer-site
 * Categories: footer
 * Inserter: no
 * Description: The bottom row of both footers: site title and tagline.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Site title and tagline', 'name of a group in the List View', 'tala-blog' ) ); ?>},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:site-title {"level":0} /-->

<!-- wp:site-tagline /--></div>
<!-- /wp:group -->
