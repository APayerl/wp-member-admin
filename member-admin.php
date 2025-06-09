<?php
/**
 * Plugin Name: Member Admin
 * Plugin URI: https://yoursite.com
 * Description: Anpassa användar-listan med ACF-fält för WordPress 6.8.1
 * Version: 1.0.0
 * Author: Din Namn
 * License: GPL v2 or later
 * Text Domain: member-admin
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 */

// Förhindra direkt åtkomst
if (!defined('ABSPATH')) {
    exit;
}

// Definiera plugin-konstanter
define('MEMBER_ADMIN_VERSION', '1.0.0');
define('MEMBER_ADMIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEMBER_ADMIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MEMBER_ADMIN_PLUGIN_FILE', __FILE__);

/**
 * Huvudklass för Member Admin plugin
 */
class MemberAdmin {
    
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    /**
     * Initialisera plugin
     */
    public function init() {
        // Ladda textdomän först
        $this->loadTextDomain();
        
        // Kontrollera beroenden
        if (!$this->isDependenciesMet()) {
            add_action('admin_notices', [$this, 'showDependencyNotice']);
            return;
        }
        
        // Ladda klasser i rätt ordning
        $this->loadClasses();
        
        // Initialisera klasser efter WordPress är redo
        add_action('admin_init', [$this, 'initializeClasses']);
    }
    
    /**
     * Kontrollera om beroenden är uppfyllda
     */
    private function isDependenciesMet() {
        return class_exists('ACF') && function_exists('get_field');
    }
    
    /**
     * Visa meddelande om saknade beroenden
     */
    public function showDependencyNotice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Member Admin kräver Advanced Custom Fields (ACF) plugin för att fungera.', 'member-admin');
        echo '</p></div>';
    }
    
    /**
     * Ladda textdomän
     */
    private function loadTextDomain() {
        load_plugin_textdomain('member-admin', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Ladda klasser i rätt ordning
     */
    private function loadClasses() {
        // Ladda i rätt ordning - dependencies först
        require_once MEMBER_ADMIN_PLUGIN_DIR . 'includes/class-acf-field-manager.php';
        require_once MEMBER_ADMIN_PLUGIN_DIR . 'includes/class-user-list-customizer.php';
        require_once MEMBER_ADMIN_PLUGIN_DIR . 'includes/class-admin-interface.php';
    }
    
    /**
     * Initialisera klasser
     */
    public function initializeClasses() {
        // Initialisera endast om vi är i admin och har rätt behörigheter
        if (!is_admin()) {
            return;
        }
        
        MemberAdminACFFieldManager::getInstance();
        MemberAdminUserListCustomizer::getInstance();
        MemberAdminInterface::getInstance();
    }
}

// Starta plugin
MemberAdmin::getInstance();

// Aktiverings- och avaktiveringshooks
register_activation_hook(__FILE__, 'member_admin_activate');
register_deactivation_hook(__FILE__, 'member_admin_deactivate');

/**
 * Aktivering av plugin
 */
function member_admin_activate() {
    // Kontrollera WordPress-version
    if (version_compare(get_bloginfo('version'), '6.8', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Member Admin kräver WordPress 6.8 eller senare.', 'member-admin'));
    }
    
    // Kontrollera PHP-version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Member Admin kräver PHP 7.4 eller senare.', 'member-admin'));
    }
    
    // Skapa standardinställningar
    $defaultSettings = [
        'enabled_fields' => [],
        'field_order' => []
    ];
    
    if (!get_option('member_admin_settings')) {
        add_option('member_admin_settings', $defaultSettings);
    }
}

/**
 * Avaktivering av plugin
 */
function member_admin_deactivate() {
    // Rensa eventuella tillfälliga data
    delete_transient('member_admin_acf_fields');
} 