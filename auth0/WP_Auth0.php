<?php

/**
 * Plugin Name: Login by Auth0
 * Plugin URL: https://github.com/auth0/wordpress
 * Description: Login by Auth0 provides improved username/password login, passwordless login, social login, multi-factor authentication, and single sign-on for all your sites.
 * Version: 4.6.2
 * Author: Auth0
 * Author URI: https://auth0.com
 * Text Domain: wp-auth0
 */

define('WPA0_VERSION', '4.6.2');
define('AUTH0_DB_VERSION', 23);

define('WPA0_PLUGIN_FILE', __FILE__);
define('WPA0_PLUGIN_DIR', plugin_dir_path(__FILE__)); // Includes trailing slash
define('WPA0_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPA0_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPA0_PLUGIN_JS_URL', WPA0_PLUGIN_URL . 'assets/js/');
define('WPA0_PLUGIN_CSS_URL', WPA0_PLUGIN_URL . 'assets/css/');
define('WPA0_PLUGIN_IMG_URL', WPA0_PLUGIN_URL . 'assets/img/');
define('WPA0_PLUGIN_LIB_URL', WPA0_PLUGIN_URL . 'assets/lib/');
define('WPA0_PLUGIN_BS_URL', WPA0_PLUGIN_URL . 'assets/bootstrap/');

define('WPA0_LOCK_CDN_URL', 'https://cdn.auth0.com/js/lock/11.30/lock.min.js');
define('WPA0_AUTH0_JS_CDN_URL', 'https://cdn.auth0.com/js/auth0/9.16/auth0.min.js');

define('WPA0_AUTH0_LOGIN_FORM_ID', 'auth0-login-form');
define('WPA0_CACHE_GROUP', 'wp_auth0');
define('WPA0_JWKS_CACHE_TRANSIENT_NAME', 'WP_Auth0_JWKS_cache');

define('WPA0_LANG', 'wp-auth0'); // deprecated; do not use for translations

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/*
 * Startup
 */

/**
 * @deprecated 4.1.0
 */
function wp_auth0_autoloader($class)
{
	return false;
}

function wp_auth0_plugins_loaded()
{
	load_plugin_textdomain('wp-auth0', false, basename(dirname(__FILE__)) . '/languages/');

	$db_manager = new WP_Auth0_DBManager(WP_Auth0_Options::Instance());
	$db_manager->install_db();
}
add_action('plugins_loaded', 'wp_auth0_plugins_loaded');

function wp_auth0_init()
{
	$router = new WP_Auth0_Routes(WP_Auth0_Options::Instance());
	$router->setup_rewrites();
}
add_action('init', 'wp_auth0_init');

function wp_auth0_shortcode($atts)
{
	if (empty($atts)) {
		$atts = [];
	}

	if (empty($atts['redirect_to']) && !empty($_SERVER['REQUEST_URI'])) {
		$atts['redirect_to'] = home_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])));
	}

	ob_start();
	\WP_Auth0_Lock::render(false, $atts);
	return ob_get_clean();
}
add_shortcode('auth0', 'wp_auth0_shortcode');

/*
 * Plugin install/uninstall/update actions
 */

function wp_auth0_activation_hook()
{
	$options    = WP_Auth0_Options::Instance();
	$db_manager = new WP_Auth0_DBManager($options);
	$router     = new WP_Auth0_Routes($options);

	$db_manager->install_db();
	$router->setup_rewrites();
	$options->save();

	flush_rewrite_rules();
}
register_activation_hook(WPA0_PLUGIN_FILE, 'wp_auth0_activation_hook');

function wp_auth0_deactivation_hook()
{
	flush_rewrite_rules();
}
register_deactivation_hook(WPA0_PLUGIN_FILE, 'wp_auth0_deactivation_hook');

function wp_auth0_uninstall_hook()
{
	$a0_options = WP_Auth0_Options::Instance();
	$a0_options->delete();

	$error_log = new WP_Auth0_ErrorLog();
	$error_log->delete();

	delete_option('auth0_db_version');

	delete_option('widget_wp_auth0_popup_widget');
	delete_option('widget_wp_auth0_widget');
	delete_option('widget_wp_auth0_social_amplification_widget');

	delete_transient(WPA0_JWKS_CACHE_TRANSIENT_NAME);
}
register_uninstall_hook(WPA0_PLUGIN_FILE, 'wp_auth0_uninstall_hook');

function wp_auth0_activated_plugin_redirect($plugin)
{

	if (defined('WP_CLI') || $plugin !== WPA0_PLUGIN_BASENAME) {
		return;
	}

	$redirect_query = wp_auth0_is_ready() ? 'page=wpa0' : 'page=wpa0-setup&activation=1';
	wp_safe_redirect(admin_url('admin.php?' . $redirect_query));
	exit;
}
add_action('activated_plugin', 'wp_auth0_activated_plugin_redirect');

/*
 * Core WP hooks
 */

function wp_auth0_add_allowed_redirect_hosts($hosts)
{
	$hosts[] = 'auth0.auth0.com';
	$hosts[] = wp_auth0_get_option('domain');
	$hosts[] = wp_auth0_get_option('custom_domain');
	$hosts[] = wp_auth0_get_option('auth0_server_domain');
	return $hosts;
}

add_filter('allowed_redirect_hosts', 'wp_auth0_add_allowed_redirect_hosts');

/**
 * Enqueue login page CSS if plugin is configured.
 */
function wp_auth0_login_enqueue_scripts()
{
	if (wp_auth0_is_ready()) {
		wp_enqueue_style('auth0', WPA0_PLUGIN_CSS_URL . 'login.css', false, WPA0_VERSION);
	}
}
add_action('login_enqueue_scripts', 'wp_auth0_login_enqueue_scripts');

/**
 * Enqueue login widget CSS if plugin is configured.
 */
function wp_auth0_enqueue_scripts()
{
	if (wp_auth0_is_ready()) {
		wp_enqueue_style('auth0-widget', WPA0_PLUGIN_CSS_URL . 'main.css', false, WPA0_VERSION);
	}
}
add_action('wp_enqueue_scripts', 'wp_auth0_enqueue_scripts');

function wp_auth0_register_widget()
{
	register_widget('WP_Auth0_Embed_Widget');
	register_widget('WP_Auth0_Popup_Widget');
}
add_action('widgets_init', 'wp_auth0_register_widget');

function wp_auth0_register_query_vars($qvars)
{
	return array_merge($qvars, ['error', 'error_description', 'a0_action', 'auth0', 'state', 'code', 'invitation', 'organization', 'organization_name']);
}
add_filter('query_vars', 'wp_auth0_register_query_vars');

/**
 * Output the Auth0 form on wp-login.php
 *
 * @hook filter:login_message
 *
 * @param string $html
 *
 * @return string
 */
function wp_auth0_render_lock_form($html)
{
	ob_start();
	\WP_Auth0_Lock::render();
	$auth0_form = ob_get_clean();
	return $auth0_form ? $auth0_form : $html;
}
add_filter('login_message', 'wp_auth0_render_lock_form', 5);

/**
 * Add settings link on plugin page.
 */
function wp_auth0_plugin_action_links($links)
{

	array_unshift(
		$links,
		wp_kses(sprintf(
			'<a href="%s">%s</a>',
			admin_url('admin.php?page=wpa0'),
			esc_html__('Settings', 'wp-auth0')
		), ['a' => ['href' => true]])
	);

	return $links;
}
add_filter('plugin_action_links_' . WPA0_PLUGIN_BASENAME, 'wp_auth0_plugin_action_links');

/**
 * Filter the avatar to use the Auth0 profile image
 *
 * @param string                                $avatar - avatar HTML
 * @param int|string|WP_User|WP_Comment|WP_Post $id_or_email - user identifier
 * @param int                                   $size - width and height of avatar
 * @param string                                $default - what to do if nothing
 * @param string                                $alt - alt text for the <img> tag
 *
 * @return string
 */
function wp_auth0_filter_get_avatar($avatar, $id_or_email, $size, $default, $alt)
{
	if (!wp_auth0_get_option('override_wp_avatars')) {
		return $avatar;
	}

	$user_id = null;

	if ($id_or_email instanceof WP_User) {
		$user_id = $id_or_email->ID;
	} elseif ($id_or_email instanceof WP_Comment) {
		$user_id = $id_or_email->user_id;
	} elseif ($id_or_email instanceof WP_Post) {
		$user_id = $id_or_email->post_author;
	} elseif (is_email($id_or_email)) {
		$maybe_user = get_user_by('email', $id_or_email);

		if ($maybe_user instanceof WP_User) {
			$user_id = $maybe_user->ID;
		}
	} elseif (is_numeric($id_or_email)) {
		$user_id = absint($id_or_email);
	}

	if (!$user_id) {
		return $avatar;
	}

	$auth0Profile = get_auth0userinfo($user_id);

	if (!$auth0Profile || empty($auth0Profile->picture)) {
		return $avatar;
	}

	return wp_kses(sprintf(
		'<img alt="%s" src="%s" class="avatar avatar-%d photo avatar-auth0" width="%d" height="%d"/>',
		esc_attr($alt),
		esc_url($auth0Profile->picture),
		absint($size),
		absint($size),
		absint($size)
	), ['img' => ['alt' => true, 'src' => true, 'class' => true, 'width' => true, 'height' => true]]);
}
add_filter('get_avatar', 'wp_auth0_filter_get_avatar', 1, 5);

/**
 * Function to call the method that clears out the error log.
 *
 * @hook admin_action_wpauth0_clear_error_log
 */
function wp_auth0_errorlog_clear_error_log()
{

	// Null coalescing validates input variable.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), WP_Auth0_ErrorLog::CLEAR_LOG_NONCE)) {
		wp_die(esc_html__('Not allowed.', 'wp-auth0'));
	}

	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Not authorized.', 'wp-auth0'));
	}

	$error_log = new WP_Auth0_ErrorLog();
	$error_log->clear();

	wp_safe_redirect(admin_url('admin.php?page=wpa0-errors&cleared=1'));
	exit;
}
add_action('admin_action_wpauth0_clear_error_log', 'wp_auth0_errorlog_clear_error_log');

function wp_auth0_export_settings_admin_action()
{

	// Null coalescing validates input variable.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), WP_Auth0_Import_Settings::EXPORT_NONCE_ACTION)) {
		wp_nonce_ays(WP_Auth0_Import_Settings::IMPORT_NONCE_ACTION);
		exit;
	}

	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Unauthorized.', 'wp-auth0'));
		exit;
	}

	$options  = WP_Auth0_Options::Instance();
	$name     = urlencode(get_auth0_curatedBlogName());
	$settings = get_option($options->get_options_name());

	header('Content-Type: application/json');
	header("Content-Disposition: attachment; filename=auth0_for_wordpress_settings-$name.json");
	header('Pragma: no-cache');

	echo wp_json_encode($settings);
	exit;
}
add_action('admin_action_wpauth0_export_settings', 'wp_auth0_export_settings_admin_action');

function wp_auth0_import_settings_admin_action()
{
	$options         = WP_Auth0_Options::Instance();
	$import_settings = new WP_Auth0_Import_Settings($options);
	$import_settings->import_settings();
}
add_action('admin_action_wpauth0_import_settings', 'wp_auth0_import_settings_admin_action');

function wp_auth0_settings_admin_action_error()
{
	// Not processing form data, using an error URL parameter to indicate a problem with the import.
	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification

	if (!wp_auth0_is_admin_page('wpa0-import-settings') || empty($_REQUEST['error'])) {
		return false;
	}

	echo wp_kses(sprintf(
		'<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p></div>',
		sanitize_text_field(wp_unslash($_REQUEST['error']))
	), ['div' => ['class' => true], 'p' => [], 'strong' => []]);
	return true;

	// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification
}
add_action('admin_notices', 'wp_auth0_settings_admin_action_error');

function wp_auth0_profile_change_email($wp_user_id, $old_user_data)
{
	$options              = WP_Auth0_Options::Instance();
	$api_client_creds     = new WP_Auth0_Api_Client_Credentials($options);
	$api_change_email     = new WP_Auth0_Api_Change_Email($options, $api_client_creds);
	$profile_change_email = new WP_Auth0_Profile_Change_Email($api_change_email);
	return $profile_change_email->update_email($wp_user_id, $old_user_data);
}
add_action('profile_update', 'wp_auth0_profile_change_email', 100, 2);

function wp_auth0_validate_new_password($errors, $user)
{
	$options             = WP_Auth0_Options::Instance();
	$api_client_creds    = new WP_Auth0_Api_Client_Credentials($options);
	$api_change_password = new WP_Auth0_Api_Change_Password($options, $api_client_creds);
	$profile_change_pwd  = new WP_Auth0_Profile_Change_Password($api_change_password);
	return $profile_change_pwd->validate_new_password($errors, $user);
}

// Used during profile update in wp-admin.
add_action('user_profile_update_errors', 'wp_auth0_validate_new_password', 10, 2);

// Used during password reset on wp-login.php.
add_action('validate_password_reset', 'wp_auth0_validate_new_password', 10, 2);

// Used during WooCommerce edit account save.
add_action('woocommerce_save_account_details_errors', 'wp_auth0_validate_new_password', 10, 2);

function wp_auth0_show_delete_identity()
{
	$profile_delete_data = new WP_Auth0_Profile_Delete_Data();
	$profile_delete_data->show_delete_identity();
}
add_action('edit_user_profile', 'wp_auth0_show_delete_identity');
add_action('show_user_profile', 'wp_auth0_show_delete_identity');

function wp_auth0_delete_user_data()
{
	$profile_delete_data = new WP_Auth0_Profile_Delete_Data();
	$profile_delete_data->delete_user_data();
}
add_action('wp_ajax_auth0_delete_data', 'wp_auth0_delete_user_data');

function wp_auth0_init_admin_menu()
{

	if (wp_auth0_is_admin_page('wpa0-help')) {
		wp_safe_redirect(admin_url('admin.php?page=wpa0#help'), 301);
		exit;
	}

	$options       = WP_Auth0_Options::Instance();
	$routes        = new WP_Auth0_Routes($options);
	$admin         = new WP_Auth0_Admin($options, $routes);

	$settings_slug  = 'wpa0';
	$settings_title = esc_html__('Settings', 'wp-auth0');
	$settings_func  = [$admin, 'render_settings_page'];

	$menu_parent = $settings_slug;
	$cap         = 'manage_options';

	add_menu_page(
		'Auth0',
		'Auth0',
		$cap,
		$menu_parent,
		$settings_func,
		WPA0_PLUGIN_IMG_URL . 'a0icon.png',
		86
	);

	if (!wp_auth0_is_ready()) {
		add_submenu_page($menu_parent, $settings_title, $settings_title, $cap, $settings_slug, $settings_func);
	} else {
		add_submenu_page($menu_parent, $settings_title, $settings_title, $cap, $settings_slug, $settings_func);
		add_submenu_page(
			$menu_parent,
			esc_html__('Help', 'wp-auth0'),
			esc_html__('Help', 'wp-auth0'),
			$cap,
			'wpa0-help',
			'__return_false'
		);
	}

	add_submenu_page(
		$menu_parent,
		esc_html__('Error Log', 'wp-auth0'),
		esc_html__('Error Log', 'wp-auth0'),
		$cap,
		'wpa0-errors',
		[new WP_Auth0_ErrorLog(), 'render_settings_page']
	);

	add_submenu_page(
		$menu_parent,
		esc_html__('Import-Export Settings', 'wp-auth0'),
		esc_html__('Import-Export settings', 'wp-auth0'),
		$cap,
		'wpa0-import-settings',
		[new WP_Auth0_Import_Settings($options), 'render_import_settings_page']
	);
}
add_action('admin_menu', 'wp_auth0_init_admin_menu', 96, 0);

function wp_auth0_create_account_message()
{
	// Not processing form data, just using a redirect parameter if present.
	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification

	// Null coalescing validates input variable.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	$current_page     = $_GET['page'] ?? null;
	$is_correct_admin = in_array($current_page, ['wpa0', 'wpa0-errors', 'wpa0-import-settings']);
	if (wp_auth0_is_ready() || !$is_correct_admin) {
		return false;
	}

	echo wp_kses(sprintf(
		'<div style="margin: 1rem 2rem 1rem 0; padding: 1.5rem; background: gray; color: white; border-radius: 4px">%s
			<strong><a href="https://auth0.com/docs/cms/wordpress/installation#manual-setup" target="_blank" style="font-weight: bold; color: white">
			%s</a></strong>.</div>',
		esc_html__('Plugin is not yet configured. Please follow the ', 'wp-auth0'),
		esc_html__('setup instructions', 'wp-auth0')
	), ['div' => ['style' => true], 'strong' => [], 'a' => ['href' => true, 'target' => true, 'style' => true]]);
	return true;

	// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification
}
function wp_auth0_deprecated_version()
{
	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification

	// Null coalescing validates input variable.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	$current_page     = $_GET['page'] ?? null;
	$is_correct_admin = in_array($current_page, ['wpa0', 'wpa0-errors', 'wpa0-import-settings']);
	if (wp_auth0_is_ready() || !$is_correct_admin) {
		return false;
	}

	echo wp_kses(sprintf(
		'<div style="margin: 1rem 2rem 1rem 0; padding: 1.5rem; background: orange; color: white; border-radius: 4px">%s
			<strong><a href="https://github.com/auth0/wordpress#upgrade" target="_blank" style="font-weight: bold; color: white">
			%s</a></strong>.</div>',
		esc_html__('This version of the Auth0 WordPress plugin is no longer supported. Please ', 'wp-auth0'),
		esc_html__('upgrade to v5.', 'wp-auth0')
	), ['div' => ['style' => true], 'strong' => [], 'a' => ['href' => true, 'target' => true, 'style' => true]]);
	return true;

	// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification
}
// add_action('admin_notices', 'wp_auth0_deprecated_version');
add_action('admin_notices', 'wp_auth0_create_account_message');

function wp_auth0_init_admin()
{
	$options = WP_Auth0_Options::Instance();
	$routes  = new WP_Auth0_Routes($options);
	$admin   = new WP_Auth0_Admin($options, $routes);
	$admin->init_admin();
}
add_action('admin_init', 'wp_auth0_init_admin');

function wp_auth0_admin_enqueue_scripts()
{
	$options = WP_Auth0_Options::Instance();
	$routes  = new WP_Auth0_Routes($options);
	$admin   = new WP_Auth0_Admin($options, $routes);
	return $admin->admin_enqueue();
}
add_action('admin_enqueue_scripts', 'wp_auth0_admin_enqueue_scripts', 1);

function wp_auth0_custom_requests($wp, $return = false)
{
	$routes = new WP_Auth0_Routes(WP_Auth0_Options::Instance());
	return $routes->custom_requests($wp, $return);
}
add_action('parse_request', 'wp_auth0_custom_requests');

function wp_auth0_profile_enqueue_scripts()
{
	global $pagenow;

	if (!in_array($pagenow, ['profile.php', 'user-edit.php'])) {
		return false;
	}

	wp_enqueue_script(
		'wpa0_user_profile',
		WPA0_PLUGIN_JS_URL . 'edit-user-profile.js',
		['jquery'],
		WPA0_VERSION,
		true
	);

	$profile  = get_auth0userinfo($GLOBALS['user_id']);
	$strategy = isset($profile->sub) ? WP_Auth0_Users::get_strategy($profile->sub) : '';

	wp_localize_script(
		'wpa0_user_profile',
		'wpa0UserProfile',
		[
			'userId'        => intval($GLOBALS['user_id']),
			'userStrategy'  => sanitize_text_field($strategy),
			'deleteIdNonce' => wp_create_nonce('delete_auth0_identity'),
			'ajaxUrl'       => admin_url('admin-ajax.php'),
			'i18n'          => [
				'confirmDeleteId'   => esc_html__('Are you sure you want to delete the Auth0 user data for this user?', 'wp-auth0'),
				'actionComplete'    => esc_html__('Deleted', 'wp-auth0'),
				'actionFailed'      => esc_html__('Action failed, please see the Auth0 error log for details.', 'wp-auth0'),
				'cannotChangeEmail' => esc_html__('Email cannot be changed for non-database connections.', 'wp-auth0'),
			],
		]
	);

	return true;
}
add_action('admin_enqueue_scripts', 'wp_auth0_profile_enqueue_scripts');

function wp_auth0_process_auth_callback()
{
	$users_repo    = new WP_Auth0_UsersRepo(WP_Auth0_Options::Instance());
	$login_manager = new WP_Auth0_LoginManager($users_repo, WP_Auth0_Options::Instance());
	return $login_manager->init_auth0();
}
add_action('template_redirect', 'wp_auth0_process_auth_callback', 1);

function wp_auth0_login_ulp_redirect()
{
	$users_repo    = new WP_Auth0_UsersRepo(WP_Auth0_Options::Instance());
	$login_manager = new WP_Auth0_LoginManager($users_repo, WP_Auth0_Options::Instance());
	return $login_manager->login_auto();
}
add_action('login_init', 'wp_auth0_login_ulp_redirect');

function wp_auth0_process_logout()
{
	$users_repo    = new WP_Auth0_UsersRepo(WP_Auth0_Options::Instance());
	$login_manager = new WP_Auth0_LoginManager($users_repo, WP_Auth0_Options::Instance());
	$login_manager->logout();
}
add_action('wp_logout', 'wp_auth0_process_logout');

function wp_auth0_ajax_delete_cache_transient()
{
	check_ajax_referer('auth0_delete_cache_transient');
	delete_transient(WPA0_JWKS_CACHE_TRANSIENT_NAME);
	wp_send_json_success();
}
add_action('wp_ajax_auth0_delete_cache_transient', 'wp_auth0_ajax_delete_cache_transient');

/**
 * AJAX endpoint to rotate the migration token.
 */
function wp_auth0_ajax_rotate_migration_token()
{
	check_ajax_referer(WP_Auth0_Admin_Advanced::ROTATE_TOKEN_NONCE_ACTION);

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['error' => esc_html__('Not authorized.', 'wp-auth0')]);
		return;
	}

	WP_Auth0_Options::Instance()->set('migration_token', wp_auth0_generate_token());
	wp_send_json_success();
}
add_action('wp_ajax_auth0_rotate_migration_token', 'wp_auth0_ajax_rotate_migration_token');

/**
 * AJAX handler to re-send verification email.
 * Hooked to: wp_ajax_nopriv_resend_verification_email
 *
 * @codeCoverageIgnore - Tested in TestEmailVerification::testResendVerificationEmail()
 */
function wp_auth0_ajax_resend_verification_email()
{
	check_ajax_referer(WP_Auth0_Email_Verification::RESEND_NONCE_ACTION);

	$options               = WP_Auth0_Options::Instance();
	$api_client_creds      = new WP_Auth0_Api_Client_Credentials($options);
	$api_jobs_verification = new WP_Auth0_Api_Jobs_Verification($options, $api_client_creds);

	if (empty($_POST['sub'])) {
		wp_send_json_error(['error' => esc_html__('No Auth0 user ID provided.', 'wp-auth0')]);
	}

	// Validated above and only sent to the change signup API endpoint.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if (!$api_jobs_verification->call(wp_unslash($_POST['sub']))) {
		wp_send_json_error(['error' => esc_html__('API call failed.', 'wp-auth0')]);
	}

	wp_send_json_success();
}
add_action('wp_ajax_nopriv_resend_verification_email', 'wp_auth0_ajax_resend_verification_email');

/**
 * Redirect a successful lost password submission to a login override page.
 *
 * @param string $location - Redirect in process.
 *
 * @return string
 */
function wp_auth0_filter_wp_redirect_lostpassword($location)
{
	// Make sure we're going to the check email action on the wp-login page.
	if ('wp-login.php?checkemail=confirm' !== $location) {
		return $location;
	}

	// Make sure we're on the lost password action on the wp-login page.
	if (!wp_auth0_is_current_login_action(['lostpassword'])) {
		return $location;
	}

	// Make sure plugin settings allow core WP login form overrides
	if ('never' === wp_auth0_get_option('wordpress_login_enabled')) {
		return $location;
	}

	// Make sure we're coming from an override page.
	$required_referrer = remove_query_arg('wle', wp_login_url());
	$required_referrer = add_query_arg('action', 'lostpassword', $required_referrer);
	$required_referrer = wp_auth0_login_override_url($required_referrer);
	if (!isset($_SERVER['HTTP_REFERER']) || $required_referrer !== $_SERVER['HTTP_REFERER']) {
		return $location;
	}

	return wp_auth0_login_override_url($location);
}

add_filter('wp_redirect', 'wp_auth0_filter_wp_redirect_lostpassword', 100);

/**
 * Add an override code to the lost password URL if authorized.
 *
 * @param string $wp_login_url - Existing lost password URL.
 *
 * @return string
 */
function wp_auth0_filter_login_override_url($wp_login_url)
{
	// Not processing form data, just using a redirect parameter if present.
	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification

	$options = WP_Auth0_Options::Instance();
	$wle = $options->get('wle_code');

	if (wp_auth0_can_show_wp_login_form() && $wle !== null) {
		// We are on an override page.
	} elseif (wp_auth0_is_current_login_action(['resetpass'])) {
		// We are on the reset password page with a link to login.
		// This page will not be shown unless we get here via a valid reset password request.
		$wp_login_url = wp_auth0_login_override_url($wp_login_url);
	}
	return esc_url($wp_login_url);

	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
}

add_filter('lostpassword_url', 'wp_auth0_filter_login_override_url', 100);
add_filter('login_url', 'wp_auth0_filter_login_override_url', 100);

/**
 * Add the core WP form override to the lost password and login forms.
 */
function wp_auth0_filter_login_override_form()
{
	// Not processing form data, just using a redirect parameter if present.
	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification

	if (wp_auth0_can_show_wp_login_form() && isset($_REQUEST['wle'])) {
		// Input is being output, not stored.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		echo wp_kses(sprintf('<input type="hidden" name="wle" value="%s" />', esc_attr(wp_unslash($_REQUEST['wle']))), ['input' => ['type' => true, 'name' => true, 'value' => true]]);
	}

	// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
}

add_action('login_form', 'wp_auth0_filter_login_override_form', 100);
add_action('lostpassword_form', 'wp_auth0_filter_login_override_form', 100);

/**
 * Add new classes to the body element on all front-end and login pages.
 *
 * @param array $classes - Array of existing classes.
 *
 * @return array
 */
function wp_auth0_filter_body_class(array $classes)
{
	if (wp_auth0_can_show_wp_login_form()) {
		$classes[] = 'a0-show-core-login';
	}
	return $classes;
}
add_filter('body_class', 'wp_auth0_filter_body_class');
add_filter('login_body_class', 'wp_auth0_filter_body_class');

/*
 * WooCommerce hooks
 */

/**
 * Add the Auth0 login form to the checkout page.
 *
 * @param string $html - Original HTML passed to this hook.
 *
 * @return mixed
 */
function wp_auth0_filter_woocommerce_checkout_login_message($html)
{
	$wp_auth0_woocommerce = new WP_Auth0_WooCommerceOverrides(WP_Auth0_Options::Instance());
	return $wp_auth0_woocommerce->override_woocommerce_checkout_login_form($html);
}
add_filter('woocommerce_checkout_login_message', 'wp_auth0_filter_woocommerce_checkout_login_message');

/**
 * Add the Auth0 login form to the account page.
 *
 * @param string $html - Original HTML passed to this hook.
 *
 * @return mixed
 */
function wp_auth0_filter_woocommerce_before_customer_login_form($html)
{
	$wp_auth0_woocommerce = new WP_Auth0_WooCommerceOverrides(WP_Auth0_Options::Instance());
	return $wp_auth0_woocommerce->override_woocommerce_login_form($html);
}
add_filter('woocommerce_before_customer_login_form', 'wp_auth0_filter_woocommerce_before_customer_login_form');

/*
 * Beta plugin deactivation
 */

// Passwordless beta testing - https://github.com/auth0/wp-auth0/issues/400
remove_filter('login_message', 'wp_auth0_pwl_plugin_login_message_before', 5);
remove_filter('login_message', 'wp_auth0_pwl_plugin_login_message_after', 6);
