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
			'order'    => __( 'Order', 'wonom-meilidisainer' ),
			'customer' => __( 'Customer', 'wonom-meilidisainer' ),
			'billing'  => __( 'Billing address', 'wonom-meilidisainer' ),
			'shipping' => __( 'Shipping address', 'wonom-meilidisainer' ),
			'payment'  => __( 'Payment and delivery', 'wonom-meilidisainer' ),
			'account'  => __( 'Account', 'wonom-meilidisainer' ),
			'shop'     => __( 'Shop', 'wonom-meilidisainer' ),
		);
	}

	/**
	 * Märgendid koos rühma, selgituse ja näidisväärtusega.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		// Näidiskuupäev poe enda formaadis, et muutujate nimekiri näitaks sama
		// kuju, mis kirja päriselt läheb.
		$sample_date = date_i18n( get_option( 'date_format' ) );

		$tags = array(
			// Tellimus.
			'order_number'         => array( 'order', __( 'Order number', 'wonom-meilidisainer' ), '1042' ),
			'order_id'             => array( 'order', __( 'Order ID in the database', 'wonom-meilidisainer' ), '1042' ),
			'order_date'           => array( 'order', __( 'Order date', 'wonom-meilidisainer' ), $sample_date ),
			'order_paid_date'      => array( 'order', __( 'Date paid', 'wonom-meilidisainer' ), $sample_date ),
			'order_status'         => array( 'order', __( 'Order status', 'wonom-meilidisainer' ), __( 'Processing', 'wonom-meilidisainer' ) ),
			'order_total'          => array( 'order', __( 'Order total', 'wonom-meilidisainer' ), '87,40 €' ),
			'order_subtotal'       => array( 'order', __( 'Subtotal', 'wonom-meilidisainer' ), '82,40 €' ),
			'order_discount'       => array( 'order', __( 'Discount', 'wonom-meilidisainer' ), '8,00 €' ),
			'coupon_codes'         => array( 'order', __( 'Coupon codes used', 'wonom-meilidisainer' ), 'SEPT20' ),
			'order_shipping_total' => array( 'order', __( 'Shipping total', 'wonom-meilidisainer' ), '5,00 €' ),
			'order_tax_total'      => array( 'order', __( 'VAT', 'wonom-meilidisainer' ), '14,28 €' ),
			'order_currency'       => array( 'order', __( 'Currency', 'wonom-meilidisainer' ), 'EUR' ),
			'item_count'           => array( 'order', __( 'Number of items', 'wonom-meilidisainer' ), '3' ),
			'customer_note'        => array( 'order', __( 'Customer note', 'wonom-meilidisainer' ), __( 'Please leave it in the parcel locker.', 'wonom-meilidisainer' ) ),
			'order_url'            => array( 'order', __( 'Order view link', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto/tellimus/1042' ),
			'order_pay_url'        => array( 'order', __( 'Payment link', 'wonom-meilidisainer' ), 'https://naidispood.ee/checkout/order-pay/1042' ),

			// Klient.
			'customer_first_name'  => array( 'customer', __( 'First name', 'wonom-meilidisainer' ), 'Mari' ),
			'customer_last_name'   => array( 'customer', __( 'Last name', 'wonom-meilidisainer' ), 'Tamm' ),
			'customer_name'        => array( 'customer', __( 'First and last name', 'wonom-meilidisainer' ), 'Mari Tamm' ),
			'customer_email'       => array( 'customer', __( 'Email', 'wonom-meilidisainer' ), 'mari.tamm@naide.ee' ),

			// Arveaadress.
			'billing_first_name'   => array( 'billing', __( 'First name', 'wonom-meilidisainer' ), 'Mari' ),
			'billing_last_name'    => array( 'billing', __( 'Last name', 'wonom-meilidisainer' ), 'Tamm' ),
			'billing_company'      => array( 'billing', __( 'Company', 'wonom-meilidisainer' ), 'Näidis OÜ' ),
			'billing_address_1'    => array( 'billing', __( 'Address line 1', 'wonom-meilidisainer' ), 'Pikk 12-4' ),
			'billing_address_2'    => array( 'billing', __( 'Address line 2', 'wonom-meilidisainer' ), '' ),
			'billing_city'         => array( 'billing', __( 'City', 'wonom-meilidisainer' ), 'Tallinn' ),
			'billing_state'        => array( 'billing', __( 'County', 'wonom-meilidisainer' ), 'Harjumaa' ),
			'billing_postcode'     => array( 'billing', __( 'Postcode', 'wonom-meilidisainer' ), '10123' ),
			'billing_country'      => array( 'billing', __( 'Country', 'wonom-meilidisainer' ), 'Eesti' ),
			'billing_phone'        => array( 'billing', __( 'Phone', 'wonom-meilidisainer' ), '5551234' ),
			'billing_email'        => array( 'billing', __( 'Email', 'wonom-meilidisainer' ), 'mari.tamm@naide.ee' ),
			'billing_address'      => array( 'billing', __( 'Full address on one line', 'wonom-meilidisainer' ), 'Pikk 12-4, 10123 Tallinn' ),

			// Tarneaadress.
			'shipping_first_name'  => array( 'shipping', __( 'First name', 'wonom-meilidisainer' ), 'Mari' ),
			'shipping_last_name'   => array( 'shipping', __( 'Last name', 'wonom-meilidisainer' ), 'Tamm' ),
			'shipping_company'     => array( 'shipping', __( 'Company', 'wonom-meilidisainer' ), '' ),
			'shipping_address_1'   => array( 'shipping', __( 'Address line 1', 'wonom-meilidisainer' ), 'Balti Jaama Turg' ),
			'shipping_address_2'   => array( 'shipping', __( 'Address line 2', 'wonom-meilidisainer' ), '' ),
			'shipping_city'        => array( 'shipping', __( 'City', 'wonom-meilidisainer' ), 'Tallinn' ),
			'shipping_state'       => array( 'shipping', __( 'County', 'wonom-meilidisainer' ), 'Harjumaa' ),
			'shipping_postcode'    => array( 'shipping', __( 'Postcode', 'wonom-meilidisainer' ), '10411' ),
			'shipping_country'     => array( 'shipping', __( 'Country', 'wonom-meilidisainer' ), 'Eesti' ),
			'shipping_phone'       => array( 'shipping', __( 'Phone', 'wonom-meilidisainer' ), '5551234' ),
			'shipping_address'     => array( 'shipping', __( 'Full address on one line', 'wonom-meilidisainer' ), 'Balti Jaama Turg, 10411 Tallinn' ),

			// Makse ja tarne.
			'payment_method'       => array( 'payment', __( 'Payment method name', 'wonom-meilidisainer' ), __( 'Bank transfer', 'wonom-meilidisainer' ) ),
			'payment_method_id'    => array( 'payment', __( 'Payment method ID', 'wonom-meilidisainer' ), 'bacs' ),
			'shipping_method'      => array( 'payment', __( 'Shipping method', 'wonom-meilidisainer' ), __( 'Parcel locker', 'wonom-meilidisainer' ) ),

			// Konto. Need on olemas ainult kontomeilides (uus konto, parooli
			// lähtestamine) — tellimusmeilides jäävad tühjaks.
			'user_login'           => array( 'account', __( 'Username', 'wonom-meilidisainer' ), 'mari.tamm' ),
			'user_email'           => array( 'account', __( 'User email', 'wonom-meilidisainer' ), 'mari.tamm@naide.ee' ),
			'user_display_name'    => array( 'account', __( 'Display name', 'wonom-meilidisainer' ), 'Mari Tamm' ),
			'set_password_url'     => array( 'account', __( 'Set password link (new account)', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto/lost-password/?key=naidis' ),
			'reset_password_url'   => array( 'account', __( 'Password reset link', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto/lost-password/?key=naidis' ),
			'login_url'            => array( 'account', __( 'Log in link', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto' ),

			// Pood.
			'site_title'           => array( 'shop', __( 'Shop name', 'wonom-meilidisainer' ), __( 'Demo Shop', 'wonom-meilidisainer' ) ),
			'shop_url'             => array( 'shop', __( 'Shop address', 'wonom-meilidisainer' ), 'https://naidispood.ee' ),
			'my_account_url'       => array( 'shop', __( 'My account link', 'wonom-meilidisainer' ), 'https://naidispood.ee/minu-konto' ),
			'admin_email'          => array( 'shop', __( 'Shop email', 'wonom-meilidisainer' ), 'pood@naidispood.ee' ),
			'year'                 => array( 'shop', __( 'Current year', 'wonom-meilidisainer' ), '2026' ),
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
	 * Konto märgendid meiliobjekti pealt.
	 *
	 * Kontomeilidel (uus konto, parooli lähtestamine) ei ole tellimust, vaid
	 * kasutaja ja lähtestusvõti. Ilma nende märgenditeta ei saa täisrežiimis
	 * kirja panna parooli seadmise linki ja kiri läheks kliendile kasutuna.
	 *
	 * Aadressid ehitame samamoodi nagu WooCommerce'i enda mallid, et link viiks
	 * täpselt sinna, kuhu WooCommerce ise viiks.
	 *
	 * @param WC_Email|null $email Meil.
	 * @param array         $ctx   Senine kontekst.
	 * @return array<string,string>
	 */
	public static function account_context( $email, $ctx = array() ) {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$ctx['login_url'] = wc_get_page_permalink( 'myaccount' );
		}

		if ( ! $email || ! is_object( $email ) ) {
			return $ctx;
		}

		$login = isset( $email->user_login ) ? (string) $email->user_login : '';

		if ( '' === $login ) {
			return $ctx;
		}

		$user = get_user_by( 'login', $login );

		$ctx['user_login']        = $login;
		$ctx['user_email']        = ! empty( $email->user_email ) ? (string) $email->user_email : ( $user ? $user->user_email : '' );
		$ctx['user_display_name'] = $user ? $user->display_name : $login;

		// Kliendi nime märgendid on kirjades samad, olgu allikaks tellimus või
		// konto — nii töötab {{customer_first_name}} igal pool ühtemoodi.
		if ( $user ) {
			$first = trim( (string) $user->first_name );
			$last  = trim( (string) $user->last_name );

			$ctx['customer_first_name'] = '' !== $first ? $first : $user->display_name;
			$ctx['customer_last_name']  = $last;
			$ctx['customer_name']       = '' !== trim( $first . $last ) ? trim( $first . ' ' . $last ) : $user->display_name;
		} else {
			$ctx['customer_first_name'] = $ctx['user_display_name'];
			$ctx['customer_name']       = $ctx['user_display_name'];
		}

		if ( '' !== $ctx['user_email'] ) {
			$ctx['customer_email'] = $ctx['user_email'];
		}

		$user_id = ! empty( $email->user_id ) ? (int) $email->user_id : ( $user ? (int) $user->ID : 0 );

		// Uue konto meilil on link juba objektil olemas; vanematel WooCommerce'i
		// versioonidel ehitab selle mall ise, seega teeme sama.
		if ( ! empty( $email->set_password_url ) ) {
			$ctx['set_password_url'] = (string) $email->set_password_url;
		} elseif ( $user_id && ! empty( $email->reset_key ) ) {
			$ctx['set_password_url'] = self::lost_password_url(
				array(
					'key'    => $email->reset_key,
					'id'     => $user_id,
					'action' => 'newaccount',
				)
			);
		}

		if ( $user_id && ! empty( $email->reset_key ) ) {
			$ctx['reset_password_url'] = self::lost_password_url(
				array(
					'key' => $email->reset_key,
					'id'  => $user_id,
				)
			);
		}

		return $ctx;
	}

	/**
	 * Parooli lähtestamise aadress — sama ehitus, mida WooCommerce'i mallid.
	 *
	 * @param array $args Päringu parameetrid.
	 * @return string
	 */
	protected static function lost_password_url( $args ) {
		if ( ! function_exists( 'wc_get_endpoint_url' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		return add_query_arg( $args, wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) );
	}

	/**
	 * Tellimusel kasutatud sooduskoodid ühe reana.
	 *
	 * Tühi string, kui koodi ei kasutatud — nii kaob „Tellimuse andmed" plokis
	 * rida ise ära, kui seal on „peida tühjad read" sees.
	 *
	 * @param WC_Order $order Tellimus.
	 * @return string
	 */
	public static function coupon_codes( $order ) {
		// get_coupon_codes() on WooCommerce 3.7+; vanemal kujul oli get_used_coupons().
		if ( is_callable( array( $order, 'get_coupon_codes' ) ) ) {
			$codes = $order->get_coupon_codes();
		} elseif ( is_callable( array( $order, 'get_used_coupons' ) ) ) {
			$codes = $order->get_used_coupons();
		} else {
			return '';
		}

		$codes = array_filter( array_map( 'trim', (array) $codes ) );

		if ( ! $codes ) {
			return '';
		}

		// Koodid on poes kirjutatud väikeste tähtedega, aga kliendile on nad
		// tuttavad sellisel kujul, nagu ta kassas sisestas.
		return implode( ', ', array_map( 'strtoupper', $codes ) );
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
		$ctx['coupon_codes']         = self::coupon_codes( $order );
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
