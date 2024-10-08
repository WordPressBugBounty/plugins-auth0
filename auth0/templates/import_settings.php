<?php
$opts          = WP_Auth0_Options::Instance();
$constant_keys = $opts->get_all_constant_keys();
?>
<div class="a0-wrap settings wrap">

	<div class="container-fluid">

		<h1><?php esc_html_e('Import and Export Settings', 'wp-auth0'); ?></h1>

		<p class="a0-step-text top-margin">
			<?php esc_html_e('You can import and export your Auth0 WordPress plugin settings here. ', 'wp-auth0'); ?>
			<?php esc_html_e('This allows you to either backup the data, or to move your settings to a new WordPress instance.', 'wp-auth0'); ?>
		</p>
		<?php if (!empty($constant_keys)) : ?>
			<p class="a0-step-text top-margin no-bottom-margin">
				<strong><?php esc_html_e('Please note:', 'wp-auth0'); ?></strong>
				<?php esc_html_e('Settings stored in constants cannot be exported or imported.', 'wp-auth0'); ?>
			</p>
		<?php endif; ?>

		<p class="nav-tabs">
			<a id="tab-import" href="#import" class="js-a0-import-export-tabs"><?php esc_html_e('Import Settings', 'wp-auth0'); ?></a>
			<a id="tab-export" href="#export" class="js-a0-import-export-tabs"><?php esc_html_e('Export Settings', 'wp-auth0'); ?></a>
		</p>

		<div class="tab-pane" id="panel-import" style="display: block">

			<form action="options.php" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wpauth0_import_settings" />
				<?php wp_nonce_field(WP_Auth0_Import_Settings::IMPORT_NONCE_ACTION); ?>
				<p class="a0-step-text top-margin">
					<?php
					esc_html_e('Paste the settings JSON in the field below. ', 'wp-auth0');
					esc_html_e('Settings that are not in the imported JSON will use existing values. ', 'wp-auth0');
					esc_html_e('Setting values will be validated so check the final values once import is complete. ', 'wp-auth0');
					?>
				<div class="a0-step-text top-margin"><textarea name="settings-json" class="large-text code" rows="6"></textarea></div>

				<div class="a0-buttons">
					<input type="submit" name="setup" class="abutton button-primary" value="<?php esc_html_e('Import', 'wp-auth0'); ?>" />
				</div>

			</form>

		</div>
		<div class="tab-pane" id="panel-export" style="display: none">

			<form action="options.php" method="post">
				<?php wp_nonce_field(WP_Auth0_Import_Settings::EXPORT_NONCE_ACTION); ?>
				<input type="hidden" name="action" value="wpauth0_export_settings" />

				<p class="a0-step-text top-margin"><?php esc_html_e('Download the entire plugin configuration.', 'wp-auth0'); ?></p>

				<div class="a0-buttons">
					<input type="submit" name="setup" class="button button-primary" value="<?php esc_html_e('Export', 'wp-auth0'); ?>" />
				</div>

			</form>

		</div>

	</div>

</div>
