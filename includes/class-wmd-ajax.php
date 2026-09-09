<?php
/**
 * AJAX: salvestus, lähtestus, serveripoolne eelvaade ja testmeil.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kujundaja taustapäringud.
 */
class WMD_Ajax {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'wp_ajax_wmd_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_wmd_reset', array( __CLASS__, 'reset' ) );
		add_action( 'wp_ajax_wmd_preview', array( __CLASS__, 'preview' ) );
		add_action( 'wp_ajax_wmd_test_email', array( __CLASS__, 'test_email' ) );
		add_action( 'wp_ajax_wmd_toggle', array( __CLASS__, 'toggle' ) );
		add_action( 'wp_ajax_wmd_save_updates', array( __CLASS__, 'save_updates' ) );
		add_action( 'wp_ajax_wmd_check_update', array( __CLASS__, 'check_update' ) );
		add_action( 'wp_ajax_wmd_update_now', array( __CLASS__, 'update_now' ) );
	}

	/**
	 * Uuenduse paigaldus otse kujundajast.
	 */
	public static function update_now() {
		self::guard();

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-meilidisainer' ) ), 403 );
		}

		$result = WMD_Updater::update_now();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'log'     => (array) $result->get_error_data(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Uuenduste seadete salvestus.
	 */
	public static function save_updates() {
		self::guard();

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-meilidisainer' ) ), 403 );
		}

		$input = array(
			'source' => isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'off',
			'repo'   => isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '',
			'json'   => isset( $_POST['json'] ) ? sanitize_text_field( wp_unslash( $_POST['json'] ) ) : '',
			'token'  => isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '',
		);

		wp_send_json_success( array( 'updates' => WMD_Updater::save_settings( $input ) ) );
	}

	/**
	 * Käsitsi uuenduste kontroll.
	 */
	public static function check_update() {
		self::guard();

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-meilidisainer' ) ), 403 );
		}

		wp_send_json_success( WMD_Updater::check_now() );
	}

	/**
	 * Õiguste ja nonce'i kontroll.
	 */
	protected static function guard() {
		$cap = wmd_woo_active() ? 'manage_woocommerce' : 'manage_options';

		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-meilidisainer' ) ), 403 );
		}

		check_ajax_referer( 'wmd_ajax', 'nonce' );
	}

	/**
	 * Päringust kujundus (JSON-stringina).
	 *
	 * @return array
	 */
	protected static function posted_design() {
		$raw = isset( $_POST['design'] ) ? wp_unslash( $_POST['design'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON puhastatakse WMD_Design::sanitize sees.

		$design = json_decode( (string) $raw, true );

		if ( ! is_array( $design ) ) {
			return array();
		}

		return $design;
	}

	/**
	 * Salvestus.
	 */
	public static function save() {
		self::guard();

		$saved = WMD_Design::save( self::posted_design() );

		wp_send_json_success( array( 'design' => $saved ) );
	}

	/**
	 * Lähtestus.
	 */
	public static function reset() {
		self::guard();

		wp_send_json_success( array( 'design' => WMD_Design::reset() ) );
	}

	/**
	 * Kujunduse sisse-/väljalülitus.
	 */
	public static function toggle() {
		self::guard();

		$on = ! empty( $_POST['on'] ) ? 1 : 0;
		update_option( 'wmd_enabled', $on );

		wp_send_json_success( array( 'enabled' => $on ) );
	}

	/**
	 * Serveripoolne eelvaade — sama tee, mida päris meil käib.
	 */
	public static function preview() {
		self::guard();

		$email_id = isset( $_POST['email'] ) ? sanitize_key( wp_unslash( $_POST['email'] ) ) : '';
		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'design';
		$list     = wmd_email_list();

		if ( ! isset( $list[ $email_id ] ) ) {
			$email_id = key( $list );
		}

		$design = self::posted_design();
		if ( ! empty( $design ) ) {
			WMD_Design::set_cache( WMD_Design::sanitize( $design ) );
		}

		$html   = '';
		$source = 'design';

		if ( 'real' === $mode && wmd_woo_active() ) {
			$html = self::render_real( $email_id );
			if ( $html ) {
				$source = 'real';
			}
		}

		if ( ! $html ) {
			$order = self::latest_order();
			$ctx   = $order ? WMD_Tags::order_context( $order ) : WMD_Tags::sample_context();
			$html  = WMD_Render::full( $email_id, $ctx );
		}

		wp_send_json_success(
			array(
				'html'   => $html,
				'source' => $source,
			)
		);
	}

	/**
	 * Renderdab päris WooCommerce'i meili viimase tellimuse pealt.
	 *
	 * @param string $email_id WC_Email id.
	 * @return string Tühi string, kui ei õnnestunud.
	 */
	protected static function render_real( $email_id ) {
		$order = self::latest_order();

		if ( ! $order ) {
			return '';
		}

		try {
			$mailer = WC()->mailer();
			$found  = null;

			foreach ( $mailer->get_emails() as $email ) {
				if ( isset( $email->id ) && $email->id === $email_id ) {
					$found = $email;
					break;
				}
			}

			if ( ! $found ) {
				return '';
			}

			$found->object    = $order;
			$found->recipient = $order->get_billing_email();

			if ( property_exists( $found, 'placeholders' ) && is_array( $found->placeholders ) ) {
				$date                                = $order->get_date_created();
				$found->placeholders['{order_date}']   = $date ? wc_format_datetime( $date ) : '';
				$found->placeholders['{order_number}'] = $order->get_order_number();
			}

			$html = $found->get_content_html();

			return $found->style_inline( $html );
		} catch ( Throwable $e ) {
			return '';
		}
	}

	/**
	 * Viimane tellimus poes, kui on.
	 *
	 * @return WC_Order|null
	 */
	protected static function latest_order() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			)
		);

		if ( empty( $orders ) || ! is_a( $orders[0], 'WC_Order' ) ) {
			return null;
		}

		return $orders[0];
	}

	/**
	 * Testmeili saatmine.
	 *
	 * Kui poes on tellimus, saadame päris WooCommerce'i meili, aga suuname
	 * saaja ajutiselt testaadressile. Muidu saadame kujunduse näidissisuga.
	 */
	public static function test_email() {
		self::guard();

		$to       = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$email_id = isset( $_POST['email'] ) ? sanitize_key( wp_unslash( $_POST['email'] ) ) : '';
		$list     = wmd_email_list();

		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Vigane e-posti aadress.', 'wonom-meilidisainer' ) ) );
		}

		if ( ! isset( $list[ $email_id ] ) ) {
			$email_id = key( $list );
		}

		// Testmeil saadetakse alati salvestatud kujundusega, et vältida üllatusi.
		$design = self::posted_design();
		if ( ! empty( $design ) ) {
			WMD_Design::save( $design );
		}

		$order = self::latest_order();
		$sent  = false;
		$mode  = 'design';

		if ( $order && wmd_woo_active() ) {
			$sent = self::send_real( $email_id, $order, $to );
			$mode = $sent ? 'real' : 'design';
		}

		if ( ! $sent ) {
			$ctx     = $order ? WMD_Tags::order_context( $order ) : WMD_Tags::sample_context();
			$html    = WMD_Render::full( $email_id, $ctx );
			$subject = sprintf(
				/* translators: %s: meili nimi. */
				__( '[TEST] %s', 'wonom-meilidisainer' ),
				$list[ $email_id ]['label']
			);

			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$sent    = wp_mail( $to, $subject, $html, $headers );
		}

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Meili saatmine ebaõnnestus. Kontrolli poe meiliseadeid.', 'wonom-meilidisainer' ) ) );
		}

		wp_send_json_success(
			array(
				'mode' => $mode,
				'to'   => $to,
			)
		);
	}

	/**
	 * Saadab päris WooCommerce'i meili testaadressile.
	 *
	 * @param string   $email_id WC_Email id.
	 * @param WC_Order $order    Tellimus.
	 * @param string   $to       Saaja.
	 * @return bool
	 */
	protected static function send_real( $email_id, $order, $to ) {
		$list = wmd_email_list();

		// Konto meilid ei käivitu tellimuse pealt — need saadame kujunduse näidisena.
		if ( isset( $list[ $email_id ]['group'] ) && 'account' === $list[ $email_id ]['group'] ) {
			return false;
		}

		try {
			$mailer = WC()->mailer();
			$found  = null;

			foreach ( $mailer->get_emails() as $email ) {
				if ( isset( $email->id ) && $email->id === $email_id ) {
					$found = $email;
					break;
				}
			}

			if ( ! $found || ! method_exists( $found, 'trigger' ) ) {
				return false;
			}

			$force_to = function () use ( $to ) {
				return $to;
			};

			$prefix_subject = function ( $subject ) {
				return '[TEST] ' . $subject;
			};

			add_filter( 'woocommerce_email_recipient_' . $email_id, $force_to, 99 );
			add_filter( 'woocommerce_email_subject_' . $email_id, $prefix_subject, 99 );

			// Mõned meilid on seadetes välja lülitatud; testiks lubame ajutiselt.
			$was_enabled = $found->is_enabled();
			if ( ! $was_enabled ) {
				add_filter( 'woocommerce_email_enabled_' . $email_id, '__return_true', 99 );
			}

			$found->trigger( $order->get_id(), $order );

			remove_filter( 'woocommerce_email_recipient_' . $email_id, $force_to, 99 );
			remove_filter( 'woocommerce_email_subject_' . $email_id, $prefix_subject, 99 );
			if ( ! $was_enabled ) {
				remove_filter( 'woocommerce_email_enabled_' . $email_id, '__return_true', 99 );
			}

			return true;
		} catch ( Throwable $e ) {
			return false;
		}
	}
}
