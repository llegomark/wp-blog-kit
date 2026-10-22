<?php
/**
 * Title: Categories and tags
 * Slug: tala-blog/post-terms
 * Categories: text
 * Inserter: no
 * Description: "Filed under" categories and the tags, at the end of an article.
 *
 * @package TalaBlog
 */

?>
<!-- wp:group {"className":"tala-terms","layout":{"type":"default"}} -->
<div class="wp-block-group tala-terms"><!-- wp:post-terms {"term":"category","prefix":"<?php /* translators: Shown before the article's categories; keep the space at the end. */ echo esc_attr__( 'Filed under: ', 'tala-blog' ); ?>"} /-->

<!-- wp:post-terms {"term":"post_tag","prefix":"<?php /* translators: Shown before the article's tags; keep the space at the end. */ echo esc_attr__( 'Tags: ', 'tala-blog' ); ?>"} /--></div>
<!-- /wp:group -->
