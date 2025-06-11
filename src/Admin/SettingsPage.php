<?php
/**
 * The settings page.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Admin;

use Upstatement\AlgoliaConnector\AlgoliaConnector;
use Exception;

/** Class */
class SettingsPage {

	/**
	 * Plugin instance.
	 *
	 * @var AlgoliaConnector
	 */
	private $plugin;

	/**
	 * Menu page slug.
	 *
	 * @var string
	 */
	public static $slug = 'algolia-account-settings';

	/**
	 * Admin page section.
	 *
	 * @var string
	 */
	private $section = 'algolia_connection_settings';

	/**
	 * Admin page option group.
	 *
	 * @var string
	 */
	private $option_group = 'algolia_connection_settings';

	/**
	 * Constructor to generate the settings page.
	 *
	 * @param AlgoliaConnector $plugin Plugin instance.
	 */
	public function __construct( AlgoliaConnector $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_filter( 'plugin_action_links_' . ALGOLIA_CONNECTOR_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add action links to the plugin list.
	 *
	 * @param array $links Array of action links.
	 *
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		return array_merge(
			$links,
			array(
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::$slug ) ) . '">' . esc_html__( 'Settings', 'algolia-connector' ) . '</a>',
			)
		);
	}

	/**
	 * Create a settings page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_options_page(
			'Algolia', // Page title.
			'Algolia', // Menu title.
			'manage_options', // Capability.
			self::$slug, // Menu slug.
			array( $this, 'render' ) // Callback function.
		);
	}

	/**
	 * Build and render the setting page.
	 *
	 * @return void
	 */
	public function render(): void {
		require_once ALGOLIA_CONNECTOR_PLUGIN_PATH . '/templates/admin/settings-page.php';
	}

	/**
	 * Add settings.
	 *
	 * @return void
	 */
	public function add_settings(): void {

		if ( isset( $_POST['_algolia_connector_settings'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_algolia_connector_settings'] ) ), $this->option_group ) ) {
			$valid_credentials = false;
			$application_id    = sanitize_text_field(
				(defined('ALGOLIA_APPLICATION_ID') ? ALGOLIA_APPLICATION_ID : null)
				?? $_ENV['ALGOLIA_APPLICATION_ID']
				?? ( isset( $_POST['algolia_application_id'] ) ? wp_unslash( $_POST['algolia_application_id'] ) : '' )
			);
			$admin_api_key     = sanitize_text_field(
				(defined('ALGOLIA_ADMIN_API_KEY') ? ALGOLIA_ADMIN_API_KEY : null)
				?? $_ENV['ALGOLIA_ADMIN_API_KEY']
				?? ( isset( $_POST['algolia_admin_api_key'] ) ? wp_unslash( $_POST['algolia_admin_api_key'] ) : '' )
			);

			$error_message = 'We were unable to authenticate you against the Algolia servers with the provided information. Please ensure that you used a valid Application ID and Admin API key. ';

			try {
				$valid_credentials = $this->plugin->valid_credentials( $admin_api_key, $application_id );
			} catch ( Exception $exception ) {
				$error_message .= $exception->getMessage();
			}

			if ( $valid_credentials ) {
				add_settings_error(
					$this->option_group,
					'connection_success',
					esc_html__( 'Connection to the Algolia servers was succesful!', 'algolia-connector' ),
					'updated'
				);
			} else {
				add_settings_error(
					$this->option_group,
					'login_exception',
					$error_message
				);
			}
		}

		add_settings_section(
			$this->section,
			'',
			array( $this, 'print_section_settings' ),
			self::$slug
		);

		add_settings_field(
			'algolia_application_id',
			esc_html__( 'Application ID', 'algolia-connector' ),
			array( $this, 'application_id_callback' ),
			self::$slug,
			$this->section
		);

		add_settings_field(
			'algolia_search_api_key',
			esc_html__( 'Search-only API key', 'algolia-connector' ),
			array( $this, 'search_api_key_callback' ),
			self::$slug,
			$this->section
		);

		add_settings_field(
			'algolia_admin_api_key',
			esc_html__( 'Admin API key', 'algolia-connector' ),
			array( $this, 'admin_api_key_callback' ),
			self::$slug,
			$this->section
		);

		add_settings_field(
			'algolia_index_name',
			esc_html__( 'Index name', 'algolia-connector' ),
			array( $this, 'index_name_callback' ),
			self::$slug,
			$this->section
		);

		register_setting( $this->option_group, 'algolia_application_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( $this->option_group, 'algolia_search_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( $this->option_group, 'algolia_admin_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( $this->option_group, 'algolia_index_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	/**
	 * Print the settings section.
	 *
	 * @return void
	 */
	public function print_section_settings(): void {
		echo '<p>' .
			wp_kses(
				sprintf(
					// translators: URL to API keys section in Algolia dashboard.
					__( 'Configure your Algolia account credentials. You can find them in the <a href="%s" target="_blank">API Keys</a> section of your Algolia dashboard. It is recommended that you save these values as environment variables.', 'algolia-connector' ),
					'https://www.algolia.com/account/api-keys/all'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
			) . '</p>';
		echo '<p>' . esc_html__( 'Once you provide your Algolia Application ID and API key, this plugin will be able to securely communicate with Algolia servers.', 'algolia-connector' ) . ' ' . esc_html__( 'We ensure your information is correct by testing them against the Algolia servers upon save.', 'algolia-connector' ) . '</p>';
		// translators: the placeholder contains the URL to Algolia's website.
		echo '<p>' . wp_kses_post( sprintf( __( 'No Algolia account yet? <a href="%s">Follow this link</a> to create one for free in a couple of minutes!', 'algolia-connector' ), 'https://www.algolia.com/users/sign_up' ) ) . '</p>';
	}

	/**
	 * Application ID callback.
	 *
	 * @return void
	 */
	public function application_id_callback(): void {
		$settings      = $this->plugin->get_settings();
		$setting       = $settings->get_application_id();
		$disabled_html = defined('ALGOLIA_APPLICATION_ID') || isset( $_ENV['ALGOLIA_APPLICATION_ID'] ) ? ' disabled' : '';
		?>
		<input type="text" name="algolia_application_id" class="regular-text" value="<?php echo esc_attr( $setting ); ?>" <?php echo esc_html( $disabled_html ); ?>/>
		<p class="description">
			<?php esc_html_e( 'Your Algolia Application ID.', 'algolia-connector' ); ?>
			<a href="https://www.algolia.com/account/api-keys/all" target="_blank"><?php esc_html_e( 'Manage your Algolia API Keys', 'algolia-connector' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Search API key callback.
	 *
	 * @return void
	 */
	public function search_api_key_callback(): void {
		$settings      = $this->plugin->get_settings();
		$setting       = $settings->get_search_api_key();
		$disabled_html = defined('ALGOLIA_SEARCH_API_KEY') || isset( $_ENV['ALGOLIA_SEARCH_API_KEY'] ) ? ' disabled' : '';

		?>
		<input type="text" name="algolia_search_api_key" class="regular-text" value="<?php echo esc_attr( $setting ); ?>" <?php echo esc_html( $disabled_html ); ?>/>
		<p class="description">
			<?php esc_html_e( 'Your Algolia Search-only API key (public).', 'algolia-connector' ); ?>
			<a href="https://www.algolia.com/account/api-keys/all" target="_blank"><?php esc_html_e( 'Manage your Algolia API Keys', 'algolia-connector' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Admin API key callback.
	 *
	 * @return void
	 */
	public function admin_api_key_callback(): void {
		$settings      = $this->plugin->get_settings();
		$setting       = $settings->get_admin_api_key();
		$disabled_html = defined('ALGOLIA_ADMIN_API_KEY') || isset( $_ENV['ALGOLIA_ADMIN_API_KEY'] ) ? ' disabled' : '';
		?>
		<input type="password" name="algolia_admin_api_key" class="regular-text" value="<?php echo esc_attr( $setting ); ?>" <?php echo esc_html( $disabled_html ); ?>/>
		<p class="description">
			<?php esc_html_e( 'Your Algolia ADMIN API key (kept private).', 'algolia-connector' ); ?>
			<a href="https://www.algolia.com/account/api-keys/all" target="_blank"><?php esc_html_e( 'Manage your Algolia API Keys', 'algolia-connector' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Index name callback.
	 *
	 * @return void
	 */
	public function index_name_callback(): void {
		$settings      = $this->plugin->get_settings();
		$index_name    = $settings->get_index_name();
		$disabled_html = defined('ALGOLIA_INDEX_NAME') || isset( $_ENV['ALGOLIA_INDEX_NAME'] ) ? ' disabled' : '';
		?>
		<input type="text" name="algolia_index_name" value="<?php echo esc_attr( $index_name ); ?>" <?php echo esc_html( $disabled_html ); ?>/>
		<p class="description"><?php esc_html_e( 'The index that content will be written to.', 'algolia-connector' ); ?></p>
		<?php
	}
}
