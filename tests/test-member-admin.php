<?php
/**
 * Tester för MemberAdmin huvudklass
 */

class MemberAdminTest extends WP_UnitTestCase {

    private $memberAdminInstance;

    /**
     * Setup innan varje test
     */
    public function setUp(): void {
        parent::setUp();
        $this->memberAdminInstance = MemberAdmin::getInstance();
    }

    /**
     * Test att singleton fungerar
     */
    public function test_singleton_pattern() {
        $instance1 = MemberAdmin::getInstance();
        $instance2 = MemberAdmin::getInstance();
        
        $this->assertSame($instance1, $instance2, 'Singleton pattern ska returnera samma instans');
    }

    /**
     * Test att plugin-konstanter är definierade
     */
    public function test_plugin_constants_defined() {
        $this->assertTrue(defined('MEMBER_ADMIN_VERSION'), 'MEMBER_ADMIN_VERSION konstant ska vara definierad');
        $this->assertTrue(defined('MEMBER_ADMIN_PLUGIN_DIR'), 'MEMBER_ADMIN_PLUGIN_DIR konstant ska vara definierad');
        $this->assertTrue(defined('MEMBER_ADMIN_PLUGIN_URL'), 'MEMBER_ADMIN_PLUGIN_URL konstant ska vara definierad');
        $this->assertTrue(defined('MEMBER_ADMIN_PLUGIN_FILE'), 'MEMBER_ADMIN_PLUGIN_FILE konstant ska vara definierad');
    }

    /**
     * Test att versionen är korrekt
     */
    public function test_plugin_version() {
        $this->assertEquals('1.0.0', MEMBER_ADMIN_VERSION, 'Plugin-versionen ska vara 1.0.0');
    }

    /**
     * Test att ACF-beroenden kontrolleras korrekt
     */
    public function test_dependencies_check() {
        // ACF är mockad i bootstrap, så detta ska returnera true
        $reflection = new ReflectionClass($this->memberAdminInstance);
        $method = $reflection->getMethod('isDependenciesMet');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->memberAdminInstance);
        $this->assertTrue($result, 'isDependenciesMet ska returnera true när ACF är tillgängligt');
    }

    /**
     * Test att textdomän laddas
     */
    public function test_text_domain_loaded() {
        // Kontrollera att textdomänen är laddad genom att testa översättning
        $translated = __('Member Admin kräver Advanced Custom Fields (ACF) plugin för att fungera.', 'member-admin');
        $this->assertNotEmpty($translated, 'Textdomänen ska vara laddad');
    }

    /**
     * Test att klasser laddas korrekt
     */
    public function test_classes_loaded() {
        $this->assertTrue(class_exists('MemberAdminACFFieldManager'), 'ACF Field Manager klass ska vara laddad');
        $this->assertTrue(class_exists('MemberAdminUserListCustomizer'), 'User List Customizer klass ska vara laddad');
        $this->assertTrue(class_exists('MemberAdminInterface'), 'Admin Interface klass ska vara laddad');
    }

    /**
     * Test donation-notice funktionalitet
     */
    public function test_donation_notice_dismissal() {
        // Simulera admin-användare
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        
        // Kör AJAX-funktionen
        $_POST['nonce'] = wp_create_nonce('member_admin_dismiss_donation');
        $_POST['action'] = 'member_admin_dismiss_donation';
        
        try {
            $this->memberAdminInstance->dismissDonationNotice();
        } catch (WPDieException $e) {
            // Detta förväntas eftersom wp_die() anropas
        }
        
        // Kontrollera att user meta har uppdaterats
        $dismissed = get_user_meta(get_current_user_id(), 'member_admin_donation_dismissed', true);
        $this->assertNotEmpty($dismissed, 'Donation notice dismissed meta ska vara satt');
    }

    /**
     * Test plugin-aktivering
     */
    public function test_plugin_activation() {
        // Rensa först
        delete_option('member_admin_settings');
        delete_option('member_admin_install_date');
        
        // Kör aktivering
        member_admin_activate();
        
        // Kontrollera att standardinställningar skapas
        $settings = get_option('member_admin_settings');
        $this->assertIsArray($settings, 'Standardinställningar ska vara en array');
        $this->assertArrayHasKey('enabled_fields', $settings, 'Settings ska ha enabled_fields');
        $this->assertArrayHasKey('field_order', $settings, 'Settings ska ha field_order');
        
        // Kontrollera installationsdatum
        $installDate = get_option('member_admin_install_date');
        $this->assertNotEmpty($installDate, 'Installationsdatum ska vara satt');
    }

    /**
     * Test plugin-avaktivering
     */
    public function test_plugin_deactivation() {
        // Sätt test-transient
        set_transient('member_admin_acf_fields', 'test_data', 3600);
        
        // Kör avaktivering
        member_admin_deactivate();
        
        // Kontrollera att transients rensas
        $transient = get_transient('member_admin_acf_fields');
        $this->assertFalse($transient, 'Transients ska rensas vid avaktivering');
    }

    /**
     * Test att export-meny läggs till
     */
    public function test_export_menu_added() {
        global $submenu;
        
        // Simulera admin och kör hook
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        set_current_screen('users.php');
        
        do_action('admin_menu');
        
        // Kontrollera att export-meny finns under users
        $this->assertArrayHasKey('users.php', $submenu, 'Users submenu ska finnas');
        
        $exportMenuFound = false;
        if (isset($submenu['users.php'])) {
            foreach ($submenu['users.php'] as $item) {
                if ($item[2] === 'member-admin-export') {
                    $exportMenuFound = true;
                    break;
                }
            }
        }
        
        $this->assertTrue($exportMenuFound, 'Export-meny ska finnas under Users');
    }

    /**
     * Test WordPress-fältdefinitioner
     */
    public function test_wp_fields_definition() {
        $wpFields = member_admin_get_wp_fields();
        
        $this->assertIsArray($wpFields, 'WP-fält ska vara en array');
        $this->assertArrayHasKey('ID', $wpFields, 'ID-fält ska finnas');
        $this->assertArrayHasKey('user_login', $wpFields, 'user_login-fält ska finnas');
        $this->assertArrayHasKey('user_email', $wpFields, 'user_email-fält ska finnas');
        $this->assertArrayHasKey('display_name', $wpFields, 'display_name-fält ska finnas');
    }

    /**
     * Test filterbara WordPress-fält
     */
    public function test_filterable_wp_fields() {
        $filterableFields = member_admin_get_filterable_wp_fields();
        
        $this->assertIsArray($filterableFields, 'Filterbara fält ska vara en array');
        $this->assertArrayHasKey('user_login', $filterableFields, 'user_login ska vara filterbart');
        $this->assertArrayHasKey('user_email', $filterableFields, 'user_email ska vara filterbart');
    }

    /**
     * Cleanup efter varje test
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Rensa test-data
        delete_option('member_admin_settings');
        delete_option('member_admin_install_date');
        delete_transient('member_admin_acf_fields');
    }
} 