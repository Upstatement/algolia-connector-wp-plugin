<?php
/**
 * Index controls.
 *
 * @package AlgoliaConnector
 */

?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( isset( $this ) ) : ?>
		<?php
		$valid_credentials = false;
		$settings          = null;
		try {
			$settings          = $this->plugin->get_settings();
			$valid_credentials = $this->plugin->valid_credentials( $settings->get_admin_api_key() );
			$connection_status = 'Connected';
		} catch ( Exception $exception ) {
			$connection_status = 'Not connected';
		}
		?>

		<div><p><strong>Status: <span style="color: <?php echo $valid_credentials ? 'green' : 'red'; ?>"><?php echo esc_html( $connection_status ); ?></span></strong></p></div>


		<?php if ( $valid_credentials && $settings ) : ?>

		<p>Manging index:  <strong><?php echo esc_html( $settings->get_index_name() ); ?></strong></p>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $this->action ); ?>">
			<?php wp_nonce_field( $this->action, '_algolia_index_controls' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">Action</th>
						<td>
							<select name="index_control" id="">
								<option value="reindex">Reindex all content</option>
								<option value="clear">Clear index</option>
							</select>

							<p class="description">Choose the action to perform on the index.</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( 'Submit' ); ?>
		</form>
		<?php endif; ?>
	<?php endif; ?>

</div>
