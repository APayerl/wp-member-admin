<?php
/**
 * Bootstrap för PHPUnit-tester
 * Laddar WordPress testmiljö för Member Admin plugin
 */

// Lägg till sökväg till wp-tests-config.php
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Kunde inte hitta WordPress test suite i $_tests_dir/includes/functions.php" . PHP_EOL;
    echo "Kör: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]" . PHP_EOL;
    exit( 1 );
}

// Lägg sökvägar
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manuellt ladda plugin som testas
 */
function _manually_load_plugin() {
    require dirname( dirname( __FILE__ ) ) . '/member-admin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Aktiverar ACF för tester (mockad version)
 */
function _load_acf_mock() {
    // Enkelt mock av ACF för tester
    if ( ! class_exists( 'ACF' ) ) {
        class ACF {
            public static function get_field_groups() {
                return [
                    [
                        'key' => 'group_test',
                        'title' => 'Test Group',
                        'location' => [
                            [
                                [
                                    'param' => 'user_form',
                                    'operator' => '==',
                                    'value' => 'all'
                                ]
                            ]
                        ]
                    ]
                ];
            }
        }
    }
    
    if ( ! function_exists( 'get_field' ) ) {
        function get_field( $field_key, $user_id = null ) {
            // Mock-data för tester
            $mock_data = [
                'test_text_field' => 'Test värde',
                'test_number_field' => 123,
                'test_email_field' => 'test@example.com',
                'test_true_false' => true,
                'test_date_field' => '20241201'
            ];
            
            return isset( $mock_data[$field_key] ) ? $mock_data[$field_key] : null;
        }
    }
    
    if ( ! function_exists( 'get_fields' ) ) {
        function get_fields( $user_id = null ) {
            return [
                'test_text_field' => [
                    'key' => 'field_test_text',
                    'label' => 'Test Text',
                    'name' => 'test_text_field',
                    'type' => 'text',
                    'required' => false
                ],
                'test_number_field' => [
                    'key' => 'field_test_number',
                    'label' => 'Test Number',
                    'name' => 'test_number_field',
                    'type' => 'number',
                    'required' => false
                ]
            ];
        }
    }
    
    if ( ! function_exists( 'update_field' ) ) {
        function update_field( $field_key, $value, $user_id ) {
            return update_user_meta( $user_id, $field_key, $value );
        }
    }
}
tests_add_filter( 'plugins_loaded', '_load_acf_mock', 1 );

// Starta WordPress test suite
require $_tests_dir . '/includes/bootstrap.php'; 