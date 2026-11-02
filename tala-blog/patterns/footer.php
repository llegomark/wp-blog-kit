<?php
/**
 * Title: Footer
 * Slug: tala-blog/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 * Description: The footer slab, on every page: a grid of cells with the site title, tagline and the Footer note template part (empty until it is
 * filled in the Site Editor), then the site's menu (its pages until a menu is made) as columns, one per item with a submenu, the other items
 * gathered in a last column; the copyright line and tagline; and the site title as a giant outlined wordmark, hidden from screen readers.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Footer slab', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-slab","layout":{"type":"default"}} -->
<div class="wp-block-group tala-slab"><!-- wp:group {"className":"tala-rail","layout":{"type":"default"}} -->
<div class="wp-block-group tala-rail"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Site and menu', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-cells","layout":{"type":"default"}} -->
<div class="wp-block-group tala-cells"><!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Site title, tagline and note', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-footer-brand","layout":{"type":"default"}} -->
<div class="wp-block-group tala-footer-brand"><!-- wp:site-title {"level":0,"isLink":false} /-->

<!-- wp:site-tagline /-->

<!-- wp:template-part {"slug":"footer-note"} /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Footer', 'footer menu label for screen readers', 'tala-blog' ) ); ?>,"overlayMenu":"never","submenuVisibility":"always","showSubmenuIcon":false,"className":"tala-footer-menu"} /--></div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"tala-blog/footer-site"} /-->

<!-- wp:group {"metadata":{"name":<?php echo tala_blog_block_text( _x( 'Wordmark', 'name of a group in the List View', 'tala-blog' ) ); ?>},"className":"tala-wordmark-band","layout":{"type":"default"}} -->
<div class="wp-block-group tala-wordmark-band"><!-- wp:site-title {"level":0,"isLink":false,"className":"tala-wordmark"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
