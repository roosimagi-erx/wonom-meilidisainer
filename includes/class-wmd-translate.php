<?php
/**
 * Masintõlge: täidab keelekihi ette ära, et kasutaja saaks seda parandada.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tõlketeenuse ühendus.
 *
 * Praegu on üks teenus — DeepL. Valik on tehtud sellepärast, et ainsana
 * oskab ta jätta puutumata kohad, mida ei tohi tõlkida: märgendi
 * translate="no" sees olev tekst tuleb tagasi muutmata. Ilma selleta
 * tõlgiks masin ka {{customer_first_name}} ära ja kiri läheks katki.
 */
class WMD_Translate {

	/**
	 * Meie keelekoodid DeepL-i omadeks.
	 *
	 * DeepL tahab sihtkeelena inglise puhul murret. Kui koodi siin ei ole,
	 * saadame selle suurtähtedega — enamik kahetähelisi koode sobib nii.
	 *
	 * @var array<string,string>
	 */
	const TARGETS = array(
		'en' => 'EN-GB',
		'pt' => 'PT-PT',
		'zh' => 'ZH',
	);

	/**
	 * Seaded kujundaja jaoks. Võtit ennast välja ei anta.
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'provider' => self::provider(),
			'hasKey'   => '' !== self::key(),
		);
	}

	/**
	 * Valitud teenus.
	 *
	 * @return string off | deepl
	 */
	public static function provider() {
		return 'deepl' === get_option( 'wmd_mt_provider', 'off' ) ? 'deepl' : 'off';
	}

	/**
	 * API võti.
	 *
	 * @return string
	 */
	private static function key() {
		return trim( (string) get_option( 'wmd_mt_key', '' ) );
	}

	/**
	 * Kas tõlkimine on kasutatav.
	 *
	 * @return bool
	 */
	public static function ready() {
		return 'off' !== self::provider() && '' !== self::key();
	}

	/**
	 * Seadete salvestus.
	 *
	 * @param array $input Toores sisend.
	 * @return array Uus olek kujundaja jaoks.
	 */
	public static function save_settings( $input ) {
		$provider = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : 'off';

		update_option( 'wmd_mt_provider', 'deepl' === $provider ? 'deepl' : 'off' );

		// Tähekestest koosnev väärtus tähendab, et võtit ei muudetud.
		$key = isset( $input['key'] ) ? trim( (string) $input['key'] ) : '';

		if ( '********' !== $key ) {
			update_option( 'wmd_mt_key', sanitize_text_field( $key ) );
		}

		return self::settings();
	}

	/**
	 * Tõlgib tekstid.
	 *
	 * @param array  $texts  Tekstid.
	 * @param string $target Sihtkeel (meie kood, nt „en").
	 * @param string $source Lähtekeel või tühi.
	 * @return array|WP_Error Tõlked samas järjekorras.
	 */
	public static function translate( $texts, $target, $source = '' ) {
		if ( ! self::ready() ) {
			return new WP_Error( 'wmd_mt_off', __( 'Automatic translation is not set up. Add a DeepL key under Settings.', 'wonom-meilidisainer' ) );
		}

		$texts = array_values( array_filter( array_map( 'strval', (array) $texts ), 'strlen' ) );

		if ( empty( $texts ) ) {
			return array();
		}

		// DeepL võtab ühe päringuga kuni 50 teksti.
		$out = array();

		foreach ( array_chunk( $texts, 50 ) as $chunk ) {
			$done = self::deepl( $chunk, $target, $source );

			if ( is_wp_error( $done ) ) {
				return $done;
			}

			$out = array_merge( $out, $done );
		}

		return $out;
	}

	/**
	 * Üks DeepL-i päring.
	 *
	 * @param array  $texts  Kuni 50 teksti.
	 * @param string $target Sihtkeel.
	 * @param string $source Lähtekeel.
	 * @return array|WP_Error
	 */
	private static function deepl( $texts, $target, $source ) {
		$key = self::key();

		// Tasuta võti lõpeb :fx ja käib teise aadressi pealt.
		$host = ':fx' === substr( $key, -3 ) ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

		$body = array(
			'target_lang'  => self::lang_code( $target ),
			'tag_handling' => 'html',
		);

		if ( '' !== $source ) {
			$body['source_lang'] = self::lang_code( $source, true );
		}

		// Muutujad pakitakse märgendisse, mida DeepL ei tõlgi.
		$prepared = array();

		foreach ( $texts as $text ) {
			$prepared[] = preg_replace( '/\{\{[^}]+\}\}/', '<span translate="no">$0</span>', $text );
		}

		$body['text'] = $prepared;

		$response = wp_remote_post(
			$host . '/v2/translate',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error( 'wmd_mt_http', self::http_message( $code ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data['translations'] ) || ! is_array( $data['translations'] ) ) {
			return new WP_Error( 'wmd_mt_body', __( 'The translation service sent an answer we could not read.', 'wonom-meilidisainer' ) );
		}

		$out = array();

		foreach ( $data['translations'] as $i => $row ) {
			$text = isset( $row['text'] ) ? (string) $row['text'] : '';

			// Muutujate ümbert märgend maha.
			$text = preg_replace( '#<span[^>]*translate=["\']?no["\']?[^>]*>(.*?)</span>#is', '$1', $text );

			// Kui lähtetekstis ei olnud märgendeid, ei tohi neid ka vastuses
			// olla — html-režiim muudab & ja < olemiteks.
			if ( isset( $texts[ $i ] ) && false === strpos( $texts[ $i ], '<' ) ) {
				$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}

			$out[] = $text;
		}

		return $out;
	}

	/**
	 * Selgitus HTTP-koodi kohta, et kasutaja teaks, mida parandada.
	 *
	 * @param int $code Kood.
	 * @return string
	 */
	private static function http_message( $code ) {
		if ( 403 === $code ) {
			return __( 'DeepL refused the key. Check that you copied it whole.', 'wonom-meilidisainer' );
		}

		if ( 456 === $code ) {
			return __( 'The DeepL quota for this month is used up.', 'wonom-meilidisainer' );
		}

		if ( 429 === $code ) {
			return __( 'DeepL asked us to slow down. Try again in a moment.', 'wonom-meilidisainer' );
		}

		/* translators: %d: HTTP status code. */
		return sprintf( __( 'The translation service answered with an error (HTTP %d).', 'wonom-meilidisainer' ), $code );
	}

	/**
	 * Keelekood DeepL-i kujul.
	 *
	 * @param string $lang   Meie kood.
	 * @param bool   $source Kas see on lähtekeel (seal ei tohi murret olla).
	 * @return string
	 */
	private static function lang_code( $lang, $source = false ) {
		$lang = strtolower( substr( (string) $lang, 0, 5 ) );

		if ( $source ) {
			return strtoupper( substr( $lang, 0, 2 ) );
		}

		return isset( self::TARGETS[ $lang ] ) ? self::TARGETS[ $lang ] : strtoupper( substr( $lang, 0, 2 ) );
	}
}
