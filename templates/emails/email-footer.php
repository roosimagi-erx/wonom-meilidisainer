<?php
/**
 * Meili jalus — asendab WooCommerce'i vaikemalli.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmd_email    = isset( $email ) ? $email : null;
$wmd_email_id = ( $wmd_email && ! empty( $wmd_email->id ) ) ? $wmd_email->id : '';
$wmd_ctx      = WMD_Emails::context_for( $wmd_email );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML koostatakse WMD_Render sees.
echo WMD_Render::footer_html( $wmd_email_id, $wmd_ctx );
