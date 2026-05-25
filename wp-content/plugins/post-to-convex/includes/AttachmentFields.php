<?php
/**
 * Media Library and attachment edit UI for manual Convex sync.
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
 * Renders Post to Convex controls on Media Library and classic attachment edit screens.
 */
class AttachmentFields {

	/**
	 * Admin AJAX actions that load attachment compat fields (without a current screen).
	 *
	 * @var list<string>
	 */
	private const COMPAT_FIELD_AJAX_ACTIONS = array(
		'query-attachments',
		'get-attachment',
		'get-attachment-compat',
		'upload-attachment',
		'save-attachment',
		'save-attachment-compat',
	);

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		$self = new self();
		add_filter( 'attachment_fields_to_edit', array( $self, 'filter_attachment_fields_to_edit' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue_media_admin_assets' ) );
	}

	/**
	 * Whether the current admin screen should show Convex attachment controls.
	 *
	 * @return bool
	 */
	public function should_show_on_current_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return false;
		}

		if ( 'upload' === $screen->id || 'attachment' === $screen->id ) {
			return true;
		}

		return 'post' === $screen->base && 'attachment' === $screen->post_type;
	}

	/**
	 * Whether compat fields should include the Convex panel for this request.
	 *
	 * Media Library grid loads attachment details via AJAX where get_current_screen() is null.
	 * Script enqueue stays screen-gated; only the filter is relaxed for library AJAX.
	 *
	 * @return bool
	 */
	public function should_add_compat_fields(): bool {
		if ( $this->should_show_on_current_screen() ) {
			return true;
		}

		if ( ! wp_doing_ajax() || ! $this->is_allowed_media_compat_ajax_action() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Insert Media context; core verifies the AJAX request before compat fields run.
		$post_id = isset( $_REQUEST['post_id'] ) ? (int) $_REQUEST['post_id'] : 0;

		if ( $post_id > 0 ) {
			$parent_type = get_post_type( $post_id );

			if ( false !== $parent_type && 'attachment' !== $parent_type ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the current request is a core media AJAX action that renders compat fields.
	 *
	 * @return bool
	 */
	private function is_allowed_media_compat_ajax_action(): bool {
		foreach ( self::COMPAT_FIELD_AJAX_ACTIONS as $ajax_action ) {
			if ( doing_action( 'wp_ajax_' . $ajax_action ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add Convex panel to attachment compat fields (library grid and attachment edit).
	 *
	 * @param array<string, array<string, string>> $fields  Existing fields.
	 * @param \WP_Post                             $attachment Attachment post.
	 * @return array<string, array<string, string>>
	 */
	public function filter_attachment_fields_to_edit( array $fields, \WP_Post $attachment ): array {
		if ( ! $this->should_add_compat_fields() ) {
			return $fields;
		}

		$fields['post_to_convex'] = array(
			'label' => __( 'Post to Convex', 'post-to-convex' ),
			'input' => 'html',
			'html'  => $this->render_panel_html( (int) $attachment->ID ),
		);

		return $fields;
	}

	/**
	 * Markup for the React mount point and description.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string
	 */
	private function render_panel_html( int $attachment_id ): string {
		$sync          = new MediaSync();
		$block_reason  = $sync->get_sync_block_reason( $attachment_id );
		$settings_url  = admin_url( 'options-general.php?page=' . AdminSettings::PAGE_SLUG );
		$description   = __( 'Sync this image to Convex (JPEG, PNG, WebP, or GIF).', 'post-to-convex' );
		$block_message = '';

		if ( null !== $block_reason && str_contains( $block_reason, 'not configured' ) ) {
			$block_message = sprintf(
				/* translators: 1: error lead-in, 2: opening anchor, 3: closing anchor */
				__( '%1$s Configure it in %2$sPost to Convex Settings%3$s.', 'post-to-convex' ),
				esc_html( $block_reason ) . ' ',
				sprintf( '<a href="%s">', esc_url( $settings_url ) ),
				'</a>'
			);
		} elseif ( null !== $block_reason ) {
			$block_message = esc_html( $block_reason );
		}

		ob_start();
		?>
		<div class="post-to-convex-media-panel-wrap">
			<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php if ( '' !== $block_message ) : ?>
				<p class="description post-to-convex-media-panel-block-reason"><?php echo wp_kses_post( $block_message ); ?></p>
			<?php endif; ?>
			<div
				class="post-to-convex-media-mount"
				data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>"
			></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue media admin script on Media Library and attachment edit screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_media_admin_assets( string $hook_suffix ): void {
		unset( $hook_suffix );

		if ( ! $this->should_show_on_current_screen() ) {
			return;
		}

		$asset_file = __DIR__ . '/../build/media-admin.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$plugin_file = dirname( __DIR__ ) . '/post-to-convex.php';
		$asset       = include $asset_file;

		wp_enqueue_style(
			'post-to-convex-media-admin',
			plugins_url( 'build/media-admin.css', $plugin_file ),
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'post-to-convex-media-admin',
			plugins_url( 'build/media-admin.js', $plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'post-to-convex-media-admin',
			'postToConvexMediaAdmin',
			array(
				'mediaIdMetaKey' => AttachmentMeta::MEDIA_ID_META_KEY,
				'scriptDebug'    => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			)
		);
	}
}
