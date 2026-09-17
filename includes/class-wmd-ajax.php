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
		add_action( 'wp_ajax_wmd_categories', array( __CLASS__, 'categories' ) );
		add_action( 'wp_ajax_wmd_test_email', array( __CLASS__, 'test_email' ) );
		add_action( 'wp_ajax_wmd_toggle', array( __CLASS__, 'toggle' ) );
		add_action( 'wp_ajax_wmd_save_updates', array( __CLASS__, 'save_updates' ) );
		add_action( 'wp_ajax_wmd_check_update', array( __CLASS__, 'check_update' ) );
		add_action( 'wp_ajax_wmd_update_now', array( __CLASS__, 'update_now' ) );
		add_action( 'wp_ajax_wmd_translate', array( __CLASS__, 'translate' ) );
		add_action( 'wp_ajax_wmd_save_mt', array( __CLASS__, 'save_mt' ) );
	}

	/**
	 * Uuenduse paigaldus otse kujundajast.
	 */
	public static function update_now() {
		self::guard();

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'wonom-meilidisainer' ) ), 403 );
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
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'wonom-meilidisainer' ) ), 403 );
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
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'wonom-meilidisainer' ) ), 403 );
		}

		wp_send_json_success( WMD_Updater::check_now() );
	}

	/**
	 * Masintõlge kujundaja jaoks.
	 *
	 * Kujundaja korjab tõlgitavad tekstid ise kokku ja paneb tulemuse tagasi —
	 * server ainult vahendab päringu. Nii ei pea server plokkide ehitust teadma
	 * ja uue ploki lisamine ei nõua siin midagi.
	 */
	public static function translate() {
		self::guard();

		// Tekst läheb tõlketeenusesse ja tuleb kujundajasse tagasi; salvestamisel
		// käib see läbi WMD_Design::sanitize nagu iga muu sisu.
		$texts  = isset( $_POST['texts'] ) ? (array) wp_unslash( $_POST['texts'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- vt kommentaari.
		$target = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';

		if ( empty( $texts ) ) {
			wp_send_json_success( array( 'texts' => array() ) );
		}

		$done = WMD_Translate::translate( $texts, $target, $source );

		if ( is_wp_error( $done ) ) {
			wp_send_json_error( array( 'message' => $done->get_error_message() ) );
		}

		wp_send_json_success( array( 'texts' => $done ) );
	}

	/**
	 * Masintõlke seadete salvestus.
	 */
	public static function save_mt() {
		self::guard();

		$input = array(
			'provider' => isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'off',
			'key'      => isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '',
		);

		wp_send_json_success( array( 'mt' => WMD_Translate::save_settings( $input ) ) );
	}

	/**
	 * Õiguste ja nonce'i kontroll.
	 */
	protected static function guard() {
		$cap = wmd_woo_active() ? 'manage_woocommerce' : 'manage_options';

		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'wonom-meilidisainer' ) ), 403 );
		}

		check_ajax_referer( 'wmd_ajax', 'nonce' );
	}

	/**
	 * Päringust kujundus (JSON-stringina).
	 *
	 * @return array
	 */
	protected static function posted_design() {
		// Kujundus tuleb base64-kujul. Põhjus on praktiline: kujunduses võib
		// olla „Oma HTML" plokk, kus on <script> või <style>. Paljud serveri
		// tulemüürid (ModSecurity jt) blokeerivad sellise POST-i enne, kui see
		// WordPressini jõuab, ja salvestus katkeb 403-ga. Base64 on siin ainult
		// transpordikiht — sisu ise puhastatakse ikka WMD_Design::sanitize sees.
		$raw = '';

		if ( isset( $_POST['design_b64'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce kontrollitakse guard() sees.
			$decoded = base64_decode( (string) wp_unslash( $_POST['design_b64'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JSON puhastatakse WMD_Design::sanitize sees.

			if ( false !== $decoded ) {
				$raw = $decoded;
			}
		} elseif ( isset( $_POST['design'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce kontrollitakse guard() sees.
			$raw = wp_unslash( $_POST['design'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON puhastatakse WMD_Design::sanitize sees.
		}

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
	 * Tootekategooriad pildiploki täitmiseks: nimi, aadress ja pilt.
	 *
	 * Kategooria pilt tuleb termi meta väljalt thumbnail_id. Kui seda ei ole,
	 * jääb pilt tühjaks ja kasutaja valib selle ise meediateegist.
	 */
	public static function categories() {
		self::guard();

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			wp_send_json_success( array( 'items' => array() ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) ) {
			wp_send_json_success( array( 'items' => array() ) );
		}

		$items = array();

		foreach ( $terms as $term ) {
			$link  = get_term_link( $term );
			$thumb = get_term_meta( $term->term_id, 'thumbnail_id', true );

			// WooCommerce'i enda pisipilt on juba ühesuuruseks lõigatud, seega
			// eelistame seda; muidu võtame keskmise ja lõikame renderdusel.
			$image = '';

			if ( $thumb ) {
				$image = wp_get_attachment_image_url( (int) $thumb, 'woocommerce_thumbnail' );

				if ( ! $image ) {
					$image = wp_get_attachment_image_url( (int) $thumb, 'medium' );
				}
			}

			$items[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'url'   => is_wp_error( $link ) ? '' : $link,
				'image' => $image ? $image : '',
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
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

		$was = self::switch_lang( self::posted_lang() );

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

		$order = self::preview_order( $order_id, $email_id );

		if ( ! $html ) {
			// Sama loogika mis testmeilil: WooCommerce'i sisuosa päriselt, mitte
			// näidisena, kui meil ei ole täisrežiimis.
			$body = null;

			if ( wmd_woo_active() && ! WMD_Design::is_full( $email_id ) ) {
				$part = self::wc_content( $email_id, $order, true );
				$body = '' !== $part['html'] ? $part['html'] : null;
			}

			$html = WMD_Render::full( $email_id, self::design_context( $email_id, $order ), $body );
		}

		self::restore_lang( $was );

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

		// Keel enne kujundust: set_cache ehitab kihid valitud keele järgi.
		$was = self::switch_lang( self::posted_lang() );

		$design = self::posted_design();
		if ( ! empty( $design ) ) {
			WMD_Design::set_cache( WMD_Design::sanitize( $design ) );
		}

		$order = self::preview_order( self::posted_order_id(), $email_id );

		// Kontomeilidel (uus konto, parooli lähtestamine) ei olegi tellimust,
		// aga WooCommerce'i sisu on neil ikka olemas — seega renderdame edasi.
		if ( ! wmd_woo_active() ) {
			self::restore_lang( $was );

			wp_send_json_success(
				array(
					'html' => '',
					'css'  => '',
					'why'  => __( 'WooCommerce is not active.', 'wonom-meilidisainer' ),
				)
			);
		}

		// Täisrežiimis ei kasutata WooCommerce'i sisuosa üldse, seega ei ole
		// mõtet meili renderdada. Tellimuse read ja väljad on ikka vaja.
		$needs_html = ! WMD_Design::is_full( $email_id );

		$part  = self::wc_content( $email_id, $order, $needs_html );
		$html  = $part['html'];
		$css   = $part['css'];
		$found = $part['email'];

		// Kogu ülejäänu, mida kujundaja vajab, et mitte näidata näidisandmeid:
		// märgendite väärtused ja WooCommerce'i osad päris tellimuse pealt.
		// Kui tellimust ei ole (kontomeilid või tühi pood), saadame tellimuse
		// märgendid tühjana — muidu jääks kanvasele näidistellimus.
		$ctx = $order ? WMD_Tags::order_context( $order ) : WMD_Tags::orderless_context();

		// Kontomeilidel tulevad väärtused kasutaja ja lähtestusvõtme pealt.
		// prepare_account_email() on need meiliobjektile juba täitnud.
		$ctx = WMD_Tags::account_context( $found, $ctx );

		$ctx['__email']         = $found;
		$ctx['__sent_to_admin'] = false;

		$parts = array();

		if ( $order ) {
			try {
				$parts = array(
					'order_table'  => WMD_Render::woo_part( 'order_table', $ctx ),
					'payment_info' => WMD_Render::woo_part( 'payment_info', $ctx ),
					'additional'   => self::additional_content( $email_id, $order ),
				);
			} catch ( Throwable $e ) {
				$parts = array();
			}
		}

		self::restore_lang( $was );

		wp_send_json_success(
			array(
				'html'   => $html,
				'css'    => $css,
				'order'  => $order ? $order->get_id() : 0,
				'items'  => $order ? WMD_Render::order_items_data( $order ) : array(),
				'totals' => $order ? WMD_Render::order_totals_data( $order ) : array(),
				'fields' => $order ? self::order_fields( $order ) : array(),
				'ctx'    => array_filter( $ctx, 'is_scalar' ),
				'parts'  => $parts,
				'addr'   => $order ? WMD_Render::address_data( $order ) : null,
				'why'    => ( $needs_html && '' === $html ) ? __( 'WooCommerce content could not be rendered.', 'wonom-meilidisainer' ) : '',
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

		$login   = '';
		$user_id = 0;

		if ( $order && $order->get_customer_id() ) {
			$user    = get_userdata( $order->get_customer_id() );
			$login   = $user ? $user->user_login : '';
			$user_id = $user ? (int) $user->ID : 0;
		}

		if ( '' === $login && $order ) {
			$login = $order->get_billing_email();
		}

		if ( '' === $login ) {
			$current = wp_get_current_user();
			$login   = $current->user_login;
			$user_id = (int) $current->ID;
		}

		$email->user_login = $login;

		// Ilma kasutaja id-ta ei saa parooli lähtestamise linki kokku panna ja
		// eelvaates jääks {{reset_password_url}} tühjaks.
		if ( property_exists( $email, 'user_id' ) && ! $email->user_id ) {
			$email->user_id = $user_id;
		}

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
	 * WooCommerce'i enda sisuosa ja stiilid.
	 *
	 * Sama tükk, mille kujundaja kanvasele paneb ja mille testmeil kirja sisse
	 * paneb — nii ei saa need kaks teineteisest lahku minna. Sisu lõigatakse
	 * markerite vahelt, mille WMD_Render päisesse ja jalusesse paneb.
	 *
	 * @param string        $email_id   WC_Email id.
	 * @param WC_Order|null $order      Tellimus või null.
	 * @param bool          $needs_html Kas sisuosa on üldse vaja.
	 * @return array{html:string,css:string,email:WC_Email|null}
	 */
	protected static function wc_content( $email_id, $order, $needs_html = true ) {
		$html  = '';
		$css   = '';
		$found = self::find_email( $email_id );

		WMD_Render::$mark_wc = true;

		try {
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

		return array(
			'html'  => $html,
			'css'   => $css,
			'email' => $found,
		);
	}

	/**
	 * Kontekst, kui kirja renderdab kujundus (eelvaade või testmeil ilma
	 * WooCommerce'i enda meilita).
	 *
	 * Kontomeilidel täidame ka kasutaja ja parooli lähtestamise märgendid —
	 * muidu jääks eelvaates ja testmeilis link tühjaks.
	 *
	 * @param string        $email_id WC_Email id.
	 * @param WC_Order|null $order    Tellimus või null.
	 * @return array
	 */
	protected static function design_context( $email_id, $order ) {
		if ( $order ) {
			$ctx = WMD_Tags::order_context( $order );
		} elseif ( wmd_email_uses_order( $email_id ) ) {
			$ctx = WMD_Tags::sample_context();
		} else {
			$ctx = WMD_Tags::orderless_context();
		}

		$found = self::find_email( $email_id );

		if ( $found ) {
			self::prepare_account_email( $found, $order );

			$ctx            = WMD_Tags::account_context( $found, $ctx );
			$ctx['__email'] = $found;
		}

		return $ctx;
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
		$order = self::preview_order( $order_id, $email_id );

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
	 * Kontomeilidele (uus konto, parooli lähtestamine) ei anta tellimust üldse:
	 * neis ei ole tellimuse infot ja võõra tellimuse pealt võetud nimi oleks
	 * lihtsalt eksitav.
	 *
	 * @param int    $order_id Soovitud tellimus või 0.
	 * @param string $email_id Meil, mille jaoks tellimust küsitakse.
	 * @return WC_Order|null
	 */
	protected static function preview_order( $order_id = 0, $email_id = '' ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		if ( '' !== $email_id && ! wmd_email_uses_order( $email_id ) ) {
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
	 * Päringus soovitud keel.
	 *
	 * @return string Tühi string, kui keelt ei antud või see pole poes olemas.
	 */
	protected static function posted_lang() {
		$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce kontrollitakse guard() sees.

		return isset( wmd_languages()[ $lang ] ) ? $lang : '';
	}

	/**
	 * Lülitab nii kujunduse kui poe enda keele.
	 *
	 * Poe keel on vaja vahetada selleks, et WooCommerce'i enda sisu (tellimuse
	 * tabel, aadressid, malli tekstid) tuleks samas keeles, mida kujundajas
	 * vaatad. Ilma selleta näeksid eestikeelset tabelit ingliskeelse teksti all.
	 *
	 * @param string $lang Keel või tühi.
	 * @return array Eelmine olek, mille restore_lang() tagasi paneb.
	 */
	protected static function switch_lang( $lang ) {
		$was = array(
			'design' => WMD_Design::set_lang( $lang ),
			'site'   => null,
		);

		if ( '' === $lang ) {
			return $was;
		}

		$current = apply_filters( 'wpml_current_language', null );

		if ( is_string( $current ) && $current !== $lang ) {
			$was['site'] = $current;
			do_action( 'wpml_switch_language', $lang );
		}

		return $was;
	}

	/**
	 * Paneb keeled tagasi nii, nagu nad enne olid.
	 *
	 * @param array $was switch_lang() tagastus.
	 */
	protected static function restore_lang( $was ) {
		WMD_Design::set_lang( $was['design'] );

		if ( null !== $was['site'] ) {
			do_action( 'wpml_switch_language', $was['site'] );
		}
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
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'wonom-meilidisainer' ) ) );
		}

		if ( ! isset( $list[ $email_id ] ) ) {
			$email_id = key( $list );
		}

		// Testmeil saadetakse alati salvestatud kujundusega, et vältida üllatusi.
		$design = self::posted_design();
		if ( ! empty( $design ) ) {
			WMD_Design::save( $design );
		}

		// Testmeil läheb selles keeles, mida kujundajas parasjagu vaatad.
		$was   = self::switch_lang( self::posted_lang() );
		$order = self::preview_order( self::posted_order_id(), $email_id );
		$sent  = false;
		$mode  = 'design';

		if ( $order && wmd_woo_active() ) {
			$sent = self::send_real( $email_id, $order, $to );
			$mode = $sent ? 'real' : 'design';
		}

		if ( ! $sent ) {
			// Kontomeilid ja tellimuseta pood käivad siit läbi. Sisuosa toome
			// WooCommerce'ilt, mitte näidisena — muidu tuleks postkasti tellimuse
			// tabel kirja, kus tellimust ei olegi.
			$body = null;

			if ( wmd_woo_active() && ! WMD_Design::is_full( $email_id ) ) {
				$part = self::wc_content( $email_id, $order, true );
				$body = '' !== $part['html'] ? $part['html'] : null;
			}

			$html    = WMD_Render::full( $email_id, self::design_context( $email_id, $order ), $body );
			$subject = sprintf(
				/* translators: %s: meili nimi. */
				__( '[TEST] %s', 'wonom-meilidisainer' ),
				$list[ $email_id ]['label']
			);

			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$sent    = wp_mail( $to, $subject, $html, $headers );
		}

		self::restore_lang( $was );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Sending the email failed. Check the shop\'s email settings.', 'wonom-meilidisainer' ) ) );
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
		// Konto meilid ei käivitu tellimuse pealt — need saadame kujundusega.
		if ( ! $order || ! wmd_email_uses_order( $email_id ) ) {
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
