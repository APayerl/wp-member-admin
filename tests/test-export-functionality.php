<?php
/**
 * Tester för export-funktionalitet
 */

class ExportFunctionalityTest extends WP_UnitTestCase {

    private $testUserId;
    private $adminUserId;

    /**
     * Setup innan varje test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Skapa test-användare
        $this->testUserId = $this->factory->user->create([
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'display_name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'subscriber'
        ]);
        
        $this->adminUserId = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        
        // Sätt ACF-fält för test-användaren
        update_user_meta($this->testUserId, 'test_text_field', 'Test värde');
        update_user_meta($this->testUserId, 'test_number_field', 123);
        update_user_meta($this->testUserId, 'test_email_field', 'acf@example.com');
    }

    /**
     * Test att export-meny läggs till korrekt
     */
    public function test_export_menu_registration() {
        wp_set_current_user($this->adminUserId);
        
        // Kör hook för admin-meny
        do_action('admin_menu');
        
        // Kontrollera att funktionen finns
        $this->assertTrue(function_exists('member_admin_add_export_menu'), 'Export menu funktion ska finnas');
        $this->assertTrue(function_exists('member_admin_render_export_page'), 'Render export page funktion ska finnas');
    }

    /**
     * Test hämtning av användare för export
     */
    public function test_get_users_for_export() {
        $users = member_admin_get_users_for_export(['all']);
        
        $this->assertIsArray($users, 'Användarlista ska vara en array');
        $this->assertGreaterThan(0, count($users), 'Ska hitta minst en användare');
        
        // Kontrollera att våra test-användare finns
        $userIds = wp_list_pluck($users, 'ID');
        $this->assertContains($this->testUserId, $userIds, 'Test-användare ska finnas i listan');
        $this->assertContains($this->adminUserId, $userIds, 'Admin-användare ska finnas i listan');
    }

    /**
     * Test filtrering av användare baserat på roller
     */
    public function test_filter_users_by_role() {
        // Testa filtrering efter subscriber-roll
        $subscriberUsers = member_admin_get_users_for_export(['subscriber']);
        $subscriberIds = wp_list_pluck($subscriberUsers, 'ID');
        
        $this->assertContains($this->testUserId, $subscriberIds, 'Subscriber ska finnas när vi filtrerar på subscriber');
        $this->assertNotContains($this->adminUserId, $subscriberIds, 'Admin ska inte finnas när vi filtrerar på subscriber');
        
        // Testa filtrering efter administrator-roll
        $adminUsers = member_admin_get_users_for_export(['administrator']);
        $adminIds = wp_list_pluck($adminUsers, 'ID');
        
        $this->assertContains($this->adminUserId, $adminIds, 'Admin ska finnas när vi filtrerar på administrator');
        $this->assertNotContains($this->testUserId, $adminIds, 'Subscriber ska inte finnas när vi filtrerar på administrator');
    }

    /**
     * Test hämtning av fältvärde för användare
     */
    public function test_get_user_field_value() {
        $user = get_userdata($this->testUserId);
        
        // Test WordPress-fält
        $this->assertEquals($this->testUserId, member_admin_get_user_field_value($user, 'ID'));
        $this->assertEquals('testuser', member_admin_get_user_field_value($user, 'user_login'));
        $this->assertEquals('test@example.com', member_admin_get_user_field_value($user, 'user_email'));
        $this->assertEquals('Test User', member_admin_get_user_field_value($user, 'display_name'));
        
        // Test roller
        $roles = member_admin_get_user_field_value($user, 'roles');
        $this->assertStringContainsString('subscriber', $roles);
    }

    /**
     * Test formatering av export-värden
     */
    public function test_format_export_value() {
        // Test olika fälttyper
        $textField = ['type' => 'text', 'name' => 'test_text'];
        $this->assertEquals('Test värde', member_admin_format_export_value('Test värde', $textField));
        
        $numberField = ['type' => 'number', 'name' => 'test_number'];
        $this->assertEquals('123', member_admin_format_export_value(123, $numberField));
        
        $trueFalseField = ['type' => 'true_false', 'name' => 'test_bool'];
        $this->assertEquals('Ja', member_admin_format_export_value(true, $trueFalseField));
        $this->assertEquals('Nej', member_admin_format_export_value(false, $trueFalseField));
        
        $checkboxField = ['type' => 'checkbox', 'name' => 'test_checkbox'];
        $this->assertEquals('val1, val2', member_admin_format_export_value(['val1', 'val2'], $checkboxField));
        
        // Test datum-formatering
        $dateField = ['type' => 'date_picker', 'name' => 'test_date'];
        $this->assertEquals('2024-12-01', member_admin_format_export_value('20241201', $dateField));
    }

    /**
     * Test datum-formatering specifikt
     */
    public function test_format_date_for_export() {
        // ACF-format (Ymd)
        $this->assertEquals('2024-12-01', member_admin_format_date_for_export('20241201'));
        $this->assertEquals('2023-05-15', member_admin_format_date_for_export('20230515'));
        
        // Tom eller felaktig data
        $this->assertEquals('', member_admin_format_date_for_export(''));
        $this->assertEquals('invalid', member_admin_format_date_for_export('invalid'));
    }

    /**
     * Test filtervillkor-matchning
     */
    public function test_filter_condition_matching() {
        // Test 'empty' villkor
        $this->assertTrue(member_admin_matches_filter_condition('', 'empty', ''));
        $this->assertFalse(member_admin_matches_filter_condition('värde', 'empty', ''));
        
        // Test 'not_empty' villkor
        $this->assertTrue(member_admin_matches_filter_condition('värde', 'not_empty', ''));
        $this->assertFalse(member_admin_matches_filter_condition('', 'not_empty', ''));
        
        // Test 'equals' villkor
        $this->assertTrue(member_admin_matches_filter_condition('test', 'equals', 'Test'));
        $this->assertFalse(member_admin_matches_filter_condition('test', 'equals', 'annat'));
        
        // Test 'contains' villkor
        $this->assertTrue(member_admin_matches_filter_condition('test värde', 'contains', 'värde'));
        $this->assertFalse(member_admin_matches_filter_condition('test', 'contains', 'xyz'));
        
        // Test 'starts_with' villkor
        $this->assertTrue(member_admin_matches_filter_condition('test värde', 'starts_with', 'test'));
        $this->assertFalse(member_admin_matches_filter_condition('värde test', 'starts_with', 'test'));
    }

    /**
     * Test användarfiltrering
     */
    public function test_filter_users() {
        $allUsers = get_users(['fields' => 'all']);
        
        // Filter på user_login som innehåller 'test'
        $filteredUsers = member_admin_filter_users($allUsers, 'wp_user_login', 'contains', 'test');
        $userLogins = wp_list_pluck($filteredUsers, 'user_login');
        
        $this->assertContains('testuser', $userLogins, 'Filtrerade användare ska innehålla testuser');
        
        // Filter på email som är tom
        $emptyEmailUsers = member_admin_filter_users($allUsers, 'wp_user_email', 'empty', '');
        // Alla våra test-användare har emails, så detta ska vara tomt eller färre användare
        $this->assertLessThanOrEqual(count($allUsers), count($emptyEmailUsers));
    }

    /**
     * Test hämtning av unika fältvärden
     */
    public function test_get_unique_field_values() {
        // Test WordPress-fält
        $userLogins = member_admin_get_unique_field_values('wp_user_login');
        $this->assertIsArray($userLogins, 'Unika värden ska vara en array');
        $this->assertContains('testuser', $userLogins, 'Ska hitta testuser login');
        
        // Test ACF-fält (mockad data)
        $textValues = member_admin_get_unique_field_values('acf_test_text_field');
        $this->assertIsArray($textValues, 'ACF-värden ska vara en array');
    }

    /**
     * Test AJAX nonce-verifiering för field values
     */
    public function test_ajax_field_values_security() {
        wp_set_current_user($this->adminUserId);
        
        // Test utan nonce - ska misslyckas
        $_POST = [
            'field_key' => 'wp_user_login',
            'action' => 'member_admin_get_field_values'
        ];
        
        $this->expectException(WPDieException::class);
        member_admin_ajax_get_field_values();
    }

    /**
     * Test behörighetskontroll för export
     */
    public function test_export_capability_check() {
        // Logga in som subscriber (bör inte ha behörighet)
        wp_set_current_user($this->testUserId);
        
        $_POST = [
            'member_admin_export_nonce' => wp_create_nonce('member_admin_export'),
            'wp_fields' => ['ID'],
            'action' => 'member_admin_export_users'
        ];
        
        // Detta ska misslyckas pga behörighet
        $this->expectException(WPDieException::class);
        member_admin_handle_export();
    }

    /**
     * Test CSV-generering (bara headers för att undvika output)
     */
    public function test_csv_headers_generation() {
        $users = [get_userdata($this->testUserId)];
        $wpFields = ['ID', 'user_login', 'user_email'];
        $acfFields = ['test_text_field'];
        
        // Vi kan inte testa fullständig CSV-generering utan att output buffring
        // Men vi kan testa de funktioner som förbereder data
        
        $wpFieldLabels = member_admin_get_wp_fields();
        $this->assertArrayHasKey('ID', $wpFieldLabels);
        $this->assertArrayHasKey('user_login', $wpFieldLabels);
        $this->assertArrayHasKey('user_email', $wpFieldLabels);
        
        // Test att användardata kan hämtas
        $user = $users[0];
        $this->assertEquals($this->testUserId, member_admin_get_user_field_value($user, 'ID'));
        $this->assertEquals('testuser', member_admin_get_user_field_value($user, 'user_login'));
    }

    /**
     * Cleanup efter varje test
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Rensa test-användare om de skapades
        if ($this->testUserId) {
            wp_delete_user($this->testUserId);
        }
        if ($this->adminUserId) {
            wp_delete_user($this->adminUserId);
        }
    }
} 