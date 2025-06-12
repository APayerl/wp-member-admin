<?php
/**
 * Enkla tester för Member Admin utan databas-koppling
 */

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase {

	public function test_plugin_file_exists() {
		$plugin_file = dirname( __DIR__ ) . '/member-admin.php';
		$this->assertFileExists( $plugin_file, 'Huvudplugin-filen ska finnas' );
	}

	public function test_plugin_version_constant() {
		require_once dirname( __DIR__ ) . '/member-admin.php';
		$this->assertTrue( defined( 'MEMBER_ADMIN_VERSION' ), 'MEMBER_ADMIN_VERSION konstant ska vara definierad' );
		$this->assertEquals( '1.0.0', MEMBER_ADMIN_VERSION, 'Version ska vara 1.0.0' );
	}

	public function test_includes_directory_exists() {
		$includes_dir = dirname( __DIR__ ) . '/includes';
		$this->assertDirectoryExists( $includes_dir, 'Includes-katalogen ska finnas' );
	}

	public function test_required_classes_exist() {
		require_once dirname( __DIR__ ) . '/member-admin.php';
		
		$this->assertTrue( class_exists( 'MemberAdmin' ), 'MemberAdmin klassen ska finnas' );
		
		// Testa att include-filerna finns (utan att ladda dem helt)
		$includes_dir = dirname( __DIR__ ) . '/includes';
		$this->assertFileExists( $includes_dir . '/class-acf-field-manager.php', 'ACF Field Manager fil ska finnas' );
		$this->assertFileExists( $includes_dir . '/class-user-list-customizer.php', 'User List Customizer fil ska finnas' );
		$this->assertFileExists( $includes_dir . '/class-admin-interface.php', 'Admin Interface fil ska finnas' );
	}

	public function test_composer_autoload_works() {
		$autoload_file = dirname( __DIR__ ) . '/vendor/autoload.php';
		$this->assertFileExists( $autoload_file, 'Composer autoload fil ska finnas' );
		
		require_once $autoload_file;
		$this->assertTrue( class_exists( 'PHPUnit\Framework\TestCase' ), 'PHPUnit ska laddas via autoload' );
	}
} 