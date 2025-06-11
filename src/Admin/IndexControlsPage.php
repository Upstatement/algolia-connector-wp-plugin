<?php
/**
 * The settings page.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Admin;

use Upstatement\AlgoliaConnector\AlgoliaConnector;
use Upstatement\AlgoliaConnector\Admin\SettingsPage;
use Upstatement\AlgoliaConnector\Services\Algolia;
use Upstatement\AlgoliaConnector\Services\Settings;

/** Class */
class IndexControlsPage {

	/**
	 * Instance of the plugin.
	 *
	 * @var AlgoliaConnector
	 */
	protected $plugin;

	/**
	 * Algolia service.
	 *
	 * @var Algolia
	 */
	private $algolia;

	/**
	 * Algolia settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Menu page slug.
	 *
	 * @var string
	 */
	public static $slug = 'algolia-index-controls';

	/**
	 * Admin page option group.
	 *
	 * @var string
	 */
	private $action = 'algolia_index_controls';

	/**
	 * Constructor to generate the settings page.
	 *
	 * @param AlgoliaConnector $plugin Plugin instance.
	 */
	public function __construct( AlgoliaConnector $plugin ) {
		$this->plugin   = $plugin;
		$this->algolia  = $plugin->get_algolia();
		$this->settings = $plugin->get_settings();

		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . $this->action, array( $this, 'handle_request' ) );
	}


	/**
	 * Create a settings page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			SettingsPage::$slug,
			'Index Controls',
			'Index Controls',
			'manage_options',
			self::$slug,
			array( $this, 'render' )
		);
	}

	/**
	 * Build and render the setting page.
	 *
	 * @return void
	 */
	public function render(): void {
		$success_message = get_transient( $this->action . '_success' );

		if ( $success_message ) {
			add_settings_error( $this->action, 'success_message', $success_message, 'updated' );
			delete_transient( $this->action . '_success' );
		}

		settings_errors( $this->action );
		require_once ALGOLIA_CONNECTOR_PLUGIN_PATH . '/templates/admin/index-controls-page.php';
	}

	/**
	 * Handle submission request.
	 *
	 * @return void
	 */
	public function handle_request() {

		if ( isset( $_POST['_algolia_index_controls'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_algolia_index_controls'] ) ), $this->action ) && isset( $_POST['index_control'] ) ) {
			$control = sanitize_text_field( wp_unslash( $_POST['index_control'] ) );

			switch ( $control ) {
				case 'reindex':
					$this->algolia->reindex_all();
					set_transient( $this->action . '_success', "Successfully triggered a complete reindex for the `{$this->settings->get_index_name()}` index." );
					break;
				case 'clear':
					$this->algolia->clear_obejcts();
					set_transient( $this->action . '_success', 'Successfully triggered a request to clear the entire index.' );
					break;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::$slug ) );
		exit;
	}
}
