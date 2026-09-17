<?php
/**
 * Halduslehe kest ja skriptide laadimine.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kujundaja leht.
 */
class WMD_Admin {

	/**
	 * Lehe hook-nimi.
	 *
	 * @var string
	 */
	protected static $hook = '';

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WMD_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menüükirje.
	 */
	public static function menu() {
		$parent = wmd_woo_active() ? 'woocommerce' : null;

		if ( $parent ) {
			self::$hook = add_submenu_page(
				$parent,
				__( 'Email Designer', 'wonom-meilidisainer' ),
				__( 'Email Designer', 'wonom-meilidisainer' ),
				'manage_woocommerce',
				'wonom-meilidisainer',
				array( __CLASS__, 'page' )
			);

			return;
		}

		self::$hook = add_menu_page(
			__( 'Email Designer', 'wonom-meilidisainer' ),
			__( 'Email Designer', 'wonom-meilidisainer' ),
			'manage_options',
			'wonom-meilidisainer',
			array( __CLASS__, 'page' ),
			'dashicons-email-alt',
			58
		);
	}

	/**
	 * Kiirlink pluginate lehel.
	 *
	 * @param array $links Lingid.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=wonom-meilidisainer' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Design emails', 'wonom-meilidisainer' ) . '</a>'
		);

		return $links;
	}

	/**
	 * CSS ja JS ainult kujundaja lehel.
	 *
	 * @param string $hook Praegune leht.
	 */
	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style( 'wmd-admin', WMD_URL . 'assets/admin.css', array(), WMD_VERSION );
		wp_enqueue_script( 'wmd-renderer', WMD_URL . 'assets/renderer.js', array( 'wp-i18n' ), WMD_VERSION, true );
		wp_enqueue_script( 'wmd-admin', WMD_URL . 'assets/admin.js', array( 'wmd-renderer', 'wp-i18n' ), WMD_VERSION, true );

		// Mõlema skripti tekstid on lähtekoodis inglise keeles; tõlked tulevad
		// languages/ kaustast JSON-failidena (wp i18n make-json).
		wp_set_script_translations( 'wmd-renderer', 'wonom-meilidisainer', WMD_DIR . 'languages' );
		wp_set_script_translations( 'wmd-admin', 'wonom-meilidisainer', WMD_DIR . 'languages' );

		wp_localize_script(
			'wmd-admin',
			'WMD',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wmd_ajax' ),
				'design'      => WMD_Design::for_js(),
				'brandSchema' => wmd_brand_schema(),
				'brandGroups' => wmd_brand_groups(),
				'blockTypes'  => wmd_block_types(),
				'emails'      => wmd_email_list(),
				'emailGroups' => wmd_email_groups(),
				'tags'        => WMD_Tags::all(),
				'tagGroups'   => WMD_Tags::groups(),
				'sampleCtx'   => WMD_Tags::sample_context(),
				'enabled'     => WMD_Emails::enabled() ? 1 : 0,
				'testTo'      => wp_get_current_user()->user_email,
				'hasWoo'      => wmd_woo_active() ? 1 : 0,
				'wcDefaults'  => self::wc_defaults(),
				'orders'      => wmd_recent_orders(),
				'gateways'    => wmd_payment_gateways(),
				// Keelte valik ilmub ainult siis, kui poes on üle ühe keele.
				'languages'   => wmd_languages(),
				'defaultLang' => wmd_default_language(),
				'canUpdate'   => current_user_can( 'update_plugins' ) ? 1 : 0,
				'updates'     => WMD_Updater::settings(),
				'mt'          => WMD_Translate::settings(),
				'pluginsUrl'  => admin_url( 'plugins.php' ),
				// Tekstid ei käi enam siit läbi: skriptid kasutavad wp.i18n,
				// nii on kõik tõlgitav ühest kohast ja üht teed pidi.
				'assetsUrl'   => WMD_URL . 'assets/',
			)
		);
	}

	/**
	 * WooCommerce'i enda teemad ja pealkirjad, et eelvaade ei näitaks midagi muud
	 * kui päris meil. Ilma tellimuseta jäävad kohatäitjad tühjaks — see on ok.
	 *
	 * @return array<string,array>
	 */
	protected static function wc_defaults() {
		$out = array();

		if ( ! wmd_woo_active() || ! function_exists( 'WC' ) ) {
			return $out;
		}

		$list = wmd_email_list();

		try {
			$mailer = WC()->mailer();

			foreach ( $mailer->get_emails() as $email ) {
				if ( empty( $email->id ) || ! isset( $list[ $email->id ] ) ) {
					continue;
				}

				// Küsime seadetest otse, mitte get_subject() kaudu — muidu tuleks
				// tagasi meie enda ülekirjutus ja väli näitaks kohatäitjana iseennast.
				$heading = method_exists( $email, 'get_default_heading' ) ? $email->get_default_heading() : '';
				$subject = method_exists( $email, 'get_default_subject' ) ? $email->get_default_subject() : '';

				$out[ $email->id ] = array(
					'heading' => wp_strip_all_tags( (string) $email->format_string( $email->get_option( 'heading', $heading ) ) ),
					'subject' => wp_strip_all_tags( (string) $email->format_string( $email->get_option( 'subject', $subject ) ) ),
				);
			}
		} catch ( Throwable $e ) {
			return $out;
		}

		return $out;
	}

	/**
	 * Lehe kest. Sisu ehitab JS.
	 */
	public static function page() {
		?>
		<div class="wrap wmd-wrap">
			<div id="wmd-app" class="wmd-app">
				<div class="wmd-boot"><?php esc_html_e( 'Loading the designer…', 'wonom-meilidisainer' ); ?></div>
			</div>
			<noscript>
				<p><?php esc_html_e( 'The designer needs JavaScript.', 'wonom-meilidisainer' ); ?></p>
			</noscript>
		</div>
		<?php
	}
}
