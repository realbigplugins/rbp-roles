<?php
/**
 * Special functionality for Guest Contributors
 *
 * @since      v1.0.0
 *
 * @package    RBP_Roles
 * @subpackage RBP_Roles/core/roles
 */

defined( 'ABSPATH' ) || die;

/**
 * Class RBP_User_Role_Guest_Contributor
 *
 * @since v1.0.0
 *
 * @package RBP_Roles
 * @subpackage RBP_Roles/core/roles
 */
class RBP_User_Role_Guest_Contributor {

	/**
	 * RBP_User_Role_Guest_Contributor constructor
	 *
	 * @since v1.0.0
	 */
	function __construct() {

		add_action( 'admin_menu', array( $this, 'modify_menu' ) );
		
		add_action( 'current_screen', array( $this, 'deny_access' ) );

	}

	/**
	 * Removes specific Menu Items that have `post` defined as their Capability Type
	 *
	 * @access		public
	 * @since		1.0.0
	 * @return		void
	 */
	public function modify_menu() {

		if ( RBPROLES()->current_role !== 'guest_contributor' ) {
			return false;
		}

		remove_menu_page( 'edit.php?post_type=hollerbox' );
		remove_menu_page( 'edit-comments.php' );
		remove_menu_page( 'upload.php' );

	}
	
	/**
	 * Prevent Users from getting sneaky and getting places they should not
	 * 
	 * @access		public
	 * @since		1.0.0
	 * @return		void
	 */
	public function deny_access() {
		
		global $current_screen;
		
		if ( RBPROLES()->current_role == 'guest_contributor' && 
			is_admin() && 
			( strpos( $current_screen->id, 'hollerbox' ) !== false || $current_screen->id == 'edit-comments' ) ) {
				
				wp_die(
					'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
					'<p>' . __( 'You are not allowed here.', 'rbp-roles' ) . '</p>',
					403
				);
			
			}
		
	}

}

$instance = new RBP_User_Role_Guest_Contributor();