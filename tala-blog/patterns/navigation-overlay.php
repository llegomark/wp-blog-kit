<?php
/**
 * Title: Menu overlay
 * Slug: tala-blog/navigation-overlay
 * Categories: navigation
 * Block Types: core/template-part/navigation-overlay
 * Inserter: no
 * Description: What the header menu opens into on narrow screens, a side sheet in the theme's colours (light or dark): a top bar
 * laid out like the header's row, with the site logo and title and a close button in the menu button's place, then the full menu.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Menu overlay', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-overlay","backgroundColor":"base","textColor":"contrast","layout":{"type":"default"}} -->
<div class="wp-block-group tala-overlay has-contrast-color has-base-background-color has-text-color has-background"><!-- wp:group {"className":"tala-band","layout":{"type":"default"}} -->
<div class="wp-block-group tala-band"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Logo, title and close button', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-rail tala-overlay-bar","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group tala-rail tala-overlay-bar"><!-- wp:pattern {"slug":"tala-blog/site-brand"} /-->

<!-- wp:navigation-overlay-close /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tala-rail tala-overlay-body","layout":{"type":"default"}} -->
<div class="wp-block-group tala-rail tala-overlay-body"><!-- wp:navigation {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Primary, expanded', 'name of the menu inside the phone menu overlay', 'tala-blog' ) ); ?>,"overlayMenu":"never","showSubmenuIcon":false,"submenuVisibility":"always","className":"tala-overlay-menu","layout":{"type":"flex","orientation":"vertical"}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
