<?php
/**
 * Uninstall Member Admin Plugin
 * 
 * Rensa alla data som skapats av pluginet när det avinstalleras
 */

// Om inte anropad av WordPress, avsluta
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Rensa plugin-inställningar
 */
function member_admin_cleanup_options() {
    delete_option('member_admin_settings');
    delete_option('member_admin_install_date');
    delete_option('member_admin_version');
}

/**
 * Rensa user meta för donation-banner
 */
function member_admin_cleanup_user_meta() {
    global $wpdb;
    
    // Ta bort donation-banner dismissed meta för alla användare
    $wpdb->delete(
        $wpdb->usermeta,
        ['meta_key' => 'member_admin_donation_dismissed'],
        ['%s']
    );
}

/**
 * Rensa transients/cache
 */
function member_admin_cleanup_transients() {
    delete_transient('member_admin_acf_fields');
    
    // Rensa alla transients som börjar med vårt prefix
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_member_admin_%'
        )
    );
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 
            '_transient_timeout_member_admin_%'
        )
    );
}

/**
 * Kör cleanup-funktioner
 */
member_admin_cleanup_options();
member_admin_cleanup_user_meta();
member_admin_cleanup_transients();

// Logga avinstallation för debug
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Member Admin: Plugin data cleaned up during uninstall');
} 