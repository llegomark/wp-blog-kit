<?php
/**
 * Title: Footer
 * Slug: tala-blog/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 * Description: Search form, site title and tagline. Search results and the 404 page use the footer without search, as they have a search form at the top.
 *
 * @package TalaBlog
 */

?>
<!-- wp:group {"className":"tala-footer","layout":{"type":"constrained"}} -->
<div class="wp-block-group tala-footer"><!-- wp:search {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Site', 'name of the search form in the footer', 'tala-blog' ) ); ?>,"label":<?php echo tala_blog_block_text( __( 'Search', 'tala-blog' ) ); ?>,"showLabel":false,"placeholder":<?php echo tala_blog_block_text( __( 'Search articles', 'tala-blog' ) ); ?>,"buttonText":<?php echo tala_blog_block_text( _x( 'Search', 'search button', 'tala-blog' ) ); ?>} /-->

<!-- wp:pattern {"slug":"tala-blog/footer-site"} /--></div>
<!-- /wp:group -->
