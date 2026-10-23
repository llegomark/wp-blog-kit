<?php
/**
 * Title: Logo and site title
 * Slug: tala-blog/site-brand
 * Categories: header
 * Inserter: no
 * Description: The site logo (when the site has one) beside the site title, shared by the header and the menu overlay so both line up exactly. The title links to the home page; the logo doesn't, so there is one link, not two.
 *
 * @package TalaBlog
 */

?>
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Logo and site title', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-brand","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tala-brand"><!-- wp:site-logo {"width":28,"isLink":false,"className":"tala-logo"} /-->

<!-- wp:site-title {"level":0} /--></div>
<!-- /wp:group -->
