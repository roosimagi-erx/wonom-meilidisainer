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
	 * Vahemälu päringu ajaks.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Terve kujundus, vaikeväärtustega täidetud.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( WMD_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = self::fill( $stored );

		return self::$cache;
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
			'mode'    => 'wrap',
			'subject' => '',
			'heading' => '',
			'before'  => array(),
			'after'   => array(),
			'body'    => array(),
		);
	}

	/**
	 * Kas see meil pannakse tervikuna ise kokku.
	 *
	 * @param string $email_id WC_Email id.
	 * @return bool
	 */
	public static function is_full( $email_id ) {
		$settings = self::email( $email_id );

		return 'full' === $settings['mode'] && ! empty( $settings['body'] );
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
		self::$cache = $clean;

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
		self::$cache = $default;

		return $default;
	}

	/**
	 * Ajutine kujundus ainult selleks päringuks (eelvaade salvestamata muudatustest).
	 *
	 * @param array $design Puhastatud kujundus.
	 */
	public static function set_cache( $design ) {
		self::$cache = self::fill( $design );
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
				'mode'    => ( isset( $stored['mode'] ) && 'full' === $stored['mode'] ) ? 'full' : 'wrap',
				'subject' => isset( $stored['subject'] ) ? (string) $stored['subject'] : '',
				'heading' => isset( $stored['heading'] ) ? (string) $stored['heading'] : '',
				'before'  => isset( $stored['before'] ) && is_array( $stored['before'] ) ? $stored['before'] : array(),
				'after'   => isset( $stored['after'] ) && is_array( $stored['after'] ) ? $stored['after'] : array(),
				'body'    => isset( $stored['body'] ) && is_array( $stored['body'] ) ? $stored['body'] : array(),
			);
		}

		$out['payments'] = isset( $design['payments'] ) && is_array( $design['payments'] ) ? $design['payments'] : array();

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
		);

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
				'mode'    => ( isset( $stored['mode'] ) && 'full' === $stored['mode'] ) ? 'full' : 'wrap',
				'subject' => isset( $stored['subject'] ) ? sanitize_text_field( $stored['subject'] ) : '',
				'heading' => isset( $stored['heading'] ) ? sanitize_text_field( $stored['heading'] ) : '',
				'before'  => self::sanitize_blocks( isset( $stored['before'] ) ? $stored['before'] : array() ),
				'after'   => self::sanitize_blocks( isset( $stored['after'] ) ? $stored['after'] : array() ),
				'body'    => self::sanitize_blocks( isset( $stored['body'] ) ? $stored['body'] : array() ),
			);
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

		foreach ( $options as $key => $label ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'on'    => 0,
				);
			}
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
				// Lubame ka liitmismärgendid ({{order_url}}), mis pole veel URL.
				$raw = trim( (string) $value );
				if ( '' === $raw ) {
					return '';
				}
				if ( preg_match( '/^\{\{[a-z0-9_]+\}\}$/i', $raw ) ) {
					return $raw;
				}
				return esc_url_raw( $raw );

			case 'richtext':
				return wp_kses( (string) $value, wmd_allowed_html() );

			case 'textarea':
				// Lisa-CSS ja oma HTML: lubame rohkem, aga skriptid mitte.
				return self::strip_scripts( (string) $value );

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
