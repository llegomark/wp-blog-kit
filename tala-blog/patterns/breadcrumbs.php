<?php
/**
 * Title: Breadcrumbs
 * Slug: tala-blog/breadcrumbs
 * Categories: text
 * Inserter: no
 * Description: Where the page sits in the site (Home › Category › Post), from the Tala SEO plugin's Breadcrumbs block. Without the plugin there is nothing here, so the theme works on its own.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

if ( WP_Block_Type_Registry::get_instance()->is_registered( 'tala-seo/breadcrumbs' ) ) :
	?>
<!-- wp:tala-seo/breadcrumbs /-->
	<?php
endif;
