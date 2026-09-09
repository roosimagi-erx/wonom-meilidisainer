<?php
/**
 * Meili jalus — asendab WooCommerce'i vaikemalli.
 *
 * WooCommerce ei anna sellele mallile ühtegi argumenti. Meili tuvastame
 * WMD_Emails kaudu, kes püüab selle kinni tegevusest woocommerce_email_footer.
 *
 * @package Wonom_Meilidisainer
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmd_email    = WMD_Emails::current();
$wmd_email_id = WMD_Emails::current_id();
$wmd_ctx      = WMD_Emails::context_for( $wmd_email );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML koostatakse WMD_Render sees.
echo WMD_Render::footer_html( $wmd_email_id, $wmd_ctx );
