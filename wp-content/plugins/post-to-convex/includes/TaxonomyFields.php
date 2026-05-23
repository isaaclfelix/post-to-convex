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
	 * Supported taxonomies and Convex API configuration.
	 *
	 * @var array<string, array{api_segment: string, label: string}>
	 */
	private const TAXONOMY_SYNC = array(
		'category' => array(
			'api_segment' => 'categories',
			'label'       => 'category',
		),
		'post_tag' => array(
			'api_segment' => 'tags',
			'label'       => 'tag',
		),
	);

	/**
	 * Initialize the taxonomy fields.
	 */
	public static function init(): void {
		$self = new self();

		foreach ( array_keys( self::TAXONOMY_SYNC ) as $taxonomy ) {
			add_action(
				"{$taxonomy}_add_form_fields",
				static function () use ( $self, $taxonomy ): void {
					$self->render_add_form_fields( $taxonomy );
				},
				10,
				0
			);

			add_action(
				"{$taxonomy}_edit_form_fields",
				static function ( \WP_Term $term ) use ( $self, $taxonomy ): void {
					$self->render_edit_form_fields( $term, $taxonomy );
				},
				10,
				1
			);

			add_action(
				"created_{$taxonomy}",
				static function ( int $term_id ) use ( $self, $taxonomy ): void {
					$self->handle_term_in_convex( $term_id, $taxonomy );
				},
				10,
				1
			);

			add_action(
				"edited_{$taxonomy}",
				static function ( int $term_id ) use ( $self, $taxonomy ): void {
					$self->handle_term_in_convex( $term_id, $taxonomy );
				},
				10,
				1
			);
		}

		add_action( 'pre_delete_term', array( $self, 'handle_term_deleted_in_convex' ), 10, 2 );

		add_action( 'admin_notices', array( $self, 'render_queued_notice' ) );
	}

	/**
	 * Transient key for the current user's queued notice on a taxonomy screen.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private function get_notice_transient_key( string $taxonomy ): string {
		return 'post_to_convex_taxonomy_notice_' . get_current_user_id() . '_' . $taxonomy;
	}

	/**
	 * Human-readable label for messages (e.g. "category", "tag").
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private function get_taxonomy_label( string $taxonomy ): string {
		return self::TAXONOMY_SYNC[ $taxonomy ]['label'] ?? $taxonomy;
	}

	/**
	 * Queue an admin notice to show after redirect on the taxonomy screen.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $message  Notice message (may contain limited HTML).
	 * @param string $type     Notice type: success or error.
	 * @return void
	 */
	private function queue_notice( string $taxonomy, string $message, string $type ): void {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! isset( self::TAXONOMY_SYNC[ $taxonomy ] ) ) {
			return;
		}

		set_transient(
			$this->get_notice_transient_key( $taxonomy ),
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
	 * Whether the screen is the list or single-term edit UI for a supported taxonomy.
	 *
	 * @param \WP_Screen|null $screen   Current screen.
	 * @param string          $taxonomy Taxonomy slug.
	 * @return bool
	 */
	private function is_taxonomy_admin_screen( ?\WP_Screen $screen, string $taxonomy ): bool {
		if ( ! $screen || ! isset( self::TAXONOMY_SYNC[ $taxonomy ] ) || $taxonomy !== $screen->taxonomy ) {
			return false;
		}

		return in_array( $screen->base, array( 'edit-tags', 'term' ), true );
	}

	/**
	 * Display a queued notice on the matching taxonomy admin screen, then discard it.
	 *
	 * @return void
	 */
	public function render_queued_notice(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! isset( self::TAXONOMY_SYNC[ $screen->taxonomy ] ) ) {
			return;
		}

		$taxonomy = $screen->taxonomy;

		if ( ! $this->is_taxonomy_admin_screen( $screen, $taxonomy ) ) {
			return;
		}

		$notice = get_transient( $this->get_notice_transient_key( $taxonomy ) );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $this->get_notice_transient_key( $taxonomy ) );

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
	 * @param \WP_Term $term     The term object.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function render_edit_form_fields( \WP_Term $term, string $taxonomy ): void {
		$remote_id = get_term_meta( $term->term_id, TermMeta::REMOTE_ID_META_KEY, true );

		$this->render_taxonomy_fields_html( $taxonomy, ! empty( $remote_id ), $remote_id, 'edit' );
	}

	/**
	 * Render the taxonomy add form fields.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function render_add_form_fields( string $taxonomy ): void {
		$this->render_taxonomy_fields_html( $taxonomy, false, '', 'add' );
	}

	/**
	 * Render the taxonomy fields HTML for add or edit screens.
	 *
	 * @param string $taxonomy      Taxonomy slug.
	 * @param bool   $has_remote_id Whether the term has a remote ID.
	 * @param string $remote_id     The remote ID of the term.
	 * @param string $layout        Form layout: add (div.form-field) or edit (table row).
	 * @return void
	 */
	private function render_taxonomy_fields_html( string $taxonomy, bool $has_remote_id, string $remote_id, string $layout ): void {
		wp_nonce_field( 'post_to_convex_taxonomy_fields_action', 'post_to_convex_taxonomy_fields_nonce' );

		if ( 'add' === $layout ) {
			?>
			<div class="form-field term-post-to-convex-wrap">
				<label for="post-to-convex">
					<?php esc_html_e( 'Post to Convex', 'post-to-convex' ); ?>
				</label>
				<?php $this->render_taxonomy_checkbox_and_description( $taxonomy, $has_remote_id, $remote_id ); ?>
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
				<?php $this->render_taxonomy_checkbox_and_description( $taxonomy, $has_remote_id, $remote_id ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Checkbox and description shared by add and edit taxonomy forms.
	 *
	 * @param string $taxonomy      Taxonomy slug.
	 * @param bool   $has_remote_id Whether the term has a remote ID.
	 * @param string $remote_id     The remote ID of the term.
	 * @return void
	 */
	private function render_taxonomy_checkbox_and_description( string $taxonomy, bool $has_remote_id, string $remote_id ): void {
		$label = $this->get_taxonomy_label( $taxonomy );
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
			<?php
			printf(
				/* translators: %s: taxonomy label (e.g. category, tag) */
				esc_html__( 'Check this box to post this %s to Convex.', 'post-to-convex' ),
				esc_html( $label )
			);
			?>

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
	 * Delete a synced term from Convex when it is removed in WordPress.
	 *
	 * @param int    $term_id  WordPress term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function handle_term_deleted_in_convex( int $term_id, string $taxonomy ): void {
		if ( ! isset( self::TAXONOMY_SYNC[ $taxonomy ] ) ) {
			return;
		}

		if ( ! current_user_can( 'delete_term', $term_id ) ) {
			return;
		}

		$remote_id = get_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY, true );

		if ( ! $remote_id ) {
			return;
		}

		$this->delete_term_in_convex( $term_id, $taxonomy, true );
	}

	/**
	 * Build the Convex API request body for a term create, update, or delete.
	 *
	 * @param \WP_Term $term      WordPress term.
	 * @param string   $taxonomy  Taxonomy slug.
	 * @param bool     $is_delete Whether this is a delete operation.
	 * @return array<string, int|string>
	 */
	private function build_convex_request_body( \WP_Term $term, string $taxonomy, bool $is_delete ): array {
		if ( $is_delete ) {
			return TermConvexPayload::delete( (int) $term->term_id );
		}

		if ( 'post_tag' === $taxonomy ) {
			return TermConvexPayload::tag( $term );
		}

		return TermConvexPayload::category( $term );
	}

	/**
	 * Performs a PUT, PATCH or DELETE request to the Convex API to create,
	 * update or delete the taxonomy term in Convex.
	 *
	 * @param int    $term_id  The term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function handle_term_in_convex( int $term_id, string $taxonomy ): void {
		if ( ! isset( self::TAXONOMY_SYNC[ $taxonomy ] ) ) {
			return;
		}

		if ( ! isset( $_POST['post_to_convex_taxonomy_fields_nonce'] ) ) {
			return;
		}

		$passed_nonce = sanitize_text_field( wp_unslash( $_POST['post_to_convex_taxonomy_fields_nonce'] ) );

		if ( ! wp_verify_nonce( $passed_nonce, 'post_to_convex_taxonomy_fields_action' ) ) {
			return;
		}

		$label       = $this->get_taxonomy_label( $taxonomy );
		$api_segment = self::TAXONOMY_SYNC[ $taxonomy ]['api_segment'];

		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! $api_url ) {
			$this->queue_notice(
				$taxonomy,
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
				$taxonomy,
				$this->settings_error_message(
					__( 'Convex secret is not configured.', 'post-to-convex' )
				),
				'error'
			);
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) || ! $term instanceof \WP_Term ) {
			$this->queue_notice(
				$taxonomy,
				sprintf(
					/* translators: %s: taxonomy label (e.g. category, tag) */
					__( '%s could not be loaded.', 'post-to-convex' ),
					ucfirst( $label )
				),
				'error'
			);
			return;
		}

		$post_to_convex = (bool) sanitize_text_field( wp_unslash( $_POST['post-to-convex'] ?? '0' ) );
		$remote_id      = get_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY, true );

		if ( $post_to_convex ) {
			$request_method = $remote_id ? 'PATCH' : 'PUT';
		} elseif ( $remote_id ) {
			if ( $this->delete_term_in_convex( $term_id, $taxonomy, true ) ) {
				delete_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY );
				$this->queue_notice(
					$taxonomy,
					sprintf(
						/* translators: %s: taxonomy label (e.g. category, tag) */
						__( '%s removed from Convex successfully.', 'post-to-convex' ),
						ucfirst( $label )
					),
					'success'
				);
			}
			return;
		} else {
			return;
		}

		$convex_request_body = $this->build_convex_request_body( $term, $taxonomy, false );

		$convex_request = wp_remote_request(
			sprintf( '%s/api/postToConvex/v1/%s', $api_url, $api_segment ),
			array(
				'headers' => array(
					'Authorization' => sprintf( 'Bearer %s', $api_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $convex_request_body ),
				'method'  => $request_method,
			)
		);

		if ( is_wp_error( $convex_request ) ) {
			$this->queue_notice(
				$taxonomy,
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
				$taxonomy,
				sprintf(
					/* translators: 1: taxonomy label, 2: error detail from the Convex API */
					__( 'Failed to sync %1$s with Convex: %2$s', 'post-to-convex' ),
					$label,
					$this->format_api_error( $response_body )
				),
				'error'
			);
			return;
		}

		if ( ! is_array( $response_body ) || empty( $response_body['id'] ) ) {
			$this->queue_notice(
				$taxonomy,
				sprintf(
					/* translators: %s: taxonomy label (e.g. category, tag) */
					__( 'Convex did not return a %s ID.', 'post-to-convex' ),
					$label
				),
				'error'
			);
			return;
		}

		update_term_meta( $term_id, TermMeta::REMOTE_ID_META_KEY, $response_body['id'] );

		if ( 'PUT' === $request_method ) {
			$this->queue_notice(
				$taxonomy,
				sprintf(
					/* translators: %s: taxonomy label (e.g. category, tag) */
					__( '%s posted to Convex successfully.', 'post-to-convex' ),
					ucfirst( $label )
				),
				'success'
			);
			return;
		}

		$this->queue_notice(
			$taxonomy,
			sprintf(
				/* translators: %s: taxonomy label (e.g. category, tag) */
				__( '%s updated in Convex successfully.', 'post-to-convex' ),
				ucfirst( $label )
			),
			'success'
		);
	}

	/**
	 * DELETE a term from Convex using its WordPress term ID as originalId.
	 *
	 * @param int    $term_id      WordPress term ID.
	 * @param string $taxonomy     Taxonomy slug.
	 * @param bool   $queue_errors Whether to queue admin notices on failure.
	 * @return bool True when Convex returned HTTP 200.
	 */
	private function delete_term_in_convex( int $term_id, string $taxonomy, bool $queue_errors ): bool {
		if ( ! isset( self::TAXONOMY_SYNC[ $taxonomy ] ) ) {
			return false;
		}

		$label       = $this->get_taxonomy_label( $taxonomy );
		$api_segment = self::TAXONOMY_SYNC[ $taxonomy ]['api_segment'];

		$api_url = get_option( AdminSettings::OPTION_URL );

		if ( ! $api_url ) {
			if ( $queue_errors ) {
				$this->queue_notice(
					$taxonomy,
					$this->settings_error_message(
						__( 'Convex Cloud URL is not configured.', 'post-to-convex' )
					),
					'error'
				);
			}
			return false;
		}

		$api_secret = SecretStore::get_plaintext_secret();

		if ( ! $api_secret ) {
			if ( $queue_errors ) {
				$this->queue_notice(
					$taxonomy,
					$this->settings_error_message(
						__( 'Convex secret is not configured.', 'post-to-convex' )
					),
					'error'
				);
			}
			return false;
		}

		$convex_request = wp_remote_request(
			sprintf( '%s/api/postToConvex/v1/%s', $api_url, $api_segment ),
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => sprintf( 'Bearer %s', $api_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( TermConvexPayload::delete( $term_id ) ),
			)
		);

		if ( is_wp_error( $convex_request ) ) {
			if ( $queue_errors ) {
				$this->queue_notice(
					$taxonomy,
					sprintf(
						/* translators: %s: error message from wp_remote_request */
						__( 'Could not reach Convex: %s', 'post-to-convex' ),
						$convex_request->get_error_message()
					),
					'error'
				);
			}
			return false;
		}

		$response_code     = wp_remote_retrieve_response_code( $convex_request );
		$raw_response_body = wp_remote_retrieve_body( $convex_request );
		$response_body     = json_decode( $raw_response_body, true );

		if ( is_null( $response_body ) ) {
			$response_body = $raw_response_body;
		}

		if ( 200 !== $response_code ) {
			if ( $queue_errors ) {
				$this->queue_notice(
					$taxonomy,
					sprintf(
						/* translators: 1: taxonomy label, 2: error detail from the Convex API */
						__( 'Failed to remove %1$s from Convex: %2$s', 'post-to-convex' ),
						$label,
						$this->format_api_error( $response_body )
					),
					'error'
				);
			}
			return false;
		}

		return true;
	}
}
