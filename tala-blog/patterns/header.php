<?php
/**
 * Title: Header
 * Slug: tala-blog/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 * Description: Site logo (when there is one), site title, the Light/Dark switch and the site's menu (its pages until a menu is made). On
 * phones the menu opens from a button into the navigation overlay template part. The menu is a navigation landmark named "Primary".
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"className":"tala-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group tala-header"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Logo, title and menu', 'name of a group in the List View', 'tala-blog' ) ); ?>},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:pattern {"slug":"tala-blog/site-brand"} /-->

<!-- wp:html -->
<button type="button" class="tala-theme-switch" aria-pressed="false" aria-label="<?php esc_attr_e( 'Switch to dark theme', 'tala-blog' ); ?>"><svg class="tala-theme-switch__moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7.5 7.5 0 0 0 10.5 10.5Z"/></svg><svg class="tala-theme-switch__sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.28 5.28l1.42 1.42M17.3 17.3l1.42 1.42M5.28 18.72l1.42-1.42M17.3 6.7l1.42-1.42"/></svg></button>
<!-- /wp:html -->

<!-- wp:navigation {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Primary', 'header menu label for screen readers', 'tala-blog' ) ); ?>,"overlay":"navigation-overlay","icon":"menu","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
