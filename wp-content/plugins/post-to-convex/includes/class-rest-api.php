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
			)
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
		$body = $request->get_json_params();

		// TODO: Validate the body against the server-side schema.
		// Data is validated in the client-side schema, but we need to validate it again here.

		$api_url = get_option( Post_To_Convex_Admin_Settings::OPTION_URL );

		if ( ! $api_url ) {
			return new WP_REST_Response( array( 'message' => 'Request error', 'error' => __( 'API URL not found', 'post-to-convex' ) ), 500 );
		}

		$api_secret = Post_To_Convex_Secret_Store::get_plaintext_secret();

		if ( ! $api_secret ) {
			return new WP_REST_Response( array( 'message' => 'Request error', 'error' => __( 'Secret not found', 'post-to-convex' ) ), 500 );
		}

		$convex_request_headers = array(
			'Authorization' => 'Bearer ' . $api_secret,
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

			return new WP_REST_Response( array( 'message' => 'Request error', 'error' => $error_message ), $response_code);
		}

		$response_body = json_decode( wp_remote_retrieve_body( $convex_request ), true );

		return new WP_REST_Response( array( 'message' => 'Post created', 'data' => $response_body ), 200 );
	}
}
