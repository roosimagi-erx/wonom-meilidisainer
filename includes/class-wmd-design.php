<?php
/**
 * Kujunduse hoidla: lugemine, puhastus ja salvestus.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kujundus elab ühes optionis JSON-struktuurina.
 */
class WMD_Design {

	/**
	 * Vahemälu päringu ajaks, keele kaupa.
	 *
	 * @var array<string,array>
	 */
	protected static $cache = array();

	/**
	 * Keel, mille kujundust praegu küsitakse. Tühi string = vaikekeel.
	 *
	 * Kirja saatmisel paneb selle paika WMD_Emails::capture() tellimuse keele
	 * järgi; kujundajas tuleb see päringuga kaasa.
	 *
	 * @var string
	 */
	protected static $lang = '';

	/**
	 * Unustab vahemälu.
	 *
	 * Vaja siis, kui salvestatud kujundus muutub keset päringut — nt import
	 * või mõne teise plugina tehtud update_option.
	 */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Kas keel on kinni pandud.
	 *
	 * Kujundajas valitud keel peab võitma tellimuse enda keele: kui vaatad
	 * inglise keelt ja saadad testmeili, tahad näha ingliskeelset kirja, mitte
	 * seda, mis keeles see tellimus juhtus tehtud olema.
	 *
	 * @var bool
	 */
	protected static $locked = false;

	/**
	 * Seab keele ja annab eelmise tagasi, et selle saaks pärast taastada.
	 *
	 * @param string $lang   Keele kood või tühi.
	 * @param bool   $locked Kas see keel võidab tellimuse oma.
	 * @return array Eelmine olek, mille set_lang() tagasi võtab.
	 */
	public static function set_lang( $lang, $locked = false ) {
		$was = array( self::$lang, self::$locked );

		// Tagasipanek: anname sama massiivi, mille varem saime.
		if ( is_array( $lang ) ) {
			self::$lang   = $lang[0];
			self::$locked = $lang[1];

			return $was;
		}

		self::$lang   = is_string( $lang ) ? $lang : '';
		self::$locked = (bool) $locked && '' !== self::$lang;

		return $was;
	}

	/**
	 * Kas keel on kinni pandud ja seda ei tohi tellimuse omaga üle kirjutada.
	 *
	 * @return bool
	 */
	public static function lang_locked() {
		return self::$locked;
	}

	/**
	 * Praegu valitud keel.
	 *
	 * @return string
	 */
	public static function lang() {
		return self::$lang;
	}

	/**
	 * Terve kujundus, vaikeväärtustega täidetud ja keelekihiga kaetud.
	 *
	 * @return array
	 */
	public static function get() {
		$lang = self::$lang;

		if ( isset( self::$cache[ $lang ] ) ) {
			return self::$cache[ $lang ];
		}

		$stored = get_option( WMD_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache[ $lang ] = self::merge_lang( self::fill( $stored ), $lang );

		return self::$cache[ $lang ];
	}

	/**
	 * Paneb keelekihi vaikekeele kujunduse peale.
	 *
	 * Reegel: kihis olev väärtus võidab, kui see on olemas ja mittetühi. Plokid
	 * asendatakse tervikuna, mitte ploki kaupa — nii on ennustatav, et „inglise
	 * keeles on jaluses need plokid", mitte segu kahest keelest.
	 *
	 * Bränd ei ole keelepõhine: värvid, logo ja paigutus on kõigis keeltes
	 * samad. Ka režiim ja WooCommerce'i lisateksti lüliti jäävad ühiseks, sest
	 * need on kirja ehitus, mitte sisu.
	 *
	 * @param array  $design Vaikekeele kujundus.
	 * @param string $lang   Keel või tühi.
	 * @return array
	 */
	protected static function merge_lang( $design, $lang ) {
		if ( '' === $lang || $lang === wmd_default_language() ) {
			return $design;
		}

		$layer = isset( $design['i18n'][ $lang ] ) && is_array( $design['i18n'][ $lang ] )
			? $design['i18n'][ $lang ]
			: array();

		if ( ! $layer ) {
			return $design;
		}

		foreach ( array( 'header', 'footer' ) as $section ) {
			if ( ! empty( $layer[ $section ] ) && is_array( $layer[ $section ] ) ) {
				$design[ $section ] = $layer[ $section ];
			}
		}

		// Makseviisid ühekaupa: tõlkimata jäänud juhis tuleb vaikekeelest, et
		// klient ei saaks kirja, kus makse juhised on lihtsalt puudu.
		if ( ! empty( $layer['payments'] ) && is_array( $layer['payments'] ) ) {
			foreach ( $layer['payments'] as $gateway => $note ) {
				if ( '' !== trim( wp_strip_all_tags( (string) $note ) ) ) {
					$design['payments'][ $gateway ] = $note;
				}
			}
		}

		if ( empty( $layer['emails'] ) || ! is_array( $layer['emails'] ) ) {
			return $design;
		}

		foreach ( $layer['emails'] as $id => $over ) {
			if ( ! isset( $design['emails'][ $id ] ) || ! is_array( $over ) ) {
				continue;
			}

			foreach ( array( 'subject', 'heading' ) as $key ) {
				if ( isset( $over[ $key ] ) && '' !== trim( (string) $over[ $key ] ) ) {
					$design['emails'][ $id ][ $key ] = $over[ $key ];
				}
			}

			foreach ( array( 'before', 'after', 'body' ) as $key ) {
				if ( ! empty( $over[ $key ] ) && is_array( $over[ $key ] ) ) {
					$design['emails'][ $id ][ $key ] = $over[ $key ];
				}
			}
		}

		return $design;
	}

	/**
	 * Brändi väärtused.
	 *
	 * @return array
	 */
	public static function brand() {
		$design = self::get();

		return $design['brand'];
	}

	/**
	 * Ühe meili seaded.
	 *
	 * @param string $email_id WC_Email id.
	 * @return array
	 */
	public static function email( $email_id ) {
		$design = self::get();

		if ( isset( $design['emails'][ $email_id ] ) ) {
			return $design['emails'][ $email_id ];
		}

		return array(
			'enabled'    => 1,
			'mode'       => 'wrap',
			'subject'    => '',
			'heading'    => '',
			'before'     => array(),
			'after'      => array(),
			'body'       => array(),
			'additional' => 0,
		);
	}

	/**
	 * Kas kujundus rakendub sellele kirjale.
	 *
	 * Kaks lülitit: ülemine „Kujundus sees" on peakraan kõigile, kirja oma
	 * lubab üksikud kirjad WooCommerce'i enda kujundusse jätta.
	 *
	 * @param string $email_id WC_Email id.
	 * @return bool
	 */
	public static function email_enabled( $email_id ) {
		$settings = self::email( $email_id );

		return ! empty( $settings['enabled'] );
	}

	/**
	 * Kas see meil pannakse tervikuna ise kokku.
	 *
	 * @param string $email_id WC_Email id.
	 * @return bool
	 */
	public static function mode( $email_id ) {
		$settings = self::email( $email_id );

		// Täisrežiim kehtib ainult siis, kui selles keeles on ka sisu. Režiim on
		// keelte vahel jagatud, aga keha mitte — nii võib juhtuda, et üks keel
		// on täisrežiimis kokku pandud ja teine veel mitte. Sel juhul on parem
		// anda WooCommerce'i oma sisu kui saata tühi kiri.
		return ( 'full' === $settings['mode'] && ! empty( $settings['body'] ) ) ? 'full' : 'wrap';
	}

	/**
	 * Kas see meil pannakse tervikuna ise kokku.
	 *
	 * @param string $email_id WC_Email id.
	 * @return bool
	 */
	public static function is_full( $email_id ) {
		return 'full' === self::mode( $email_id );
	}

	/**
	 * Salvestus. Sisend puhastatakse alati.
	 *
	 * @param array $design Toores kujundus.
	 * @return array Salvestatud kujundus.
	 */
	public static function save( $design ) {
		$clean = self::sanitize( $design );

		update_option( WMD_OPTION, $clean, false );

		// Kogu vahemälu läheb tühjaks: iga keele kiht võis muutuda.
		self::$cache = array();

		return $clean;
	}

	/**
	 * Lähtestus vaikimisi kujundusele.
	 *
	 * @return array
	 */
	public static function reset() {
		$default = wmd_default_design();

		update_option( WMD_OPTION, $default, false );
		self::$cache = array();

		return $default;
	}

	/**
	 * Ajutine kujundus ainult selleks päringuks (eelvaade salvestamata muudatustest).
	 *
	 * @param array $design Puhastatud kujundus.
	 */
	public static function set_cache( $design ) {
		$filled = self::fill( $design );

		// Sama kujundus kõigile keeltele, aga igaüks oma kihiga kaetud — nii
		// näeb kujundaja salvestamata muudatusi ka teises keeles.
		self::$cache = array();

		foreach ( array_keys( wmd_languages() ) as $lang ) {
			self::$cache[ $lang ] = self::merge_lang( $filled, $lang );
		}

		self::$cache[ self::$lang ] = self::merge_lang( $filled, self::$lang );
	}

	/**
	 * Täidab puuduvad võtmed vaikeväärtustega.
	 *
	 * @param array $design Salvestatud kujundus.
	 * @return array
	 */
	protected static function fill( $design ) {
		$default = wmd_default_design();

		$out          = array();
		$out['version'] = 1;
		$out['brand'] = array();

		foreach ( wmd_brand_schema() as $key => $field ) {
			$out['brand'][ $key ] = isset( $design['brand'][ $key ] ) ? $design['brand'][ $key ] : $field['default'];
		}

		foreach ( array( 'header', 'footer' ) as $section ) {
			$out[ $section ] = isset( $design[ $section ] ) && is_array( $design[ $section ] )
				? $design[ $section ]
				: $default[ $section ];
		}

		$out['emails'] = array();
		foreach ( array_keys( wmd_email_list() ) as $id ) {
			$stored = isset( $design['emails'][ $id ] ) && is_array( $design['emails'][ $id ] )
				? $design['emails'][ $id ]
				: $default['emails'][ $id ];

			$out['emails'][ $id ] = array(
				// Puuduv lipp tähendab „sees": vanas kujunduses seda välja ei olnud.
				'enabled' => ( isset( $stored['enabled'] ) && empty( $stored['enabled'] ) ) ? 0 : 1,
				'mode'    => ( isset( $stored['mode'] ) && 'full' === $stored['mode'] ) ? 'full' : 'wrap',
				'subject' => isset( $stored['subject'] ) ? (string) $stored['subject'] : '',
				'heading' => isset( $stored['heading'] ) ? (string) $stored['heading'] : '',
				'before'  => isset( $stored['before'] ) && is_array( $stored['before'] ) ? $stored['before'] : array(),
				'after'   => isset( $stored['after'] ) && is_array( $stored['after'] ) ? $stored['after'] : array(),
				'body'    => isset( $stored['body'] ) && is_array( $stored['body'] ) ? $stored['body'] : array(),
				'additional' => empty( $stored['additional'] ) ? 0 : 1,
			);
		}

		$out['payments'] = isset( $design['payments'] ) && is_array( $design['payments'] ) ? $design['payments'] : array();

		// Keelekihid lähevad läbi muutmata: neid ei täideta vaikeväärtustega,
		// sest tühi väli kihis tähendabki „võta vaikekeelest".
		$out['i18n'] = isset( $design['i18n'] ) && is_array( $design['i18n'] ) ? $design['i18n'] : array();

		return $out;
	}

	/**
	 * Kujundus JSON-i jaoks.
	 *
	 * Tühi PHP massiiv muutub JSON-is massiiviks `[]`, mitte objektiks `{}`.
	 * JavaScripti massiivile string-võtme lisamine kaob JSON.stringify käigus
	 * vaikselt ära, seega peavad võtmega kogumid siit välja minema objektina.
	 *
	 * @param array|null $design Kujundus või null praeguse jaoks.
	 * @return array
	 */
	public static function for_js( $design = null ) {
		if ( null === $design ) {
			$design = self::get();
		}

		$design['payments'] = (object) ( isset( $design['payments'] ) ? $design['payments'] : array() );

		$i18n = isset( $design['i18n'] ) && is_array( $design['i18n'] ) ? $design['i18n'] : array();

		foreach ( $i18n as $lang => $layer ) {
			if ( isset( $layer['payments'] ) ) {
				$i18n[ $lang ]['payments'] = (object) $layer['payments'];
			}

			if ( isset( $layer['emails'] ) ) {
				$i18n[ $lang ]['emails'] = (object) $layer['emails'];
			}
		}

		$design['i18n'] = (object) $i18n;

		return $design;
	}

	/**
	 * Ühe makseviisi juhised.
	 *
	 * @param string $gateway_id Makselahenduse id.
	 * @return string
	 */
	public static function payment_note( $gateway_id ) {
		$design = self::get();

		return isset( $design['payments'][ $gateway_id ] ) ? (string) $design['payments'][ $gateway_id ] : '';
	}

	/**
	 * Terve struktuuri puhastus.
	 *
	 * @param array $design Toores kujundus.
	 * @return array
	 */
	public static function sanitize( $design ) {
		if ( ! is_array( $design ) ) {
			$design = array();
		}

		$out = array(
			'version'  => 1,
			'brand'    => array(),
			'header'   => array(),
			'footer'   => array(),
			'emails'   => array(),
			'payments' => array(),
			'i18n'     => array(),
		);

		if ( isset( $design['i18n'] ) ) {
			$out['i18n'] = self::sanitize_i18n( $design['i18n'] );
		}

		if ( isset( $design['payments'] ) && is_array( $design['payments'] ) ) {
			foreach ( $design['payments'] as $gateway => $note ) {
				$gateway = sanitize_key( $gateway );

				if ( '' === $gateway ) {
					continue;
				}

				$out['payments'][ $gateway ] = wp_kses( (string) $note, wmd_allowed_html() );
			}
		}

		foreach ( wmd_brand_schema() as $key => $field ) {
			$value              = isset( $design['brand'][ $key ] ) ? $design['brand'][ $key ] : $field['default'];
			$out['brand'][ $key ] = self::sanitize_value( $value, $field );
		}

		foreach ( array( 'header', 'footer' ) as $section ) {
			$blocks          = isset( $design[ $section ] ) ? $design[ $section ] : array();
			$out[ $section ] = self::sanitize_blocks( $blocks );
		}

		foreach ( array_keys( wmd_email_list() ) as $id ) {
			$stored = isset( $design['emails'][ $id ] ) ? $design['emails'][ $id ] : array();

			$out['emails'][ $id ] = array(
				// Puuduv lipp tähendab „sees": vanas kujunduses seda välja ei olnud.
				'enabled' => ( isset( $stored['enabled'] ) && empty( $stored['enabled'] ) ) ? 0 : 1,
				'mode'    => ( isset( $stored['mode'] ) && 'full' === $stored['mode'] ) ? 'full' : 'wrap',
				'subject' => isset( $stored['subject'] ) ? sanitize_text_field( $stored['subject'] ) : '',
				'heading' => isset( $stored['heading'] ) ? sanitize_text_field( $stored['heading'] ) : '',
				'before'  => self::sanitize_blocks( isset( $stored['before'] ) ? $stored['before'] : array() ),
				'after'   => self::sanitize_blocks( isset( $stored['after'] ) ? $stored['after'] : array() ),
				'body'    => self::sanitize_blocks( isset( $stored['body'] ) ? $stored['body'] : array() ),
				'additional' => empty( $stored['additional'] ) ? 0 : 1,
			);
		}

		return $out;
	}

	/**
	 * Keelekihtide puhastus.
	 *
	 * Kiht käib läbi sama puhastuse mis vaikekeel, aga tühjaks jäetud väljad
	 * jäävad kihist välja — need tähendavad „võta vaikekeelest". Keeli, mida
	 * siin poes praegu ei ole, ei visata ära: teisest poest imporditud kujundus
	 * võib neid sisaldada ja hiljem võib see keel lisanduda.
	 *
	 * @param mixed $i18n Toored kihid.
	 * @return array
	 */
	protected static function sanitize_i18n( $i18n ) {
		$out = array();

		if ( ! is_array( $i18n ) && ! is_object( $i18n ) ) {
			return $out;
		}

		$emails = array_keys( wmd_email_list() );

		foreach ( (array) $i18n as $lang => $layer ) {
			$lang = sanitize_key( $lang );

			if ( '' === $lang || ( ! is_array( $layer ) && ! is_object( $layer ) ) ) {
				continue;
			}

			$layer = (array) $layer;
			$clean = array();

			foreach ( array( 'header', 'footer' ) as $section ) {
				if ( ! empty( $layer[ $section ] ) ) {
					$blocks = self::sanitize_blocks( $layer[ $section ] );

					if ( $blocks ) {
						$clean[ $section ] = $blocks;
					}
				}
			}

			if ( ! empty( $layer['payments'] ) ) {
				$pay = array();

				foreach ( (array) $layer['payments'] as $gateway => $note ) {
					$gateway = sanitize_key( $gateway );
					$note    = wp_kses( (string) $note, wmd_allowed_html() );

					if ( '' !== $gateway && '' !== trim( wp_strip_all_tags( $note ) ) ) {
						$pay[ $gateway ] = $note;
					}
				}

				if ( $pay ) {
					$clean['payments'] = $pay;
				}
			}

			if ( ! empty( $layer['emails'] ) ) {
				$mails = array();

				foreach ( (array) $layer['emails'] as $id => $over ) {
					if ( ! in_array( $id, $emails, true ) || ( ! is_array( $over ) && ! is_object( $over ) ) ) {
						continue;
					}

					$over = (array) $over;
					$one  = array();

					foreach ( array( 'subject', 'heading' ) as $key ) {
						if ( isset( $over[ $key ] ) && '' !== trim( (string) $over[ $key ] ) ) {
							$one[ $key ] = sanitize_text_field( $over[ $key ] );
						}
					}

					foreach ( array( 'before', 'after', 'body' ) as $key ) {
						if ( ! empty( $over[ $key ] ) ) {
							$blocks = self::sanitize_blocks( $over[ $key ] );

							if ( $blocks ) {
								$one[ $key ] = $blocks;
							}
						}
					}

					if ( $one ) {
						$mails[ $id ] = $one;
					}
				}

				if ( $mails ) {
					$clean['emails'] = $mails;
				}
			}

			if ( $clean ) {
				$out[ $lang ] = $clean;
			}
		}

		return $out;
	}

	/**
	 * Plokinimekirja puhastus.
	 *
	 * @param mixed $blocks Toores nimekiri.
	 * @return array
	 */
	public static function sanitize_blocks( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		$types = wmd_block_types();
		$out   = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) || ! isset( $types[ $block['type'] ] ) ) {
				continue;
			}

			$type  = $block['type'];
			$props = array();

			foreach ( $types[ $type ]['fields'] as $key => $field ) {
				$value         = isset( $block['props'][ $key ] ) ? $block['props'][ $key ] : $field['default'];
				$props[ $key ] = self::sanitize_value( $value, $field );
			}

			$id = isset( $block['id'] ) ? sanitize_key( $block['id'] ) : '';
			if ( '' === $id ) {
				$id = 'b' . substr( md5( $type . microtime( true ) . wp_rand() ), 0, 10 );
			}

			$out[] = array(
				'id'    => $id,
				'type'  => $type,
				'props' => $props,
				'cond'  => self::sanitize_cond( isset( $block['cond'] ) ? $block['cond'] : array() ),
			);
		}

		return $out;
	}

	/**
	 * Veergude nimekirja puhastus.
	 *
	 * Järjekord tuleb kasutaja käest, aga võtmed peavad olema skeemis lubatud.
	 * Puuduvad veerud lisame lõppu välja lülitatuna, et uus versioon ei kaotaks
	 * kasutaja seadeid ega peidaks uusi valikuid.
	 *
	 * @param mixed $value Toores nimekiri.
	 * @param array $field Välja kirjeldus.
	 * @return array
	 */
	protected static function sanitize_columns( $value, $field ) {
		$options = isset( $field['options'] ) ? $field['options'] : array();
		$out     = array();
		$seen    = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $col ) {
				if ( ! is_array( $col ) || empty( $col['key'] ) ) {
					continue;
				}

				$key = sanitize_key( $col['key'] );

				if ( ! isset( $options[ $key ] ) || isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$out[]        = array(
					'key'   => $key,
					'label' => isset( $col['label'] ) ? sanitize_text_field( $col['label'] ) : $options[ $key ],
					'on'    => empty( $col['on'] ) ? 0 : 1,
				);
			}
		}

		// Puuduv rida läheb sinna, kuhu ta skeemis kuulub — eelmise tuttava rea
		// järele, mitte nimekirja lõppu. Sama reegel on kujundaja poolel
		// (mergeColumns admin.js-is), et salvestus ja ekraan ei läheks lahku.
		$keys = array_keys( $options );

		foreach ( $keys as $i => $key ) {
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$at = count( $out );

			for ( $j = $i - 1; $j >= 0; $j-- ) {
				$found = -1;

				foreach ( $out as $n => $col ) {
					if ( $col['key'] === $keys[ $j ] ) {
						$found = $n;
						break;
					}
				}

				if ( -1 !== $found ) {
					$at = $found + 1;
					break;
				}
			}

			array_splice(
				$out,
				$at,
				0,
				array(
					array(
						'key'   => $key,
						'label' => $options[ $key ],
						'on'    => 0,
					),
				)
			);

			$seen[ $key ] = true;
		}

		return $out;
	}

	/**
	 * Silt-väärtus ridade puhastus.
	 *
	 * Väärtus võib sisaldada märgendeid, seega ei tohi seda URL-ina puhastada.
	 *
	 * @param mixed $value Toores nimekiri.
	 * @return array
	 */
	/**
	 * Pildikaartide puhastus: pilt, link ja nimi.
	 *
	 * Linki ei aja siin läbi esc_url_raw, sest seal võib olla {{muutuja}} —
	 * aadressiks tehakse see alles renderdamisel, kui muutuja on asendatud.
	 *
	 * @param mixed $value Toored read.
	 * @return array
	 */
	protected static function sanitize_cards( $value ) {
		$out = array();

		if ( ! is_array( $value ) ) {
			return $out;
		}

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$out[] = array(
				'image' => isset( $row['image'] ) ? sanitize_text_field( $row['image'] ) : '',
				'link'  => isset( $row['link'] ) ? sanitize_text_field( $row['link'] ) : '',
				'label' => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
			);

			// Ülempiir, et vigane import ei kasvataks kujundust lõputult.
			if ( count( $out ) >= 24 ) {
				break;
			}
		}

		return $out;
	}

	protected static function sanitize_pairs( $value ) {
		$out = array();

		if ( ! is_array( $value ) ) {
			return $out;
		}

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$val   = isset( $row['value'] ) ? sanitize_text_field( $row['value'] ) : '';
			$link  = isset( $row['link'] ) ? sanitize_text_field( $row['link'] ) : '';

			if ( '' === $label && '' === $val ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'value' => $val,
				'link'  => $link,
			);
		}

		return $out;
	}

	/**
	 * Ploki nähtavuse tingimus.
	 *
	 * Tühi nimekiri tähendab „näita alati". Praegu on ainus tingimus makseviis,
	 * sest just selle järgi tuleb sisu kõige sagedamini eristada.
	 *
	 * @param mixed $cond Toores tingimus.
	 * @return array
	 */
	protected static function sanitize_cond( $cond ) {
		$pay = array();

		if ( is_array( $cond ) && isset( $cond['pay'] ) && is_array( $cond['pay'] ) ) {
			foreach ( $cond['pay'] as $id ) {
				$id = sanitize_key( $id );
				if ( '' !== $id ) {
					$pay[] = $id;
				}
			}
		}

		return array( 'pay' => array_values( array_unique( $pay ) ) );
	}

	/**
	 * Ühe välja puhastus tema tüübi järgi.
	 *
	 * @param mixed $value Väärtus.
	 * @param array $field Välja kirjeldus.
	 * @return mixed
	 */
	protected static function sanitize_value( $value, $field ) {
		switch ( $field['type'] ) {
			case 'columns':
				return self::sanitize_columns( $value, $field );

			case 'pairs':
				return self::sanitize_pairs( $value );

			case 'cards':
				return self::sanitize_cards( $value );

			case 'metakey':
				return wmd_meta_key( sanitize_text_field( (string) $value ) );

			case 'color':
				return wmd_sanitize_color( $value );

			case 'range':
				$num = is_numeric( $value ) ? (float) $value : (float) $field['default'];
				if ( isset( $field['min'] ) && $num < $field['min'] && 0 !== (int) $num ) {
					$num = $field['min'];
				}
				if ( isset( $field['max'] ) && $num > $field['max'] ) {
					$num = $field['max'];
				}
				return (int) $num;

			case 'toggle':
				return empty( $value ) ? 0 : 1;

			case 'select':
				$options = isset( $field['options'] ) ? $field['options'] : array();
				return isset( $options[ $value ] ) ? (string) $value : (string) $field['default'];

			case 'align':
				return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'left';

			case 'url':
			case 'image':
				$raw = trim( (string) $value );

				if ( '' === $raw ) {
					return '';
				}

				// Märgendeid sisaldav aadress ei ole veel URL: esc_url_raw
				// sööks looksulud ära ja jälgimislingist jääks järele prügi.
				// Aadressiks tehakse see renderdamisel, kus on esc_url().
				if ( false !== strpos( $raw, '{{' ) ) {
					return sanitize_text_field( $raw );
				}

				return esc_url_raw( $raw );

			case 'richtext':
				return wp_kses( (string) $value, wmd_allowed_html() );

			case 'textarea':
				// Lisa-CSS ja oma HTML: lubame rohkem, aga skriptid mitte.
				$html = self::strip_scripts( (string) $value );

				// Poehaldajal ei ole unfiltered_html õigust, seega piirame teda
				// samamoodi nagu postituse sisu — <iframe>, <form> ja <object>
				// meilis ei ole kellelegi vaja.
				if ( ! current_user_can( 'unfiltered_html' ) ) {
					$html = wp_kses_post( $html );
				}

				return $html;

			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Eemaldab skriptid ja sündmuseatribuudid.
	 *
	 * @param string $html Sisend.
	 * @return string
	 */
	protected static function strip_scripts( $html ) {
		$html = preg_replace( '#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', $html );
		$html = preg_replace( '#<\s*script[^>]*/?>#is', '', (string) $html );
		$html = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $html );
		$html = preg_replace( '/javascript\s*:/i', '', (string) $html );

		return (string) $html;
	}
}
