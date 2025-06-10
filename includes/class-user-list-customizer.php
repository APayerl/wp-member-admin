<?php
/**
 * User List Customizer
 * Hanterar anpassning av användar-listan med ACF-fält
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klass för att anpassa användar-listan
 */
class MemberAdminUserListCustomizer {
    
    private static $instance = null;
    private $fieldManager;
    
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
        // Kontrollera att ACF är tillgängligt innan vi fortsätter
        if (!class_exists('ACF') || !function_exists('get_field')) {
            return;
        }
        
        $this->fieldManager = MemberAdminACFFieldManager::getInstance();
        $this->init();
    }
    
    /**
     * Initialisera hooks
     */
    private function init() {
        add_filter('manage_users_columns', [$this, 'addCustomColumns']);
        add_filter('manage_users_custom_column', [$this, 'displayCustomColumnContent'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'makeColumnsSortable']);
        add_action('pre_user_query', [$this, 'handleColumnSorting']);
        
        // Lägg till CSS och JavaScript för inline-redigering
        add_action('admin_head-users.php', [$this, 'addCustomCSS']);
        add_action('admin_footer-users.php', [$this, 'addInlineEditJS']);
        
        // AJAX hooks för inline-redigering
        add_action('wp_ajax_member_admin_update_field', [$this, 'ajaxUpdateField']);
    }
    
    /**
     * Lägg till anpassade kolumner till användar-listan
     */
    public function addCustomColumns($columns) {
        $settings = get_option('member_admin_settings', ['enabled_fields' => []]);
        $enabledFields = $settings['enabled_fields'];
        
        if (empty($enabledFields)) {
            return $columns;
        }
        
        $acfFields = $this->fieldManager->getUserACFFields();
        
        // Lägg till kolumner för varje aktiverat fält
        foreach ($enabledFields as $fieldKey) {
            if (isset($acfFields[$fieldKey])) {
                $field = $acfFields[$fieldKey];
                $columnKey = 'member_admin_' . $field['key'];
                $columns[$columnKey] = $field['label'] . ' <small>(' . $field['group'] . ')</small>';
            }
        }
        
        return $columns;
    }
    
    /**
     * Visa innehåll för anpassade kolumner (med inline-redigering)
     */
    public function displayCustomColumnContent($output, $columnName, $userId) {
        if (strpos($columnName, 'member_admin_') !== 0) {
            return $output;
        }
        
        // Kontrollera behörighet för redigering
        if (!current_user_can('edit_user', $userId)) {
            return $output;
        }
        
        // Extrahera fält-key från kolumnnamnet
        $fieldKey = str_replace('member_admin_', '', $columnName);
        
        $acfFields = $this->fieldManager->getUserACFFields();
        
        if (!isset($acfFields[$fieldKey])) {
            return '—';
        }
        
        $field = $acfFields[$fieldKey];
        $value = $this->fieldManager->getUserFieldValue($userId, $fieldKey);
        $formattedValue = $this->fieldManager->formatFieldValue($value, $field);
        
        // Skapa redigerbar kolumn
        return $this->createEditableField($userId, $fieldKey, $field, $value, $formattedValue);
    }
    
    /**
     * Skapa redigerbart fält baserat på ACF-fälttyp
     */
    private function createEditableField($userId, $fieldKey, $field, $value, $formattedValue) {
        $fieldType = $field['type'];
        $fieldName = $field['name'];
        $isEditable = $this->isFieldEditable($fieldType);
        
        if (!$isEditable) {
            return '<div class="member-admin-column">' . $formattedValue . '</div>';
        }
        
        $html = '<div class="member-admin-editable-field" data-user-id="' . $userId . '" data-field-key="' . $fieldKey . '" data-field-type="' . $fieldType . '">';
        $html .= '<div class="member-admin-display" style="cursor: pointer; padding: 2px 5px; border-radius: 3px;">';
        $html .= '<span class="member-admin-value">' . ($formattedValue ?: '—') . '</span>';
        $html .= '<span class="member-admin-edit-icon" style="margin-left: 5px; color: #666; font-size: 12px;">✏️</span>';
        $html .= '</div>';
        
        $html .= '<div class="member-admin-edit" style="display: none;">';
        $html .= $this->createEditInput($fieldKey, $field, $value);
        $html .= '<div class="member-admin-actions" style="margin-top: 3px;">';
        $html .= '<button class="button-primary member-admin-save" style="margin-right: 3px; font-size: 11px; padding: 2px 6px;">Spara</button>';
        $html .= '<button class="button member-admin-cancel" style="font-size: 11px; padding: 2px 6px;">Avbryt</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Skapa input-element baserat på fälttyp
     */
    private function createEditInput($fieldKey, $field, $value) {
        $fieldType = $field['type'];
        $fieldName = $field['name'];
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        
        switch ($fieldType) {
            case 'text':
            case 'email':
            case 'url':
                return '<input type="' . ($fieldType === 'text' ? 'text' : $fieldType) . '" 
                        class="member-admin-input" 
                        value="' . esc_attr($value) . '" 
                        style="width: 100%; font-size: 12px;" ' . $required . '>';
                        
            case 'textarea':
                return '<textarea class="member-admin-input" 
                        style="width: 100%; height: 60px; font-size: 12px;" ' . $required . '>' . 
                        esc_textarea($value) . '</textarea>';
                        
            case 'number':
                return '<input type="number" 
                        class="member-admin-input" 
                        value="' . esc_attr($value) . '" 
                        style="width: 100%; font-size: 12px;" ' . $required . '>';
                        
            case 'select':
                return $this->createSelectInput($field, $value);
                
            case 'radio':
                return $this->createRadioInput($field, $value);
                
            case 'checkbox':
                return $this->createCheckboxInput($field, $value);
                
            case 'true_false':
                $checked = $value ? 'checked' : '';
                return '<label style="font-size: 12px;">
                        <input type="checkbox" class="member-admin-input" ' . $checked . '> 
                        ' . ($field['message'] ?: 'Ja') . '
                        </label>';
                        
            case 'date_picker':
                return '<input type="date" 
                        class="member-admin-input" 
                        value="' . esc_attr($this->convertDateForInput($value)) . '" 
                        style="width: 100%; font-size: 12px;" ' . $required . '>';
                        
            default:
                return '<input type="text" 
                        class="member-admin-input" 
                        value="' . esc_attr($value) . '" 
                        style="width: 100%; font-size: 12px;" ' . $required . '>';
        }
    }
    
    /**
     * Skapa select input
     */
    private function createSelectInput($field, $value) {
        $html = '<select class="member-admin-input" style="width: 100%; font-size: 12px;">';
        $html .= '<option value="">Välj...</option>';
        
        if (isset($field['choices']) && is_array($field['choices'])) {
            foreach ($field['choices'] as $choiceValue => $choiceLabel) {
                $selected = ($value == $choiceValue) ? 'selected' : '';
                $html .= '<option value="' . esc_attr($choiceValue) . '" ' . $selected . '>' . 
                         esc_html($choiceLabel) . '</option>';
            }
        }
        
        $html .= '</select>';
        return $html;
    }
    
    /**
     * Skapa radio input
     */
    private function createRadioInput($field, $value) {
        $html = '<div style="font-size: 12px;">';
        
        if (isset($field['choices']) && is_array($field['choices'])) {
            foreach ($field['choices'] as $choiceValue => $choiceLabel) {
                $checked = ($value == $choiceValue) ? 'checked' : '';
                $html .= '<label style="display: block; margin-bottom: 2px;">';
                $html .= '<input type="radio" name="member_admin_radio_' . $field['key'] . '" 
                         class="member-admin-input" value="' . esc_attr($choiceValue) . '" ' . $checked . '> ';
                $html .= esc_html($choiceLabel);
                $html .= '</label>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Skapa checkbox input
     */
    private function createCheckboxInput($field, $value) {
        $html = '<div style="font-size: 12px;">';
        $valueArray = is_array($value) ? $value : [];
        
        if (isset($field['choices']) && is_array($field['choices'])) {
            foreach ($field['choices'] as $choiceValue => $choiceLabel) {
                $checked = in_array($choiceValue, $valueArray) ? 'checked' : '';
                $html .= '<label style="display: block; margin-bottom: 2px;">';
                $html .= '<input type="checkbox" class="member-admin-input member-admin-checkbox" 
                         value="' . esc_attr($choiceValue) . '" ' . $checked . '> ';
                $html .= esc_html($choiceLabel);
                $html .= '</label>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Konvertera datum för input
     */
    private function convertDateForInput($value) {
        if (!$value) return '';
        
        // ACF sparar datum som Ymd format
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        
        return $value;
    }
    
    /**
     * Kontrollera om fälttyp är redigerbar
     */
    private function isFieldEditable($fieldType) {
        $editableTypes = [
            'text',
            'textarea', 
            'number',
            'email',
            'url',
            'select',
            'radio',
            'checkbox',
            'true_false',
            'date_picker'
        ];
        
        return in_array($fieldType, $editableTypes, true);
    }
    
    /**
     * Gör kolumner sorterbara
     */
    public function makeColumnsSortable($columns) {
        $settings = get_option('member_admin_settings', ['enabled_fields' => []]);
        $enabledFields = $settings['enabled_fields'];
        
        if (empty($enabledFields)) {
            return $columns;
        }
        
        $acfFields = $this->fieldManager->getUserACFFields();
        
        foreach ($enabledFields as $fieldKey) {
            if (isset($acfFields[$fieldKey])) {
                $field = $acfFields[$fieldKey];
                $columnKey = 'member_admin_' . $field['key'];
                
                // Endast vissa fälttyper bör vara sorterbara
                if ($this->isFieldSortable($field['type'])) {
                    $columns[$columnKey] = $columnKey;
                }
            }
        }
        
        return $columns;
    }
    
    /**
     * Kontrollera om fälttyp är sorterbar
     */
    private function isFieldSortable($fieldType) {
        $sortableTypes = [
            'text',
            'number',
            'email',
            'date_picker',
            'date_time_picker',
            'select',
            'radio',
            'true_false'
        ];
        
        return in_array($fieldType, $sortableTypes, true);
    }
    
    /**
     * Hantera sortering av kolumner
     */
    public function handleColumnSorting($user_query) {
        // Kontrollera att vi är i admin och hanterar användarlistan
        if (!is_admin()) {
            return;
        }
        
        // Kontrollera om detta är en sortering av våra kolumner
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '';
        
        if (strpos($orderby, 'member_admin_') !== 0) {
            return;
        }
        
        // Extrahera fält-key från orderby
        $fieldKey = str_replace('member_admin_', '', $orderby);
        
        $acfFields = $this->fieldManager->getUserACFFields();
        
        if (!isset($acfFields[$fieldKey])) {
            return;
        }
        
        $field = $acfFields[$fieldKey];
        $fieldName = $field['name'];
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
        
        // Modifiera SQL-frågan direkt
        global $wpdb;
        
        // Lägg till JOIN för meta-tabellen
        $user_query->query_from .= " LEFT JOIN {$wpdb->usermeta} AS ma_meta ON ({$wpdb->users}.ID = ma_meta.user_id AND ma_meta.meta_key = '{$fieldName}')";
        
        // Sätt ORDER BY baserat på fälttyp
        if ($field['type'] === 'number') {
            $user_query->query_orderby = "ORDER BY CAST(ma_meta.meta_value AS UNSIGNED) {$order}";
        } elseif ($field['type'] === 'date_picker') {
            // För datum, hantera tomma värden och sortera korrekt
            $user_query->query_orderby = "ORDER BY 
                CASE 
                    WHEN ma_meta.meta_value IS NULL OR ma_meta.meta_value = '' THEN " . ($order === 'ASC' ? '1' : '0') . "
                    ELSE " . ($order === 'ASC' ? '0' : '1') . "
                END,
                ma_meta.meta_value {$order}";
        } else {
            // Standard string-sortering
            $user_query->query_orderby = "ORDER BY ma_meta.meta_value {$order}";
        }
    }
    

    
    /**
     * Lägg till anpassad CSS för inline-redigering
     */
    public function addCustomCSS() {
        echo '<style>
            .member-admin-column {
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .member-admin-column img {
                vertical-align: middle;
            }
            
            .member-admin-column small {
                color: #666;
                font-size: 11px;
            }
            
            /* Inline editing styles */
            .member-admin-editable-field {
                min-width: 120px;
                position: relative;
            }
            
            .member-admin-display:hover {
                background-color: #f0f0f0;
            }
            
            .member-admin-edit-icon {
                opacity: 0;
                transition: opacity 0.2s;
            }
            
            .member-admin-display:hover .member-admin-edit-icon {
                opacity: 1;
            }
            
            .member-admin-edit {
                background: white;
                border: 1px solid #ddd;
                border-radius: 3px;
                padding: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                position: absolute;
                z-index: 100;
                min-width: 200px;
                top: 0;
                left: 0;
            }
            
            .member-admin-input {
                border: 1px solid #ddd !important;
                border-radius: 3px !important;
                padding: 3px 5px !important;
            }
            
            .member-admin-saving {
                opacity: 0.6;
                pointer-events: none;
            }
            
            /* Datum-kolumner behöver lite mer plats */
            .column-member_admin_date_picker,
            .column-member_admin_date_time_picker {
                min-width: 120px;
            }
            
            /* Responsiv hantering */
            @media screen and (max-width: 782px) {
                .member-admin-editable-field {
                    min-width: 100px;
                }
                
                .member-admin-edit {
                    min-width: 150px;
                }
                
                .column-member_admin_date_picker,
                .column-member_admin_date_time_picker {
                    min-width: 100px;
                }
            }
        </style>';
    }
    
    /**
     * Lägg till JavaScript för inline-redigering
     */
    public function addInlineEditJS() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Klick för att starta redigering
            $(document).on('click', '.member-admin-display', function() {
                var $field = $(this).closest('.member-admin-editable-field');
                if ($field.hasClass('member-admin-editing')) return;
                
                $field.addClass('member-admin-editing');
                $(this).hide();
                $field.find('.member-admin-edit').show();
                
                // Fokusera på första input
                $field.find('.member-admin-input').first().focus();
            });
            
            // Avbryt redigering
            $(document).on('click', '.member-admin-cancel', function(e) {
                e.preventDefault();
                var $field = $(this).closest('.member-admin-editable-field');
                cancelEdit($field);
            });
            
            // Spara ändringar
            $(document).on('click', '.member-admin-save', function(e) {
                e.preventDefault();
                var $field = $(this).closest('.member-admin-editable-field');
                saveField($field);
            });
            
            // Enter för att spara, Escape för att avbryta
            $(document).on('keydown', '.member-admin-input', function(e) {
                if (e.key === 'Enter' && e.target.type !== 'textarea') {
                    e.preventDefault();
                    var $field = $(this).closest('.member-admin-editable-field');
                    saveField($field);
                } else if (e.key === 'Escape') {
                    var $field = $(this).closest('.member-admin-editable-field');
                    cancelEdit($field);
                }
            });
            
            // Stäng vid klick utanför
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.member-admin-editable-field').length) {
                    $('.member-admin-editing').each(function() {
                        cancelEdit($(this));
                    });
                }
            });
            
            function cancelEdit($field) {
                $field.removeClass('member-admin-editing member-admin-saving');
                $field.find('.member-admin-edit').hide();
                $field.find('.member-admin-display').show();
            }
            
            function saveField($field) {
                var userId = $field.data('user-id');
                var fieldKey = $field.data('field-key');
                var fieldType = $field.data('field-type');
                var value = getFieldValue($field, fieldType);
                
                $field.addClass('member-admin-saving');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'member_admin_update_field',
                        user_id: userId,
                        field_key: fieldKey,
                        field_type: fieldType,
                        value: value,
                        nonce: '<?php echo wp_create_nonce('member_admin_update_field'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Uppdatera visning
                            $field.find('.member-admin-value').html(response.data.formatted_value || '—');
                            cancelEdit($field);
                            
                            // Visa kort success-meddelande
                            showMessage('Fältet uppdaterat!', 'success');
                        } else {
                            $field.removeClass('member-admin-saving');
                            showMessage(response.data.message || 'Fel vid sparning', 'error');
                        }
                    },
                    error: function() {
                        $field.removeClass('member-admin-saving');
                        showMessage('Ett fel uppstod vid sparning', 'error');
                    }
                });
            }
            
            function getFieldValue($field, fieldType) {
                switch (fieldType) {
                    case 'checkbox':
                        var values = [];
                        $field.find('.member-admin-checkbox:checked').each(function() {
                            values.push($(this).val());
                        });
                        return values;
                        
                    case 'radio':
                        return $field.find('input[type="radio"]:checked').val() || '';
                        
                    case 'true_false':
                        return $field.find('input[type="checkbox"]').is(':checked') ? 1 : 0;
                        
                    case 'date_picker':
                        var dateValue = $field.find('.member-admin-input').val();
                        // Konvertera från YYYY-MM-DD till Ymd (ACF format)
                        return dateValue ? dateValue.replace(/-/g, '') : '';
                        
                    default:
                        return $field.find('.member-admin-input').val() || '';
                }
            }
            
            function showMessage(message, type) {
                var $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after($message);
                
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Uppdatera fält-värde
     */
    public function ajaxUpdateField() {
        check_ajax_referer('member_admin_update_field', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Du har inte behörighet att utföra denna åtgärd.', 'member-admin')]);
        }
        
        $userId = intval($_POST['user_id']);
        $fieldKey = sanitize_text_field($_POST['field_key']);
        $fieldType = sanitize_text_field($_POST['field_type']);
        $value = $_POST['value']; // Saniteras nedan baserat på fälttyp
        
        // Kontrollera att användaren kan redigeras
        if (!current_user_can('edit_user', $userId)) {
            wp_send_json_error(['message' => __('Du har inte behörighet att redigera denna användare.', 'member-admin')]);
        }
        
        // Hämta fält-information
        $acfFields = $this->fieldManager->getUserACFFields();
        if (!isset($acfFields[$fieldKey])) {
            wp_send_json_error(['message' => __('Fältet hittades inte.', 'member-admin')]);
        }
        
        $field = $acfFields[$fieldKey];
        $fieldName = $field['name'];
        
        // Sanitera värde baserat på fälttyp
        $sanitizedValue = $this->sanitizeFieldValue($value, $fieldType);
        
        // Validera värde
        $validation = $this->validateFieldValue($sanitizedValue, $field);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['message']]);
        }
        
        // Spara värde
        $result = update_user_meta($userId, $fieldName, $sanitizedValue);
        
        if ($result !== false) {
            // Hämta värdet igen med ACF:s get_field för att få korrekt formatering
            $savedValue = $this->fieldManager->getUserFieldValue($userId, $fieldKey);
            $formattedValue = $this->fieldManager->formatFieldValue($savedValue, $field);
            
            wp_send_json_success([
                'message' => __('Fältet uppdaterat!', 'member-admin'),
                'formatted_value' => $formattedValue
            ]);
        } else {
            wp_send_json_error(['message' => __('Kunde inte spara fältet.', 'member-admin')]);
        }
    }
    
    /**
     * Sanitera fält-värde baserat på typ
     */
    private function sanitizeFieldValue($value, $fieldType) {
        switch ($fieldType) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'checkbox':
                return is_array($value) ? array_map('sanitize_text_field', $value) : [];
            case 'true_false':
                return $value ? 1 : 0;
            case 'date_picker':
                // Kontrollera att det är ett giltigt datum i Ymd format
                if (preg_match('/^\d{8}$/', $value)) {
                    return $value;
                }
                return '';
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Validera fält-värde
     */
    private function validateFieldValue($value, $field) {
        // Kontrollera om fältet är obligatoriskt
        if (isset($field['required']) && $field['required']) {
            if (empty($value) && $value !== 0 && $value !== '0') {
                return [
                    'valid' => false,
                    'message' => sprintf(__('Fältet "%s" är obligatoriskt.', 'member-admin'), $field['label'])
                ];
            }
        }
        
        // Validera email
        if ($field['type'] === 'email' && !empty($value)) {
            if (!is_email($value)) {
                return [
                    'valid' => false,
                    'message' => __('Ange en giltig e-postadress.', 'member-admin')
                ];
            }
        }
        
        // Validera URL
        if ($field['type'] === 'url' && !empty($value)) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                return [
                    'valid' => false,
                    'message' => __('Ange en giltig URL.', 'member-admin')
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Hämta tillgängliga ACF-fält för dropdown
     */
    public function getAvailableFields() {
        return $this->fieldManager->getUserACFFields();
    }
    
    /**
     * Uppdatera aktiverade fält
     */
    public function updateEnabledFields($fieldKeys) {
        $settings = get_option('member_admin_settings', []);
        $oldFields = isset($settings['enabled_fields']) ? $settings['enabled_fields'] : [];
        $newFields = array_values(array_unique($fieldKeys));
        
        $settings['enabled_fields'] = $newFields;
        
        // Returnera true även om värdena är samma (för att undvika onödiga fel)
        $result = update_option('member_admin_settings', $settings);
        
        // update_option returnerar false om värdet inte ändrades, men det är inte ett fel
        // Vi returnerar true om antingen uppdateringen lyckades eller om värdena är identiska
        if ($result || $oldFields === $newFields) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Hämta aktiverade fält
     */
    public function getEnabledFields() {
        $settings = get_option('member_admin_settings', ['enabled_fields' => []]);
        return $settings['enabled_fields'];
    }
} 