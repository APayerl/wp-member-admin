<?php
/**
 * Plugin Name: Member Admin
 * Plugin URI: https://github.com/APayerl/wp-member-admin
 * Description: Anpassa användar-listan med ACF-fält för WordPress 6.8.1
 * Version: 1.0.0
 * Author: Anders Payerl
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
        
        // Ladda klasser alltid
        $this->loadClasses();
        
        // Kontrollera beroenden och visa meddelande om nödvändigt
        if (!$this->isDependenciesMet()) {
            add_action('admin_notices', [$this, 'showDependencyNotice']);
        }
        
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
        

        
        // Initialisera ACF-beroende klasser endast om ACF finns
        if ($this->isDependenciesMet()) {
            MemberAdminACFFieldManager::getInstance();
            MemberAdminUserListCustomizer::getInstance();
            MemberAdminInterface::getInstance();
        }
        
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

// ============================================
// EXPORT-FUNKTIONALITET (Enkla funktioner)
// ============================================

// Lägg till export-meny (körs på admin_menu hook)
add_action('admin_menu', 'member_admin_add_export_menu');

/**
 * Lägg till export-meny under Användare
 */
function member_admin_add_export_menu() {
    add_users_page(
        __('Exportera Användare', 'member-admin'),  // Sidtitel
        __('Exportera Användare', 'member-admin'),  // Menynamn i adminmenyn
        'manage_options',                           // Behörighetsnivå
        'member-admin-export',                      // Slug för sidan
        'member_admin_render_export_page'           // Callback-funktion som visar innehållet
    );
}

/**
 * Rendera export-sidan
 */
function member_admin_render_export_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Exportera Användare', 'member-admin'); ?></h1>
        <p><?php _e('Välj vilka fält som ska inkluderas i CSV-exporten och ladda ner användardata.', 'member-admin'); ?></p>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="member-admin-export-form">
            <?php wp_nonce_field('member_admin_export', 'member_admin_export_nonce'); ?>
            <input type="hidden" name="action" value="member_admin_export_users">
            
            <div style="display: flex; gap: 30px; margin-top: 20px;">
                <!-- WordPress Standard-fält -->
                <div style="flex: 1;">
                    <h2><?php _e('WordPress Användarfält', 'member-admin'); ?></h2>
                    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                        <?php member_admin_render_wp_fields(); ?>
                    </div>
                </div>
                
                <!-- ACF-fält -->
                <div style="flex: 1;">
                    <h2><?php _e('ACF-fält', 'member-admin'); ?></h2>
                    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                        <?php member_admin_render_acf_fields(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Filtreringsinställningar -->
            <div style="margin-top: 30px;">
                <h2><?php _e('Filtrering av användare', 'member-admin'); ?></h2>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                    <div id="filter-container">
                        <div class="filter-row" style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <label style="min-width: 120px;"><?php _e('Filtrera på fält:', 'member-admin'); ?></label>
                            <select name="filter_field" id="filter-field-select" style="min-width: 200px;">
                                <option value=""><?php _e('Välj fält att filtrera på', 'member-admin'); ?></option>
                                <optgroup label="<?php _e('WordPress-fält', 'member-admin'); ?>">
                                    <?php member_admin_render_filter_wp_options(); ?>
                                </optgroup>
                                <?php if (class_exists('ACF') && function_exists('get_field')): ?>
                                <optgroup label="<?php _e('ACF-fält', 'member-admin'); ?>">
                                    <?php member_admin_render_filter_acf_options(); ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                            <select name="filter_condition" id="filter-condition-select" style="min-width: 150px;">
                                <option value="not_empty"><?php _e('Har ett värde', 'member-admin'); ?></option>
                                <option value="empty"><?php _e('Är tomt', 'member-admin'); ?></option>
                                <option value="equals"><?php _e('Är lika med', 'member-admin'); ?></option>
                                <option value="contains"><?php _e('Innehåller', 'member-admin'); ?></option>
                                <option value="starts_with"><?php _e('Börjar med', 'member-admin'); ?></option>
                            </select>
                            <input type="text" name="filter_value" id="filter-value-input" placeholder="<?php _e('Värde att filtrera på', 'member-admin'); ?>" style="min-width: 200px;">
                            <select name="filter_value_select" id="filter-value-select" style="min-width: 200px; display: none;">
                                <option value=""><?php _e('Välj värde', 'member-admin'); ?></option>
                            </select>
                        </div>
                        <p class="description">
                            <?php _e('Endast användare som matchar filtreringskriteriet kommer att exporteras. Lämna tomt för att exportera alla användare.', 'member-admin'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Export-inställningar -->
            <div style="margin-top: 30px;">
                <h2><?php _e('Export-inställningar', 'member-admin'); ?></h2>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Användarroller', 'member-admin'); ?></th>
                            <td>
                                <?php member_admin_render_user_roles(); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('CSV-avgränsare', 'member-admin'); ?></th>
                            <td>
                                <select name="csv_delimiter">
                                    <option value=","><?php _e('Komma (,)', 'member-admin'); ?></option>
                                    <option value=";"><?php _e('Semikolon (;)', 'member-admin'); ?></option>
                                    <option value="\t"><?php _e('Tab', 'member-admin'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Teckenuppsättning', 'member-admin'); ?></th>
                            <td>
                                <select name="charset">
                                    <option value="UTF-8">UTF-8</option>
                                    <option value="ISO-8859-1">ISO-8859-1 (Latin-1)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Export-knappar -->
            <div style="margin-top: 30px;">
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Exportera CSV', 'member-admin'); ?>">
                    <button type="button" id="select-all-wp" class="button" style="margin-left: 10px;"><?php _e('Välj alla WP-fält', 'member-admin'); ?></button>
                    <button type="button" id="select-all-acf" class="button"><?php _e('Välj alla ACF-fält', 'member-admin'); ?></button>
                    <button type="button" id="select-none" class="button"><?php _e('Avmarkera alla', 'member-admin'); ?></button>
                </p>
            </div>
        </form>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Välj alla WordPress-fält
        $('#select-all-wp').on('click', function() {
            $('#wp-fields input[type="checkbox"]').prop('checked', true);
        });
        
        // Välj alla ACF-fält
        $('#select-all-acf').on('click', function() {
            $('#acf-fields input[type="checkbox"]').prop('checked', true);
        });
        
        // Avmarkera alla
        $('#select-none').on('click', function() {
            $('#wp-fields input[type="checkbox"], #acf-fields input[type="checkbox"]').prop('checked', false);
        });
        
        // Hantera filterfältets ändringar
        $('#filter-field-select').on('change', function() {
            var selectedField = $(this).val();
            var conditionSelect = $('#filter-condition-select');
            var valueInput = $('#filter-value-input');
            var valueSelect = $('#filter-value-select');
            
            if (selectedField === '') {
                conditionSelect.val('not_empty');
                valueInput.hide();
                valueSelect.hide();
                return;
            }
            
            // Visa/dölj värdefält baserat på villkor
            updateFilterValueVisibility();
            
            // Ladda kända värden för fältet via AJAX
            loadFieldValues(selectedField);
        });
        
        // Hantera villkorsändringar
        $('#filter-condition-select').on('change', function() {
            updateFilterValueVisibility();
        });
        
        function updateFilterValueVisibility() {
            var condition = $('#filter-condition-select').val();
            var valueInput = $('#filter-value-input');
            var valueSelect = $('#filter-value-select');
            
            if (condition === 'empty' || condition === 'not_empty') {
                valueInput.hide();
                valueSelect.hide();
            } else {
                var selectedField = $('#filter-field-select').val();
                if (selectedField && valueSelect.children().length > 1) {
                    valueInput.hide();
                    valueSelect.show();
                } else {
                    valueInput.show();
                    valueSelect.hide();
                }
            }
        }
        
        function loadFieldValues(fieldKey) {
            var valueSelect = $('#filter-value-select');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'member_admin_get_field_values',
                    field_key: fieldKey,
                    nonce: '<?php echo wp_create_nonce('member_admin_field_values'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        valueSelect.empty().append('<option value=""><?php echo esc_js(__('Välj värde', 'member-admin')); ?></option>');
                        
                        $.each(response.data, function(index, value) {
                            valueSelect.append('<option value="' + value + '">' + value + '</option>');
                        });
                        
                        updateFilterValueVisibility();
                    }
                }
            });
        }
        
        // Validering innan submit
        $('#member-admin-export-form').on('submit', function(e) {
            var checkedFields = $('#wp-fields input[type="checkbox"]:checked, #acf-fields input[type="checkbox"]:checked').length;
            if (checkedFields === 0) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Välj minst ett fält att exportera.', 'member-admin')); ?>');
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * Rendera WordPress standard-fält
 */
function member_admin_render_wp_fields() {
    $wpFields = member_admin_get_wp_fields();
    
    echo '<div id="wp-fields">';
    foreach ($wpFields as $key => $label) {
        $checked = in_array($key, ['ID', 'user_login', 'user_email', 'display_name']) ? 'checked' : '';
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="checkbox" name="wp_fields[]" value="' . esc_attr($key) . '" ' . $checked . '> ';
        echo esc_html($label);
        echo '</label>';
    }
    echo '</div>';
}

/**
 * Rendera ACF-fält
 */
function member_admin_render_acf_fields() {
    echo '<div id="acf-fields">';
    
    if (!class_exists('ACF') || !function_exists('get_field')) {
        echo '<p><em>' . __('ACF plugin krävs för att visa ACF-fält.', 'member-admin') . '</em></p>';
    } else {
        $fieldManager = MemberAdminACFFieldManager::getInstance();
        $acfFields = $fieldManager->getUserACFFields();
        
        if (empty($acfFields)) {
            echo '<p><em>' . __('Inga ACF-fält hittades för användare.', 'member-admin') . '</em></p>';
        } else {
            foreach ($acfFields as $key => $field) {
                echo '<label style="display: block; margin-bottom: 8px;">';
                echo '<input type="checkbox" name="acf_fields[]" value="' . esc_attr($key) . '"> ';
                echo esc_html($field['label']);
                echo '<br><small style="color: #666;">' . esc_html($field['group']) . ' | ' . esc_html($field['type']) . '</small>';
                echo '</label>';
            }
        }
    }
    
    echo '</div>';
}

/**
 * Rendera användarroller
 */
function member_admin_render_user_roles() {
    global $wp_roles;
    
    echo '<label style="display: block; margin-bottom: 8px;">';
    echo '<input type="checkbox" name="user_roles[]" value="all" checked> ';
    echo '<strong>' . __('Alla roller', 'member-admin') . '</strong>';
    echo '</label>';
    
    foreach ($wp_roles->roles as $role => $details) {
        echo '<label style="display: block; margin-bottom: 5px; margin-left: 20px;">';
        echo '<input type="checkbox" name="user_roles[]" value="' . esc_attr($role) . '"> ';
        echo esc_html($details['name']);
        echo '</label>';
    }
}

/**
 * Renderera WordPress-fält för filtrering
 */
function member_admin_render_filter_wp_options() {
    $wpFields = member_admin_get_filterable_wp_fields();
    
    foreach ($wpFields as $key => $label) {
        echo '<option value="wp_' . esc_attr($key) . '">' . esc_html($label) . '</option>';
    }
}

/**
 * Renderera ACF-fält för filtrering
 */
function member_admin_render_filter_acf_options() {
    if (!class_exists('ACF') || !function_exists('get_field')) {
        return;
    }
    
    $fieldManager = MemberAdminACFFieldManager::getInstance();
    $acfFields = $fieldManager->getUserACFFields();
    
    foreach ($acfFields as $key => $field) {
        echo '<option value="acf_' . esc_attr($key) . '">' . esc_html($field['label']) . ' (' . esc_html($field['type']) . ')</option>';
    }
}

/**
 * Hämta filterbara WordPress-fält
 */
function member_admin_get_filterable_wp_fields() {
    return [
        'user_login' => __('Användarnamn', 'member-admin'),
        'user_email' => __('E-post', 'member-admin'),
        'display_name' => __('Visningsnamn', 'member-admin'),
        'first_name' => __('Förnamn', 'member-admin'),
        'last_name' => __('Efternamn', 'member-admin'),
        'user_url' => __('Webbplats', 'member-admin'),
        'description' => __('Biografisk info', 'member-admin'),
        'locale' => __('Språk', 'member-admin'),
        'roles' => __('Användarroller', 'member-admin')
    ];
}

/**
 * Hämta WordPress standard-fält
 */
function member_admin_get_wp_fields() {
    return [
        'ID' => __('Användar-ID', 'member-admin'),
        'user_login' => __('Användarnamn', 'member-admin'),
        'user_email' => __('E-post', 'member-admin'),
        'user_nicename' => __('Användarens trevliga namn', 'member-admin'),
        'user_url' => __('Webbplats', 'member-admin'),
        'user_registered' => __('Registreringsdatum', 'member-admin'),
        'user_status' => __('Använderstatus', 'member-admin'),
        'display_name' => __('Visningsnamn', 'member-admin'),
        'first_name' => __('Förnamn', 'member-admin'),
        'last_name' => __('Efternamn', 'member-admin'),
        'nickname' => __('Smeknamn', 'member-admin'),
        'description' => __('Biografisk info', 'member-admin'),
        'rich_editing' => __('Rich Editor', 'member-admin'),
        'syntax_highlighting' => __('Syntaxmarkering', 'member-admin'),
        'comment_shortcuts' => __('Kommentarsgenvägar', 'member-admin'),
        'admin_color' => __('Adminfärgschema', 'member-admin'),
        'use_ssl' => __('Använd SSL', 'member-admin'),
        'show_admin_bar_front' => __('Visa admin-bar fram', 'member-admin'),
        'locale' => __('Språk', 'member-admin'),
        'roles' => __('Användarroller', 'member-admin')
    ];
}

/**
 * AJAX-hanterare för att hämta kända fältvärden
 */
add_action('wp_ajax_member_admin_get_field_values', 'member_admin_ajax_get_field_values');
function member_admin_ajax_get_field_values() {
    // Kontrollera nonce
    if (!wp_verify_nonce($_POST['nonce'], 'member_admin_field_values')) {
        wp_die(__('Säkerhetsverifiering misslyckades.', 'member-admin'));
    }
    
    // Kontrollera behörighet
    if (!current_user_can('manage_options')) {
        wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'member-admin'));
    }
    
    $fieldKey = sanitize_text_field($_POST['field_key']);
    $values = member_admin_get_unique_field_values($fieldKey);
    
    wp_send_json_success($values);
}

/**
 * Hämta unika värden för ett fält
 */
function member_admin_get_unique_field_values($fieldKey) {
    $values = [];
    $users = get_users(['fields' => 'all']);
    
    foreach ($users as $user) {
        if (strpos($fieldKey, 'wp_') === 0) {
            // WordPress-fält
            $wpField = substr($fieldKey, 3);
            $value = member_admin_get_user_field_value($user, $wpField);
        } elseif (strpos($fieldKey, 'acf_') === 0) {
            // ACF-fält
            $acfField = substr($fieldKey, 4);
            $value = get_field($acfField, 'user_' . $user->ID);
            
            // Formatera värdet för visning
            if (class_exists('ACF')) {
                $fieldManager = MemberAdminACFFieldManager::getInstance();
                $acfFields = $fieldManager->getUserACFFields();
                if (isset($acfFields[$acfField])) {
                    $value = member_admin_format_export_value($value, $acfFields[$acfField]);
                }
            }
        } else {
            continue;
        }
        
        if (!empty($value) && !in_array($value, $values)) {
            $values[] = $value;
        }
    }
    
    // Sortera värden
    sort($values);
    
    // Begränsa till max 100 värden för prestanda
    return array_slice($values, 0, 100);
}

/**
 * Hantera CSV-export (lägg till hook för detta)
 */
add_action('admin_post_member_admin_export_users', 'member_admin_handle_export');
function member_admin_handle_export() {
    // Kontrollera nonce
    if (!wp_verify_nonce($_POST['member_admin_export_nonce'], 'member_admin_export')) {
        wp_die(__('Säkerhetsverifiering misslyckades.', 'member-admin'));
    }
    
    // Kontrollera behörighet
    if (!current_user_can('manage_options')) {
        wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'member-admin'));
    }
    
    // Hämta valda fält
    $wpFields = isset($_POST['wp_fields']) ? array_map('sanitize_text_field', $_POST['wp_fields']) : [];
    $acfFields = isset($_POST['acf_fields']) ? array_map('sanitize_text_field', $_POST['acf_fields']) : [];
    $userRoles = isset($_POST['user_roles']) ? array_map('sanitize_text_field', $_POST['user_roles']) : ['all'];
    $delimiter = isset($_POST['csv_delimiter']) ? sanitize_text_field($_POST['csv_delimiter']) : ',';
    $charset = isset($_POST['charset']) ? sanitize_text_field($_POST['charset']) : 'UTF-8';
    
    // Hämta filtreringsinställningar
    $filterField = isset($_POST['filter_field']) ? sanitize_text_field($_POST['filter_field']) : '';
    $filterCondition = isset($_POST['filter_condition']) ? sanitize_text_field($_POST['filter_condition']) : 'not_empty';
    $filterValue = isset($_POST['filter_value']) ? sanitize_text_field($_POST['filter_value']) : '';
    $filterValueSelect = isset($_POST['filter_value_select']) ? sanitize_text_field($_POST['filter_value_select']) : '';
    
    // Använd select-värde om det finns, annars input-värde
    if (!empty($filterValueSelect)) {
        $filterValue = $filterValueSelect;
    }
    
    // Konvertera \t till riktig tab
    if ($delimiter === '\t') {
        $delimiter = "\t";
    }
    
    // Kontrollera att minst ett fält är valt
    if (empty($wpFields) && empty($acfFields)) {
        wp_die(__('Inga fält valda för export.', 'member-admin'));
    }
    
    // Hämta användare
    $users = member_admin_get_users_for_export($userRoles, $filterField, $filterCondition, $filterValue);
    
    // Generera CSV
    member_admin_generate_csv($users, $wpFields, $acfFields, $delimiter, $charset);
}

/**
 * Hämta användare baserat på roller och filtrering
 */
function member_admin_get_users_for_export($userRoles, $filterField = '', $filterCondition = 'not_empty', $filterValue = '') {
    $args = ['fields' => 'all'];
    
    if (!in_array('all', $userRoles)) {
        $args['role__in'] = $userRoles;
    }
    
    $users = get_users($args);
    
    // Applicera filtreringen om den är specificerad
    if (!empty($filterField)) {
        $users = member_admin_filter_users($users, $filterField, $filterCondition, $filterValue);
    }
    
    return $users;
}

/**
 * Filtrera användare baserat på fältkriterier
 */
function member_admin_filter_users($users, $filterField, $filterCondition, $filterValue) {
    $filteredUsers = [];
    
    foreach ($users as $user) {
        $fieldValue = member_admin_get_filter_field_value($user, $filterField);
        
        if (member_admin_matches_filter_condition($fieldValue, $filterCondition, $filterValue)) {
            $filteredUsers[] = $user;
        }
    }
    
    return $filteredUsers;
}

/**
 * Hämta fältvärde för filtrering
 */
function member_admin_get_filter_field_value($user, $filterField) {
    if (strpos($filterField, 'wp_') === 0) {
        // WordPress-fält
        $wpField = substr($filterField, 3);
        return member_admin_get_user_field_value($user, $wpField);
    } elseif (strpos($filterField, 'acf_') === 0) {
        // ACF-fält
        $acfField = substr($filterField, 4);
        $value = get_field($acfField, 'user_' . $user->ID);
        
        // Formatera värdet för jämförelse
        if (class_exists('ACF')) {
            $fieldManager = MemberAdminACFFieldManager::getInstance();
            $acfFields = $fieldManager->getUserACFFields();
            if (isset($acfFields[$acfField])) {
                $value = member_admin_format_export_value($value, $acfFields[$acfField]);
            }
        }
        
        return $value;
    }
    
    return '';
}

/**
 * Kontrollera om ett värde matchar filtervillkoret
 */
function member_admin_matches_filter_condition($fieldValue, $condition, $filterValue) {
    switch ($condition) {
        case 'empty':
            return empty($fieldValue);
        
        case 'not_empty':
            return !empty($fieldValue);
        
        case 'equals':
            return strcasecmp($fieldValue, $filterValue) === 0;
        
        case 'contains':
            return stripos($fieldValue, $filterValue) !== false;
        
        case 'starts_with':
            return stripos($fieldValue, $filterValue) === 0;
        
        default:
            return false;
    }
}

/**
 * Generera och ladda ner CSV
 */
function member_admin_generate_csv($users, $wpFields, $acfFields, $delimiter, $charset) {
    // Sätt headers för nedladdning
    $filename = 'anvandare_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=' . $charset);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Öppna output stream
    $output = fopen('php://output', 'w');
    
    // Konvertera till ISO-8859-1 om valt
    $convertCharset = ($charset === 'ISO-8859-1');
    
    // Skapa header-rad
    $headers = [];
    
    // WordPress-fält headers
    $wpFieldLabels = member_admin_get_wp_fields();
    foreach ($wpFields as $field) {
        $header = isset($wpFieldLabels[$field]) ? $wpFieldLabels[$field] : $field;
        $headers[] = $convertCharset ? utf8_decode($header) : $header;
    }
    
    // ACF-fält headers
    $acfFieldsData = [];
    if (class_exists('ACF') && function_exists('get_field')) {
        $fieldManager = MemberAdminACFFieldManager::getInstance();
        $acfFieldsData = $fieldManager->getUserACFFields();
    }
    
    foreach ($acfFields as $fieldKey) {
        if (isset($acfFieldsData[$fieldKey])) {
            $header = $acfFieldsData[$fieldKey]['label'];
            $headers[] = $convertCharset ? utf8_decode($header) : $header;
        }
    }
    
    // Skriv headers
    fputcsv($output, $headers, $delimiter);
    
    // Skriv användardata
    foreach ($users as $user) {
        $row = [];
        
        // WordPress-fält data
        foreach ($wpFields as $field) {
            $value = member_admin_get_user_field_value($user, $field);
            $row[] = $convertCharset ? utf8_decode($value) : $value;
        }
        
        // ACF-fält data
        foreach ($acfFields as $fieldKey) {
            if (isset($acfFieldsData[$fieldKey])) {
                $acfValue = get_field($fieldKey, 'user_' . $user->ID);
                $formattedValue = member_admin_format_export_value($acfValue, $acfFieldsData[$fieldKey]);
                $row[] = $convertCharset ? utf8_decode($formattedValue) : $formattedValue;
            } else {
                $row[] = '';
            }
        }
        
        fputcsv($output, $row, $delimiter);
    }
    
    fclose($output);
    exit;
}

/**
 * Hämta WordPress fält-värde
 */
function member_admin_get_user_field_value($user, $field) {
    switch ($field) {
        case 'roles':
            return implode(', ', $user->roles);
        case 'user_registered':
            return date('Y-m-d H:i:s', strtotime($user->user_registered));
        default:
            if (isset($user->$field)) {
                return $user->$field;
            }
            return get_user_meta($user->ID, $field, true);
    }
}

/**
 * Formatera värde för export
 */
function member_admin_format_export_value($value, $field) {
    if (empty($value)) {
        return '';
    }
    
    switch ($field['type']) {
        case 'checkbox':
            return is_array($value) ? implode(', ', $value) : $value;
        case 'true_false':
            return $value ? 'Ja' : 'Nej';
        case 'date_picker':
            return member_admin_format_date_for_export($value);
        case 'image':
        case 'file':
            if (is_array($value) && isset($value['url'])) {
                return $value['url'];
            } elseif (is_numeric($value)) {
                return wp_get_attachment_url($value) ?: '';
            }
            return '';
        case 'user':
            if (is_numeric($value)) {
                $user = get_userdata($value);
                return $user ? $user->display_name : '';
            }
            return '';
        case 'post_object':
            if (is_numeric($value)) {
                return get_the_title($value) ?: '';
            }
            return '';
        default:
            return is_array($value) ? implode(', ', $value) : strval($value);
    }
}

/**
 * Formatera datum för export
 */
function member_admin_format_date_for_export($value) {
    if (empty($value)) {
        return '';
    }
    
    // ACF sparar datum som Ymd
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }
    
    return $value;
}

// Aktiverings-, avaktiverings- och avinstallationshooks
register_activation_hook(__FILE__, 'member_admin_activate');
register_deactivation_hook(__FILE__, 'member_admin_deactivate');
register_uninstall_hook(__FILE__, 'member_admin_uninstall');

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

/**
 * Avinstallation av plugin
 */
function member_admin_uninstall() {
    // Anropa uninstall.php för fullständig rensning
    if (file_exists(dirname(__FILE__) . '/uninstall.php')) {
        include_once dirname(__FILE__) . '/uninstall.php';
    }
} 