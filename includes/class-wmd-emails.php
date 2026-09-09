<?php
/**
 * WooCommerce'i meilide ülevõtmine: päise-/jalusemall, stiilid, teemad.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ühendus WooCommerce'i meilimootoriga.
 */
class WMD_Emails {

	/**
	 * Parasjagu renderdatav meil.
	 *
	 * WooCommerce ei anna päise- ja jalusemallile meiliobjekti kaasa —
	 * WC_Emails::email_header() saadab mallile ainult pealkirja ja poe nime.
	 * Küll aga käivitatakse enne seda tegevus woocommerce_email_header, kuhu
	 * meiliobjekt kaasa antakse. Sealt me selle kinni püüamegi.
	 *
	 * @var WC_Email|null
	 */
	protected static $current = null;

	/**
	 * Haagid.
	 */
	public static function init() {
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 20, 3 );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'styles' ), 20, 2 );

		// Prioriteet 1, et jõuaksime enne WC_Emails oma malli renderdust (10).
		add_action( 'woocommerce_email_header', array( __CLASS__, 'capture' ), 1, 2 );
		add_action( 'woocommerce_email_footer', array( __CLASS__, 'capture' ), 1, 1 );
		add_action( 'woocommerce_email_footer', array( __CLASS__, 'release' ), 999 );

		foreach ( array_keys( wmd_email_list() ) as $id ) {
			add_filter( 'woocommerce_email_subject_' . $id, array( __CLASS__, 'subject' ), 20, 3 );
		}
	}

	/**
	 * Jätab meiliobjekti meelde.
	 *
	 * Päises on esimene argument pealkiri ja teine meil, jaluses on meil ainuke
	 * argument — seepärast vaatame mõlemat.
	 *
	 * @param mixed $first  Pealkiri või meil.
	 * @param mixed $second Meil, kui esimene oli pealkiri.
	 */
	public static function capture( $first = null, $second = null ) {
		if ( is_a( $second, 'WC_Email' ) ) {
			self::$current = $second;
			return;
		}

		if ( is_a( $first, 'WC_Email' ) ) {
			self::$current = $first;
		}
	}

	/**
	 * Unustab meili, kui kiri on valmis.
	 */
	public static function release() {
		self::$current = null;
	}

	/**
	 * Parasjagu renderdatav meil.
	 *
	 * @return WC_Email|null
	 */
	public static function current() {
		return self::$current;
	}

	/**
	 * Parasjagu renderdatava meili id.
	 *
	 * @return string Tühi string, kui meili ei õnnestunud tuvastada.
	 */
	public static function current_id() {
		$email = self::current();

		return ( $email && ! empty( $email->id ) ) ? (string) $email->id : '';
	}

	/**
	 * Kas kujundus on sisse lülitatud.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) get_option( 'wmd_enabled', 1 );
	}

	/**
	 * Suuname päise ja jaluse malli enda omale.
	 *
	 * @param string $template      Leitud tee.
	 * @param string $template_name Malli nimi.
	 * @param string $template_path Teema alamkaust.
	 * @return string
	 */
	public static function locate_template( $template, $template_name, $template_path ) {
		if ( ! self::enabled() ) {
			return $template;
		}

		$ours = array(
			'emails/email-header.php' => WMD_DIR . 'templates/emails/email-header.php',
			'emails/email-footer.php' => WMD_DIR . 'templates/emails/email-footer.php',
		);

		// Sisumall võetakse üle ainult nendel meilidel, kus kasutaja on valinud
		// „terve meil ise" ja kehas on vähemalt üks plokk.
		if ( ! isset( $ours[ $template_name ] ) ) {
			$email_id = self::email_by_template( $template_name );

			if ( '' === $email_id || ! WMD_Design::is_full( $email_id ) ) {
				return $template;
			}

			$ours[ $template_name ] = WMD_DIR . 'templates/emails/wmd-body.php';
		}

		// Kui teema on malli ise üle kirjutanud, jätame teema oma alles.
		$theme_override = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);

		if ( $theme_override ) {
			return $template;
		}

		return $ours[ $template_name ];
	}

	/**
	 * Millisele meilile see WooCommerce'i sisumall kuulub.
	 *
	 * @param string $template_name Malli nimi.
	 * @return string Tühi string, kui see pole meili sisumall.
	 */
	protected static function email_by_template( $template_name ) {
		static $map = null;

		if ( null === $map ) {
			$map = array();
			foreach ( wmd_email_list() as $id => $meta ) {
				if ( ! empty( $meta['template'] ) ) {
					$map[ $meta['template'] ] = $id;
				}
			}
		}

		return isset( $map[ $template_name ] ) ? $map[ $template_name ] : '';
	}

	/**
	 * Lisame brändi CSS-i WooCommerce'i vaikestiilide järele.
	 *
	 * @param string   $css   Olemasolev CSS.
	 * @param WC_Email $email Meil.
	 * @return string
	 */
	public static function styles( $css, $email = null ) {
		if ( ! self::enabled() ) {
			return $css;
		}

		return $css . "\n" . WMD_Render::email_css();
	}

	/**
	 * Meili teema ülekirjutus, kui kujundajas on see täidetud.
	 *
	 * @param string   $subject Vaiketeema.
	 * @param mixed    $object  Tellimus või muu objekt.
	 * @param WC_Email $email   Meil.
	 * @return string
	 */
	public static function subject( $subject, $object = null, $email = null ) {
		if ( ! self::enabled() || ! $email || empty( $email->id ) ) {
			return $subject;
		}

		$settings = WMD_Design::email( $email->id );
		$custom   = trim( (string) $settings['subject'] );

		if ( '' === $custom ) {
			return $subject;
		}

		$order = is_a( $object, 'WC_Order' ) ? $object : null;
		$ctx   = WMD_Tags::order_context( $order );

		return wp_strip_all_tags( WMD_Tags::replace( $custom, $ctx ) );
	}

	/**
	 * Kontekst meiliobjektist.
	 *
	 * @param WC_Email|null $email Meil.
	 * @return array
	 */
	public static function context_for( $email = null ) {
		$order = null;

		if ( $email && isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
			$order = $email->object;
		}

		$ctx = WMD_Tags::order_context( $order );

		// Kontomeilidel pole tellimust, aga kliendi nimi võib objektil olla.
		if ( ! $order && $email && isset( $email->user_login ) ) {
			$ctx['customer_first_name'] = isset( $email->user_login ) ? (string) $email->user_login : '';
		}

		// WooCommerce'i plokid ja {{meta:...}} vajavad objekte endid.
		$ctx['__order'] = $order;
		$ctx['__email'] = $email;

		return $ctx;
	}
}
