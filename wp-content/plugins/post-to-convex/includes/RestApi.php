<?php
/**
 * Custom REST API routes for Post to Convex.
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
 * Registers plugin REST routes under the `post-to-convex/v1` namespace.
 */
class RestApi {

	/**
	 * REST route namespace (first segment of the URL after `wp-json/`).
	 *
	 * @var string
	 */
	public const ROUTE_NAMESPACE = 'post-to-convex/v1';

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		$self = new self();
		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/createPost',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'handle_create_post' ),
				'permission_callback' => array( $this, 'can_access_api' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/updatePost',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'handle_update_post' ),
				'permission_callback' => array( $this, 'can_access_api' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/removePostServer',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_remove_post' ),
				'permission_callback' => array( $this, 'can_access_api' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}

	/**
	 * Who may call the api routes.
	 *
	 * @return bool
	 */
	public function can_access_api(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle the create post request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_create_post( \WP_REST_Request $request ): \WP_REST_Response {
		$request_error_message = __( 'Request error', 'post-to-convex' );

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Request body must be a JSON object', 'post-to-convex' ),
				),
				400
			);
		}

		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! $api_url ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'API URL not found', 'post-to-convex' ),
				),
				500
			);
		}

		$api_secret = SecretStore::get_plaintext_secret();

		if ( ! $api_secret ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Secret not found', 'post-to-convex' ),
				),
				500
			);
		}

		$post_id = intval( $body['id'] );

		global $wpdb;

		$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $post ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Post not found', 'post-to-convex' ),
				),
				404
			);
		}

		$convex_request_headers = array(
			'Authorization' => sprintf( 'Bearer %s', $api_secret ),
			'Content-Type'  => 'application/json',
		);

		$convex_request_body = array(
			'title'         => $post->post_title,
			'slug'          => $post->post_name,
			'content'       => $post->post_content,
			'excerpt'       => $post->post_excerpt,
			'type'          => $post->post_type,
			'status'        => $post->post_status,
			'commentStatus' => $post->comment_status,
			'createdAt'     => $post->post_date_gmt,
			'updatedAt'     => $post->post_modified_gmt,
			'originalId'    => intval( $post->ID ),
			'authorId'      => intval( $post->post_author ),
			'categoryIds'   => array(),
			'tagIds'        => array(),
		);

		$convex_request = wp_remote_post(
			sprintf( '%s/api/postToConvex/v1/posts', $api_url ),
			array(
				'headers' => $convex_request_headers,
				'body'    => wp_json_encode( $convex_request_body ),
				'method'  => $request->get_method(),
			)
		);

		$response_code = wp_remote_retrieve_response_code( $convex_request );

		if ( is_wp_error( $convex_request ) ) {
			$error_message = $convex_request->get_error_message();

			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => $error_message,
				),
				$response_code
			);
		}

		$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );

		if ( 200 !== $response_code ) {
			return new \WP_REST_Response(
				array(
					'message' => __( 'Failed to create post', 'post-to-convex' ),
					'error'   => $response_body['error'],
				),
				$response_code
			);
		}

		update_post_meta( $post_id, PostMeta::REMOTE_ID_META_KEY, $response_body['id'] );

		return new \WP_REST_Response(
			array(
				'message' => __( 'Post created', 'post-to-convex' ),
				'data'    => $response_body,
			),
			200
		);
	}

	/**
	 * Handle the update post request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_update_post( \WP_REST_Request $request ): \WP_REST_Response {
		$request_error_message = __( 'Request error', 'post-to-convex' );

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Request body must be a JSON object', 'post-to-convex' ),
				),
				400
			);
		}

		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! $api_url ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'API URL not found', 'post-to-convex' ),
				),
				500
			);
		}

		$api_secret = SecretStore::get_plaintext_secret();

		if ( ! $api_secret ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Secret not found', 'post-to-convex' ),
				),
				500
			);
		}

		$post_id = intval( $body['id'] );

		global $wpdb;

		$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $post ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Post not found', 'post-to-convex' ),
				),
				404
			);
		}

		$convex_request_headers = array(
			'Authorization' => sprintf( 'Bearer %s', $api_secret ),
			'Content-Type'  => 'application/json',
		);

		$remote_id = get_post_meta( $post_id, PostMeta::REMOTE_ID_META_KEY, true );

		if ( ! $remote_id ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Remote ID not found', 'post-to-convex' ),
				),
				404
			);
		}

		$convex_request_body = array(
			'_id'           => $remote_id,
			'title'         => $post->post_title,
			'slug'          => $post->post_name,
			'content'       => $post->post_content,
			'excerpt'       => $post->post_excerpt,
			'type'          => $post->post_type,
			'status'        => $post->post_status,
			'commentStatus' => $post->comment_status,
			'createdAt'     => $post->post_date_gmt,
			'updatedAt'     => $post->post_modified_gmt,
			'originalId'    => intval( $post->ID ),
			'authorId'      => intval( $post->post_author ),
			'categoryIds'   => array(),
			'tagIds'        => array(),
		);

		$convex_request = wp_remote_post(
			sprintf( '%s/api/postToConvex/v1/posts', $api_url ),
			array(
				'headers' => $convex_request_headers,
				'body'    => wp_json_encode( $convex_request_body ),
				'method'  => $request->get_method(),
			)
		);

		$response_code = wp_remote_retrieve_response_code( $convex_request );

		if ( is_wp_error( $convex_request ) ) {
			$error_message = $convex_request->get_error_message();

			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => $error_message,
				),
				$response_code
			);
		}

		$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );

		if ( 200 !== $response_code ) {
			return new \WP_REST_Response(
				array(
					'message' => __( 'Failed to create post', 'post-to-convex' ),
					'error'   => $response_body['error'],
				),
				$response_code
			);
		}

		return new \WP_REST_Response(
			array(
				'message' => __( 'Post created', 'post-to-convex' ),
				'data'    => $response_body,
			),
			200
		);
	}

	/**
	 * Handle the remove post server request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_remove_post( \WP_REST_Request $request ): \WP_REST_Response {
		$request_error_message = __( 'Request error', 'post-to-convex' );

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Request body must be a JSON object', 'post-to-convex' ),
				),
				400
			);
		}

		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! $api_url ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'API URL not found', 'post-to-convex' ),
				),
				500
			);
		}

		$api_secret = SecretStore::get_plaintext_secret();

		if ( ! $api_secret ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Secret not found', 'post-to-convex' ),
				),
				500
			);
		}

		$post_id = intval( $body['id'] );

		global $wpdb;

		$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $post ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Post not found', 'post-to-convex' ),
				),
				404
			);
		}

		$remote_id = get_post_meta( $post_id, PostMeta::REMOTE_ID_META_KEY, true );

		if ( ! $remote_id ) {
			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => __( 'Remote ID not found', 'post-to-convex' ),
				),
				404
			);
		}

		$convex_request_headers = array(
			'Authorization' => sprintf( 'Bearer %s', $api_secret ),
			'Content-Type'  => 'application/json',
		);

		$convex_request_body = array(
			'_id' => $remote_id,
		);

		$convex_request = wp_remote_request(
			sprintf( '%s/api/postToConvex/v1/posts', $api_url ),
			array(
				'method'  => 'DELETE',
				'headers' => $convex_request_headers,
				'body'    => wp_json_encode( $convex_request_body ),
			)
		);

		$response_code = wp_remote_retrieve_response_code( $convex_request );

		if ( is_wp_error( $convex_request ) ) {
			$error_message = $convex_request->get_error_message();

			return new \WP_REST_Response(
				array(
					'message' => $request_error_message,
					'error'   => $error_message,
				),
				$response_code
			);
		}

		$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );

		if ( 200 !== $response_code ) {
			return new \WP_REST_Response(
				array(
					'message' => __( 'Failed to remove post', 'post-to-convex' ),
					'error'   => $response_body['error'],
				),
				$response_code
			);
		}

		delete_post_meta( $post_id, PostMeta::REMOTE_ID_META_KEY );

		return new \WP_REST_Response(
			array(
				'message' => __( 'Post removed', 'post-to-convex' ),
				'data'    => $response_body,
			),
			200
		);
	}
}
