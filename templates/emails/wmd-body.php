<?php
/**
 * Meili sisu, kui kasutaja on valinud „terve meil ise".
 *
 * See mall asendab WooCommerce'i enda sisumalli (nt customer-processing-order.php).
 * Erinevalt päisest ja jalusest annab WooCommerce sisumallile kaasa nii meili kui
 * tellimuse, seega saame konteksti otse siit.
 *
 * @package Wonom_Meilidisainer
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmd_email    = isset( $email ) ? $email : null;
$wmd_email_id = ( $wmd_email && ! empty( $wmd_email->id ) ) ? $wmd_email->id : '';
$wmd_order    = isset( $order ) ? $order : null;
$wmd_heading  = isset( $email_heading ) ? $email_heading : '';
$wmd_admin    = isset( $sent_to_admin ) ? (bool) $sent_to_admin : false;

// Konteksti ehitab WMD_Emails, et kontomeilidel oleksid olemas ka kasutaja ja
// parooli lähtestamise märgendid — ilma nendeta läheks kiri ilma lingita.
$wmd_ctx = WMD_Emails::context_for( $wmd_email );

// WooCommerce'i enda plokid vajavad neid objekte; hoiame need kontekstis.
// Tellimus tuleb mallilt endalt, sest see on siin kindlasti õige.
if ( $wmd_order ) {
	$wmd_ctx            = array_merge( $wmd_ctx, WMD_Tags::order_context( $wmd_order ) );
	$wmd_ctx['__order'] = $wmd_order;
}

$wmd_ctx['__email']         = $wmd_email;
$wmd_ctx['__sent_to_admin'] = $wmd_admin;

do_action( 'woocommerce_email_header', $wmd_heading, $wmd_email );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML koostatakse WMD_Render sees.
echo WMD_Render::body_html( $wmd_email_id, $wmd_ctx );

// Poe seadetes olev lisatekst jääb alles, et miski vaikselt ei kaoks.
if ( ! empty( $additional_content ) ) {
	echo '<div class="wmd-additional">';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo '</div>';
}

do_action( 'woocommerce_email_footer', $wmd_email );
