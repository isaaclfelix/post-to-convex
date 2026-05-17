<?php
/**
 * Settings screen under Settings → Post to Convex Settings.
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
 * Registers options and renders the admin settings page.
 */
class AdminSettings {

	/**
	 * Option group for the settings.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'post_to_convex_settings';

	/**
	 * Option for the Convex Cloud URL.
	 *
	 * @var string
	 */
	public const OPTION_URL = 'post_to_convex_cloud_url';

	/**
	 * Option for the Convex secret.
	 *
	 * @var string
	 */
	public const OPTION_SECRET = 'post_to_convex_secret';

	/**
	 * Page slug for the settings page.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'post-to-convex-settings';

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'add_options_page' ) );
		add_action( 'admin_init', array( $self, 'register_settings' ) );
	}

	/**
	 * Add submenu under Settings.
	 *
	 * @return void
	 */
	public function add_options_page(): void {
		add_options_page(
			__( 'Post to Convex Settings', 'post-to-convex' ),
			__( 'Post to Convex Settings', 'post-to-convex' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_SECRET,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_secret' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'post_to_convex_connection',
			__( 'Connection', 'post-to-convex' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_URL,
			__( 'Convex Cloud URL', 'post-to-convex' ),
			array( $this, 'render_field_cloud_url' ),
			self::PAGE_SLUG,
			'post_to_convex_connection'
		);

		add_settings_field(
			self::OPTION_SECRET,
			__( 'Convex secret', 'post-to-convex' ),
			array( $this, 'render_field_secret' ),
			self::PAGE_SLUG,
			'post_to_convex_connection'
		);
	}

	/**
	 * Preserve existing ciphertext when the password field is left empty; otherwise encrypt plaintext for storage.
	 *
	 * @param mixed $value Submitted value.
	 * @return string Ciphertext (prefixed) or previous option value.
	 */
	public function sanitize_secret( mixed $value ): string {
		$existing = (string) get_option( self::OPTION_SECRET, '' );

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $existing;
		}

		$plain     = sanitize_text_field( $value );
		$encrypted = SecretStore::encrypt( $plain );

		if ( '' === $encrypted ) {
			add_settings_error(
				'post_to_convex',
				'post_to_convex_secret_encrypt_failed',
				__( 'The Convex secret could not be encrypted (OpenSSL may be unavailable). The previous value was left unchanged.', 'post-to-convex' )
			);
			return $existing;
		}

		return $encrypted;
	}

	/**
	 * Convex Cloud URL field.
	 *
	 * @return void
	 */
	public function render_field_cloud_url(): void {
		$value = (string) get_option( self::OPTION_URL, '' );
		printf(
			'<input type="url" class="regular-text" name="%1$s" id="%1$s" value="%2$s" placeholder="https://your-deployment.convex.site" />',
			esc_attr( self::OPTION_URL ),
			esc_attr( $value )
		);
	}

	/**
	 * Convex secret (password) field — value is not echoed for security.
	 *
	 * @return void
	 */
	public function render_field_secret(): void {
		printf(
			'<input type="password" class="regular-text" name="%1$s" id="%1$s" value="" autocomplete="new-password" spellcheck="false" />',
			esc_attr( self::OPTION_SECRET )
		);
		echo '<p class="description">' . esc_html__( 'Leave blank when saving to keep the current secret unchanged. The value is stored encrypted in the database.', 'post-to-convex' ) . '</p>';
	}

	/**
	 * Step-by-step help shown under the connection fields (before Save).
	 *
	 * @return void
	 */
	private function render_setup_instructions_below_fields(): void {
		?>
		<div class="post-to-convex-setup-instructions">
			<h2 class="title"><?php esc_html_e( 'Convex secret setup', 'post-to-convex' ); ?></h2>
			<ol class="post-to-convex-setup-steps">
				<li>
					<p>
						<?php esc_html_e( 'On your computer, open a terminal and generate a random secret by running:', 'post-to-convex' ); ?>
					</p>
					<p><code>openssl rand -hex 32</code></p>
					<p>
						<?php esc_html_e( 'Copy the full hexadecimal string that the command prints. You will use the same value in WordPress and in Convex.', 'post-to-convex' ); ?>
					</p>
				</li>
				<li>
					<p>
						<?php esc_html_e( 'Paste that value into the “Convex secret” field above, then click “Save Changes” at the bottom of this page so WordPress stores it (encrypted in the database).', 'post-to-convex' ); ?>
					</p>
				</li>
				<li>
					<p>
						<?php esc_html_e( 'In the Convex dashboard, open your deployment, go to the environment variables section for that project, and add a new variable.', 'post-to-convex' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'Name the variable', 'post-to-convex' ); ?>
						<code>POST_TO_CONVEX_SECRET</code>
						<?php esc_html_e( 'and set its value to the same key you generated with OpenSSL (it must match the secret saved in WordPress).', 'post-to-convex' ); ?>
					</p>
				</li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Render the full settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'post_to_convex' ); ?>

			<form action="options.php" method="post">
				<?php
				$this->render_setup_instructions_below_fields();
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
