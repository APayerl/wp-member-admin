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
        
        // Lägg till donation-banner
        add_action('admin_notices', [$this, 'showDonationNotice']);
        add_action('wp_ajax_member_admin_dismiss_donation', [$this, 'dismissDonationNotice']);
        
        // Lägg till donation-länkar på plugin-kortet
        add_filter('plugin_action_links_' . plugin_basename(MEMBER_ADMIN_PLUGIN_FILE), [$this, 'addPluginActionLinks']);
    }
    
    /**
     * Visa donation-notice (kan stängas bort)
     */
    public function showDonationNotice() {
        // Visa endast på användarsidan och för admins
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'users' || !current_user_can('manage_options')) {
            return;
        }
        
        // Kontrollera om användaren har stängt bort det (pausat i 2 månader)
        $dismissedTime = get_user_meta(get_current_user_id(), 'member_admin_donation_dismissed', true);
        if ($dismissedTime && (time() - $dismissedTime) < (2 * 30 * 24 * 60 * 60)) {
            return; // Pausad i 2 månader
        }
        
        // För testning - ta bort detta senare om du vill ha väntetid
        // $installDate = get_option('member_admin_install_date');
        // if (!$installDate || (time() - $installDate) < (3 * 24 * 60 * 60)) {
        //     return;
        // }
        
        ?>
        <div class="notice notice-info is-dismissible" id="member-admin-donation-notice" style="border-left-color: #0073aa; background: #f0f8ff;">
            <div style="display: flex; align-items: center; padding: 10px 0;">
                <div style="margin-right: 15px; font-size: 24px;">☕</div>
                <div style="flex: 1;">
                    <p style="margin: 0; font-size: 14px;">
                        <strong><?php _e('Gillar du Member Admin?', 'member-admin'); ?></strong><br>
                        <?php _e('Detta plugin utvecklas på fritiden. Om det hjälper dig, överväg att köpa en kaffe till utvecklaren!', 'member-admin'); ?>
                    </p>
                </div>
                <div style="margin-left: 15px;">
                    <a href="https://buymeacoffee.com/payerl" target="_blank" class="button button-secondary" style="background: #FFDD00; border-color: #FFDD00; color: #000; text-decoration: none; margin-right: 10px;">
                        ☕ <?php _e('Köp en kaffe', 'member-admin'); ?>
                    </a>
                    <a href="https://thanks.dev/apayerl" target="_blank" class="button button-secondary" style="background: #000; border-color: #000; color: #fff; text-decoration: none;">
                        💝 <?php _e('Thanks.dev', 'member-admin'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#member-admin-donation-notice').on('click', '.notice-dismiss', function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'member_admin_dismiss_donation',
                            nonce: '<?php echo wp_create_nonce('member_admin_dismiss_donation'); ?>'
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Stäng av donation-notice (pausa i 2 månader)
     */
    public function dismissDonationNotice() {
        check_ajax_referer('member_admin_dismiss_donation', 'nonce');
        
        if (current_user_can('manage_options')) {
            update_user_meta(get_current_user_id(), 'member_admin_donation_dismissed', time());
        }
        
        wp_die();
    }
    
    /**
     * Lägg till donation-länkar på plugin-kortet
     */
    public function addPluginActionLinks($links) {
        $donationLinks = [
            '<a href="https://buymeacoffee.com/payerl" target="_blank" style="color: #d63638; font-weight: bold;">☕ ' . __('Köp en kaffe', 'member-admin') . '</a>',
            '<a href="https://thanks.dev/apayerl" target="_blank" style="color: #d63638; font-weight: bold;">💝 ' . __('Thanks.dev', 'member-admin') . '</a>'
        ];
        
        return array_merge($links, $donationLinks);
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
    
    // Spara installationsdatum för donation-banner
    if (!get_option('member_admin_install_date')) {
        add_option('member_admin_install_date', current_time('timestamp'));
    }
    
}

/**
 * Avaktivering av plugin
 */
function member_admin_deactivate() {
    // Rensa eventuella tillfälliga data
    delete_transient('member_admin_acf_fields');
} 