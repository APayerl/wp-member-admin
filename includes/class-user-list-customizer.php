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
        add_action('pre_get_users', [$this, 'handleColumnSorting']);
        
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
    public function handleColumnSorting(WP_User_Query $query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
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
        
        // Specialhantering för olika fälttyper
        $this->setupSortingForFieldType($query, $field);
    }
    
    /**
     * Konfigurera sortering baserat på fälttyp
     */
    private function setupSortingForFieldType($query, $field) {
        $fieldName = $field['name'];
        $fieldType = $field['type'];
        
        // Grundläggande meta_query för att hantera tomma värden korrekt
        $metaQuery = [
            'relation' => 'OR',
            'exists_clause' => [
                'key' => $fieldName,
                'compare' => 'EXISTS'
            ],
            'not_exists_clause' => [
                'key' => $fieldName,
                'compare' => 'NOT EXISTS'
            ]
        ];
        
        $query->set('meta_query', $metaQuery);
        $query->set('meta_key', $fieldName);
        
        // Olika sorteringstyper baserat på fälttyp
        switch ($fieldType) {
            case 'number':
                $query->set('orderby', 'meta_value_num');
                break;
                
            case 'date_picker':
                // ACF sparar datum som Ymd (20240315) vilket sorteras korrekt kronologiskt som string
                // eftersom YYYYMMDD format naturligt sorteras i rätt ordning
                $query->set('orderby', 'meta_value');
                // Sätt tomma värden sist
                $query->set('meta_query', [
                    'relation' => 'OR',
                    'exists_clause' => [
                        'key' => $fieldName,
                        'compare' => 'EXISTS',
                        'value' => ''
                    ],
                    'not_exists_clause' => [
                        'key' => $fieldName,
                        'compare' => 'NOT EXISTS'
                    ]
                ]);
                $query->set('orderby', [
                    'exists_clause' => 'DESC',  // Existerande fält först
                    'meta_value' => $query->get('order') ?: 'ASC'  // Sedan sortera datum
                ]);
                break;
                
            case 'date_time_picker':
                // Datum-tid fält sorteras också som string (korrekt format från ACF)
                $query->set('orderby', 'meta_value');
                $query->set('meta_query', [
                    'relation' => 'OR',
                    'exists_clause' => [
                        'key' => $fieldName,
                        'compare' => 'EXISTS',
                        'value' => ''
                    ],
                    'not_exists_clause' => [
                        'key' => $fieldName,
                        'compare' => 'NOT EXISTS'
                    ]
                ]);
                $query->set('orderby', [
                    'exists_clause' => 'DESC',
                    'meta_value' => $query->get('order') ?: 'ASC'
                ]);
                break;
                
            case 'true_false':
                // Boolean fält - 1/0 sorteras numeriskt
                $query->set('orderby', 'meta_value_num');
                break;
                
            case 'select':
            case 'radio':
            case 'text':
            case 'email':
            default:
                // Standard string-sortering
                $query->set('orderby', 'meta_value');
                // Hantera tomma värden - sätt dem sist
                $query->set('orderby', [
                    'exists_clause' => 'DESC',
                    'meta_value' => $query->get('order') ?: 'ASC'
                ]);
                break;
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
        $settings['enabled_fields'] = array_values(array_unique($fieldKeys));
        
        return update_option('member_admin_settings', $settings);
    }
    
    /**
     * Hämta aktiverade fält
     */
    public function getEnabledFields() {
        $settings = get_option('member_admin_settings', ['enabled_fields' => []]);
        return $settings['enabled_fields'];
    }
} 