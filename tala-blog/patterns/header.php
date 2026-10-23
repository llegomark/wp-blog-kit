<?php
/**
 * Title: Header
 * Slug: tala-blog/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 * Description: Site logo (when there is one), site title and the site's menu (its pages until a menu is made). On phones the menu opens
 * from a button into the navigation overlay template part. The menu is a navigation landmark named "Primary".
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"className":"tala-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group tala-header"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:pattern {"slug":"tala-blog/site-brand"} /-->

<!-- wp:navigation {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Primary', 'header menu label for screen readers', 'tala-blog' ) ); ?>,"overlay":"navigation-overlay","icon":"menu","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
