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
			add_filter( 'woocommerce_email_additional_content_' . $id, array( __CLASS__, 'additional_content' ), 20, 3 );
		}
	}

	/**
	 * WooCommerce'i „Lisatekst" kirja lõpus.
	 *
	 * WooCommerce'i seadetes ei saa seda välja tühjendada: WC_Settings_API
	 * asendab tühja väärtuse vaikeväärtusega, seega vaiketekst tuleb tagasi.
	 * Ainus koht, kus selle päriselt välja lülitada saab, on siin.
	 *
	 * @param string   $content Lisatekst.
	 * @param mixed    $object  Tellimus või muu objekt.
	 * @param WC_Email $email   Meil.
	 * @return string
	 */
	public static function additional_content( $content, $object = null, $email = null ) {
		try {
			return self::build_additional_content( $content, $object, $email );
		} catch ( Throwable $e ) {
			// Turvavõrk: vea korral jääb WooCommerce'i enda tekst.
			self::log_error( $e, 'additional content', $email );

			return $content;
		}
	}

	/**
	 * Lisateksti otsus. Eraldi meetod, et additional_content() saaks vea kinni
	 * püüda ilma loogikat muutmata.
	 *
	 * @param string   $content Lisatekst.
	 * @param mixed    $object  Tellimus või muu objekt.
	 * @param WC_Email $email   Meil.
	 * @return string
	 */
	protected static function build_additional_content( $content, $object = null, $email = null ) {
		if ( ! $email || empty( $email->id ) || ! self::enabled_for( $email->id ) ) {
			return $content;
		}

		self::use_email_language( $email );

		$settings = WMD_Design::email( $email->id );

		return empty( $settings['additional'] ) ? '' : $content;
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
		} elseif ( is_a( $first, 'WC_Email' ) ) {
			self::$current = $first;
		}

		if ( self::$current ) {
			try {
				self::use_email_language( self::$current );
			} catch ( Throwable $e ) {
				// Keele valik ei tohi kirja saatmist katkestada: vea korral
				// jääb vaikekeele kujundus.
				self::log_error( $e, 'language', self::$current );
			}
		}
	}

	/**
	 * Lülitab kujunduse selle kirja keelde.
	 *
	 * Mitmekeelses poes saadab WooCommerce Multilingual tellimusmeili tellimuse
	 * keeles. Kujunduse sisu — pealkirjad, plokid, makseviiside juhised — on
	 * meie oma ja WCML seda ei tea, seega valime keelekihi siin ise.
	 *
	 * @param WC_Email $email Meil.
	 */
	protected static function use_email_language( $email ) {
		// Kujundajast valitud keel võidab: seal tahetakse näha just seda keelt,
		// mitte seda, mis keeles eelvaate tellimus juhtus tehtud olema.
		if ( WMD_Design::lang_locked() ) {
			return;
		}

		$order = ( isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) ? $email->object : null;

		WMD_Design::set_lang( wmd_email_language( $order ) );
	}

	/**
	 * Unustab meili, kui kiri on valmis.
	 */
	public static function release() {
		self::$current = null;

		// Lukustatud keel jääb alles — selle paneb tagasi see, kes lukustas.
		if ( ! WMD_Design::lang_locked() ) {
			WMD_Design::set_lang( '' );
		}
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
	 * Kas kujundus surutakse peale hoolimata lülititest.
	 *
	 * @var bool
	 */
	protected static $forced = false;

	/**
	 * Lülitab ülevõtmise korraks sunniviisiliselt sisse.
	 *
	 * Kujundajas peab eelvaade ja testmeil näitama seda kujundust, mida sa
	 * parasjagu ehitad — ka siis, kui kirja lüliti on veel väljas. Ilma selleta
	 * ei jõua meie päis ja jalus renderdusse ning koos nendega jäävad tulemata
	 * markerid, mille järgi me WooCommerce'i sisuosa üles leiame.
	 *
	 * @param bool $on Kas sundida.
	 * @return bool Eelmine olek, mille saab hiljem tagasi anda.
	 */
	public static function force( $on ) {
		$was = self::$forced;

		self::$forced = (bool) $on;

		return $was;
	}

	/**
	 * Kas kujundus on sisse lülitatud.
	 *
	 * @return bool
	 */
	public static function enabled() {
		// Vaikemalliga uuesti koostamise ajal ei tohi meie mallid vahele tulla.
		if ( self::$bypass ) {
			return false;
		}

		if ( self::$forced ) {
			return true;
		}

		return (bool) get_option( 'wmd_enabled', 1 );
	}

	/**
	 * Kirjad, mille koostamine ebaõnnestus: objekti id => WC_Email.
	 *
	 * @var array<int,mixed>
	 */
	protected static $failed = array();

	/**
	 * Kiri, mida WooCommerce parasjagu saadab. Jääb meelde stiilide küsimisel,
	 * sest saatmise lõpus olev sisufilter kirjaobjekti kaasa ei anna.
	 *
	 * @var WC_Email|null
	 */
	protected static $sending = null;

	/**
	 * Kas kirja koostatakse parasjagu uuesti WooCommerce'i vaikemalliga.
	 *
	 * @var bool
	 */
	protected static $bypass = false;

	/**
	 * Kas päästefilter on juba lisatud. Lisame selle alles esimese vea korral,
	 * et korras kirja teel ei oleks ühtegi lisahaaki.
	 *
	 * @var bool
	 */
	protected static $rescue_hooked = false;

	/**
	 * Võti kirjaobjekti järgi. Ilma objektita on võti 0.
	 *
	 * @param mixed $email Meil.
	 * @return int
	 */
	protected static function key( $email ) {
		return is_object( $email ) ? spl_object_id( $email ) : 0;
	}

	/**
	 * Kas selle kirja koostamine on juba ebaõnnestunud.
	 *
	 * @param mixed $email Meil.
	 * @return bool
	 */
	public static function has_failed( $email ) {
		return isset( self::$failed[ self::key( $email ) ] );
	}

	/**
	 * Turvavõrk kirja osa koostamise ümber.
	 *
	 * Korras kirja puhul annab see tagasi täpselt selle, mille tagasikutse
	 * annab — midagi ei lisata ega muudeta. Kui tagasikutse viskab vea, jätame
	 * selle kirja meelde, logime vea ja anname vaikeväärtuse. Kiri koostatakse
	 * siis saatmise lõpus uuesti WooCommerce'i vaikemalliga (vt rescue()), nii
	 * et klient saab kirja ikka kätte ja tellimuse töötlus ei katke.
	 *
	 * @param string   $where    Mis osa: header, body või footer.
	 * @param mixed    $email    Meil.
	 * @param callable $callback Koostaja.
	 * @param mixed    $default  Mida anda vea korral.
	 * @return mixed
	 */
	public static function guard( $where, $email, $callback, $default = '' ) {
		// Sama kirja ülejäänud osi ei ole enam mõtet koostada.
		if ( self::has_failed( $email ) ) {
			return $default;
		}

		$level = ob_get_level();

		try {
			/**
			 * Käivitub enne kirja osa koostamist.
			 *
			 * @param string $where Mis osa.
			 * @param mixed  $email Meil.
			 */
			do_action( 'wmd_render_part', $where, $email );

			return call_user_func( $callback );
		} catch ( Throwable $e ) {
			// Poolikuks jäänud väljundipuhvrid kinni, muidu satuks nende sisu
			// valesse kohta.
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			self::fail( $e, $where, $email );

			return $default;
		}
	}

	/**
	 * Märgib kirja ebaõnnestunuks ja paneb päästefiltri valmis.
	 *
	 * @param Throwable $e     Viga.
	 * @param string    $where Mis osa.
	 * @param mixed     $email Meil.
	 */
	public static function fail( $e, $where, $email = null ) {
		self::$failed[ self::key( $email ) ] = is_object( $email ) ? $email : true;

		if ( ! self::$rescue_hooked ) {
			add_filter( 'woocommerce_mail_content', array( __CLASS__, 'rescue' ), 1 );
			self::$rescue_hooked = true;
		}

		self::log_error( $e, $where, $email );
	}

	/**
	 * Koostab ebaõnnestunud kirja uuesti WooCommerce'i vaikemalliga.
	 *
	 * Käivitub WC_Email::send() sees vahetult enne saatmist ja ainult siis,
	 * kui selle kirja koostamisel oli viga.
	 *
	 * @param string $content Kirja sisu.
	 * @return string
	 */
	public static function rescue( $content ) {
		$email         = self::$sending;
		self::$sending = null;

		if ( ! is_a( $email, 'WC_Email' ) || ! self::has_failed( $email ) ) {
			return $content;
		}

		unset( self::$failed[ self::key( $email ) ] );

		$was          = self::$bypass;
		$level        = ob_get_level();
		$html         = '';
		self::$bypass = true;

		try {
			$html = (string) $email->style_inline( $email->get_content_html() );
		} catch ( Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			self::log_error( $e, 'default template', $email );
		}

		self::$bypass  = $was;
		self::$sending = null;

		return '' !== trim( $html ) ? $html : $content;
	}

	/**
	 * Kirjutab vea WooCommerce'i logisse (WooCommerce → Olek → Logid, allikas
	 * „wonom-meilidisainer").
	 *
	 * @param Throwable $e     Viga.
	 * @param string    $where Mis osa.
	 * @param mixed     $email Meil.
	 */
	protected static function log_error( $e, $where, $email = null ) {
		$message = sprintf(
			'Email "%1$s": building the %2$s failed, falling back to WooCommerce defaults. %3$s: %4$s (%5$s:%6$d)',
			( is_object( $email ) && ! empty( $email->id ) ) ? $email->id : 'unknown',
			$where,
			get_class( $e ),
			$e->getMessage(),
			basename( $e->getFile() ),
			$e->getLine()
		);

		try {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( $message, array( 'source' => 'wonom-meilidisainer' ) );

				return;
			}
		} catch ( Throwable $ignored ) {
			// Logimine ei tohi kirja saatmist katkestada.
			unset( $ignored );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- varuvariant, kui WooCommerce'i logi ei ole.
		error_log( 'Wonom Meilidisainer: ' . $message );
	}

	/**
	 * Kas kujundus rakendub just sellele kirjale.
	 *
	 * Tühi id tähendab, et me ei tea, mis kirja renderdatakse — siis otsustab
	 * ainult peakraan. Nii ei jää miski juhuslikult kujunduseta.
	 *
	 * @param string $email_id WC_Email id.
	 * @return bool
	 */
	public static function enabled_for( $email_id ) {
		if ( self::$bypass ) {
			return false;
		}

		if ( self::$forced ) {
			return true;
		}

		if ( ! self::enabled() ) {
			return false;
		}

		return '' === $email_id || WMD_Design::email_enabled( $email_id );
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
		try {
			return self::resolve_template( $template, $template_name, $template_path );
		} catch ( Throwable $e ) {
			// Turvavõrk: vea korral jääb WooCommerce'i leitud mall.
			self::log_error( $e, 'template lookup', self::current() );

			return $template;
		}
	}

	/**
	 * Malli valik. Eraldi meetod, et locate_template() saaks vea kinni püüda
	 * ilma loogikat muutmata.
	 *
	 * @param string $template      Leitud tee.
	 * @param string $template_name Malli nimi.
	 * @param string $template_path Teema alamkaust.
	 * @return string
	 */
	protected static function resolve_template( $template, $template_name, $template_path ) {
		// Päise ja jaluse puhul teame kirja capture() kaudu; sisumalli puhul
		// ütleb malli nimi ise, mis kirjaga on tegu.
		$for_email = self::email_by_template( $template_name );

		if ( '' === $for_email ) {
			$for_email = self::current_id();
		}

		if ( ! self::enabled_for( $for_email ) ) {
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
		// WooCommerce küsib stiile saatmise sees vahetult enne sisufiltrit —
		// siit saab rescue() teada, mis kirja parasjagu saadetakse.
		if ( is_a( $email, 'WC_Email' ) ) {
			self::$sending = $email;
		}

		try {
			$for_email = ( $email && ! empty( $email->id ) ) ? $email->id : self::current_id();

			if ( ! self::enabled_for( $for_email ) ) {
				return $css;
			}

			return $css . "\n" . WMD_Render::email_css();
		} catch ( Throwable $e ) {
			// Ilma meie stiilideta ei ole meie kujundus terve — parem kogu kiri
			// vaikemalliga.
			self::fail( $e, 'styles', $email );

			return $css;
		}
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
		try {
			return self::build_subject( $subject, $object, $email );
		} catch ( Throwable $e ) {
			// Turvavõrk: vea korral jääb WooCommerce'i enda teema.
			self::log_error( $e, 'subject', $email );

			return $subject;
		}
	}

	/**
	 * Teema koostamine. Eraldi meetod, et subject() saaks vea kinni püüda ilma
	 * loogikat muutmata.
	 *
	 * @param string   $subject Vaiketeema.
	 * @param mixed    $object  Tellimus või muu objekt.
	 * @param WC_Email $email   Meil.
	 * @return string
	 */
	protected static function build_subject( $subject, $object = null, $email = null ) {
		if ( ! $email || empty( $email->id ) || ! self::enabled_for( $email->id ) ) {
			return $subject;
		}

		// Teema renderdatakse enne kirja sisu, seega keel tuleb siin ise valida
		// — capture() ei ole veel jõudnud käia.
		self::use_email_language( $email );

		$settings = WMD_Design::email( $email->id );
		$custom   = trim( (string) $settings['subject'] );

		if ( '' === $custom ) {
			return $subject;
		}

		$order = is_a( $object, 'WC_Order' ) ? $object : null;
		$ctx   = $order ? WMD_Tags::order_context( $order ) : WMD_Tags::orderless_context();

		// Kontomeili teemas peab {{user_login}} ja {{customer_first_name}} samuti
		// töötama — seal ei ole tellimust, vaid kasutaja.
		$ctx = WMD_Tags::account_context( $email, $ctx );

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

		// Ilma tellimuseta jätame tellimuse märgendid tühjaks, mitte puudu —
		// nii ei jää kuhugi asendamata {{order_number}} rippuma.
		$ctx = $order ? WMD_Tags::order_context( $order ) : WMD_Tags::orderless_context();

		// Kontomeilidel (uus konto, parooli lähtestamine) on tellimuse asemel
		// kasutaja ja lähtestusvõti.
		$ctx = WMD_Tags::account_context( $email, $ctx );

		// WooCommerce'i plokid ja {{meta:...}} vajavad objekte endid.
		$ctx['__order'] = $order;
		$ctx['__email'] = $email;

		return $ctx;
	}
}
