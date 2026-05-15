<?php
/**
 * Custom REST API routes for Post to Convex.
 *
 * @package Post_To_Convex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin REST routes under the `post-to-convex/v1` namespace.
 */
class Post_To_Convex_Rest_Api {

	/** REST route namespace (first segment of the URL after `wp-json/`). */
	public const ROUTE_NAMESPACE = 'post-to-convex/v1';

	/**
	 * Boot hooks.
	 */
	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/createPost',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_create_post' ),
				'permission_callback' => array( $this, 'can_access_api' ),
				'args'                => self::get_create_post_args(),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/createPostServer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_create_post_server' ),
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
	 * JSON body schema for createPost (keep in sync with client `createPostEndpointSchema` in src/schemas.ts).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_create_post_args() {
		$string_field = array(
			'required' => true,
			'type'     => 'string',
		);

		$integer_field = array(
			'required' => true,
			'type'     => 'integer',
		);

		$array_of_integers_field = array(
			'required' => true,
			'type'     => 'array',
			'items'    => array(
				'type' => 'integer',
			),
		);

		return array(
			'title'         => $string_field,
			'slug'          => $string_field,
			'content'       => $string_field,
			'excerpt'       => $string_field,
			'type'          => $string_field,
			'status'        => $string_field,
			'commentStatus' => $string_field,
			'createdAt'     => $string_field,
			'updatedAt'     => $string_field,
			'originalId'    => $integer_field,
			'authorId'      => $integer_field,
			'categoryIds'   => $array_of_integers_field,
			'tagIds'        => $array_of_integers_field,
		);
	}

	/**
	 * Who may call the api routes.
	 *
	 * @return bool
	 */
	public function can_access_api() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle the create post request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_post( $request ) {
		$request_error_message = __( 'Request error', 'post-to-convex' );

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'Request body must be a JSON object', 'post-to-convex' ) ), 400 );
		}

		$api_url = get_option( Post_To_Convex_Admin_Settings::OPTION_URL );

		if ( ! $api_url ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'API URL not found', 'post-to-convex' ) ), 500 );
		}

		$api_secret = Post_To_Convex_Secret_Store::get_plaintext_secret();

		if ( ! $api_secret ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'Secret not found', 'post-to-convex' ) ), 500 );
		}

		$convex_request_headers = array(
			'Authorization' => sprintf( 'Bearer %s', $api_secret ),
			'Content-Type'  => 'application/json',
		);

		$convex_request = wp_remote_post(
			sprintf( '%s/api/postToConvex/v1/posts', $api_url ),
			array(
				'headers' => $convex_request_headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		$response_code = wp_remote_retrieve_response_code( $convex_request );

		if ( is_wp_error( $convex_request ) ) {
			$error_message = $convex_request->get_error_message();

			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => $error_message ), $response_code );
		}

		$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );

		return new WP_REST_Response( array( 'message' => 'Post created', 'data' => $response_body ), 200 );
	}

	/**
	 * Handle the create post server request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_post_server( $request ) {
		$request_error_message = __( 'Request error', 'post-to-convex' );

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'Request body must be a JSON object', 'post-to-convex' ) ), 400 );
		}

		$api_url = get_option( Post_To_Convex_Admin_Settings::OPTION_URL );

		if ( ! $api_url ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'API URL not found', 'post-to-convex' ) ), 500 );
		}

		$api_secret = Post_To_Convex_Secret_Store::get_plaintext_secret();

		if ( ! $api_secret ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'Secret not found', 'post-to-convex' ) ), 500 );
		}

		$post_id = intval( $body['id'] );

		global $wpdb;

		$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) );

		if ( ! $post ) {
			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => __( 'Post not found', 'post-to-convex' ) ), 404 );
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
			'createdAt'     => $post->post_date,
			'updatedAt'     => $post->post_modified,
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
			)
		);

		$response_code = wp_remote_retrieve_response_code( $convex_request );

		if ( is_wp_error( $convex_request ) ) {
			$error_message = $convex_request->get_error_message();

			return new WP_REST_Response( array( 'message' => $request_error_message, 'error' => $error_message ), $response_code );
		}

		$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );

		return new WP_REST_Response( array( 'message' => 'Post created', 'data' => $response_body ), 200 );
	}
}
