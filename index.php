<?php
/*
Plugin Name:  Clean Media Library
Plugin URI:   https://github.com/polsola
Description:  Keep your media files clean & fix old files. Remove accents and special characters, convert all letters to lowercase.
Version:      1.0
Author:       Pol Solà
Author URI:   http://www.polsola.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Add fix link to media library list row
 *
 * @param array $actions
 */
function ps_add_fix_link( $actions ) {
    global $post;
    $actions[] = '<a href="#" data-id="' . $post->ID . '" class="fix-media-button">Fix</a>';
    return $actions;
}
add_filter( 'media_row_actions', 'ps_add_fix_link', 10, 1 );

/**
 * Enqueue scripts to admin
 */
function ps_media_fix_scripts() {

	wp_enqueue_script( 'fix-media-ajax', plugins_url( '/ajax.js', __FILE__ ), array('jquery'), '1.0', true );

	wp_localize_script( 'fix-media-ajax', 'fixmedia', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));

}
add_action( 'admin_enqueue_scripts', 'ps_media_fix_scripts' );

/**
 * Fix attachment via ajax
 */
function ps_fix_attachment() {

  // Get attachment id
  $attachment_id = $_POST['attachment_id'];

  // We only want to look at image attachments
	if ( !wp_attachment_is_image($attachment_id) )
		return;

  // Get attachment data
  $attachment_data = wp_get_attachment_metadata( $attachment_id );

  $filename = $attachment_data['file'];

  // Explode filname to get subdirectory and filname
  $filename_clean_array = explode('/', $filename, 3);

  $filename_subdirectory = $filename_clean_array[0] . '/' . $filename_clean_array[1] . '/';

  // Clean filename
  $filename_clean = sanitize_file_name_chars( $filename_clean_array[2] );

  $filename_clean_dir = $filename_subdirectory . $filename_clean;

  // Build full image dis
  $upload_dir = wp_upload_dir();

  $filename_upload_dir = $upload_dir['basedir'] . '/' . $filename;
  $filename_clean_upload_dir = $upload_dir['basedir'] . '/' . $filename_clean_dir;

  // Do the rename
  rename($filename_upload_dir, $filename_clean_upload_dir);

  // Fix WordPress data
  $attachment_data['file'] = $filename_clean;

  // Delete old image sizes
  foreach ($attachment_data['sizes'] as $size) {
    unlink( $upload_dir['basedir'] . '/' . $filename_subdirectory . $size['file'] );
  }

  // Update filename in DB
  update_post_meta( $attachment_id, '_wp_attached_file', $filename_clean_dir );

  // Regenerate thumbnails
  $image_metadata = wp_generate_attachment_metadata( $attachment_id, $filename_clean_upload_dir );
  wp_update_attachment_metadata( $attachment_id, $image_metadata );

  // Return data for view
  $return = array(
    'filename' => $filename_clean,
    'src'      => wp_get_attachment_thumb_url( $attachment_id )
  );

  wp_send_json( $return );

}
add_action( 'wp_ajax_ps_fix_attachment', 'ps_fix_attachment' );

/**
 * Sanitize file upload filenames
 */
function sanitize_file_name_chars($filename) {

	$sanitized_filename = remove_accents($filename); // Convert to ASCII

	// Standard replacements
	$invalid = array(
		' ' => '-',
		'%20' => '-',
		'_' => '-'
	);
	$sanitized_filename = str_replace(array_keys($invalid), array_values($invalid), $sanitized_filename);

	$sanitized_filename = preg_replace('/[^A-Za-z0-9-\. ]/', '', $sanitized_filename); // Remove all non-alphanumeric except .
	$sanitized_filename = preg_replace('/\.(?=.*\.)/', '', $sanitized_filename); // Remove all but last .
	$sanitized_filename = preg_replace('/-+/', '-', $sanitized_filename); // Replace any more than one - in a row
	$sanitized_filename = str_replace('-.', '.', $sanitized_filename); // Remove last - if at the end
	$sanitized_filename = strtolower($sanitized_filename); // Lowercase

	return $sanitized_filename;
}

add_filter('sanitize_file_name', 'sanitize_file_name_chars', 10);
