<?php
/**
 * Provides helper functions.
 *
 * @since	  1.0.0
 *
 * @package	RBP_Roles
 * @subpackage RBP_Roles/core
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Returns the main plugin object
 *
 * @since		1.0.0
 *
 * @return		RBP_Roles
 */
function RBPROLES() {
	return RBP_Roles::instance();
}

/**
 * Gets the current user role.
 *
 * @since		1.0.0
 * @return		string Current role.
 */
function rbp_userroles_current_role() {
	return RBPROLES()->current_role;
}