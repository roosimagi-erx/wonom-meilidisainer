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

$wmd_email    = WMD_Emails::current();
$wmd_email_id = WMD_Emails::current_id();
$wmd_heading  = isset( $email_heading ) ? $email_heading : '';
$wmd_ctx      = WMD_Emails::context_for( $wmd_email );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML koostatakse WMD_Render sees.
echo WMD_Render::header_html( $wmd_heading, $wmd_email_id, $wmd_ctx );
