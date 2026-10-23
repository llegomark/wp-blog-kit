<?php
/**
 * Title: Header
 * Slug: tala-blog/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 * Description: Site title and the site's pages. The menu is a labelled navigation landmark.
 *
 * @package TalaBlog
 */

?>
<!-- wp:group {"className":"tala-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group tala-header"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:site-title {"level":0} /-->

<!-- wp:group {"tagName":"nav","ariaLabel":<?php echo tala_blog_block_text( _x( 'Primary', 'header menu label for screen readers', 'tala-blog' ) ); ?>,"className":"tala-nav","layout":{"type":"default"}} -->
<nav class="wp-block-group tala-nav" aria-label="<?php echo esc_attr_x( 'Primary', 'header menu label for screen readers', 'tala-blog' ); ?>"><!-- wp:page-list /--></nav>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
