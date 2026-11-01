<?php
/**
 * Title: Search form
 * Slug: tala-blog/search
 * Categories: text
 * Inserter: no
 * Description: The search form at the top of every page but the home page: above the breadcrumbs of
 * articles, pages and archives, and on search results and the 404 page. Those pages use the footer
 * without search, so this is their only search form; the home page keeps the one in the footer.
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:search {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Articles', 'name of the search form in the page', 'tala-blog' ) ); ?>,"label":<?php echo tala_blog_block_text( __( 'Search', 'tala-blog' ) ); ?>,"showLabel":false,"placeholder":<?php echo tala_blog_block_text( __( 'Search articles', 'tala-blog' ) ); ?>,"buttonText":<?php echo tala_blog_block_text( _x( 'Search', 'search button', 'tala-blog' ) ); ?>} /-->
