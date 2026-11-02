<?php
/**
 * Title: Search form
 * Slug: tala-blog/search
 * Categories: text
 * Inserter: no
 * Description: The search form in the page, under the title of search results and on the 404 page. Every
 * page also has the site search in its header (tala-blog/header), named "Site"; this one is named "Articles".
 *
 * @package TalaBlog
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:search {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Articles', 'name of the search form in the page', 'tala-blog' ) ); ?>,"label":<?php echo tala_blog_block_text( __( 'Search', 'tala-blog' ) ); ?>,"showLabel":false,"placeholder":<?php echo tala_blog_block_text( __( 'Search articles', 'tala-blog' ) ); ?>,"buttonText":<?php echo tala_blog_block_text( _x( 'Search', 'search button', 'tala-blog' ) ); ?>} /-->
