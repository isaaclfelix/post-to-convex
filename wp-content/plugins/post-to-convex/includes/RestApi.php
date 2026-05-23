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

		$convex_request_body = $this->build_convex_post_fields( $post );

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

		$convex_request_body = array_merge(
			array( '_id' => $remote_id ),
			$this->build_convex_post_fields( $post )
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

	/**
	 * Build shared Convex post fields from a WordPress post row.
	 *
	 * @param object $post Post row from the database.
	 * @return array<string, mixed>
	 */
	private function build_convex_post_fields( object $post ): array {
		$post_id  = intval( $post->ID );
		$taxonomy = $this->build_taxonomy_payload( $post_id );

		return array_merge(
			array(
				'title'         => $post->post_title,
				'slug'          => $post->post_name,
				'content'       => Util::translate_blocks( $post->post_content ),
				'excerpt'       => $post->post_excerpt,
				'type'          => $post->post_type,
				'status'        => $post->post_status,
				'commentStatus' => $post->comment_status,
				'createdAt'     => $post->post_date_gmt,
				'updatedAt'     => $post->post_modified_gmt,
				'originalId'    => $post_id,
				'authorId'      => intval( $post->post_author ),
			),
			$taxonomy
		);
	}

	/**
	 * Build categories, tags, and permalink category fields for the Convex API.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array{categories: array<int, array<string, int|string>>, tags: array<int, array<string, int|string>>, permalinkCategoryOriginalId: int}
	 */
	private function build_taxonomy_payload( int $post_id ): array {
		if ( $this->is_post_uncategorized( $post_id ) ) {
			return array_merge(
				$this->get_uncategorized_taxonomy_payload(),
				array( 'tags' => $this->build_tags_payload( $post_id ) )
			);
		}

		$category_terms = get_the_terms( $post_id, 'category' );
		$categories     = array();

		if ( is_array( $category_terms ) ) {
			foreach ( $category_terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$categories[] = TermConvexPayload::category( $term );
				}
			}
		}

		return array(
			'categories'                  => $categories,
			'tags'                        => $this->build_tags_payload( $post_id ),
			'permalinkCategoryOriginalId' => $this->get_permalink_category_original_id( $category_terms ),
		);
	}

	/**
	 * Taxonomy payload when a post has no real category assignment.
	 *
	 * Uses the site default category from Settings → Writing.
	 *
	 * @return array{categories: array<int, array<string, int|string>>, permalinkCategoryOriginalId: int}
	 */
	private function get_uncategorized_taxonomy_payload(): array {
		$default_term = $this->get_default_category_term();

		if ( null === $default_term ) {
			return array(
				'categories'                  => array(),
				'permalinkCategoryOriginalId' => 0,
			);
		}

		return array(
			'categories'                  => array(
				TermConvexPayload::category( $default_term ),
			),
			'permalinkCategoryOriginalId' => (int) $default_term->term_id,
		);
	}

	/**
	 * The site's default post category term.
	 *
	 * @return \WP_Term|null
	 */
	private function get_default_category_term(): ?\WP_Term {
		$term_id = $this->get_default_category_id();

		if ( $term_id <= 0 ) {
			return null;
		}

		$term = get_term( $term_id, 'category' );

		if ( is_wp_error( $term ) || ! $term instanceof \WP_Term ) {
			return null;
		}

		return $term;
	}

	/**
	 * Term ID of the site's default post category.
	 *
	 * @return int
	 */
	private function get_default_category_id(): int {
		return (int) get_option( 'default_category', 1 );
	}

	/**
	 * Whether the post is effectively uncategorized (no terms or only the default category).
	 *
	 * @param int $post_id WordPress post ID.
	 * @return bool
	 */
	private function is_post_uncategorized( int $post_id ): bool {
		$category_terms = get_the_terms( $post_id, 'category' );

		if ( is_wp_error( $category_terms ) || empty( $category_terms ) ) {
			return true;
		}

		if ( 1 === count( $category_terms ) ) {
			$default_category_id = $this->get_default_category_id();
			$term                = $category_terms[0];
			return $term instanceof \WP_Term && (int) $term->term_id === $default_category_id;
		}

		return false;
	}

	/**
	 * Map post tags to the Convex tag shape.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<int, array<string, int|string>>
	 */
	private function build_tags_payload( int $post_id ): array {
		$tag_terms = get_the_terms( $post_id, 'post_tag' );

		if ( is_wp_error( $tag_terms ) || empty( $tag_terms ) ) {
			return array();
		}

		$tags = array();

		foreach ( $tag_terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$tags[] = TermConvexPayload::tag( $term );
			}
		}

		return $tags;
	}

	/**
	 * Original ID of the deepest assigned category (used in permalinks).
	 *
	 * @param array<int, \WP_Term>|false|\WP_Error $category_terms Assigned category terms.
	 * @return int
	 */
	private function get_permalink_category_original_id( array|false|\WP_Error $category_terms ): int {
		if ( is_wp_error( $category_terms ) || empty( $category_terms ) || ! is_array( $category_terms ) ) {
			return 0;
		}

		$deepest_term_id = 0;
		$max_depth       = -1;

		foreach ( $category_terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$depth = $this->get_category_depth( (int) $term->term_id );

			if ( $depth > $max_depth ) {
				$max_depth       = $depth;
				$deepest_term_id = (int) $term->term_id;
			}
		}

		return $deepest_term_id;
	}

	/**
	 * Depth of a category in its hierarchy (root = 0).
	 *
	 * @param int $term_id Category term ID.
	 * @return int
	 */
	private function get_category_depth( int $term_id ): int {
		$depth      = 0;
		$current_id = $term_id;

		while ( $current_id > 0 ) {
			$term = get_term( $current_id, 'category' );

			if ( is_wp_error( $term ) || ! $term instanceof \WP_Term ) {
				break;
			}

			if ( (int) $term->parent > 0 ) {
				++$depth;
				$current_id = (int) $term->parent;
				continue;
			}

			break;
		}

		return $depth;
	}
}
