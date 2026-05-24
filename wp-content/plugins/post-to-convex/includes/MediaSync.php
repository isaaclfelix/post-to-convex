<?php
/**
 * Syncs WordPress media attachments to Convex.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Uploads and deletes media in Convex when attachments change in WordPress.
 *
 * Media uploads require the PHP cURL extension with CURLFile support. WordPress
 * wp_remote_request() is not used for multipart PUT uploads because buffering
 * large bodies through the HTTP API layer proved unreliable (HTTP/2 resets,
 * cURL error 18). Deletes still use wp_remote_request with JSON bodies.
 */
class MediaSync {

	/**
	 * MIME types accepted by the Convex media endpoint.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
	);

	/**
	 * Convex media API path segment (appended to cloud URL).
	 *
	 * @var string
	 */
	private const MEDIA_API_PATH = '/api/postToConvex/v1/media';

	/**
	 * Prevents re-entrant sync while a request is in flight.
	 *
	 * @var bool
	 */
	private bool $syncing = false;

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		$self = new self();
		add_action( 'add_attachment', array( $self, 'handle_add_attachment' ), 10, 1 );
		add_action( 'rest_after_insert_attachment', array( $self, 'handle_rest_after_insert_attachment' ), 10, 3 );
		add_action( 'delete_attachment', array( $self, 'handle_delete_attachment' ), 10, 1 );
		add_action( 'set_post_thumbnail', array( $self, 'handle_set_post_thumbnail' ), 10, 3 );
	}

	/**
	 * Return the stored Convex media id, uploading first when missing.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return string|null Convex mediaId or null when sync is skipped or fails.
	 */
	public function ensure_attachment_synced( int $attachment_id ): ?string {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$existing = get_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, true );

		if ( is_string( $existing ) && '' !== $existing ) {
			return $existing;
		}

		return $this->upload_attachment( $attachment_id );
	}

	/**
	 * Upload a new attachment to Convex when it is added to the media library.
	 *
	 * Covers legacy Plupload/async-upload flows where attachment hooks run normally.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return void
	 */
	public function handle_add_attachment( int $attachment_id ): void {
		$this->maybe_upload_attachment( $attachment_id );
	}

	/**
	 * Upload attachments created via the REST API (block editor and modern media library).
	 *
	 * REST uploads call wp_insert_attachment() with $fire_after_hooks = false, so
	 * add_attachment never runs for those requests.
	 *
	 * @param \WP_Post         $attachment Inserted attachment.
	 * @param \WP_REST_Request $request    REST request (unused).
	 * @param bool             $creating   True when creating a new attachment.
	 * @return void
	 */
	public function handle_rest_after_insert_attachment( \WP_Post $attachment, \WP_REST_Request $request, bool $creating ): void {
		unset( $request );

		if ( ! $creating ) {
			return;
		}

		$this->maybe_upload_attachment( (int) $attachment->ID );
	}

	/**
	 * Delete media from Convex when an attachment is removed from WordPress.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return void
	 */
	public function handle_delete_attachment( int $attachment_id ): void {
		if ( $this->syncing ) {
			return;
		}

		$media_id = get_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, true );

		if ( ! is_string( $media_id ) || '' === $media_id ) {
			return;
		}

		$this->syncing = true;

		try {
			$this->delete_media_in_convex( $media_id );
		} finally {
			$this->syncing = false;
		}
	}

	/**
	 * Upload featured image attachments that were not synced yet.
	 *
	 * @param int $post_id            Post ID.
	 * @param int $thumbnail_id       New thumbnail attachment ID.
	 * @param int $previous_thumbnail_id Previous thumbnail attachment ID.
	 * @return void
	 */
	public function handle_set_post_thumbnail( int $post_id, int $thumbnail_id, int $previous_thumbnail_id ): void {
		unset( $post_id, $previous_thumbnail_id );

		if ( $thumbnail_id <= 0 ) {
			return;
		}

		$this->maybe_upload_attachment( $thumbnail_id );
	}

	/**
	 * Upload when the attachment is a supported image without a Convex media id.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return void
	 */
	private function maybe_upload_attachment( int $attachment_id ): void {
		if ( $this->syncing ) {
			return;
		}

		$existing = get_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, true );

		if ( is_string( $existing ) && '' !== $existing ) {
			return;
		}

		$this->upload_attachment( $attachment_id );
	}

	/**
	 * Upload an attachment file to Convex and persist the returned mediaId.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return string|null Convex mediaId or null when sync is skipped or fails.
	 */
	private function upload_attachment( int $attachment_id ): ?string {
		if ( $this->syncing ) {
			return null;
		}

		if ( ! $this->can_sync_attachment( $attachment_id ) ) {
			return null;
		}

		$config = $this->get_api_config();

		if ( null === $config ) {
			return null;
		}

		if ( ! self::is_curl_available() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Post to Convex: media upload skipped because the PHP cURL extension (CURLFile) is not available.' );
			return null;
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! is_string( $file_path ) || '' === $file_path || ! is_readable( $file_path ) ) {
			return null;
		}

		$mime_type = get_post_mime_type( $attachment_id );

		if ( ! is_string( $mime_type ) || ! self::is_allowed_mime_type( $mime_type ) ) {
			return null;
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment instanceof \WP_Post ) {
			return null;
		}

		$form_fields = $this->get_attachment_form_fields( $attachment );
		$filename    = basename( $file_path );

		$this->syncing = true;

		try {
			$request_url   = $config['url'] . self::MEDIA_API_PATH;
			$upload_result = $this->send_media_upload(
				$request_url,
				$config['secret'],
				$form_fields,
				$file_path,
				$mime_type,
				$filename
			);

			if ( null !== $upload_result['error'] ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Post to Convex: media upload failed: ' . $upload_result['error'] );
				return null;
			}

			$response_code = $upload_result['code'];
			$response_body = json_decode( $upload_result['body'], true );

			if ( 200 !== $response_code || ! is_array( $response_body ) || empty( $response_body['mediaId'] ) ) {
				$error_detail = is_array( $response_body ) && isset( $response_body['error'] )
					? (string) $response_body['error']
					: 'HTTP ' . (string) $response_code;
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Post to Convex: media upload failed: ' . $error_detail );
				return null;
			}

			$media_id = sanitize_text_field( (string) $response_body['mediaId'] );
			update_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, $media_id );

			return $media_id;
		} finally {
			$this->syncing = false;
		}
	}

	/**
	 * Send a multipart media upload to Convex.
	 *
	 * @param string               $request_url Request URL.
	 * @param string               $secret      Bearer secret.
	 * @param array<string,string> $form_fields Text fields for the multipart body.
	 * @param string               $file_path   Path to the attachment file.
	 * @param string               $mime_type   File MIME type.
	 * @param string               $filename    Original filename.
	 * @return array{code: int, body: string, error: string|null}
	 */
	private function send_media_upload(
		string $request_url,
		string $secret,
		array $form_fields,
		string $file_path,
		string $mime_type,
		string $filename
	): array {
		if ( ! self::is_curl_available() ) {
			return array(
				'code'  => 0,
				'body'  => '',
				'error' => 'PHP cURL extension (CURLFile) is required for media uploads.',
			);
		}

		$result = $this->send_media_upload_via_curl(
			$request_url,
			$secret,
			$form_fields,
			$file_path,
			$mime_type,
			$filename
		);

		// Retry with only the file field when Convex returns 500 (handler/parser edge cases).
		if ( 500 === $result['code'] ) {
			$result = $this->send_media_upload_via_curl(
				$request_url,
				$secret,
				array(),
				$file_path,
				$mime_type,
				$filename
			);
		}

		return $result;
	}

	/**
	 * Upload via native cURL and CURLFile so the client builds multipart correctly.
	 *
	 * @param string               $request_url Request URL.
	 * @param string               $secret      Bearer secret.
	 * @param array<string,string> $form_fields Text fields for the multipart body.
	 * @param string               $file_path   Path to the attachment file.
	 * @param string               $mime_type   File MIME type.
	 * @param string               $filename    Original filename.
	 * @return array{code: int, body: string, error: string|null}
	 */
	private function send_media_upload_via_curl(
		string $request_url,
		string $secret,
		array $form_fields,
		string $file_path,
		string $mime_type,
		string $filename
	): array {
		$post_fields = array(
			'file' => new \CURLFile( $file_path, $mime_type, $filename ),
		);

		foreach ( $form_fields as $name => $value ) {
			if ( '' !== $value ) {
				$post_fields[ $name ] = $value;
			}
		}

		$curl_handle = curl_init( $request_url );

		if ( false === $curl_handle ) {
			return array(
				'code'  => 0,
				'body'  => '',
				'error' => 'curl_init failed',
			);
		}

		curl_setopt_array(
			$curl_handle,
			array(
				CURLOPT_CUSTOMREQUEST  => 'PUT',
				CURLOPT_POSTFIELDS     => $post_fields,
				CURLOPT_HTTPHEADER     => array(
					'Authorization: Bearer ' . $secret,
				),
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 60,
			)
		);

		$response_body = curl_exec( $curl_handle );
		$curl_error    = curl_error( $curl_handle );
		$response_code = (int) curl_getinfo( $curl_handle, CURLINFO_HTTP_CODE );
		unset( $curl_handle );

		if ( false === $response_body ) {
			return array(
				'code'  => $response_code,
				'body'  => '',
				'error' => '' !== $curl_error ? $curl_error : 'curl_exec failed',
			);
		}

		return array(
			'code'  => $response_code,
			'body'  => (string) $response_body,
			'error' => null,
		);
	}

	/**
	 * DELETE a media row from Convex.
	 *
	 * @param string $media_id Convex media document id.
	 * @return void
	 */
	private function delete_media_in_convex( string $media_id ): void {
		$config = $this->get_api_config();

		if ( null === $config ) {
			return;
		}

		$convex_request = wp_remote_request(
			$config['url'] . self::MEDIA_API_PATH,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => sprintf( 'Bearer %s', $config['secret'] ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'mediaId' => $media_id,
					)
				),
			)
		);

		if ( is_wp_error( $convex_request ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Post to Convex: media delete failed: ' . $convex_request->get_error_message() );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $convex_request );

		if ( 200 !== $response_code ) {
			$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );
			$error_detail  = is_array( $response_body ) && isset( $response_body['error'] )
				? (string) $response_body['error']
				: 'HTTP ' . (string) $response_code;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Post to Convex: media delete failed: ' . $error_detail );
		}
	}

	/**
	 * Whether the MIME type is allowed by the Convex media endpoint.
	 *
	 * @param string $mime_type Attachment MIME type.
	 * @return bool
	 */
	public static function is_allowed_mime_type( string $mime_type ): bool {
		return in_array( $mime_type, self::ALLOWED_MIME_TYPES, true );
	}

	/**
	 * Whether the PHP cURL extension can upload media (curl_init + CURLFile).
	 *
	 * @return bool
	 */
	public static function is_curl_available(): bool {
		return function_exists( 'curl_init' ) && class_exists( 'CURLFile', false );
	}

	/**
	 * Map a WordPress attachment to Convex multipart text fields.
	 *
	 * @param \WP_Post $attachment Attachment post object.
	 * @return array{alt: string, title: string, caption: string, description: string}
	 */
	public function get_attachment_form_fields( \WP_Post $attachment ): array {
		$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );

		return array(
			'alt'         => is_string( $alt ) ? $alt : '',
			'title'       => (string) $attachment->post_title,
			'caption'     => (string) $attachment->post_excerpt,
			'description' => (string) $attachment->post_content,
		);
	}

	/**
	 * Whether the attachment can be synced to Convex.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return bool
	 */
	private function can_sync_attachment( int $attachment_id ): bool {
		if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'edit_post', $attachment_id ) ) {
			return false;
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		$mime_type = get_post_mime_type( $attachment_id );

		return is_string( $mime_type ) && self::is_allowed_mime_type( $mime_type );
	}

	/**
	 * Load Convex URL and secret when configured.
	 *
	 * @return array{url: string, secret: string}|null
	 */
	private function get_api_config(): ?array {
		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! is_string( $api_url ) || '' === $api_url ) {
			return null;
		}

		$api_secret = SecretStore::get_plaintext_secret();

		if ( ! is_string( $api_secret ) || '' === $api_secret ) {
			return null;
		}

		return array(
			'url'    => rtrim( $api_url, '/' ),
			'secret' => $api_secret,
		);
	}

}
