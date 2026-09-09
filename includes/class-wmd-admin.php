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
				__( 'Meilidisainer', 'wonom-meilidisainer' ),
				__( 'Meilidisainer', 'wonom-meilidisainer' ),
				'manage_woocommerce',
				'wonom-meilidisainer',
				array( __CLASS__, 'page' )
			);

			return;
		}

		self::$hook = add_menu_page(
			__( 'Meilidisainer', 'wonom-meilidisainer' ),
			__( 'Meilidisainer', 'wonom-meilidisainer' ),
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
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Kujunda meilid', 'wonom-meilidisainer' ) . '</a>'
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
		wp_enqueue_script( 'wmd-renderer', WMD_URL . 'assets/renderer.js', array(), WMD_VERSION, true );
		wp_enqueue_script( 'wmd-admin', WMD_URL . 'assets/admin.js', array( 'wmd-renderer' ), WMD_VERSION, true );

		wp_localize_script(
			'wmd-admin',
			'WMD',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wmd_ajax' ),
				'design'      => WMD_Design::get(),
				'brandSchema' => wmd_brand_schema(),
				'brandGroups' => wmd_brand_groups(),
				'blockTypes'  => wmd_block_types(),
				'emails'      => wmd_email_list(),
				'emailGroups' => wmd_email_groups(),
				'tags'        => WMD_Tags::all(),
				'sampleCtx'   => WMD_Tags::sample_context(),
				'enabled'     => WMD_Emails::enabled() ? 1 : 0,
				'testTo'      => wp_get_current_user()->user_email,
				'hasWoo'      => wmd_woo_active() ? 1 : 0,
				'canUpdate'   => current_user_can( 'update_plugins' ) ? 1 : 0,
				'updates'     => WMD_Updater::settings(),
				'pluginsUrl'  => admin_url( 'plugins.php' ),
				'i18n'        => array(
					'saved'        => __( 'Salvestatud', 'wonom-meilidisainer' ),
					'saveFailed'   => __( 'Salvestamine ebaõnnestus', 'wonom-meilidisainer' ),
					'confirmReset' => __( 'Kas lähtestada kogu kujundus vaikeväärtustele? Seda ei saa tagasi võtta.', 'wonom-meilidisainer' ),
					'confirmDelete' => __( 'Kustutan selle ploki?', 'wonom-meilidisainer' ),
					'sending'      => __( 'Saadan…', 'wonom-meilidisainer' ),
					'sent'         => __( 'Testmeil saadetud', 'wonom-meilidisainer' ),
					'unsaved'      => __( 'Salvestamata muudatused', 'wonom-meilidisainer' ),
					'noBlocks'     => __( 'Siin pole veel ühtegi plokki. Lisa allpool.', 'wonom-meilidisainer' ),
					'inherit'      => __( 'Brändist', 'wonom-meilidisainer' ),
					'pickImage'    => __( 'Vali pilt', 'wonom-meilidisainer' ),
					'change'       => __( 'Vaheta', 'wonom-meilidisainer' ),
					'remove'       => __( 'Eemalda', 'wonom-meilidisainer' ),
				),
			)
		);
	}

	/**
	 * Lehe kest. Sisu ehitab JS.
	 */
	public static function page() {
		?>
		<div class="wrap wmd-wrap">
			<div id="wmd-app" class="wmd-app">
				<div class="wmd-boot"><?php esc_html_e( 'Laen kujundajat…', 'wonom-meilidisainer' ); ?></div>
			</div>
			<noscript>
				<p><?php esc_html_e( 'Kujundaja vajab JavaScripti.', 'wonom-meilidisainer' ); ?></p>
			</noscript>
		</div>
		<?php
	}
}
