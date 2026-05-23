<?php
/**
 * Settings fields under Taxonomy terms create and edit pages.
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
 * Registers fields for taxonomy terms create and edit pages and handles their submission.
 */
class TaxonomyFields {

	/**
	 * Transient TTL for queued admin notices (seconds).
	 *
	 * @var int
	 */
	private const NOTICE_TRANSIENT_TTL = 45;

	/**
	 * Initialize the taxonomy fields.
	 */
	public static function init(): void {
		$self = new self();

		// Register the taxonomy fields for the supported taxonomies.
		add_action( 'category_add_form_fields', array( $self, 'render_add_form_fields' ), 10, 0 );
		add_action( 'category_edit_form_fields', array( $self, 'render_edit_form_fields' ), 10, 1 );

		// Handle the category fields submission.
		add_action( 'created_category', array( $self, 'handle_category_in_convex' ), 10, 1 );
		add_action( 'edited_category', array( $self, 'handle_category_in_convex' ), 10, 1 );

		add_action( 'admin_notices', array( $self, 'render_queued_notice' ) );
	}

	/**
	 * Transient key for the current user's queued notice.
	 *
	 * @return string
	 */
	private function get_notice_transient_key(): string {
		return 'post_to_convex_taxonomy_notice_' . get_current_user_id();
	}

	/**
	 * Queue an admin notice to show after redirect on the category screen.
	 *
	 * @param string $message Notice message (may contain limited HTML).
	 * @param string $type    Notice type: success or error.
	 * @return void
	 */
	private function queue_notice( string $message, string $type ): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		set_transient(
			$this->get_notice_transient_key(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => $message,
			),
			self::NOTICE_TRANSIENT_TTL
		);
	}

	/**
	 * Settings page URL for connection configuration.
	 *
	 * @return string
	 */
	private function get_settings_url(): string {
		return admin_url( 'options-general.php?page=' . AdminSettings::PAGE_SLUG );
	}

	/**
	 * Message pointing users to the plugin settings page.
	 *
	 * @param string $text Leading sentence without the link.
	 * @return string
	 */
	private function settings_error_message( string $text ): string {
		return sprintf(
			/* translators: 1: error lead-in, 2: opening anchor, 3: closing anchor */
			__( '%1$s Configure it in %2$sPost to Convex Settings%3$s.', 'post-to-convex' ),
			$text,
			sprintf( '<a href="%s">', esc_url( $this->get_settings_url() ) ),
			'</a>'
		);
	}

	/**
	 * Extract a user-facing error string from a Convex API response body.
	 *
	 * @param mixed $response_body Decoded or raw response body.
	 * @return string
	 */
	private function format_api_error( mixed $response_body ): string {
		if ( is_array( $response_body ) && isset( $response_body['error'] ) && is_string( $response_body['error'] ) ) {
			return $response_body['error'];
		}

		return __( 'Unknown error from Convex.', 'post-to-convex' );
	}

	/**
	 * Whether the current admin screen is the category list or single-term edit UI.
	 *
	 * @param \WP_Screen|null $screen Current screen.
	 * @return bool
	 */
	private function is_category_admin_screen( ?\WP_Screen $screen ): bool {
		if ( ! $screen || 'category' !== $screen->taxonomy ) {
			return false;
		}

		// edit-tags.php (list + add) and term.php (single term edit) share id edit-category.
		return in_array( $screen->base, array( 'edit-tags', 'term' ), true );
	}

	/**
	 * Display a queued notice on the category admin screen, then discard it.
	 *
	 * @return void
	 */
	public function render_queued_notice(): void {
		$screen = get_current_screen();

		if ( ! $this->is_category_admin_screen( $screen ) ) {
			return;
		}

		$notice = get_transient( $this->get_notice_transient_key() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $this->get_notice_transient_key() );

		$type = 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success';

		wp_admin_notice(
			wp_kses_post( (string) $notice['message'] ),
			array(
				'type'        => $type,
				'dismissible' => true,
			)
		);
	}

	/**
	 * Render the taxonomy edit form fields.
	 *
	 * @param \WP_Term $term The term object.
	 * @return void
	 */
	public function render_edit_form_fields( \WP_Term $term ): void {
		$remote_id = get_term_meta( $term->term_id, TermMeta::REMOTE_ID_META_KEY, true );

		$this->render_taxonomy_fields_html( ! empty( $remote_id ), $remote_id, 'edit' );
	}

	/**
	 * Render the taxonomy add form fields.
	 *
	 * @return void
	 */
	public function render_add_form_fields(): void {
		$this->render_taxonomy_fields_html( false, '', 'add' );
	}

	/**
	 * Render the taxonomy fields HTML for add or edit screens.
	 *
	 * @param bool   $has_remote_id Whether the term has a remote ID.
	 * @param string $remote_id     The remote ID of the term.
	 * @param string $layout        Form layout: add (div.form-field) or edit (table row).
	 * @return void
	 */
	private function render_taxonomy_fields_html( bool $has_remote_id, string $remote_id, string $layout ): void {
		wp_nonce_field( 'post_to_convex_taxonomy_fields_action', 'post_to_convex_taxonomy_fields_nonce' );

		if ( 'add' === $layout ) {
			?>
			<div class="form-field term-post-to-convex-wrap">
				<label for="post-to-convex">
					<?php esc_html_e( 'Post to Convex', 'post-to-convex' ); ?>
				</label>
				<?php $this->render_taxonomy_checkbox_and_description( $has_remote_id, $remote_id ); ?>
			</div>
			<?php
			return;
		}

		?>
		<tr class="form-field term-post-to-convex-wrap">
			<th scope="row">
				<label for="post-to-convex">
					<?php esc_html_e( 'Post to Convex', 'post-to-convex' ); ?>
				</label>
			</th>
			<td>
				<?php $this->render_taxonomy_checkbox_and_description( $has_remote_id, $remote_id ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Checkbox and description shared by add and edit category forms.
	 *
	 * @param bool   $has_remote_id Whether the term has a remote ID.
	 * @param string $remote_id     The remote ID of the term.
	 * @return void
	 */
	private function render_taxonomy_checkbox_and_description( bool $has_remote_id, string $remote_id ): void {
		?>
		<input
			type="checkbox"
			name="post-to-convex"
			id="post-to-convex"
			value="1"
			aria-describedby="post-to-convex-description"
			<?php checked( $has_remote_id, true ); ?>
		/>
		<p class="description" id="post-to-convex-description">
			<?php esc_html_e( 'Check this box to post this category to Convex.', 'post-to-convex' ); ?>

			<?php if ( $has_remote_id ) : ?>
				<br>
				<span class="remote-id">
					<?php esc_html_e( 'Remote ID:', 'post-to-convex' ); ?>
					<?php echo esc_html( $remote_id ); ?>
				</span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Performs a PUT, PATCH or DELETE request to the Convex API to create,
	 * update or delete the taxonomy term in Convex.
	 *
	 * @param int $term_id The term ID.
	 * @return void
	 */
	public function handle_category_in_convex( int $term_id ): void {
		if ( ! isset( $_POST['post_to_convex_taxonomy_fields_nonce'] ) ) {
			return;
		}

		$passed_nonce = sanitize_text_field( wp_unslash( $_POST['post_to_convex_taxonomy_fields_nonce'] ) );

		if ( ! wp_verify_nonce( $passed_nonce, 'post_to_convex_taxonomy_fields_action' ) ) {
			return;
		}

		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! $api_url ) {
			$this->queue_notice(
				$this->settings_error_message(
					__( 'Convex Cloud URL is not configured.', 'post-to-convex' )
				),
				'error'
			);
			return;
		}

		$api_secret = SecretStore::get_plaintext_secret();

		if ( ! $api_secret ) {
			$this->queue_notice(
				$this->settings_error_message(
					__( 'Convex secret is not configured.', 'post-to-convex' )
				),
				'error'
			);
			return;
		}

		$term = get_term( $term_id, 'category' );

		if ( is_wp_error( $term ) || ! $term instanceof \WP_Term ) {
			$this->queue_notice(
				__( 'Category could not be loaded.', 'post-to-convex' ),
				'error'
			);
			return;
		}

		$post_to_convex = (bool) sanitize_text_field( wp_unslash( $_POST['post-to-convex'] ?? '0' ) );
		$remote_id      = get_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY, true );
		$is_delete      = false;

		// If the post to convex checkbox was checked, create or update the term in Convex.
		if ( $post_to_convex ) {
			// If a remote id was found, update the term in Convex.
			// Otherwise, create the term in Convex.
			$request_method = $remote_id ? 'PATCH' : 'PUT';

			$convex_request_body = array(
				'originalId'       => (int) $term->term_id,
				'name'             => $term->name,
				'slug'             => $term->slug,
				'description'      => $term->description,
				'parentOriginalId' => (int) $term->parent,
			);
		} elseif ( $remote_id ) {
			// If a remote id was found but no post to convex checkbox was checked,
			// delete the term from Convex.
			$request_method = 'DELETE';

			$convex_request_body = array(
				'originalId' => (int) $term->term_id,
			);

			$is_delete = true;
		} else {
			// Do nothing.
			return;
		}

		$convex_request_headers = array(
			'Authorization' => sprintf( 'Bearer %s', $api_secret ),
			'Content-Type'  => 'application/json',
		);

		// Post to Convex.
		$convex_request = wp_remote_request(
			sprintf( '%s/api/postToConvex/v1/categories', $api_url ),
			array(
				'headers' => $convex_request_headers,
				'body'    => wp_json_encode( $convex_request_body ),
				'method'  => $request_method,
			)
		);

		if ( is_wp_error( $convex_request ) ) {
			$this->queue_notice(
				sprintf(
					/* translators: %s: error message from wp_remote_request */
					__( 'Could not reach Convex: %s', 'post-to-convex' ),
					$convex_request->get_error_message()
				),
				'error'
			);
			return;
		}

		$response_code     = wp_remote_retrieve_response_code( $convex_request );
		$raw_response_body = wp_remote_retrieve_body( $convex_request );
		$response_body     = json_decode( $raw_response_body, true );

		if ( is_null( $response_body ) ) {
			$response_body = $raw_response_body;
		}

		if ( 200 !== $response_code ) {
			$this->queue_notice(
				sprintf(
					/* translators: %s: error detail from the Convex API */
					__( 'Failed to sync category with Convex: %s', 'post-to-convex' ),
					$this->format_api_error( $response_body )
				),
				'error'
			);
			return;
		}

		if ( $is_delete ) {
			delete_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY );
			$this->queue_notice(
				__( 'Category removed from Convex successfully.', 'post-to-convex' ),
				'success'
			);
			return;
		}

		if ( ! is_array( $response_body ) || empty( $response_body['id'] ) ) {
			$this->queue_notice(
				__( 'Convex did not return a category ID.', 'post-to-convex' ),
				'error'
			);
			return;
		}

		update_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY, $response_body['id'] );

		if ( 'PUT' === $request_method ) {
			$this->queue_notice(
				__( 'Category posted to Convex successfully.', 'post-to-convex' ),
				'success'
			);
			return;
		}

		$this->queue_notice(
			__( 'Category updated in Convex successfully.', 'post-to-convex' ),
			'success'
		);
	}
}
