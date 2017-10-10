<?php
/**
 * The base user role class.
 *
 * @since      v1.0.0
 *
 * @package    RBP_Roles
 * @subpackage RBP_Roles/core
 */

defined( 'ABSPATH' ) || die;

/**
 * Class RBP_User_Role
 *
 * The base class for creating a user role.
 *
 * @since v1.0.0
 *
 * @package RBP_Roles
 * @subpackage RBP_Roles/core
 */
class RBP_User_Role {

	/**
	 * The ID of the role.
	 *
	 * @since v1.0.0
	 *
	 * @var string
	 */
	public $role_id;

	/**
	 * The name of the role.
	 *
	 * @since v1.0.0
	 *
	 * @var string
	 */
	public $role_name;

	/**
	 * The role's capabilities.
	 *
	 * @since v1.0.0
	 *
	 * @var array
	 */
	public $capabilities;

	/**
	 * The arguments for the role.
	 *
	 * @since v1.0.0
	 *
	 * @var array
	 */
	public $args;

	/**
	 * RBP_User_Role constructor
	 *
	 * @since v1.0.0
	 */
	function __construct( $role_id, $role_name, $capabilities, $args = array() ) {

		$this->role_id      = $role_id;
		$this->role_name    = $role_name;
		$this->args         = $args;
		$this->capabilities = $this->setup_capabilities( $capabilities );

		$this->add_role();
	}

	/**
	 * Sets up the role's capabilities.
	 *
	 * @since v1.0.0
	 *
	 * @param array $capabilities Any manually set capabilities.
	 *
	 * @return array The final capabilities.
	 */
	private function setup_capabilities( $capabilities ) {
		
		$capabilities = array_fill_keys( $capabilities, true );

		if ( isset( $this->args['base_role'] ) ) {
			if ( $base_role = get_role( $this->args['base_role'] ) ) {
				$capabilities = $capabilities + $base_role->capabilities;
			}
		}

		if ( isset( $this->args['exclude_caps'] ) ) {
			$capabilities = array_diff_key( $capabilities, $this->args['exclude_caps'] );
		}

		/**
		 * Allows filtering of the role's capabilities.
		 *
		 * @since v1.0.0
		 */
		$capabilities = apply_filters( "rbp_user_role_{$this->role_id}_capabilities", $capabilities, $this );

		return $capabilities;
	}

	/**
	 * Adds the role to WordPress.
	 *
	 * @since v1.0.0
	 */
	private function add_role() {

		if ( ! get_role( $this->role_id ) ) {
			add_role( $this->role_id, $this->role_name, $this->capabilities );
		}
	}
}