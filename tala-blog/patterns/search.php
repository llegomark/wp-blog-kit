<?php
/**
 * Title: Search form
 * Slug: tala-blog/search
 * Categories: text
 * Inserter: no
 * Description: The search form on search results and on the 404 page. The footer has its own, named
 * differently, so the two search landmarks on those pages can be told apart.
 *
 * @package TalaBlog
 */

?>
<!-- wp:search {"ariaLabel":<?php echo tala_blog_block_text( _x( 'Articles', 'name of the search form in the page', 'tala-blog' ) ); ?>,"label":<?php echo tala_blog_block_text( __( 'Search', 'tala-blog' ) ); ?>,"showLabel":false,"placeholder":<?php echo tala_blog_block_text( __( 'Search articles', 'tala-blog' ) ); ?>,"buttonText":<?php echo tala_blog_block_text( _x( 'Search', 'search button', 'tala-blog' ) ); ?>} /-->
