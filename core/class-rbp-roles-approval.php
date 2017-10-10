<?php
/**
 * Force certain User Roles to go through an Approval Workflow in order to post content
 *
 * @since      1.0.0
 *
 * @package    RBP_Roles
 * @subpackage RBP_Roles/core
 */

defined( 'ABSPATH' ) || die;

class RBP_User_Roles_Approval {
	
	public $restricted_user_roles = array();
	
	public $restricted_content_types = array();
	
	function __construct( $restricted_user_roles, $restricted_content_types = array() ) {
		
		$this->restricted_user_roles = $restricted_user_roles;
		$this->restricted_content_types = $restricted_content_types;
		
		add_filter( 'pre_get_posts', array( $this, 'modify_pre_get_posts' ), 100 );
		add_action( 'add_meta_boxes', array( $this, 'modify_meta_boxes' ), 100 );
		add_action( 'admin_notices', array( $this, 'revision_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'revision_redirect' ) );
		add_action( 'pre_post_update', array( $this, 'save_pending' ), 1, 2 );
		add_action( 'pre_post_update', array( $this, 'push_revision' ), 1, 2 );
		add_filter( 'the_title', array( $this, 'list_table_title' ) );
		
		if ( isset( $_GET['rbp_revision_delete'] ) ) {
			add_action( 'after_delete_post', array( $this, 'delete_revision_redirect' ) );
		}
		
	}
	
	/**
	 * Modfiies the Main Post Query. Prevents confusing duplicates where "Revision" Posts are used
	 * 
	 * @param		object $wp_query WP_Query Object
	 *                                   
	 * @access		public
	 * @since		1.0.0
	 * @return		void
	 */
	public function modify_pre_get_posts( $wp_query ) {
		
		global $current_screen;
        
        $user_role = rbp_userroles_current_role();
		
		$current_screen_possibilities = array();
		foreach ( $this->restricted_content_types as $content_type ) {
			$current_screen_possibilities[] = 'edit-' . $content_type;
		}
		
		if ( ( ! in_array( $user_role, $this->restricted_user_roles ) && ! current_user_can( 'manage_options' ) ) ||
		     ! is_admin() ||
		     ! $wp_query->is_main_query() ||
		     ! in_array( $current_screen->id, $current_screen_possibilities )
		) {
			return;
		}
		
		static $did_one = false;
		if ( ! $did_one ) {
			$did_one = true;
		}
		else {
			return;
		}
		
		// Prevents confusing duplicates
		$wp_query->set( 'meta_query', array(
			array(
				'key' => 'rbp_revision_child',
				'value' => '',
				'compare' => 'NOT EXISTS',
			),
		) );
		
	}
	
	/**
	 * Gets the edit post link even without priviledges
	 * 
	 * @param		integer $post_ID
	 * 
	 * @access		private
	 * @since		1.0.0
	 * @return		string  Post Edit Link
	 */
	private function get_edit_post_link( $post_ID ) {
		$post_type_object = get_post_type_object( get_post_type( $post_ID ) );
		return admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post_ID ) );
	}
	
	/**
	 * Add review MB and remove a couple others.
	 *
	 * @since		1.0.0
	 * @access		private
	 * @return		void
	 */
	public function modify_meta_boxes() {
		
		$current_user_role = rbp_userroles_current_role();

		if ( in_array( $current_user_role, $this->restricted_user_roles ) ||
			( current_user_can( 'manage_options' ) && get_post_meta( get_the_ID(), 'rbp_revision_parent', true ) )
		   ) {

			$post_type = get_post_type();

			if ( in_array( $post_type, $this->restricted_content_types ) ) {

				remove_meta_box( 'submitdiv', null, 'side' );

				remove_meta_box( 'pageparentdiv', null, 'side' );

				remove_meta_box( 'expirationdatediv', $post_type, 'side' );

				add_meta_box(
					'rbp_section_manager_submit',
					'Submit',
					array( $this, current_user_can( 'manage_options' ) ? 'push_revision_mb' : 'save_revision_mb' ),
					$post_type,
					'side',
					'high'
				);
				
			}

		}

	}

	/**
	 * Submit for review metabox.
	 *
	 * @since		1.0.0
	 * @access		public
	 * @return		void
	 */
	public function save_revision_mb() {

		wp_nonce_field( 'rbp_submit_review', 'rbp_submit_review_nonce' );

		if ( $revision_parent = get_post_meta( get_the_ID(), 'rbp_revision_parent', true ) ) :

			$delete_revision_link = add_query_arg(
				'rbp_revision_delete',
				$revision_parent,
				get_delete_post_link( get_the_ID(), '', true )
			);

			if ( get_post_status() == 'pending' ) : ?>

				<div class="submitbox" id="submitpost">
					<p>
						<input name="save" type="submit" class="button button-primary button-large" id="publish"
							   value="Update Revision">

						<a href="<?php echo $delete_revision_link; ?>"
						   class="submitdelete deletion">
							Delete Revision
						</a>
					</p>
				</div>

			<?php else : ?>
				<div class="submitbox" id="submitpost">
					<p>
						<input name="submit" type="submit" class="button button-primary button-large" id="publish"
							   value="Submit for Review">

						<input name="save" type="submit" class="button button-secondary button-large" id="publish"
							   value="Update Draft">

						<a href="<?php echo $delete_revision_link; ?>"
						   class="submitdelete deletion">
							Delete Draft
						</a>
					</p>
				</div>
			<?php endif;

		else : ?>
			<div class="submitbox" id="submitpost">
				<input name="submit" type="submit" class="button button-primary button-large" id="publish"
					   value="Submit for Review">

				<input name="save" type="submit" class="button button-secondary button-large" id="draft"
					   value="Save Draft">
			</div>
		<?php endif;

	}

	/**
	 * Push review metabox.
	 *
	 * @since		1.0.0
	 * @access		public
	 * @return		void
	 */
	public function push_revision_mb() {

		$delete_revision_link = add_query_arg(
			'rbp_revision_delete',
			get_post_meta( get_the_ID(), 'rbp_revision_parent', true ),
			get_delete_post_link( get_the_ID(), '', true )
		);
		?>

		<div class="submitbox" id="submitpost">
			<p>
				<input name="save" type="submit" class="button button-primary button-large" id="publish"
					   value="Accept Revision">

				<a href="<?php echo $delete_revision_link; ?>"
				   class="submitdelete deletion">
					Delete Revision
				</a>
			</p>
		</div>
		<?php

	}

	/**
	 * Outputs the revision notice if on a revision.
	 *
	 * @since		1.0.0
	 * @access		public
	 * @return		void
	 */
	public function revision_notice() {
		
		if ( ! in_array( get_current_screen()->id, $this->restricted_content_types ) ) {
			return;
		}
		
		if ( $parent_ID = get_post_meta( get_the_ID(), 'rbp_revision_parent', true ) ) : ?>

			<div class="notice notice-warning">
				<p>
					<?php if ( get_post_status() == 'draft' ) : ?>
					This is a revision draft.
					<?php else : ?>
					This is a revision that is awaiting approval.
					<?php endif; ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo add_query_arg( 'rbp_view_original', '1', get_edit_post_link( $parent_ID ) ); ?>"
					   class="button">
						View Original
					</a>
					<?php endif; ?>
				</p>
			</div>

		<?php endif;
		
		if ( $revision_ID = get_post_meta( get_the_ID(), 'rbp_revision_child', true ) ) : ?>

			<div class="notice notice-warning">
				<p>
					This page has a pending revision.
					<a href="<?php echo get_edit_post_link( $revision_ID ); ?>" class="button">
						View Revision
					</a>
				</p>
			</div>

		<?php endif;
		
	}
	
	/**
	 * Redirect to revision.
	 *
	 * @since		1.0.0
	 * @access		public
	 * @return		void
	 */
	function revision_redirect() {
		
		if ( ! is_admin() ||
			! ( $revision_ID = get_post_meta( get_the_ID(), 'rbp_revision_child', true ) ) ||
			isset( $_GET['rbp_view_original'] ) ||
			! in_array( get_current_screen()->id, $this->restricted_content_types )
		   ) {
			return;
		}
		
		$post_type_object = get_post_type_object( get_post_type() );
		
		wp_redirect( $this->get_edit_post_link( $revision_ID ) );
		
		exit();
		
	}
	
	/**
	 * Saves a revision
	 * 
	 * @param		integer $post_ID
	 * @param		array   $data    Array of unslashed post data
	 * 
	 * @since		1.0.0
	 * @access		public
	 * @return		void
	 */
	public function save_pending( $post_ID, $data ) {
		
		global $post;
		
		if ( ! isset( $_POST['rbp_submit_review_nonce'] ) ||
			! wp_verify_nonce( $_POST['rbp_submit_review_nonce'], 'rbp_submit_review' ) ||
			! current_user_can( 'edit_' . str_replace( '-', '_', get_post_type() ) . 's' )
		   ) {
			return;
		}
		
		// Only fire once
		remove_action( 'pre_post_update', array( $this, 'save_pending' ), 1 );
		
		$is_draft = isset( $_POST['save'] );
		$submit   = isset( $_POST['submit'] );
		if ( $revision_parent = get_post_meta( $post_ID, 'rbp_revision_parent', true ) ) {
			
			// Revision exists, need to update
			$revision_ID = $post_ID;
			
			// Submit for review
			if ( $submit ) {
				// Add action instead of saving here, because it will be overridden later otherwise.
				add_action( 'save_post', array( $this, 'set_pending' ) );
			}
			
		}
		else {
			
			// First revision
			$revision_ID = 0;
			$revision_ID = wp_update_post( array(
				'ID'           => $revision_ID,
				'post_status'  => $is_draft ? 'draft' : 'pending',
				'post_title'   => $data['post_title'],
				'post_content' => $data['post_content'],
			) );

			// Ensure the Post Meta from the Original Post still makes it into the Revision
			$this->preserve_section_meta( $post_ID, $revision_ID );

		}
		
		$post_type_object  = get_post_type_object( get_post_type() );
		$revision_edit_URL = $this->get_edit_post_link( $revision_ID );
		
		// Let the site admin know (not if draft though)
		if ( $submit ) {
			
			$current_user = wp_get_current_user();
			$message = "A revision has been submitted for the page \"" . get_the_title( $post_ID ) . "\".\n
				It was submitted by {$current_user->display_name}.\n
				You can edit this post here: $revision_edit_URL";
			wp_mail( 'steve@realbigmarketing.com', 'Realbigplugins - New Revision Submission', $message );
			
		}
		
		// If new post, update meta and redirect
		if ( $revision_ID !== $post_ID && $revision_ID && ! is_wp_error( $revision_ID ) ) {
			
			// Create post to post relationships
			update_post_meta( $revision_ID, 'rbp_revision_parent', $post_ID );
			update_post_meta( $post_ID, 'rbp_revision_child', $revision_ID );
			wp_redirect( $revision_edit_URL );
			exit();
			
		}
		
	}
	
	/**
	 * Set the status to pending.
	 * 
	 * @param		integer $post_ID The being saved post ID
	 *                                               
	 * @access		public
	 * @since		1.0.0
	 * @return		void
	 */
	public function set_pending( $post_ID ) {
		
		remove_action( 'save_post', array( $this, 'set_pending' ) );
		wp_update_post( array(
			'ID'          => $post_ID,
			'post_status' => 'pending',
		) );
		
	}
	
	/**
	 * Push a revision
	 * 
	 * @param		integer $post_ID
	 * @param		array   $data    Array of unslashed post data
	 *                                                  
	 * @access		public
	 * @since		1.0.0
	 * @return		void
	 */
	public function push_revision( $post_ID, $data ) {
		
		if ( ! current_user_can( 'manage_options' ) ||
			! ( $parent_ID = get_post_meta( $post_ID, 'rbp_revision_parent', true ) )
		   ) {
			return;
		}
		
		remove_action( 'pre_post_update', array( $this, 'push_revision' ), 1 );
		
		delete_post_meta( $parent_ID, 'rbp_revision_child' );
		
		wp_delete_post( $post_ID, true );
		
		wp_update_post( array(
			'ID'           => $parent_ID,
			'post_title'   => $data['post_title'],
			'post_content' => $data['post_content'],
		) );
		
		wp_redirect( get_edit_post_link( $parent_ID, false ) );
		
		exit();
		
	}
	
	/**
	 * Adds revision notice when post has revision
	 * 
	 * @param		string $title Title of Post in the List Table
	 *                                                 
	 * @access		public
	 * @since		1.0.0
	 * @return		string Title of Post in the List Table
	 */
	public function list_table_title( $title ) {
		
		$current_screen_possibilities = array();
		foreach ( $this->restricted_content_types as $content_type ) {
			$current_screen_possibilities[] = 'edit-' . $content_type;
		}
		
		if ( ! function_exists( 'get_current_screen' ) || ! is_admin() || ! in_array( get_current_screen()->id, $current_screen_possibilities ) ) {
			return $title;
		};
		
		if ( $child_ID = get_post_meta( get_the_ID(), 'rbp_revision_child', true ) ) {
			if ( get_post_status( $child_ID ) == 'draft' ) {
				return "$title (Revision Draft)";
			} else {
				return "$title (Revision Pending)";
			}
		}
		elseif ( get_post_meta( get_the_ID(), 'rbp_revision_parent', true ) ) {
			return "$title (Has Pending Revisions)";
		}
		else {
			return $title;
		}
		
	}
	
	/**
	 * Redirect if deleting revision.
	 *
	 * @access		public
	 * @since		1.0.0
	 * @return		void
	 */
	function delete_revision_redirect() {
		
		$parent_ID = $_GET['rbp_revision_delete'];
		
		delete_post_meta( $parent_ID, 'rbp_revision_child' );
		
		wp_redirect( $this->get_edit_post_link( $parent_ID ) );
		
		exit();
		
	}
	
	/**
	 * If a User doesn't have a Metabox displayed, the created Revision does not hold that data.
	 * This means it is overwritten upon approval, which is no good in terms of Sections.
	 * 
	 * @param integer $post_ID     The Original Post's Post ID
	 * @param integer $revision_ID The Revision's Post ID
	 *                                                 
	 * @access		private
	 * @since		1.0.0
	 * @return		void
	 */
	private function preserve_section_meta( $post_ID, $revision_ID ) {

		/**
		 * Allows filtering of the Meta Keys that will be excluded from preservation
		 *
		 * @since 1.0.0
		 */
		$exclude_meta = apply_filters( 'rbp_userrole_exclude_revision_meta_keys', array(
			'_edit_last',
			'_edit_lock',
		) );

		// Grab all Post Meta Keys set in the original Post
		$post_meta_keys = get_post_meta( $post_ID, '', true );

		foreach ( $post_meta_keys as $key => $value ) {

			// Exclude certain keys
			if ( in_array( $key, $exclude_meta ) ) continue;

			// In case the User did set the Meta manually in the Revision
			if ( isset( $_POST[ $key ] ) ) continue;

			// Ensure the revision holds it, even if the User making the revision didn't send that data
			update_post_meta( $revision_ID, $key, reset( $value ) );

		}

	}
	
}