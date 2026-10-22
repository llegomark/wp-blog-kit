<?php
/**
 * Demo content for testing Tala Blog + Tala SEO in WordPress Playground.
 * Run with WordPress loaded. Not part of the theme or plugin.
 */

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

update_option( 'blogname', 'Guro Notes' );
update_option( 'blogdescription', 'Practical guides for Filipino teachers' );
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
update_option( 'date_format', 'F j, Y' );
update_option( 'timezone_string', 'Asia/Manila' );
update_option( 'posts_per_page', 5 );
$wp_rewrite->flush_rules( false );

/**
 * Imports a local image into the media library.
 */
function seed_image( $file, $alt ) {
	$bits = wp_upload_bits( basename( $file ), null, file_get_contents( $file ) );
	$type = wp_check_filetype( $bits['file'] );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'],
			'post_title'     => $alt,
			'post_status'    => 'inherit',
		),
		$bits['file']
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $bits['file'] ) );
	if ( '' !== $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	return $id;
}

$dir = getenv( 'KIT_DEV_MEDIA' ) ?: '/kit-dev/media/';

// Site icon (favicon) and author.
update_option( 'site_icon', seed_image( $dir . 'icon.png', '' ) );
$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin->ID );
wp_update_user(
	array(
		'ID'           => $admin->ID,
		'display_name' => 'Maria Santos',
		'first_name'   => 'Maria',
		'last_name'    => 'Santos',
		'description'  => 'Maria teaches Grade 5 at a public elementary school in Laguna and has been writing practical guides for fellow teachers since 2019.',
		'user_url'     => 'https://example.com/maria',
	)
);
update_user_meta( $admin->ID, 'tala_seo_photo', seed_image( $dir . 'photo.jpg', 'Maria Santos' ) );
update_user_meta( $admin->ID, 'tala_facebook', 'https://www.facebook.com/example.maria' );

$planning = wp_insert_term( 'Lesson planning', 'category' )['term_id'];
$class    = wp_insert_term( 'Classroom guides', 'category' )['term_id'];
$weekly   = wp_insert_term( 'Weekly plans', 'category', array( 'parent' => $planning ) )['term_id'];

$body = <<<'HTML'
<!-- wp:paragraph -->
<p>A good plan saves you time on the busiest days of the week. This guide walks through the steps one at a time, with a short example you can copy into your own notebook.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Start with the objective</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Write the objective as something learners will be able to do by the end of the lesson. Keep it to one sentence, and use a verb you can observe: <em>identify</em>, <em>explain</em>, <em>solve</em>.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Name the skill, not the activity.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Match it to the week's competency.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Check that you can assess it in five minutes.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Plan the activities</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Pick one activity that introduces the idea, one that lets learners practise it, and one that shows you who still needs help. Three is enough for a 50-minute period.</p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Plan less, and leave room for the questions your learners will ask.</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">A sample timetable</h3>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>Minutes</th><th>Activity</th></tr></thead><tbody><tr><td>10</td><td>Review and motivation</td></tr><tr><td>25</td><td>Guided practice in pairs</td></tr><tr><td>15</td><td>Short check and wrap-up</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>Keep your plan in one folder per quarter so you can reuse what worked next school year.</p>
<!-- /wp:paragraph -->
HTML;

$posts = array(
	array( 'How to write a weekly home learning plan', 'A step-by-step guide to writing a weekly home learning plan that parents can follow, with a sample timetable you can copy.', $weekly, 1, '-40 days' ),
	array( 'Classroom routines that save ten minutes a day', 'Five classroom routines for passing papers, taking attendance and starting lessons that give back ten minutes every school day.', $class, 2, '-30 days' ),
	array( 'Writing quiz questions that check real understanding', 'How to write short quiz questions that show whether learners understood the lesson, with examples for Math and English.', $planning, 3, '-20 days' ),
	array( 'Preparing for the first parent-teacher meeting', 'What to prepare, what to say and what to send home before your first parent-teacher meeting of the school year.', $class, 4, '-12 days' ),
	array( 'Building a daily reading habit in Grade 5', 'Simple ways to build a fifteen-minute daily reading habit in Grade 5, even without a classroom library.', $planning, 5, '-6 days' ),
	array( 'Keeping school forms organized all year', 'A filing system for school forms that keeps attendance, grades and reports easy to find at the end of every quarter.', $class, 6, '-2 days' ),
);

foreach ( $posts as $i => $p ) {
	$date = gmdate( 'Y-m-d H:i:s', strtotime( $p[4] ) );
	$id   = wp_insert_post(
		array(
			'post_author'   => $admin->ID,
			'post_title'    => $p[0],
			'post_content'  => $body,
			'post_status'   => 'publish',
			'post_category' => array( $p[2] ),
			'tags_input'    => array( 'teaching', 'planning' ),
			'post_date_gmt' => $date,
			'post_date'     => get_date_from_gmt( $date ),
			'meta_input'    => array( '_tala_seo_description' => $p[1] ),
			'_thumbnail_id' => seed_image( $dir . 'cover-' . $p[3] . '.jpg', 'Cover illustration: ' . strtolower( $p[0] ) ),
		)
	);
}

// The first post was revised a week after publishing: shows "Updated".
$first = get_page_by_path( 'how-to-write-a-weekly-home-learning-plan', OBJECT, 'post' );
$mod   = gmdate( 'Y-m-d H:i:s', strtotime( '-33 days' ) );
$GLOBALS['wpdb']->update(
	$GLOBALS['wpdb']->posts,
	array(
		'post_modified_gmt' => $mod,
		'post_modified'     => get_date_from_gmt( $mod ),
	),
	array( 'ID' => $first->ID )
);
clean_post_cache( $first->ID );

wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_title'   => 'About',
		'post_status'  => 'publish',
		'post_content' => "<!-- wp:paragraph -->\n<p>Guro Notes collects practical guides written by teachers, for teachers.</p>\n<!-- /wp:paragraph -->",
		'meta_input'   => array( '_tala_seo_description' => 'Who writes Guro Notes and why: practical guides by Filipino teachers, for Filipino teachers.' ),
	)
);

// Publish-guard checks outside the REST API: each should end up as a draft.
$no_description = wp_insert_post( array( 'post_title' => 'Guard test: no description', 'post_status' => 'publish', '_thumbnail_id' => seed_image( $dir . 'cover-1.jpg', 'x' ) ) );
$no_image       = wp_insert_post( array( 'post_title' => 'Guard test: no image', 'post_status' => 'publish', 'meta_input' => array( '_tala_seo_description' => 'Has a description.' ) ) );
$no_alt         = wp_insert_post( array( 'post_title' => 'Guard test: image without alt', 'post_status' => 'publish', 'meta_input' => array( '_tala_seo_description' => 'Has a description.' ), '_thumbnail_id' => seed_image( $dir . 'small.jpg', '' ) ) );
update_option(
	'seed_guard_results',
	array(
		'no_description' => get_post_status( $no_description ),
		'no_image'       => get_post_status( $no_image ),
		'image_no_alt'   => get_post_status( $no_alt ),
	)
);

update_option( 'seed_done', gmdate( 'c' ) );
