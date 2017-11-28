<?php
/**
 * Plugin Name: RBP User Roles
 * Description: Adds additional User Roles for RBP
 * Version: 1.0.0
 * Text Domain: rbp-roles
 * Author: Eric Defore
 * Author URI: http://realbigmarketing.com/
 * Contributors: d4mation
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RBP_Roles' ) ) {

	/**
	 * Main RBP_Roles class
	 *
	 * @since	  1.0.0
	 */
	class RBP_Roles {
		
		/**
		 * @var			RBP_Roles $plugin_data Holds Plugin Header Info
		 * @since		1.0.0
		 */
		public $plugin_data;
		
		/**
		 * @var			RBP_Roles $admin_errors Stores all our Admin Errors to fire at once
		 * @since		1.0.0
		 */
		private $admin_errors;
		
		/**
		 * @var			RBP_Roles $roles the new Roles
		 * @since		1.0.0
		 */
		public $roles = array();
		
		/**
		 * @var			RBP_Roles $current_role The current user's role
		 * @since		1.0.0
		 */
		public $current_role = false;

		/**
		 * Get active instance
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  object self::$instance The one true RBP_Roles
		 */
		public static function instance() {
			
			static $instance = null;
			
			if ( null === $instance ) {
				$instance = new static();
			}
			
			return $instance;

		}
		
		protected function __construct() {
			
			$this->setup_constants();
			$this->load_textdomain();
			
			if ( version_compare( get_bloginfo( 'version' ), '4.4' ) < 0 ) {
				
				$this->admin_errors[] = sprintf( _x( '%s requires v%s of %s or higher to be installed!', 'Outdated Dependency Error', 'rbp-roles' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '4.4', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>WordPress</strong></a>' );
				
				if ( ! has_action( 'admin_notices', array( $this, 'admin_errors' ) ) ) {
					add_action( 'admin_notices', array( $this, 'admin_errors' ) );
				}
				
				return false;
				
			}
			
			$this->require_necessities();
			
			// Register our CSS/JS for the whole plugin
			add_action( 'init', array( $this, 'register_scripts' ) );
			
			// Add our Roles
			add_action( 'init', array( $this, 'add_roles' ) );
			
			// Store the current Role in our Object
			add_action( 'init', array( $this, 'get_current_role' ) );
			
			// Removes added Roles on Plugin Deactivation
			register_deactivation_hook( __FILE__, array( $this, 'remove_roles' ) );
			
		}

		/**
		 * Setup plugin constants
		 *
		 * @access	  private
		 * @since	  1.0.0
		 * @return	  void
		 */
		private function setup_constants() {
			
			// WP Loads things so weird. I really want this function.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}
			
			// Only call this once, accessible always
			$this->plugin_data = get_plugin_data( __FILE__ );

			if ( ! defined( 'RBP_Roles_VER' ) ) {
				// Plugin version
				define( 'RBP_Roles_VER', $this->plugin_data['Version'] );
			}

			if ( ! defined( 'RBP_Roles_DIR' ) ) {
				// Plugin path
				define( 'RBP_Roles_DIR', plugin_dir_path( __FILE__ ) );
			}

			if ( ! defined( 'RBP_Roles_URL' ) ) {
				// Plugin URL
				define( 'RBP_Roles_URL', plugin_dir_url( __FILE__ ) );
			}
			
			if ( ! defined( 'RBP_Roles_FILE' ) ) {
				// Plugin File
				define( 'RBP_Roles_FILE', __FILE__ );
			}

		}

		/**
		 * Internationalization
		 *
		 * @access	  private 
		 * @since	  1.0.0
		 * @return	  void
		 */
		private function load_textdomain() {

			// Set filter for language directory
			$lang_dir = RBP_Roles_DIR . '/languages/';
			$lang_dir = apply_filters( 'rbp_roles_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), 'rbp-roles' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'rbp-roles', $locale );

			// Setup paths to current locale file
			$mofile_local   = $lang_dir . $mofile;
			$mofile_global  = WP_LANG_DIR . '/rbp-roles/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/rbp-roles/ folder
				// This way translations can be overridden via the Theme/Child Theme
				load_textdomain( 'rbp-roles', $mofile_global );
			}
			else if ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/rbp-roles/languages/ folder
				load_textdomain( 'rbp-roles', $mofile_local );
			}
			else {
				// Load the default language files
				load_plugin_textdomain( 'rbp-roles', false, $lang_dir );
			}

		}
		
		/**
		 * Include different aspects of the Plugin
		 * 
		 * @access	  private
		 * @since	  1.0.0
		 * @return	  void
		 */
		private function require_necessities() {
			
			// Base Class
			require_once __DIR__ . '/core/class-rbp-user-role.php';
			
			// Special Functionality Per-Role
			require_once __DIR__ . '/core/roles/class-rbp-user-role-guest-contributor.php';
			
			// Force Approval Workflow for certain Roles and CPTs
			require_once __DIR__ . '/core/class-rbp-roles-approval.php';
			
			// Not used in this implementation, but code and example is here for future use
			/*
			new RBP_User_Roles_Approval( 
				array(
					//'guest_author',
				),
				array(
					//'post',
				)
			);
			*/
			
		}
		
		/**
		 * Gets the current user's role.
		 * 
		 * @access		private
		 * @since		1.0.0
		 * @return		void
		 */
		public function get_current_role() {
			
			if ( is_user_logged_in() ) {
				$current_user       = wp_get_current_user();
				$roles              = $current_user->roles;
				$this->current_role = array_shift( $roles );
			}

			// Staging for some reason always had NULL as the Role. This fixes it.
			// My Local environment worked just fine though, so maybe in most cases this won't be needed
			if ( $this->current_role === NULL ) {

				global $user_ID;

				$user_data = get_userdata( $user_ID );
				$user_role = array_shift( $user_data->roles );
				$this->current_role = $user_role;

			}

		}
		
		public function add_roles() {
			
			$administrator = get_role( 'administrator' );
			
			$capability_types = apply_filters( 'rbp_capability_types', array(
				'documentation',
			) );
			
			foreach ( $capability_types as $capability_type ) {
				
				$administrator->add_cap( 'read_private_' . $capability_type . 's' );
				$administrator->add_cap( 'publish_' . $capability_type . 's' );
				$administrator->add_cap( 'edit_' . $capability_type . '' );
				$administrator->add_cap( 'edit_' . $capability_type . 's' );
				$administrator->add_cap( 'delete_' . $capability_type . '' );
				$administrator->add_cap( 'edit_private_' . $capability_type . '' );
				$administrator->add_cap( 'delete_private_' . $capability_type . '' );
				$administrator->add_cap( 'edit_published_' . $capability_type . '' );
				$administrator->add_cap( 'delete_published_' . $capability_type . '' );
				$administrator->add_cap( 'edit_others_' . $capability_type . 's' );
				$administrator->add_cap( 'delete_others_' . $capability_type . 's' );
				
			}
			
			$this->roles['guest_author'] = new RBP_User_Role(
				'guest_author',
				__( 'Guest Author', 'rbp-roles' ),
				array(
				),
				array(
					'base_role' => 'author',
					'exclude_caps' => array(
						'publish_posts' => true, // array_diff_key
					),
				)
			);
			
			$this->roles['guest_contributor'] = new RBP_User_Role(
				'guest_contributor',
				__( 'Guest Contributor', 'rbp-roles' ),
				array(
				),
				array(
					'base_role' => 'contributor',
				)
			);
			
		}
		
		/**
		 * Show admin errors.
		 * 
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  HTML
		 */
		public function admin_errors() {
			?>
			<div class="error">
				<?php foreach ( $this->admin_errors as $notice ) : ?>
					<p>
						<?php echo $notice; ?>
					</p>
				<?php endforeach; ?>
			</div>
			<?php
		}
		
		/**
		 * Register our CSS/JS to use later
		 * 
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  void
		 */
		public function register_scripts() {
			
			wp_register_style(
				'rbp-roles',
				RBP_Roles_URL . 'assets/css/style.css',
				null,
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBP_Roles_VER
			);
			
			wp_register_script(
				'rbp-roles',
				RBP_Roles_URL . 'assets/js/script.js',
				array( 'jquery' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBP_Roles_VER,
				true
			);
			
			wp_localize_script( 
				'rbp-roles',
				'rbpRoles',
				apply_filters( 'rbp_roles_localize_script', array() )
			);
			
			wp_register_style(
				'rbp-roles-admin',
				RBP_Roles_URL . 'assets/css/admin.css',
				null,
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBP_Roles_VER
			);
			
			wp_register_script(
				'rbp-roles-admin',
				RBP_Roles_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBP_Roles_VER,
				true
			);
			
			wp_localize_script( 
				'rbp-roles-admin',
				'rbpRoles',
				apply_filters( 'rbp_roles_localize_admin_script', array() )
			);
			
		}
		
		public function remove_roles() {
		
			$administrator = get_role( 'administrator' );

			$capability_types = apply_filters( 'rbp_capability_types', array(
				'documentation',
			) );

			foreach ( $capability_types as $capability_type ) {

				$administrator->remove_cap( 'read_private_' . $capability_type . 's' );
				$administrator->remove_cap( 'publish_' . $capability_type . 's' );
				$administrator->remove_cap( 'edit_' . $capability_type . '' );
				$administrator->remove_cap( 'edit_' . $capability_type . 's' );
				$administrator->remove_cap( 'delete_' . $capability_type . '' );
				$administrator->remove_cap( 'edit_private_' . $capability_type . '' );
				$administrator->remove_cap( 'delete_private_' . $capability_type . '' );
				$administrator->remove_cap( 'edit_published_' . $capability_type . '' );
				$administrator->remove_cap( 'delete_published_' . $capability_type . '' );
				$administrator->remove_cap( 'edit_others_' . $capability_type . 's' );
				$administrator->remove_cap( 'delete_others_' . $capability_type . 's' );

			}

			remove_role( 'guest_author' );
			
			remove_role( 'guest_contributor' );

		}
		
	}
	
} // End Class Exists Check

/**
 * The main function responsible for returning the one true RBP_Roles
 * instance to functions everywhere
 *
 * @since	  1.0.0
 * @return	  \RBP_Roles The one true RBP_Roles
 */
add_action( 'plugins_loaded', 'rbp_roles_load' );
function rbp_roles_load() {

	require_once __DIR__ . '/core/rbp-roles-functions.php';
	RBPROLES();

}