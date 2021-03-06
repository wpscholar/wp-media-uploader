<?php

namespace wpscholar\WordPress;

if ( \defined( 'ABSPATH' ) && ! \function_exists( 'wp_handle_upload' ) ) {
	require ABSPATH . '/wp-admin/includes/file.php';
}

/**
 * Class MediaUploader
 *
 * @package wpscholar\WordPress
 */
class MediaUploader {

	/**
	 * File data
	 *
	 * @var array
	 */
	protected $_file;

	/**
	 * MediaUploader constructor.
	 *
	 * @param array $file An array containing file data (a single element of $_FILES, call once for each file uploaded)
	 */
	public function __construct( array $file ) {

		if ( ! isset( $file['error'], $file['name'], $file['tmp_name'], $file['size'], $file['type'] ) ) {
			trigger_error( 'Invalid file data', E_USER_ERROR );
		}

		$this->_file = $file;
	}

	/**
	 * Upload file into WordPress uploads directory and create attachment in database.
	 *
	 * @param int $parent_post_id The ID of the post to which this attachment should be associated. Defaults to 0 (none).
	 *
	 * @return \WP_Error|int Returns a WP_Error instance on failure or the attachment ID on success.
	 */
	public function upload( $parent_post_id = 0 ) {

		try {

			$file = wp_handle_upload( $this->_file, [ 'test_form' => false ] );

			if ( isset( $file['error'] ) ) {
				throw new \RuntimeException( $file['error'] );
			}

			$file_name = basename( $file['file'] );

			$wp_filetype = wp_check_filetype( $file_name );

			$attachment = [
				'guid'           => $file['url'],
				'post_content'   => '',
				'post_mime_type' => $wp_filetype['type'],
				'post_parent'    => $parent_post_id,
				'post_status'    => 'inherit',
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
			];

			$attachment_id = wp_insert_attachment( $attachment, $file['file'], $parent_post_id, true );

			if ( is_wp_error( $attachment_id ) ) {
				/**
				 * @var \WP_Error $error
				 */
				$error = $attachment_id;
				throw new \RuntimeException( $error->get_error_message() );
			}

			if ( ! \function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$attachment_meta = wp_generate_attachment_metadata( $attachment_id, $file['file'] );
			wp_update_attachment_metadata( $attachment_id, $attachment_meta );

			return $attachment_id;

		} catch ( \Exception $e ) {

			return new \WP_Error( 'upload', $e->getMessage() );

		}

	}

}
