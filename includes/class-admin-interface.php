<?php
/**
 * Admin Interface
 * Hanterar admin-gränssnittet för Member Admin plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klass för admin-gränssnittet
 */
class MemberAdminInterface {
    
    private static $instance = null;
    private $customizer;
    
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
        $this->customizer = MemberAdminUserListCustomizer::getInstance();
        $this->init();
    }
    
    /**
     * Initialisera hooks
     */
    private function init() {
        add_action('admin_head-users.php', [$this, 'addCustomizeButton']);
        add_action('admin_footer-users.php', [$this, 'addCustomizeModal']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX-hooks
        add_action('wp_ajax_member_admin_get_fields', [$this, 'ajaxGetFields']);
        add_action('wp_ajax_member_admin_update_fields', [$this, 'ajaxUpdateFields']);
    }
    
    /**
     * Lägg till anpassa-knapp på användarsidan
     */
    public function addCustomizeButton() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Lägg till anpassa-knapp i sidhuvudet
                $('.page-title-action').after('<a href="#" id="member-admin-customize-btn" class="page-title-action" style="margin-left: 10px;"><?php echo esc_js(__('Anpassa kolumner', 'member-admin')); ?></a>');
                
                // Hantera klick på anpassa-knapp
                $('#member-admin-customize-btn').on('click', function(e) {
                    e.preventDefault();
                    $('#member-admin-modal').show();
                    // Vänta tills modal är synlig innan vi laddar fält
                    setTimeout(function() {
                        if (typeof window.loadMemberAdminFields === 'function') {
                            window.loadMemberAdminFields();
                        }
                    }, 100);
                });
            });
        </script>
        <?php
    }
    
    /**
     * Lägg till modal för anpassning
     */
    public function addCustomizeModal() {
        ?>
        <div id="member-admin-modal" class="member-admin-modal" style="display: none;">
            <div class="member-admin-modal-content">
                <div class="member-admin-modal-header">
                    <h2><?php _e('Anpassa användarkolumner', 'member-admin'); ?></h2>
                    <span class="member-admin-close">&times;</span>
                </div>
                
                <div class="member-admin-modal-body">
                    <p><?php _e('Välj vilka ACF-fält som ska visas som kolumner i användarlistan:', 'member-admin'); ?></p>
                    
                    <div id="member-admin-loading" style="display: none;">
                        <p><?php _e('Laddar fält...', 'member-admin'); ?></p>
                    </div>
                    
                    <div id="member-admin-fields-container">
                        <!-- Fält laddas via AJAX -->
                    </div>
                    
                    <div id="member-admin-no-fields" style="display: none;">
                        <p><em><?php _e('Inga ACF-fält hittades för användare. Se till att du har skapat ACF-fältgrupper som är kopplade till användare.', 'member-admin'); ?></em></p>
                    </div>
                </div>
                
                <div class="member-admin-modal-footer">
                    <button type="button" id="member-admin-save-btn" class="button button-primary">
                        <?php _e('Spara ändringar', 'member-admin'); ?>
                    </button>
                    <button type="button" id="member-admin-cancel-btn" class="button">
                        <?php _e('Avbryt', 'member-admin'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            // Gör funktionen globalt tillgänglig
            window.loadMemberAdminFields = function() {
                const container = jQuery('#member-admin-fields-container');
                const loading = jQuery('#member-admin-loading');
                const noFields = jQuery('#member-admin-no-fields');
                
                container.empty();
                loading.show();
                noFields.hide();
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'member_admin_get_fields',
                        nonce: '<?php echo wp_create_nonce('member_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        loading.hide();
                        
                        if (response.success && response.data.fields) {
                            const fields = response.data.fields;
                            const enabledFields = response.data.enabled || [];
                            
                            if (Object.keys(fields).length === 0) {
                                noFields.show();
                                return;
                            }
                            
                            let html = '<div class="member-admin-fields-grid">';
                            
                            for (const [key, field] of Object.entries(fields)) {
                                const isChecked = enabledFields.includes(key) ? 'checked' : '';
                                const fieldTypeLabel = getMemberAdminFieldTypeLabel(field.type);
                                
                                html += `
                                    <div class="member-admin-field-item">
                                        <label>
                                            <input type="checkbox" name="member_admin_fields[]" value="${key}" ${isChecked}>
                                            <strong>${field.label}</strong>
                                            <br>
                                            <small>${field.group} | ${fieldTypeLabel}</small>
                                        </label>
                                    </div>
                                `;
                            }
                            
                            html += '</div>';
                            container.html(html);
                        } else {
                            noFields.show();
                        }
                    },
                    error: function() {
                        loading.hide();
                        alert('<?php echo esc_js(__('Ett fel uppstod vid laddning av fält.', 'member-admin')); ?>');
                    }
                });
            };
            
            function getMemberAdminFieldTypeLabel(type) {
                const typeLabels = {
                    'text': '<?php echo esc_js(__('Text', 'member-admin')); ?>',
                    'textarea': '<?php echo esc_js(__('Textområde', 'member-admin')); ?>',
                    'number': '<?php echo esc_js(__('Nummer', 'member-admin')); ?>',
                    'email': '<?php echo esc_js(__('E-post', 'member-admin')); ?>',
                    'url': '<?php echo esc_js(__('URL', 'member-admin')); ?>',
                    'select': '<?php echo esc_js(__('Dropdown', 'member-admin')); ?>',
                    'checkbox': '<?php echo esc_js(__('Kryssruta', 'member-admin')); ?>',
                    'radio': '<?php echo esc_js(__('Alternativknappar', 'member-admin')); ?>',
                    'true_false': '<?php echo esc_js(__('Sant/Falskt', 'member-admin')); ?>',
                    'date_picker': '<?php echo esc_js(__('Datum', 'member-admin')); ?>',
                    'time_picker': '<?php echo esc_js(__('Tid', 'member-admin')); ?>',
                    'date_time_picker': '<?php echo esc_js(__('Datum & Tid', 'member-admin')); ?>',
                    'image': '<?php echo esc_js(__('Bild', 'member-admin')); ?>',
                    'file': '<?php echo esc_js(__('Fil', 'member-admin')); ?>',
                    'user': '<?php echo esc_js(__('Användare', 'member-admin')); ?>',
                    'post_object': '<?php echo esc_js(__('Inlägg', 'member-admin')); ?>'
                };
                
                return typeLabels[type] || type;
            }
            
            jQuery(document).ready(function($) {
                // Stäng modal
                $('.member-admin-close, #member-admin-cancel-btn').on('click', function() {
                    $('#member-admin-modal').hide();
                });
                
                // Stäng modal vid klick utanför
                $(window).on('click', function(e) {
                    if (e.target.id === 'member-admin-modal') {
                        $('#member-admin-modal').hide();
                    }
                });
                
                // Spara ändringar
                $('#member-admin-save-btn').on('click', function() {
                    const $saveBtn = $(this);
                    const originalText = $saveBtn.text();
                    
                    // Visa laddningsindikator
                    $saveBtn.prop('disabled', true).text('<?php echo esc_js(__('Sparar...', 'member-admin')); ?>');
                    
                    const selectedFields = [];
                    $('input[name="member_admin_fields[]"]:checked').each(function() {
                        selectedFields.push($(this).val());
                    });
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'member_admin_update_fields',
                            fields: selectedFields,
                            nonce: '<?php echo wp_create_nonce('member_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Visa framgångsmeddelande kort
                                $saveBtn.text('<?php echo esc_js(__('Sparat!', 'member-admin')); ?>');
                                
                                setTimeout(function() {
                                    $('#member-admin-modal').hide();
                                    location.reload(); // Ladda om sidan för att visa ändringarna
                                }, 800);
                            } else {
                                $saveBtn.prop('disabled', false).text(originalText);
                                const errorMsg = response.data && response.data.message ? 
                                    response.data.message : 
                                    '<?php echo esc_js(__('Ett fel uppstod vid sparning.', 'member-admin')); ?>';
                                alert(errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            $saveBtn.prop('disabled', false).text(originalText);
                            console.error('AJAX Error:', status, error);
                            alert('<?php echo esc_js(__('Ett fel uppstod vid sparning. Kontrollera konsolen för mer information.', 'member-admin')); ?>');
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Lägg till CSS och JavaScript
     */
    public function enqueueAssets($hook) {
        if ($hook !== 'users.php') {
            return;
        }
        
        // Lägg till CSS direkt i sidhuvudet
        add_action('admin_head', [$this, 'addModalCSS']);
    }
    
    /**
     * Lägg till CSS för modal
     */
    public function addModalCSS() {
        ?>
        <style>
            .member-admin-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }
            
            .member-admin-modal-content {
                background-color: #fff;
                margin: 5% auto;
                border: 1px solid #ccc;
                border-radius: 4px;
                width: 80%;
                max-width: 600px;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .member-admin-modal-header {
                padding: 20px 25px;
                border-bottom: 1px solid #ddd;
                position: relative;
            }
            
            .member-admin-modal-header h2 {
                margin: 0;
                font-size: 22px;
            }
            
            .member-admin-close {
                position: absolute;
                right: 20px;
                top: 20px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                color: #999;
            }
            
            .member-admin-close:hover {
                color: #000;
            }
            
            .member-admin-modal-body {
                padding: 25px;
            }
            
            .member-admin-modal-footer {
                padding: 20px 25px;
                border-top: 1px solid #ddd;
                text-align: right;
            }
            
            .member-admin-modal-footer .button {
                margin-left: 10px;
            }
            
            .member-admin-fields-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            
            .member-admin-field-item {
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                background: #f9f9f9;
            }
            
            .member-admin-field-item label {
                cursor: pointer;
                display: block;
            }
            
            .member-admin-field-item input[type="checkbox"] {
                margin-right: 8px;
                margin-bottom: 5px;
            }
            
            .member-admin-field-item small {
                color: #666;
                font-size: 12px;
            }
            
            .member-admin-field-item:hover {
                background: #f0f0f0;
            }
            
            .member-admin-field-item input[type="checkbox"]:checked + strong {
                color: #0073aa;
            }
        </style>
        <?php
    }
    
    /**
     * AJAX: Hämta tillgängliga fält
     */
    public function ajaxGetFields() {
        check_ajax_referer('member_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'member-admin'));
        }
        
        $availableFields = $this->customizer->getAvailableFields();
        $enabledFields = $this->customizer->getEnabledFields();
        
        wp_send_json_success([
            'fields' => $availableFields,
            'enabled' => $enabledFields
        ]);
    }
    
    /**
     * AJAX: Uppdatera aktiverade fält
     */
    public function ajaxUpdateFields() {
        check_ajax_referer('member_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har inte behörighet att utföra denna åtgärd.', 'member-admin'));
        }
        
        $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : [];
        
        $success = $this->customizer->updateEnabledFields($fields);
        
        if ($success) {
            // Rensa cache när inställningar uppdateras
            MemberAdminACFFieldManager::getInstance()->clearFieldsCache();
            wp_send_json_success(['message' => __('Inställningar sparade!', 'member-admin')]);
        } else {
            wp_send_json_error(['message' => __('Kunde inte spara inställningar.', 'member-admin')]);
        }
    }
} 