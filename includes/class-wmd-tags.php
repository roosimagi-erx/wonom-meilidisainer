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
	 * Märgendite rühmad kujundaja nimekirja jaoks.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'order'    => __( 'Tellimus', 'wonom-meilidisainer' ),
			'customer' => __( 'Klient', 'wonom-meilidisainer' ),
			'billing'  => __( 'Arveaadress', 'wonom-meilidisainer' ),
			'shipping' => __( 'Tarneaadress', 'wonom-meilidisainer' ),
			'payment'  => __( 'Makse ja tarne', 'wonom-meilidisainer' ),
			'shop'     => __( 'Pood', 'wonom-meilidisainer' ),
		);
	}

	/**
	 * Märgendid koos rühma, selgituse ja näidisväärtusega.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$tags = array(
			// Tellimus.
			'order_number'         => array( 'order', __( 'Tellimuse number', 'wonom-meilidisainer' ), '1042' ),
			'order_id'             => array( 'order', __( 'Tellimuse ID andmebaasis', 'wonom-meilidisainer' ), '1042' ),
			'order_date'           => array( 'order', __( 'Tellimuse kuupäev', 'wonom-meilidisainer' ), '09.09.2026' ),
			'order_paid_date'      => array( 'order', __( 'Maksmise kuupäev', 'wonom-meilidisainer' ), '09.09.2026' ),
			'order_status'         => array( 'order', __( 'Tellimuse olek', 'wonom-meilidisainer' ), __( 'Töötlemisel', 'wonom-meilidisainer' ) ),
			'order_total'          => array( 'order', __( 'Tellimuse summa', 'wonom-meilidisainer' ), '87,40 €' ),
			'order_subtotal'       => array( 'order', __( 'Vahesumma', 'wonom-meilidisainer' ), '82,40 €' ),
			'order_discount'       => array( 'order', __( 'Allahindlus', 'wonom-meilidisainer' ), '8,00 €' ),
			'order_shipping_total' => array( 'order', __( 'Tarne summa', 'wonom-meilidisainer' ), '5,00 €' ),
			'order_tax_total'      => array( 'order', __( 'Käibemaks', 'wonom-meilidisainer' ), '14,28 €' ),
			'order_currency'       => array( 'order', __( 'Valuuta', 'wonom-meilidisainer' ), 'EUR' ),
			'item_count'           => array( 'order', __( 'Toodete arv', 'wonom-meilidisainer' ), '3' ),
			'customer_note'        => array( 'order', __( 'Kliendi märkus', 'wonom-meilidisainer' ), __( 'Palun jätke pakiautomaati.', 'wonom-meilidisainer' ) ),
			'order_url'            => array( 'order', __( 'Tellimuse vaate link', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto/tellimus/1042' ),
			'order_pay_url'        => array( 'order', __( 'Maksmise link', 'wonom-meilidisainer' ), 'https://naidispood.ee/checkout/order-pay/1042' ),

			// Klient.
			'customer_first_name'  => array( 'customer', __( 'Eesnimi', 'wonom-meilidisainer' ), 'Mari' ),
			'customer_last_name'   => array( 'customer', __( 'Perenimi', 'wonom-meilidisainer' ), 'Tamm' ),
			'customer_name'        => array( 'customer', __( 'Ees- ja perenimi', 'wonom-meilidisainer' ), 'Mari Tamm' ),
			'customer_email'       => array( 'customer', __( 'E-post', 'wonom-meilidisainer' ), 'mari.tamm@naide.ee' ),

			// Arveaadress.
			'billing_first_name'   => array( 'billing', __( 'Eesnimi', 'wonom-meilidisainer' ), 'Mari' ),
			'billing_last_name'    => array( 'billing', __( 'Perenimi', 'wonom-meilidisainer' ), 'Tamm' ),
			'billing_company'      => array( 'billing', __( 'Ettevõte', 'wonom-meilidisainer' ), 'Näidis OÜ' ),
			'billing_address_1'    => array( 'billing', __( 'Aadressirida 1', 'wonom-meilidisainer' ), 'Pikk 12-4' ),
			'billing_address_2'    => array( 'billing', __( 'Aadressirida 2', 'wonom-meilidisainer' ), '' ),
			'billing_city'         => array( 'billing', __( 'Linn', 'wonom-meilidisainer' ), 'Tallinn' ),
			'billing_state'        => array( 'billing', __( 'Maakond', 'wonom-meilidisainer' ), 'Harjumaa' ),
			'billing_postcode'     => array( 'billing', __( 'Sihtnumber', 'wonom-meilidisainer' ), '10123' ),
			'billing_country'      => array( 'billing', __( 'Riik', 'wonom-meilidisainer' ), 'Eesti' ),
			'billing_phone'        => array( 'billing', __( 'Telefon', 'wonom-meilidisainer' ), '5551234' ),
			'billing_email'        => array( 'billing', __( 'E-post', 'wonom-meilidisainer' ), 'mari.tamm@naide.ee' ),
			'billing_address'      => array( 'billing', __( 'Terve aadress ühes reas', 'wonom-meilidisainer' ), 'Pikk 12-4, 10123 Tallinn' ),

			// Tarneaadress.
			'shipping_first_name'  => array( 'shipping', __( 'Eesnimi', 'wonom-meilidisainer' ), 'Mari' ),
			'shipping_last_name'   => array( 'shipping', __( 'Perenimi', 'wonom-meilidisainer' ), 'Tamm' ),
			'shipping_company'     => array( 'shipping', __( 'Ettevõte', 'wonom-meilidisainer' ), '' ),
			'shipping_address_1'   => array( 'shipping', __( 'Aadressirida 1', 'wonom-meilidisainer' ), 'Balti Jaama Turg' ),
			'shipping_address_2'   => array( 'shipping', __( 'Aadressirida 2', 'wonom-meilidisainer' ), '' ),
			'shipping_city'        => array( 'shipping', __( 'Linn', 'wonom-meilidisainer' ), 'Tallinn' ),
			'shipping_state'       => array( 'shipping', __( 'Maakond', 'wonom-meilidisainer' ), 'Harjumaa' ),
			'shipping_postcode'    => array( 'shipping', __( 'Sihtnumber', 'wonom-meilidisainer' ), '10411' ),
			'shipping_country'     => array( 'shipping', __( 'Riik', 'wonom-meilidisainer' ), 'Eesti' ),
			'shipping_phone'       => array( 'shipping', __( 'Telefon', 'wonom-meilidisainer' ), '5551234' ),
			'shipping_address'     => array( 'shipping', __( 'Terve aadress ühes reas', 'wonom-meilidisainer' ), 'Balti Jaama Turg, 10411 Tallinn' ),

			// Makse ja tarne.
			'payment_method'       => array( 'payment', __( 'Makseviisi nimi', 'wonom-meilidisainer' ), __( 'Panga ülekanne', 'wonom-meilidisainer' ) ),
			'payment_method_id'    => array( 'payment', __( 'Makseviisi tunnus', 'wonom-meilidisainer' ), 'bacs' ),
			'shipping_method'      => array( 'payment', __( 'Tarneviis', 'wonom-meilidisainer' ), __( 'Pakiautomaat', 'wonom-meilidisainer' ) ),

			// Pood.
			'site_title'           => array( 'shop', __( 'Poe nimi', 'wonom-meilidisainer' ), __( 'Näidispood', 'wonom-meilidisainer' ) ),
			'shop_url'             => array( 'shop', __( 'Poe aadress', 'wonom-meilidisainer' ), 'https://naidispood.ee' ),
			'my_account_url'       => array( 'shop', __( 'Minu konto link', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto' ),
			'admin_email'          => array( 'shop', __( 'Poe e-post', 'wonom-meilidisainer' ), 'pood@naidispood.ee' ),
			'year'                 => array( 'shop', __( 'Käesolev aasta', 'wonom-meilidisainer' ), '2026' ),
		);

		$out = array();

		foreach ( $tags as $key => $parts ) {
			$out[ $key ] = array(
				'group'  => $parts[0],
				'label'  => $parts[1],
				'sample' => $parts[2],
			);
		}

		return apply_filters( 'wmd_tags', $out );
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
			$ctx['site_title']  = get_bloginfo( 'name' );
			$ctx['shop_url']    = home_url( '/' );
			$ctx['admin_email'] = get_option( 'admin_email' );
			$ctx['year']        = gmdate( 'Y' );
		}

		return $ctx;
	}

	/**
	 * Poe märgendid, mis ei sõltu tellimusest.
	 *
	 * @return array<string,string>
	 */
	protected static function shop_context() {
		return array(
			'site_title'     => get_bloginfo( 'name' ),
			'shop_url'       => home_url( '/' ),
			'admin_email'    => get_option( 'admin_email' ),
			'year'           => gmdate( 'Y' ),
			'my_account_url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ),
		);
	}

	/**
	 * Kontekst kirjale, millel tellimust ei olegi.
	 *
	 * Poe märgendid on olemas, kõik tellimuse omad tühjad. Nii ei jää kanvasele
	 * näidistellimuse „Tere Mari" kirja, mis tellimusest midagi ei tea.
	 *
	 * @return array<string,string>
	 */
	public static function orderless_context() {
		$ctx = self::shop_context();

		foreach ( self::all() as $key => $tag ) {
			if ( 'shop' !== $tag['group'] && ! isset( $ctx[ $key ] ) ) {
				$ctx[ $key ] = '';
			}
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
		$ctx = self::shop_context();

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $ctx;
		}

		$created = $order->get_date_created();
		$paid    = $order->get_date_paid();

		$ctx['order_number']         = $order->get_order_number();
		$ctx['order_id']             = (string) $order->get_id();
		$ctx['order_date']           = $created ? wc_format_datetime( $created ) : '';
		$ctx['order_paid_date']      = $paid ? wc_format_datetime( $paid ) : '';
		$ctx['order_status']         = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();
		$ctx['order_total']          = wp_strip_all_tags( $order->get_formatted_order_total() );
		$ctx['order_subtotal']       = wp_strip_all_tags( wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ) );
		$ctx['order_discount']       = wp_strip_all_tags( wc_price( $order->get_total_discount(), array( 'currency' => $order->get_currency() ) ) );
		$ctx['order_shipping_total'] = wp_strip_all_tags( wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) );
		$ctx['order_tax_total']      = wp_strip_all_tags( wc_price( $order->get_total_tax(), array( 'currency' => $order->get_currency() ) ) );
		$ctx['order_currency']       = $order->get_currency();
		$ctx['item_count']           = (string) $order->get_item_count();
		$ctx['customer_note']        = $order->get_customer_note();
		$ctx['order_url']            = $order->get_view_order_url();
		$ctx['order_pay_url']        = $order->get_checkout_payment_url();

		$ctx['customer_first_name'] = $order->get_billing_first_name();
		$ctx['customer_last_name']  = $order->get_billing_last_name();
		$ctx['customer_name']       = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$ctx['customer_email']      = $order->get_billing_email();

		$ctx['billing_first_name'] = $order->get_billing_first_name();
		$ctx['billing_last_name']  = $order->get_billing_last_name();
		$ctx['billing_company']    = $order->get_billing_company();
		$ctx['billing_address_1']  = $order->get_billing_address_1();
		$ctx['billing_address_2']  = $order->get_billing_address_2();
		$ctx['billing_city']       = $order->get_billing_city();
		$ctx['billing_state']      = $order->get_billing_state();
		$ctx['billing_postcode']   = $order->get_billing_postcode();
		$ctx['billing_country']    = $order->get_billing_country();
		$ctx['billing_phone']      = $order->get_billing_phone();
		$ctx['billing_email']      = $order->get_billing_email();
		$ctx['billing_address']    = self::one_line( $order->get_formatted_billing_address() );

		$ctx['shipping_first_name'] = $order->get_shipping_first_name();
		$ctx['shipping_last_name']  = $order->get_shipping_last_name();
		$ctx['shipping_company']    = $order->get_shipping_company();
		$ctx['shipping_address_1']  = $order->get_shipping_address_1();
		$ctx['shipping_address_2']  = $order->get_shipping_address_2();
		$ctx['shipping_city']       = $order->get_shipping_city();
		$ctx['shipping_state']      = $order->get_shipping_state();
		$ctx['shipping_postcode']   = $order->get_shipping_postcode();
		$ctx['shipping_country']    = $order->get_shipping_country();
		$ctx['shipping_phone']      = is_callable( array( $order, 'get_shipping_phone' ) ) ? $order->get_shipping_phone() : '';
		$ctx['shipping_address']    = self::one_line( $order->get_formatted_shipping_address() );

		$ctx['payment_method']    = $order->get_payment_method_title();
		$ctx['payment_method_id'] = $order->get_payment_method();
		$ctx['shipping_method']   = $order->get_shipping_method();

		// WooCommerce'i plokid ja {{meta:...}} vajavad tellimust ennast.
		$ctx['__order'] = $order;

		return $ctx;
	}

	/**
	 * Mitmerealine aadress ühte ritta.
	 *
	 * @param string $html Aadress.
	 * @return string
	 */
	protected static function one_line( $html ) {
		$text = wp_strip_all_tags( str_replace( array( '<br/>', '<br>', '<br />' ), ', ', (string) $html ) );

		return trim( preg_replace( '/\s*,\s*/', ', ', $text ) );
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
