<?php
/**
 * ACF Field Manager
 * Hanterar upptäckning och hantering av ACF-fält
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klass för att hantera ACF-fält
 */
class MemberAdminACFFieldManager {
    
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
        // Inga direkta hooks här, klassen används som service
    }
    
    /**
     * Hämta alla ACF-fält som är kopplade till användare
     */
    public function getUserACFFields() {
        $cachedFields = get_transient('member_admin_acf_fields');
        if ($cachedFields !== false) {
            return $cachedFields;
        }
        
        $fields = [];
        
        if (!function_exists('acf_get_field_groups')) {
            return $fields;
        }
        
        // Hämta alla fältgrupper
        $fieldGroups = acf_get_field_groups();
        
        foreach ($fieldGroups as $group) {
            // Kontrollera om fältgruppen är kopplad till användare
            if ($this->isUserFieldGroup($group)) {
                $groupFields = acf_get_fields($group['key']);
                
                foreach ($groupFields as $field) {
                    $fields[$field['key']] = [
                        'key' => $field['key'],
                        'name' => $field['name'],
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'group' => $group['title'],
                        'choices' => $field['choices'] ?? [],
                        'sub_fields' => $field['sub_fields'] ?? []
                    ];
                }
            }
        }
        
        // Cacha resultatet i 5 minuter
        set_transient('member_admin_acf_fields', $fields, 5 * MINUTE_IN_SECONDS);
        
        return $fields;
    }
    
    /**
     * Kontrollera om en fältgrupp är kopplad till användare
     */
    private function isUserFieldGroup($group) {
        if (!isset($group['location'])) {
            return false;
        }
        
        foreach ($group['location'] as $locationGroup) {
            foreach ($locationGroup as $rule) {
                if ($rule['param'] === 'user_form' || $rule['param'] === 'user_role') {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Hämta värde för ACF-fält för en specifik användare
     */
    public function getUserFieldValue($userId, $fieldKey) {
        if (!function_exists('get_field')) {
            return null;
        }
        
        return get_field($fieldKey, 'user_' . $userId);
    }
    
    /**
     * Formatera ACF-fältvärde för visning i användar-listan
     */
    public function formatFieldValue($value, $field) {
        if (empty($value)) {
            return '—';
        }
        
        switch ($field['type']) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
            case 'password':
                return wp_trim_words(strval($value), 10);
                
            case 'number':
                return number_format(floatval($value));
                
            case 'select':
            case 'radio':
            case 'button_group':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return strval($value);
                
            case 'checkbox':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value ? __('Ja', 'member-admin') : __('Nej', 'member-admin');
                
            case 'true_false':
                return $value ? __('Ja', 'member-admin') : __('Nej', 'member-admin');
                
            case 'date_picker':
                if (is_string($value)) {
                    $date = DateTime::createFromFormat('Ymd', $value);
                    if ($date !== false) {
                        return $date->format('Y-m-d');
                    }
                }
                return strval($value);
                
            case 'time_picker':
                return strval($value);
                
            case 'date_time_picker':
                if (is_string($value)) {
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                    if ($date !== false) {
                        return $date->format('Y-m-d H:i');
                    }
                }
                return strval($value);
                
            case 'image':
                if (is_array($value) && isset($value['url'])) {
                    return '<img src="' . esc_url($value['url']) . '" style="max-width:50px;max-height:50px;" alt="' . esc_attr($value['alt'] ?? '') . '">';
                } elseif (is_numeric($value)) {
                    $imageUrl = wp_get_attachment_image_url(intval($value), 'thumbnail');
                    if ($imageUrl) {
                        return '<img src="' . esc_url($imageUrl) . '" style="max-width:50px;max-height:50px;" alt="">';
                    }
                }
                return '—';
                
            case 'file':
                if (is_array($value) && isset($value['url'])) {
                    return '<a href="' . esc_url($value['url']) . '" target="_blank">' . esc_html($value['filename'] ?? 'Fil') . '</a>';
                } elseif (is_numeric($value)) {
                    $fileUrl = wp_get_attachment_url(intval($value));
                    $fileName = get_the_title(intval($value));
                    if ($fileUrl) {
                        return '<a href="' . esc_url($fileUrl) . '" target="_blank">' . esc_html($fileName ?: 'Fil') . '</a>';
                    }
                }
                return '—';
                
            case 'user':
                if (is_array($value)) {
                    $userNames = [];
                    foreach ($value as $userId) {
                        $user = get_userdata(intval($userId));
                        if ($user) {
                            $userNames[] = $user->display_name;
                        }
                    }
                    return implode(', ', $userNames);
                } elseif (is_numeric($value)) {
                    $user = get_userdata(intval($value));
                    return $user ? $user->display_name : '—';
                }
                return '—';
                
            case 'post_object':
                if (is_array($value)) {
                    $postTitles = [];
                    foreach ($value as $postId) {
                        $postTitle = get_the_title(intval($postId));
                        if ($postTitle) {
                            $postTitles[] = $postTitle;
                        }
                    }
                    return implode(', ', $postTitles);
                } elseif (is_numeric($value)) {
                    $postTitle = get_the_title(intval($value));
                    return $postTitle ?: '—';
                }
                return '—';
                
            default:
                if (is_array($value)) {
                    return implode(', ', array_filter(array_map('strval', $value)));
                }
                return wp_trim_words(strval($value), 10);
        }
    }
    
    /**
     * Rensa cache för ACF-fält
     */
    public function clearFieldsCache() {
        delete_transient('member_admin_acf_fields');
    }
} 