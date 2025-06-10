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
        
        // Lägg till CSS för bättre visning
        add_action('admin_head-users.php', [$this, 'addCustomCSS']);
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
     * Visa innehåll för anpassade kolumner
     */
    public function displayCustomColumnContent($output, $columnName, $userId) {
        if (strpos($columnName, 'member_admin_') !== 0) {
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
        
        return $this->fieldManager->formatFieldValue($value, $field);
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
     * Lägg till anpassad CSS för bättre visning
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
            
            /* Datum-kolumner behöver lite mer plats */
            .column-member_admin_date_picker,
            .column-member_admin_date_time_picker {
                min-width: 100px;
            }
            
            /* Responsiv hantering */
            @media screen and (max-width: 782px) {
                .member-admin-column {
                    max-width: 100px;
                }
                
                .column-member_admin_date_picker,
                .column-member_admin_date_time_picker {
                    min-width: 90px;
                }
            }
        </style>';
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