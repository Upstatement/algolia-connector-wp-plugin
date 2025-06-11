<?php
/**
 * Form options admin template.
 *
 * @package AlgoliaConnector
 */

use Upstatement\AlgoliaConnector\Admin\SettingsPage;

?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form method="post" action="options.php">
		<?php
		if ( isset( $this ) ) {
			$valid_credentials = false;
			try {
				$settings          = $this->plugin->get_settings();
				$valid_credentials = $this->plugin->valid_credentials( $settings->get_admin_api_key() );
				$connection_status = 'Connected';
			} catch ( Exception $exception ) {
				$connection_status = 'Not connected';
			}
			?>

			<div><p><strong>Status: <span style="color: <?php echo $valid_credentials ? 'green' : 'red'; ?>"><?php echo esc_html( $connection_status ); ?></span></strong></p></div>

			<?php
			wp_nonce_field( $this->option_group, '_algolia_connector_settings' );

			settings_fields( $this->option_group );
			do_settings_sections( SettingsPage::$slug );
			submit_button();
		}
		?>
	</form>
</div>
