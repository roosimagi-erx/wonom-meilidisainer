<?php
/**
 * Eemaldab plugina andmed desinstallimisel.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wmd_design' );
delete_option( 'wmd_enabled' );
delete_option( 'wmd_update_source' );
delete_option( 'wmd_update_repo' );
delete_option( 'wmd_update_json' );
delete_option( 'wmd_update_token' );
delete_transient( 'wmd_update_info' );
