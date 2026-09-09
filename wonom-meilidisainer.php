<?php
/**
 * Plugin Name:       Wonom Meilidisainer
 * Description:       WooCommerce'i tellimusmeilide visuaalne kujundaja. Bränd seadistatakse üks kord ja rakendub kõigile meilidele; iga meili saab soovi korral eraldi täiendada plokkidega. Elav eelvaade, testmeil, ühtegi rida koodi.
 * Version:           0.9.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * Author:            Wonom Digital
 * Text Domain:       wonom-meilidisainer
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WMD_VERSION', '0.9.1' );
define( 'WMD_FILE', __FILE__ );
define( 'WMD_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMD_URL', plugin_dir_url( __FILE__ ) );
define( 'WMD_OPTION', 'wmd_design' );

require_once WMD_DIR . 'includes/helpers.php';
require_once WMD_DIR . 'includes/class-wmd-design.php';
require_once WMD_DIR . 'includes/class-wmd-tags.php';
require_once WMD_DIR . 'includes/class-wmd-render.php';
require_once WMD_DIR . 'includes/class-wmd-emails.php';
require_once WMD_DIR . 'includes/class-wmd-admin.php';
require_once WMD_DIR . 'includes/class-wmd-ajax.php';
require_once WMD_DIR . 'includes/class-wmd-updater.php';

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'wonom-meilidisainer', false, dirname( plugin_basename( WMD_FILE ) ) . '/languages' );

		WMD_Admin::init();
		WMD_Ajax::init();
		WMD_Updater::init();

		if ( ! wmd_woo_active() ) {
			add_action( 'admin_notices', 'wmd_notice_no_woo' );
			return;
		}

		WMD_Emails::init();
	}
);

register_activation_hook(
	WMD_FILE,
	function () {
		if ( false === get_option( WMD_OPTION, false ) ) {
			add_option( WMD_OPTION, wmd_default_design(), '', 'no' );
		}
	}
);

/**
 * Teade, kui WooCommerce puudub.
 */
function wmd_notice_no_woo() {
	echo '<div class="notice notice-warning"><p>';
	esc_html_e( 'Wonom Meilidisainer vajab töötamiseks WooCommerce\'i. Kujundajat saab kasutada, aga meilid jäävad muutmata.', 'wonom-meilidisainer' );
	echo '</p></div>';
}
