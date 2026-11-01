<?php
/**
 * Title: Footer without search
 * Slug: tala-blog/footer-without-search
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 * Description: Site title and tagline, for pages with their own search form at the top (every page but the home page), so they show one search form, not two.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"className":"tala-footer","layout":{"type":"constrained"}} -->
<div class="wp-block-group tala-footer"><!-- wp:pattern {"slug":"tala-blog/footer-site"} /--></div>
<!-- /wp:group -->
