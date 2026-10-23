<?php
/**
 * Title: Menu overlay
 * Slug: tala-blog/navigation-overlay
 * Categories: navigation
 * Block Types: core/template-part/navigation-overlay
 * Inserter: no
 * Description: What the header menu opens into on phones: the site title, a close button and the full menu, in the theme's colours (light or dark).
 *
 * @package TalaBlog
 */

?>
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Menu overlay', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-overlay","backgroundColor":"base","textColor":"contrast","layout":{"type":"constrained"}} -->
<div class="wp-block-group tala-overlay has-contrast-color has-base-background-color has-text-color has-background"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Title and close button', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-overlay-bar","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group tala-overlay-bar"><!-- wp:site-title {"level":0} /-->

<!-- wp:navigation-overlay-close /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Primary, expanded', 'name of the menu inside the phone menu overlay', 'tala-blog' ) ); ?>,"overlayMenu":"never","showSubmenuIcon":false,"submenuVisibility":"always","className":"tala-overlay-menu","layout":{"type":"flex","orientation":"vertical"}} /--></div>
<!-- /wp:group -->
