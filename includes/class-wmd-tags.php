<?php
/**
 * Liitmismärgendid: {{order_number}} jms.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Märgendite nimekiri ja asendamine.
 */
class WMD_Tags {

	/**
	 * Märgendid koos selgituse ja näidisväärtusega.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return apply_filters(
			'wmd_tags',
			array(
				'site_title'          => array(
					'label'  => __( 'Poe nimi', 'wonom-meilidisainer' ),
					'sample' => __( 'Näidispood', 'wonom-meilidisainer' ),
				),
				'shop_url'            => array(
					'label'  => __( 'Poe aadress', 'wonom-meilidisainer' ),
					'sample' => 'https://naidispood.ee',
				),
				'customer_first_name' => array(
					'label'  => __( 'Kliendi eesnimi', 'wonom-meilidisainer' ),
					'sample' => 'Mari',
				),
				'customer_last_name'  => array(
					'label'  => __( 'Kliendi perenimi', 'wonom-meilidisainer' ),
					'sample' => 'Tamm',
				),
				'customer_email'      => array(
					'label'  => __( 'Kliendi e-post', 'wonom-meilidisainer' ),
					'sample' => 'mari.tamm@naide.ee',
				),
				'order_number'        => array(
					'label'  => __( 'Tellimuse number', 'wonom-meilidisainer' ),
					'sample' => '1042',
				),
				'order_date'          => array(
					'label'  => __( 'Tellimuse kuupäev', 'wonom-meilidisainer' ),
					'sample' => '09.09.2026',
				),
				'order_total'         => array(
					'label'  => __( 'Tellimuse summa', 'wonom-meilidisainer' ),
					'sample' => '87,40 €',
				),
				'order_url'           => array(
					'label'  => __( 'Tellimuse vaate link', 'wonom-meilidisainer' ),
					'sample' => 'https://naidispood.ee/minu-konto/tellimus/1042',
				),
				'payment_method'      => array(
					'label'  => __( 'Makseviis', 'wonom-meilidisainer' ),
					'sample' => __( 'Pangalink', 'wonom-meilidisainer' ),
				),
				'shipping_method'     => array(
					'label'  => __( 'Tarneviis', 'wonom-meilidisainer' ),
					'sample' => __( 'Pakiautomaat', 'wonom-meilidisainer' ),
				),
				'my_account_url'      => array(
					'label'  => __( 'Minu konto link', 'wonom-meilidisainer' ),
					'sample' => 'https://naidispood.ee/minu-konto',
				),
				'year'                => array(
					'label'  => __( 'Käesolev aasta', 'wonom-meilidisainer' ),
					'sample' => '2026',
				),
			)
		);
	}

	/**
	 * Näidisväärtused eelvaate jaoks.
	 *
	 * @return array<string,string>
	 */
	public static function sample_context() {
		$ctx = array();
		foreach ( self::all() as $key => $tag ) {
			$ctx[ $key ] = $tag['sample'];
		}

		if ( function_exists( 'get_bloginfo' ) ) {
			$ctx['site_title'] = get_bloginfo( 'name' );
			$ctx['shop_url']   = home_url( '/' );
			$ctx['year']       = gmdate( 'Y' );
		}

		return $ctx;
	}

	/**
	 * Kontekst päris tellimusest.
	 *
	 * @param WC_Order|null $order Tellimus.
	 * @return array<string,string>
	 */
	public static function order_context( $order = null ) {
		$ctx = array(
			'site_title'     => get_bloginfo( 'name' ),
			'shop_url'       => home_url( '/' ),
			'year'           => gmdate( 'Y' ),
			'my_account_url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ),
		);

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $ctx;
		}

		$date = $order->get_date_created();

		$ctx['customer_first_name'] = $order->get_billing_first_name();
		$ctx['customer_last_name']  = $order->get_billing_last_name();
		$ctx['customer_email']      = $order->get_billing_email();
		$ctx['order_number']        = $order->get_order_number();
		$ctx['order_date']          = $date ? wc_format_datetime( $date ) : '';
		$ctx['order_total']         = wp_strip_all_tags( $order->get_formatted_order_total() );
		$ctx['order_url']           = $order->get_view_order_url();
		$ctx['payment_method']      = $order->get_payment_method_title();
		$ctx['shipping_method']     = $order->get_shipping_method();

		// WooCommerce'i plokid ja {{meta:...}} vajavad tellimust ennast.
		$ctx['__order'] = $order;

		return $ctx;
	}

	/**
	 * Asendab märgendid tekstis.
	 *
	 * @param string $text Sisend.
	 * @param array  $ctx  Kontekst.
	 * @return string
	 */
	public static function replace( $text, $ctx ) {
		$text = (string) $text;

		if ( false === strpos( $text, '{{' ) ) {
			return $text;
		}

		return preg_replace_callback(
			'/\{\{\s*([a-z0-9_:\-]+)\s*\}\}/i',
			function ( $m ) use ( $ctx ) {
				$key = strtolower( $m[1] );

				// {{meta:_tracking_number}} loeb välja otse tellimuselt. Nii saab
				// kasutada ka tarnepluginate välju, mida me ette ei tea.
				if ( 0 === strpos( $key, 'meta:' ) ) {
					$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;

					if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
						return '';
					}

					// Võtit ei tohi väiketäheliseks teha — meta võtmed on tõstutundlikud.
					$meta_key = trim( substr( $m[1], 5 ) );

					return (string) $order->get_meta( $meta_key, true );
				}

				return isset( $ctx[ $key ] ) && is_scalar( $ctx[ $key ] ) ? (string) $ctx[ $key ] : '';
			},
			$text
		);
	}
}
