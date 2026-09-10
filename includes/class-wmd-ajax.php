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
		add_action( 'wp_ajax_wmd_wc_part', array( __CLASS__, 'wc_part' ) );
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

		wp_send_json_success( array( 'design' => WMD_Design::for_js( $saved ) ) );
	}

	/**
	 * Lähtestus.
	 */
	public static function reset() {
		self::guard();

		wp_send_json_success( array( 'design' => WMD_Design::for_js( WMD_Design::reset() ) ) );
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

		$order_id = self::posted_order_id();
		$html     = '';
		$source   = 'design';

		if ( 'real' === $mode && wmd_woo_active() ) {
			$html = self::render_real( $email_id, $order_id );
			if ( $html ) {
				$source = 'real';
			}
		}

		$order = self::preview_order( $order_id );

		if ( ! $html ) {
			$ctx  = $order ? WMD_Tags::order_context( $order ) : WMD_Tags::sample_context();
			$html = WMD_Render::full( $email_id, $ctx );
		}

		wp_send_json_success(
			array(
				'html'    => $html,
				'source'  => $source,
				'order'   => $order ? $order->get_id() : 0,
				'payment' => $order ? $order->get_payment_method() : '',
			)
		);
	}

	/**
	 * Ainult WooCommerce'i enda sisuosa, ilma meie päise ja jaluseta.
	 *
	 * Kujundaja paneb selle oma kanvasel plokkide vahele, et sa näeksid päris
	 * teksti — sealhulgas seda, kui su enda plokk ütleb sama, mida WooCommerce.
	 * See osa ei sõltu plokkidest, seega piisab ühest päringust meili ja
	 * tellimuse kohta.
	 */
	public static function wc_part() {
		self::guard();

		$email_id = isset( $_POST['email'] ) ? sanitize_key( wp_unslash( $_POST['email'] ) ) : '';
		$list     = wmd_email_list();

		if ( ! isset( $list[ $email_id ] ) ) {
			$email_id = key( $list );
		}

		$design = self::posted_design();
		if ( ! empty( $design ) ) {
			WMD_Design::set_cache( WMD_Design::sanitize( $design ) );
		}

		$order = self::preview_order( self::posted_order_id() );

		// Kontomeilidel (uus konto, parooli lähtestamine) ei olegi tellimust,
		// aga WooCommerce'i sisu on neil ikka olemas — seega renderdame edasi.
		if ( ! wmd_woo_active() ) {
			wp_send_json_success(
				array(
					'html' => '',
					'css'  => '',
					'why'  => __( 'WooCommerce ei ole aktiivne.', 'wonom-meilidisainer' ),
				)
			);
		}

		$html = '';
		$css  = '';

		// Täisrežiimis ei kasutata WooCommerce'i sisuosa üldse, seega ei ole
		// mõtet meili renderdada. Tellimuse read ja väljad on ikka vaja.
		$needs_html = ! WMD_Design::is_full( $email_id );

		WMD_Render::$mark_wc = true;

		try {
			$found = self::find_email( $email_id );

			if ( $found ) {
				if ( $order ) {
					$found->object    = $order;
					$found->recipient = $order->get_billing_email();

					if ( property_exists( $found, 'placeholders' ) && is_array( $found->placeholders ) ) {
						$date                                  = $order->get_date_created();
						$found->placeholders['{order_date}']   = $date ? wc_format_datetime( $date ) : '';
						$found->placeholders['{order_number}'] = $order->get_order_number();
					}
				}

				self::prepare_account_email( $found, $order );

				// Stiile ei reastata sisse — eelvaade on brauser, mitte postkast,
				// ja reastaja võiks markerid ära süüa.
				if ( $needs_html ) {
					$full  = $found->get_content_html();
					$start = strpos( $full, WMD_Render::WC_START );
					$end   = strpos( $full, WMD_Render::WC_END );

					if ( false !== $start && false !== $end && $end > $start ) {
						$html = substr( $full, $start + strlen( WMD_Render::WC_START ), $end - $start - strlen( WMD_Render::WC_START ) );
					}
				}

				ob_start();
				wc_get_template( 'emails/email-styles.php' );
				$css = apply_filters( 'woocommerce_email_styles', (string) ob_get_clean(), $found );
			}
		} catch ( Throwable $e ) {
			$html = '';
		}

		WMD_Render::$mark_wc = false;

		// Kogu ülejäänu, mida kujundaja vajab, et mitte näidata näidisandmeid:
		// märgendite väärtused ja WooCommerce'i osad päris tellimuse pealt.
		$ctx = WMD_Tags::order_context( $order );

		$ctx['__email']         = self::find_email( $email_id );
		$ctx['__sent_to_admin'] = false;

		$parts = array();

		try {
			$parts = array(
				'order_table'  => WMD_Render::woo_part( 'order_table', $ctx ),
				'payment_info' => WMD_Render::woo_part( 'payment_info', $ctx ),
				'additional'   => self::additional_content( $email_id, $order ),
			);
		} catch ( Throwable $e ) {
			$parts = array();
		}

		wp_send_json_success(
			array(
				'html'   => $html,
				'css'    => $css,
				'order'  => $order->get_id(),
				'items'  => WMD_Render::order_items_data( $order ),
				'totals' => WMD_Render::order_totals_data( $order ),
				'fields' => self::order_fields( $order ),
				'ctx'    => array_filter( $ctx, 'is_scalar' ),
				'parts'  => $parts,
				'addr'   => WMD_Render::address_data( $order ),
				'why'    => ( $needs_html && '' === $html ) ? __( 'WooCommerce\'i sisu ei õnnestunud renderdada.', 'wonom-meilidisainer' ) : '',
			)
		);
	}

	/**
	 * Selle tellimuse enda väljad, et kujundaja väljavalik ei oleks üldine
	 * nimekiri, vaid näitaks seda, mis päriselt olemas on.
	 *
	 * @param WC_Order $order Tellimus.
	 * @return array
	 */
	protected static function order_fields( $order ) {
		$out = array();

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $out;
		}

		foreach ( $order->get_meta_data() as $meta ) {
			$data = $meta->get_data();

			if ( empty( $data['key'] ) ) {
				continue;
			}

			$value = $data['value'];

			// Massiivid ja objektid ei sobi meili teksti — jätame need nimekirjast välja.
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$out[] = array(
				'key'    => (string) $data['key'],
				'sample' => mb_substr( wp_strip_all_tags( $value ), 0, 60 ),
			);
		}

		usort(
			$out,
			function ( $a, $b ) {
				return strcmp( $a['key'], $b['key'] );
			}
		);

		return $out;
	}

	/**
	 * Täidab kontomeili eelvaate jaoks vajalikud väljad.
	 *
	 * Uue konto ja parooli lähtestamise meilid ei käi tellimuse pealt, vaid
	 * ootavad kasutajanime ja linke. Ilma nendeta tuleks eelvaatesse pooleldi
	 * tühi kiri, mis ei näita, mida klient päriselt saab.
	 *
	 * @param WC_Email      $email Meil.
	 * @param WC_Order|null $order Tellimus, kui on.
	 */
	protected static function prepare_account_email( $email, $order ) {
		if ( ! property_exists( $email, 'user_login' ) ) {
			return;
		}

		$login = '';

		if ( $order && $order->get_customer_id() ) {
			$user  = get_userdata( $order->get_customer_id() );
			$login = $user ? $user->user_login : '';
		}

		if ( '' === $login && $order ) {
			$login = $order->get_billing_email();
		}

		if ( '' === $login ) {
			$login = wp_get_current_user()->user_login;
		}

		$email->user_login = $login;

		if ( property_exists( $email, 'user_email' ) ) {
			$email->user_email = $order ? $order->get_billing_email() : wp_get_current_user()->user_email;
		}

		if ( property_exists( $email, 'user_pass' ) ) {
			$email->user_pass = '';
		}

		if ( property_exists( $email, 'password_generated' ) ) {
			$email->password_generated = false;
		}

		if ( property_exists( $email, 'set_password_url' ) && ! $email->set_password_url && function_exists( 'wc_get_page_permalink' ) ) {
			$email->set_password_url = wc_get_page_permalink( 'myaccount' );
		}

		if ( property_exists( $email, 'reset_key' ) && ! $email->reset_key ) {
			$email->reset_key = 'NAIDIS';
		}
	}

	/**
	 * WooCommerce'i meiliseadetes olev lisatekst.
	 *
	 * Täisrežiimis lisab meie mall selle kirja lõppu, et poe seadistus ei kaoks
	 * märkamatult. Kujundaja peab seda samuti näitama, muidu kanvas valetab.
	 *
	 * @param string   $email_id WC_Email id.
	 * @param WC_Order $order    Tellimus.
	 * @return string
	 */
	protected static function additional_content( $email_id, $order ) {
		$email = self::find_email( $email_id );

		if ( ! $email || ! is_callable( array( $email, 'get_additional_content' ) ) ) {
			return '';
		}

		$email->object = $order;
		$text          = trim( (string) $email->get_additional_content() );

		if ( '' === $text ) {
			return '';
		}

		return wp_kses_post( wpautop( wptexturize( $text ) ) );
	}

	/**
	 * WC_Email objekt id järgi.
	 *
	 * @param string $email_id WC_Email id.
	 * @return WC_Email|null
	 */
	protected static function find_email( $email_id ) {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( isset( $email->id ) && $email->id === $email_id ) {
				return $email;
			}
		}

		return null;
	}

	/**
	 * Renderdab päris WooCommerce'i meili viimase tellimuse pealt.
	 *
	 * @param string $email_id WC_Email id.
	 * @return string Tühi string, kui ei õnnestunud.
	 */
	protected static function render_real( $email_id, $order_id = 0 ) {
		$order = self::preview_order( $order_id );

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
	 * Eelvaates kasutatav tellimus.
	 *
	 * Kui päringus on tellimuse id, võtame selle. Muidu poe viimase.
	 *
	 * @param int $order_id Soovitud tellimus või 0.
	 * @return WC_Order|null
	 */
	protected static function preview_order( $order_id = 0 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				return $order;
			}
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
	 * Päringus soovitud tellimuse id.
	 *
	 * @return int
	 */
	protected static function posted_order_id() {
		return isset( $_POST['order'] ) ? absint( wp_unslash( $_POST['order'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce kontrollitakse guard() sees.
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

		$order = self::preview_order( self::posted_order_id() );
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
