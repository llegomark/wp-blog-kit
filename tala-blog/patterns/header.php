<?php
/**
 * Title: Header
 * Slug: tala-blog/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 * Description: The header band: the site logo (when there is one) and title, the site's menu (its pages until a menu is made), the
 * site search (a button that opens into a field, with a separator before it) and the Light/Dark switch. The menu is a navigation landmark named
 * "Primary"; on narrow screens it folds behind a button at the end of the row and opens into the navigation overlay template part.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"className":"tala-band tala-header","layout":{"type":"default"}} -->
<div class="wp-block-group tala-band tala-header"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Logo, title, menu, search and switch', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-rail","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group tala-rail"><!-- wp:pattern {"slug":"tala-blog/site-brand"} /-->

<!-- wp:navigation {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Primary', 'header menu label for screen readers', 'tala-blog' ) ); ?>,"overlay":"navigation-overlay","icon":"menu","submenuVisibility":"click","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /-->

<!-- wp:search {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Site', 'name of the search form in the header', 'tala-blog' ) ); ?>,"label":<?php echo tala_blog_block_text( __( 'Search', 'tala-blog' ) ); ?>,"showLabel":false,"placeholder":<?php echo tala_blog_block_text( __( 'Search articles', 'tala-blog' ) ); ?>,"buttonText":<?php echo tala_blog_block_text( _x( 'Search', 'search button', 'tala-blog' ) ); ?>,"buttonPosition":"button-only","buttonUseIcon":true} /-->

<!-- wp:html -->
<button type="button" class="tala-theme-switch" aria-pressed="false" aria-label="<?php echo esc_attr_x( 'Dark theme', 'name of the light/dark switch button', 'tala-blog' ); ?>"><svg class="tala-theme-switch__moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7.5 7.5 0 0 0 10.5 10.5Z"/></svg><svg class="tala-theme-switch__sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.28 5.28l1.42 1.42M17.3 17.3l1.42 1.42M5.28 18.72l1.42-1.42M17.3 6.7l1.42-1.42"/></svg></button>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
