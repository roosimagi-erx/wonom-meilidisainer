<?php
/**
 * Meili päis — asendab WooCommerce'i vaikemalli.
 *
 * WooCommerce ei anna sellele mallile meiliobjekti kaasa, ainult pealkirja ja
 * poe nime. Meili tuvastame WMD_Emails kaudu, kes püüab selle kinni tegevusest
 * woocommerce_email_header.
 *
 * @package Wonom_Meilidisainer
 * @version 10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmd_email   = WMD_Emails::current();
$wmd_heading = isset( $email_heading ) ? $email_heading : '';

// Koostamine käib läbi turvavõrgu: korras kirja puhul on väljund täpselt
// sama, vea korral jääb see osa tühjaks ja kiri koostatakse saatmisel uuesti
// WooCommerce'i vaikemalliga (vt WMD_Emails::guard).
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML koostatakse WMD_Render sees.
echo WMD_Emails::guard(
	'header',
	$wmd_email,
	static function () use ( $wmd_email, $wmd_heading ) {
		$wmd_email_id = WMD_Emails::current_id();
		$wmd_ctx      = WMD_Emails::context_for( $wmd_email );

		return WMD_Render::header_html( $wmd_heading, $wmd_email_id, $wmd_ctx );
	}
);
