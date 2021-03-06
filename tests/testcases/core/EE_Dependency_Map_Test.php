<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) { exit('No direct script access allowed'); }
/**
 * Class EE_Dependency_Map_Test
 *
 * @package 			Event Espresso
 * @subpackage 	core
 * @author 				Darren Ethier
 * @since 				4.9.0
 * @group 				core
 *
 */
class EE_Dependency_Map_Test extends EE_UnitTestCase {

	/**
	 * @type EE_Dependency_Map
	 */
	protected $_dependency_map;


	public function setUp() {
		parent::setUp();
		$this->_dependency_map = EE_Dependency_Map::instance();
	}


	public function tearDown() {
		$this->_dependency_map->reset();
		parent::tearDown();
	}



	public function test_core_dependencies() {
		$this->validate_core_dependency_map( $this->_dependency_map->dependency_map() );
	}

	public function validate_core_dependency_map( $dependencies_or_load, $classname = '' ) {
		if ( is_array( $dependencies_or_load ) ) {
			foreach ( $dependencies_or_load as $classname => $dependency ) {
				$this->validate_core_dependency_map( $dependency, $classname );
			}
		} else {
			// verify that a valid class constant has been set for the value
			$this->assertEquals(
				( EE_Dependency_Map::load_new_object || EE_Dependency_Map::load_from_cache ),
				$dependencies_or_load,
				sprintf( 'The %s class has an invalid value in the EE_Dependency_Map.', $classname )
			);
			// now verify that a loader exists for the class
			$loader = $this->_dependency_map->class_loader( $classname );
			$this->assertNotEmpty(
				$loader,
				sprintf( 'A class loader should be set for "%s" but appears to be missing.', $classname )
			);
		}
	}




	public function test_core_class_loaders() {
		$skip = array(
			'EE_Session' => 'session doesn\'t load during unit tests',
			'EE_Messages_Template_Defaults' => 'Closure has required arguments'
		);
		//loop through and verify the class loader can successfully load the class it is set for
		foreach ( $this->_dependency_map->class_loaders() as $class => $loader ) {
			if ( isset( $skip[ $class ] ) ) {
				continue;
			}
			$dependency = $loader instanceof Closure ? $loader() : EE_Registry::instance()->$loader( $class );
			// helpers are simply loaded and do not return an instance
			if ( $loader === 'load_helper' ) {
				$this->assertTrue( $dependency );
			} else {
				$this->assertInstanceOf(
					$class,
					$dependency,
					sprintf(
						'The "%1$s" class has "%2$s" set as its loader, but instead of an object, we received "%3$s"',
						$class,
						$loader instanceof Closure ? print_r( $loader, true ) : $loader,
						print_r( $dependency, true )
					)
				);
			}
		}
	}



	public function test_core_class_loader_for_EE_Messages_Template_Defaults() {
		/** @type EE_Message_Resource_Manager $Message_Resource_Manager */
		$Message_Resource_Manager = EE_Registry::instance()->load_lib( 'Message_Resource_Manager' );
		$loader = $this->_dependency_map->class_loader('EE_Messages_Template_Defaults');
		$this->assertInstanceOf( 'Closure', $loader );
		$Messages_Template_Defaults = $loader(
			array(
				$Message_Resource_Manager->valid_messenger( 'html' ),
				$Message_Resource_Manager->valid_message_type( 'invoice' )
			)
		);
		$this->assertInstanceOf( 'EE_Messages_Template_Defaults', $Messages_Template_Defaults );
	}


	public function test_register_class_loader() {
		$this->_dependency_map->register_class_loader( 'Dummy_Class', 'load_lib' );
		$actual_class_loader = $this->_dependency_map->class_loader( 'Dummy_Class' );
		$this->assertNotEmpty( $actual_class_loader );
		$this->assertEquals( 'load_lib', $actual_class_loader );

		$this->setExpectedException(
			'EE_Error',
			'"dummy_loader" is not a valid loader method on EE_Registry.'
		);

		$this->_dependency_map->register_class_loader( 'Dummy_Class', 'dummy_loader' );
	}



	public function test_register_dependency() {
		//test a successful registration.
		$registered = $this->_dependency_map->register_dependencies( 'Dummy_Class', array( 'EE_Something', 'EE_Something_Else' ) );
		$this->assertTrue( $registered );
		$actual_dependency_map = $this->_dependency_map->dependency_map();
		$this->assertTrue( isset( $actual_dependency_map['Dummy_Class'] ) );
		$this->assertEquals( array( 'EE_Something', 'EE_Something_Else' ), $actual_dependency_map['Dummy_Class'] );

		//test a unsuccessful registration (cannot override an existing dependency.
		$registered = $this->_dependency_map->register_dependencies( 'Dummy_Class', array() );
		$this->assertFalse( $registered );
	}



}
// Location: tests/testcases/core/EE_Dependency_Map_Test.php
