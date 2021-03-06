<?php
/*
Plugin name: Tidal
Plugin URI: http://oomphinc.com/
Description: Tidal connects individuals to top brands and publishers
Author: Tidal
Author URI: https://tid.al/
Version: 1.0.0
*/

/*  Copyright 2014  Tidal Labs

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/***
 ** Tidal: The WordPress plugin!
 ***/

define( 'TIDAL_VERSION', '1.0' );

class Tidal {
	// Define and register singleton
	private static $instance = false;
	public static function instance() {
		if( !self::$instance )
			self::$instance = new Tidal;

		return self::$instance;
	}

	private function __clone() { }

	// Contributor post type
	const post_type                 = 'tidal_contributor';
	const username_meta             = 'tidal_username';

	// Post meta
	const tidal_id_meta             = 'tidal_id';
	const tidal_contributor_meta    = 'tidal_contributor';
	const tidal_callback_token_meta = 'callback_token';

	// Taxonomy
	const taxonomy                  = 'tidal';

	// Settings
	private $options                = '';
	const dashboard_url             = 'https://tid.al/';


	/**
	 * Register actions and filters.
	 */
	private function __construct() {
		// Enqueue essential assets
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		//add_filter( 'tiny_mce_before_init', array( $this, 'filter_tiny_mce_before_init' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'pre_get_posts', array( $this, 'action_pre_get_posts' ) );

		add_action( 'save_post', array( $this, 'action_save_post' ), 10, 3 );
		add_action( 'save_post_tidal_contributor', array( $this, 'action_save_post_tidal_contributor' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'action_deleted_post' ) );
		add_filter( 'add_post_metadata', array( $this, 'filter_add_post_metadata' ), 10, 5 );
		add_action( 'added_post_meta', array( $this, 'action_added_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'action_added_post_meta' ), 10, 4 );
		add_filter( 'pre_insert_term', array( $this, 'filter_pre_insert_term' ), 10, 2 );

		// Attribution
		add_filter( 'the_author', array( $this, 'filter_the_author' ) );
		add_filter( 'get_author_data', array( $this, 'filter_get_author_data' ), 10, 1 );
		add_filter( 'get_avatar', array( $this, 'filter_get_avatar' ), 10, 5 );
		add_filter( 'author_link', array( $this, 'filter_author_link' ), 10, 3 );

		// Theming
		add_filter( 'post_class', array( $this, 'filter_post_class' ), 10, 3 );
		add_filter( 'is_tidal_post', array( $this, 'filter_is_tidal_post' ), 10, 2 );
		add_filter( 'tidal_contributors', array( $this, 'filter_tidal_contributors' ) );
		add_filter( 'template_include', array( $this, 'filter_template_include' ), 99 );

		// XMLRPC
		add_filter( 'xmlrpc_methods', array( $this, 'filter_xmlrpc_methods' ) );

		// Callback to send "on published" email notification and update stats
		add_action( 'publish_post', array( $this, 'action_post_published' ), 10, 2 );

		$this->options = get_option('tidal');
	}

	/**
	 * Register post types and taxonomies.
	 */
	function action_init() {
		// Post types
		register_post_type( $this::post_type, array(
			'labels'               => array(
				'name'               => 'Contributor',
				'singular_name'      => 'Contributor',
				'menu_name'          => 'Contributors',
				'name_admin_bar'     => 'Add New Contributor',
				'all_items'          => 'All Contributors',
				'add_new'            => 'Add New Contributor',
				'add_new_item'       => 'Add New Contributor',
				'edit_item'          => 'Edit Contributor',
				'new_item'           => 'New Contributor',
				'view_item'          => 'View Contributor',
				'search_items'       => 'Search Contributors',
				'not_found'          => 'No Contributors found',
				'not_found_in_trash' => 'Contributors not found in trash',
			),
			'description'          => 'Tidal Contributors',
			'public'               => true,
			'exclude_from_search'  => true,
			'show_in_nav_menus'    => false,
			'menu_icon'            => 'dashicons-id',
			'supports'             => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'taxonomies'           => array('post_tag'),
		) );

		// Taxonomies
		$rewrite_slug = ! empty( $this->options['contributor_base'] ) ? $this->options['contributor_base'] : 'contributor';

		register_taxonomy(
			$this::taxonomy,
			array('post'),
			array(
				'labels'        => array(
					'name'              => 'Username',
					'singular_name'     => 'Username',
					'all_items'         => 'All Usernames',
					'edit_item'         => 'Edit Username',
					'view_item'         => 'View Username',
					'update_item'       => 'Update Username',
					'add_new_item'      => 'Add New Username',
					'new_item_name'     => 'New Username',
					'search_items'      => 'Search Usernames',
					'popular_items'     => 'Top Usernames',
				),
				'public'        => false,
				'rewrite'       => array(
					'slug' => $rewrite_slug,
				),
		) );

		// Contributed endpoint
		$endpoint = ! empty( $this->options['contributed_endpoint'] ) ? $this->options['contributed_endpoint'] : 'contributed';
		add_rewrite_endpoint( $endpoint, EP_ROOT );
	}

	/**
	 * Add options page.
	 */
	function action_admin_menu() {
		add_menu_page(
			'Tidal Labs',
			'Tidal Labs',
			'manage_options',
			'tidal-labs',
			array( $this, 'create_admin_page' ),
			'dashicons-id-alt'
		);

		add_submenu_page(
			'tidal-labs',
			'Tidal Labs Settings',
			'Tidal Labs Settings',
			'manage_options',
			'tidal-labs',
			array( $this, 'create_admin_settings_page' )
		);

		global $submenu;

		$submenu['tidal-labs'][] = array( 'Tidal Dashboard', 'manage_options', $this::dashboard_url );

	}

	/**
	 * Add settings, sections and fields.
	 */
	function action_admin_init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

		register_setting(
			'tidal',
			'tidal',
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'tidal',
			'Permalinks',
			array( $this, 'render_settings_section' ),
			'tidal_settings_page'
		);

		add_settings_field(
			'tidal_contributor',
			'Contributor Base',
			array( $this, 'settings_field_contributor' ),
			'tidal_settings_page',
			'tidal'
		);

		add_settings_field(
			'tidal_contributed',
			'Contributed Endpoint',
			array( $this, 'settings_field_contributed' ),
			'tidal_settings_page',
			'tidal'
		);

		add_settings_field(
			'channel_id',
			'Channel ID',
			array( $this, 'settings_field_channel_id' ),
			'tidal_settings_page',
			'tidal'
		);
	}

	/**
	 * Enqueue scripts in admin
	 */
	function action_admin_enqueue_scripts() {
		if( apply_filters( 'is_tidal_post', false) || $this::post_type == get_post_type() ) {
			wp_enqueue_script( 'tidal-js', plugins_url( 'js/tidal.js', __FILE__ ), array( 'jquery' ), TIDAL_VERSION, true );
		}
	}

	/**
	 * Modify the main query
	 *
	 * @param $query WP_Query object
	 */
	function action_pre_get_posts( $query ) {
		if( ! $query->is_main_query() || $query->is_admin ) {
			return;
		}

		// Exclude Tidal-contributed posts from user that owns Tidal post
		if( is_author() ) {
			$query->set( 'meta_query', array( array(
				'key'     => $this::tidal_id_meta,
				'compare' => 'NOT EXISTS',
			)));
		}

		// Get only Tidal-contributed posts for the contributed endpoint
		$endpoint = ! empty( $this->options['contributed_endpoint'] ) ? $this->options['contributed_endpoint'] : 'contributed';
		if( isset( $query->query_vars[ $endpoint ] ) ) {
			$term_ids = array();
			$contributed_term = '';

			if( $query->query_vars[ $endpoint ] ) {
				$contributed_term = get_term_by( 'name', $query->query_vars[ $endpoint ], $this::taxonomy );
			}

			if( $contributed_term ) {
				$term_ids[] = $contributed_term->term_id;
			}
			else {
				$terms = get_terms( $this::taxonomy );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
					foreach( $terms as $term ) {
						$term_ids[] = $term->term_id;
					}
				}
			}

			if( $term_ids ) {
				$query->set( 'tax_query', array( array(
					'taxonomy' => $this::taxonomy,
					'field'    => 'id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				)));
			}
		}
	}

	/**
	 * Render nothing to create menu stub.
	 */
	public function create_admin_settings_page() { }

	/**
	 * Render Tidal Settings options page.
	 */
	public function create_admin_page()
	{
		$this->options = get_option('tidal');
		?>
		<div class="wrap">
			<h2>Tidal Settings</h2>
			<form method="post" action="options.php">
			<?php
				settings_fields('tidal');
				do_settings_sections('tidal_settings_page');
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize each setting field
	 *
	 * @param array $input Option to sanatize
	 */
	public function sanitize( $input ) {
		if( ! empty( $input ) ) {
			foreach( $input as $key => $value ) {
				switch ( $key ) {
					case 'contributor_base' :
					case 'contributed-endpoint':
						$value = esc_url_raw( $value );
						$value = str_replace( 'http://', '', $value );
						break;
				}
				$input[ $key ] = $value;
			}
		}

		return $input;
	}

	/**
	 * Render Tidal permalinks setting section.
	 *
	 * @param array $args Settings section args
	 */
	function render_settings_section( $args ) {
		echo 'If you like, you may enter custom structures for your Tidal contributor archive pages and Tidal-contributed endpoint.';
	}

	/**
	 * Render contributor settings field.
	 *
	 * @param array $args Settings fields args
	 */
	function settings_field_contributor( $args ) {
		$contributor_base = isset( $this->options['contributor_base'] ) ? $this->options['contributor_base'] : 'contributor';
	?>
		<input name="tidal[contributor_base]" id="tidal-cotributor-base" type="text" value="<?php echo esc_attr( $contributor_base ); ?>" class="regular-text code" />
		<p class="description">Default value is <strong>contributor</strong>. Example: <code>http://example.org/contributor/john/</code></p>
	<?php
	}

	/**
	 * Render contributed settings field.
	 *
	 * @param array $args Settings fields args
	 */
	function settings_field_contributed( $args ) {
		$contributed_endpoint = isset( $this->options['contributed_endpoint'] ) ? $this->options['contributed_endpoint'] : 'contributed';
	?>
		<input name="tidal[contributed_endpoint]" id="tidal-cotributed-endpoint" type="text" value="<?php echo esc_attr( $contributed_endpoint ); ?>" class="regular-text code" />
		<p class="description">Default value is <strong>contributed</strong>. Example: <code>http://example.org/contributed/</code></p>
	<?php
	}

	/**
	 * Render channel ID field.
	 *
	 * @param array $args Settings fields args
	 */
	function settings_field_channel_id( $args ) {
		$channel_id = isset( $this->options['channel_id'] ) ? $this->options['channel_id'] : '';
	?>
		<input name="tidal[channel_id]" id="tidal-channel-id" type="text" value="<?php echo esc_attr( $channel_id ); ?>" class="regular-text code" />
		<p class="description">Channel ID (ask an engineer from Tidal for assistance)</p>
	<?php
	}

	/**
	 * Make tiny mce editor read only for Tidal-contributed posts
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	function filter_tiny_mce_before_init( $settings ) {
		$settings['readonly'] = 1;
		return $settings;
	}

	/**
	 * Adds the meta box container.
	 *
	 * @global WP_Post $post The Wordpress post object.
	 *
	 * @param string $post_type
	 */
	public function add_meta_box( $post_type ) {
		global $post;

		$tidal_id = get_post_meta( $post->ID, $this::tidal_id_meta, true );

		if ( $tidal_id && 'post' == $post_type ) {
			add_meta_box(
				'tidal_post_contributor',
				__( 'Tidal Contributor', 'tidal' ),
				array( $this, 'render_meta_box_content' ),
				'post',
				'side',
				'high'
			);
		}
	}

	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box_content( $post ) {
		if( $contrib_post = $this::get_contributor_post( $post->ID ) ) {

			$edit_link = get_edit_post_link( $contrib_post->ID );
			$username = get_post_meta( $contrib_post->ID, $this::username_meta, true );

			if( ! empty( $contrib_post->post_title ) ) {
				echo '<p style="text-align:center;">';
				echo '<a class="post-edit-link" href="' . $edit_link . '">';
				echo esc_html( $contrib_post->post_title );
				echo '</a>';
				echo '</p>';
			}

			if( $thumbnail = get_the_post_thumbnail( $contrib_post->ID, array( 250, 250 ) ) ) {
				echo '<a class="post-edit-link" href="' . $edit_link . '">';
				echo $thumbnail;
				echo '</a>';
			}

			if( ! empty( $contrib_post->post_title ) ) {
				echo '<p class="username">';
				echo 'Username: ';
				echo '<b>' . esc_html( $username ) . '</b>';
				echo '</p>';
			}

			if( ! empty( $contrib_post->post_content ) ) {
				echo '<p class="description">';
				echo esc_html( $contrib_post->post_content );
				echo '</p>';
			}
		}
		else {
			echo '<p style="text-align:center;">';
			echo __( 'No Tidal Contributor Found.', 'tidal' );
			echo '</p>';
		}
	}

	/**
	 * Perform validation when creating/updating posts or contributors.
	 *
	 * @param int     $post_id The ID of the post.
	 * @param WP_POST $post    A WP_Post object.
	 * @param bool    $update  True if the post was updated.
	 */
	function action_save_post( $post_id, $post, $update ) {

		if( 'publish' == $post->post_status ) {
			$tidal_term_id = 0;

			$tidal_id = get_post_meta( $post_id, $this::tidal_id_meta , true );

			if( $tidal_contributor = get_post_meta( $post_id, $this::tidal_contributor_meta , true ) ) {
				if( $term = term_exists( $tidal_contributor, $this::taxonomy ) ) {
					$tidal_term_id = $term['term_id'];
				}
			}


			// If any of the two required tidal meta data is not set or valid, remove contributor meta and set post to draft status
			if( ( ! $tidal_id && $tidal_contributor ) || ( $tidal_id && ! $tidal_term_id ) ) {
				delete_post_meta( $post_id, $this::tidal_contributor_meta );

				remove_action( 'save_post', array( $this, 'action_save_post' ), 10, 3 );
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
				add_action( 'save_post', array( $this, 'action_save_post' ), 10, 3 );
			}
		}
	}

	/**
	 * Notify Tidal platform API that the post was published so users can be emailed and stats updated
	 *
	 * @param int     $post_id The ID of the post.
	 * @param WP_POST $post    A WP_Post object.
	 */
	function action_post_published($post_id, $post) {

		$url = 'https://api.tid.al/v1/service/wordpress/notifypublished';

		$permalink = get_permalink( $post_id );

		$tidal_id = get_post_meta( $post_id, $this::tidal_id_meta , true );

		$token = get_post_meta( $post_id, $this::tidal_callback_token_meta, true );

		if ($token) {
			$response = wp_remote_post( $url, 
				array(
					'method' => 'POST',
					'timeout' => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking' => true,
					'headers' => array(),
					'body' => array( 'post_id' => $tidal_id, 'token' => $token, 'permalink' => $permalink ),
					'cookies' => array()
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				error_log( 
					'Something went wrong in tidal plugin action_post_published: $error_message . ' . 
					// add http code and message to log
					print_r( $response['response'], true )
				);
			}
		} else {
			error_log("Missing token in tidal plugin action_post_published for post id $tidal_id");
		}
	}

	/**
	 * Perform validation when creating/updating contributors.
	 * Set contributor post to draft if all Tidal contributors
	 * conditions are not met.
	 *
	 * @param int     $post_id The ID of the post.
	 * @param WP_POST $post    A WP_Post object.
	 * @param bool    $update  True if the post was updated.
	 */
	function action_save_post_tidal_contributor( $post_id, $post, $update ) {

		if( 'publish' == $post->post_status ) {
			if( $username = get_post_meta( $post_id, $this::username_meta, true ) ) {
				if( ! $term = get_term_by( 'slug', $post->post_name, $this::taxonomy ) ) {
					$result = wp_insert_term( $username, $this::taxonomy, array( 'slug' => $post->post_name ) );
				}
			}
			else {
				remove_action( 'save_post_tidal_contributor', array( $this, 'action_save_post_tidal_contributor' ), 10, 3 );
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
				add_action( 'save_post_tidal_contributor', array( $this, 'action_save_post_tidal_contributor' ), 10, 3 );
			}
		}
		else if( 'trash' == $post->post_status || 'draft' == $post->post_status) {
			// Set Tidal-contributed post to Draft status when contributor is set to Draft status or Trashed
			$query = new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => $this::taxonomy,
						'field' => 'slug',
						'terms' => $post->post_name,
					)
				)
			));

			if( ! empty( $query->posts ) ) {
				foreach( $query->posts as $post ) {
					$result = wp_update_post( array(
						'ID'          => $post->ID,
						'post_status' => 'draft',
					) );
				}
			}
		}

	}

	/**
	 * Trash Tidal-contributed posts when associated contributer
	 * is deleted.
	 *
	 * @param int $post_id The ID of the post.
	 */
	function action_deleted_post( $post_id ) {
		$post = get_post( $post_id );

		if( $this::post_type == $post->post_type ) {
			$query = new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => $this::taxonomy,
						'field' => 'slug',
						'terms' => $post->post_name,
					)
				)
			));

			if( ! empty( $query->posts ) ) {
				foreach( $query->posts as $post ) {
					$result = wp_update_post( array(
						'ID'          => $post->ID,
						'post_status' => 'trash',
					) );
				}
			}
		}
	}

	/**
	 * Logic for Tidal meta data
	 *
	 * @param null   $default    Default value of null.
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param bool   $unique     Optional, default is false. Whether the specified metadata key should be
	 * 		unique for the object. If true, and the object already has a value for the specified
	 * 		metadata key, no change will be made.
	 *
	 * @return null|bool Null to add meta data, true to continue
	 */
	function filter_add_post_metadata( $default, $object_id, $meta_key, $meta_value, $unique ) {

		$post = get_post( $object_id );

		// Handle contributor usernames
		if( $this::post_type == $post->post_type && $this::username_meta == $meta_key ) {

			$query = new WP_Query( array(
				'post_type'  => $this::post_type,
				'meta_query' => array( array(
					'key'   => $this::username_meta,
					'value' => $meta_value,
				)),
			));

			// If username already exist, shortcircuit add metadata
			if( ! empty( $query->posts ) ) {
				return true;
			}

			// If username meta key already exists, update meta value and associated Tidal term
			if( $username = get_post_meta( $object_id, $meta_key, true ) ) {
				update_post_meta( $object_id, $meta_key, $meta_value );

				if( $term = get_term_by( 'slug', $post->post_name, $this::taxonomy ) ) {
					wp_update_term( $term->ID, $this::taxonomy, array(
						'slug' => $meta_value,
					));
				}

				return true;
			}
		}

		// Handle contributor meta
		if( $this::tidal_contributor_meta == $meta_key ) {
			// Short circuit if the contributor does not exist
			if( ! $term = get_term_by( 'slug', $meta_value, $this::taxonomy ) ) {
				return true;
			}

			// If contributor meta already exists, update the meta value
			if( $contributor_id = get_post_meta( $object_id, $meta_key, true ) ) {
				if( $contributor_id != $meta_value ) {
					update_post_meta( $object_id, $meta_key, $meta_value );
				}

				return true;
			}
		}

		// Check if Tidal ID exists for Tidal-contributed posts
		if( $this::tidal_id_meta == $meta_key ) {
			$query = new WP_Query( array(
				'posts_per_page' => 1,
				'meta_query'     => array( array(
					'key'   => $this::tidal_id_meta,
					'value' => $meta_value,
				)),
			));

			// If Tidal ID already exist, shortcircuit add metadata
			if( ! empty( $query->posts ) ) {
				return true;
			}
		}

		return $default;
	}

	/**
	 * Tag Tidal-contributed post with Tidal taxonomy
	 *
	 * @param int    $meta_id    ID of the metadata object.
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 */
	function action_added_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if( $this::tidal_contributor_meta == $meta_key ) {
			wp_set_object_terms( $object_id, $meta_value, $this::taxonomy );
		}
	}

	/**
	 * Prevent Tidal taxonomy terms from being created unless an associated contributor exists.
	 *
	 * @param string $term     The term to add or update.
	 * @param string $taxonomy The taxonomy to which to add the term.
	 *
	 * @return string|WP_Error
	 */
	function filter_pre_insert_term( $term, $taxonomy ) {

		if( $this::taxonomy == $taxonomy ) {
			$query = new WP_Query( array(
				'post_type'  => $this::post_type,
				'meta_key'   => $this::username_meta,
				'meta_value' => $term,
			) );

			if( empty( $query->posts ) ) {
				return new WP_Error( 'contributor_not_exist', __( 'Contributor does not exist', 'tidal' ) );
			}
		}

		return $term;
	}

	/**
	 * Use contributor display name as author for Tidal posts.
	 *
	 * @param string $display_name The term to add or update.
	 *
	 * @return string
	 */
	function filter_the_author( $display_name ) {
		if( $post = $this->get_contributor_post() ) {
			if( ! empty( $post->post_title )  ) {
				return $post->post_title;
			}
		}

		return $display_name;
	}

	/**
	 * Obtain author data (name and link) in the form of an array based
	 * on whether or not the given $post is a post sent from the Tidal CMS
	 * or a post created directly on WP.
	 *
	 * @param string $post The post to check against.
	 *
	 * @return array
	 */
	function filter_get_author_data( $post ) {

		$data = array();

		if (apply_filters('is_tidal_post', false)) {
			$data['author_name'] = apply_filters('the_author', false);
			$data['author_link'] = apply_filters('author_link', false);
		} else {
			$author = get_userdata($post->post_author);
			$data['author_name'] = $author->display_name;
			$data['author_link'] = '/author/' . $author->user_nicename;
		}

		return $data;
	}

	/**
	 * Use contributor featured image as contributor avatar.
	 *
	 * @param string            $avatar      <img> tag for the user's avatar
	 * @param int|string|object $id_or_email A user ID,  email address, or comment object
	 * @param int               $size        Size of the avatar image
	 * @param string            $default     URL to a default image to use if no avatar is available
	 * @param string            $alt         Alternative text to use in image tag. Defaults to blank
	 *
	 * @return string <img> tag for the user's avatar
	 */
	function filter_get_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		if( $post = $this->get_contributor_post() ) {
			if( ! empty( $post->ID )  ) {
				if( $thumbnail = get_the_post_thumbnail( $post->ID, array( $size, $size ) ) ) {
					return $thumbnail;
				}
			}
		}

		return $avatar;
	}

	/**
	 * Replace author urls with contributor links.
	 *
	 * @param string $link            The URL to the author's page.
	 * @param int    $author_id       The author's id.
	 * @param string $author_nicename The author's nice name.
	 *
	 * @return string
	 */
	function filter_author_link( $link, $author_id = null, $author_nicename = null) {
		/*
		 * Keeping this as a backup in case we forget how to make good associations.
		 * if( $post = $this->get_contributor_post() ) {
			if( ! empty( $post->post_name)  ) {
				return '/contributor/' . $post->post_name;
			}
		}*/
		if( $post = get_post() ) {
			if( $terms = wp_get_object_terms( $post->ID, $this::taxonomy ) ) {
				$term_link = get_term_link( $terms[0], $this::taxonomy );
				if( ! is_wp_error( $term_link ) ) {
					return $term_link;
				}
			}
		}

		return $link;
	}

	/**
	 * Add tidal-post class to the Tidal posts.
	 *
	 * @param array        $classes Array of classes.
	 * @param string|array $class   One or more classes to add to the class list.
	 * @param int          $post_id The post id.
	 *
	 * @return array Array of classes.
	 */
	function filter_post_class( $classes, $class, $post_id ) {
		if( get_post_meta( $post_id, $this::tidal_id_meta, true ) ) {
			$classes[] = 'tidal-post';
		}

		return $classes;
	}

	/**
	 * Check whether a post is a Tidal post.
	 *
	 * @param bool $is_tidal Default value.
	 * @param int  $post_id  Optional post id.
	 *
	 * @return bool
	 */
	function filter_is_tidal_post( $is_tidal = false, $post_id = 0 ) {
		if( ! $post_id && $post = get_post() ) {
			$post_id = $post->ID;
		}

		if( get_post_meta( $post_id, $this::tidal_id_meta, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieve an array of all Tidal contributor post objects.
	 *
	 * @param array $contributors Default array.
	 *
	 * @return array Array of post objects.
	 */
	function filter_tidal_contributors( $defaults = array() ) {
		$contributors = array();
		if( $terms = get_terms( $this::taxonomy ) ) {
			foreach( $terms as $term ) {
				$posts = get_posts( array(
					'name'        => $term->slug,
					'post_type'   => $this::post_type,
					'numberposts' => 1
				));

				if( ! empty( $posts[0] ) ) {
					$contributors[] = $posts[0];
				}
			}
		}

		return $contributors;
	}

	/**
	 * Get associated contributor post.
	 *
	 * @param int $post_id
	 *
	 * @return WP_Post
	 */
	function get_contributor_post( $post_id = 0 ) {
		if( ! $post_id && $post = get_post() ) {
			$post_id = $post->ID;
		}

		if( $post_id ) {
			if( $meta_value = get_post_meta( $post_id, $this::tidal_contributor_meta, true ) ) {
				$posts = get_posts( array(
					'name'        => $meta_value,
					'post_type'   => $this::post_type,
					'numberposts' => 1
				));

				if( ! empty( $posts[0] ) ) {
					return $posts[0];
				}
			}
		}

		return array();
	}

	/**
	 * Use author archive template or custom template for contributor archive page.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string $template The path of the template to include.
	 */
	function filter_template_include( $template ) {
		if( is_tax( $this::taxonomy ) ) {
			if( $tidal_template = locate_template( array( 'author-tidal.php' ) ) ) {
				return $tidal_template;
			}
			else if( $author_template = locate_template( array( 'author.php' ) ) ) {
				return $author_template;
			}
		}

		return $template;
	}

	/**
	 * Add endpoint to XMLRPC methods to retrieve post id by Tidal id.
	 *
	 * @param array $methods XMLRPC methods
	 *
	 * @return array
	 */
	function filter_xmlrpc_methods( $methods ) {
		$methods['wp.newPost'] = array( $this, 'tidal_newPost' );
		$methods['tidal.getPostID'] = array( $this, 'tidal_getPostID' );

		return $methods;
	}

	/**
	 * Override core new post method. Adds a check for duplicate Tidal
	 * username when creating a Tidal-contributed post then calls the
	 * Wordpress new post method.
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $content_struct
	 *      $content_struct can contain:
	 *      - post_type (default: 'post')
	 *      - post_status (default: 'draft')
	 *      - post_title
	 *      - post_author
	 *      - post_excerpt
	 *      - post_content
	 *      - post_date_gmt | post_date
	 *      - post_format
	 *      - post_password
	 *      - comment_status - can be 'open' | 'closed'
	 *      - ping_status - can be 'open' | 'closed'
	 *      - sticky
	 *      - post_thumbnail - ID of a media item to use as the post thumbnail/featured image
	 *      - custom_fields - array, with each element containing 'key' and 'value'
	 *      - terms - array, with taxonomy names as keys and arrays of term IDs as values
	 *      - terms_names - array, with taxonomy names as keys and arrays of term names as values
	 *      - enclosure
	 *      - any other fields supported by wp_insert_post()
	 * @return string post_id
	 */
	function tidal_newPost( $args ) {
		global $wp_xmlrpc_server;

		if( ! empty( $args[3] ) ) {
			$content_struct = $args[3];

			if( ! empty( $content_struct['post_type'] ) && $this::post_type == $content_struct['post_type'] ) {
				if( ! empty( $content_struct['custom_fields'] ) && is_array( $content_struct['custom_fields'] ) ) {
					foreach( $content_struct['custom_fields'] as $custom_field ) {
						if( ! empty( $custom_field['key'] ) && $this::username_meta == $custom_field['key'] && ! empty( $custom_field['value'] ) ) {
							$query = new WP_Query( array(
								'post_type'  => $this::post_type,
								'meta_query' => array( array(
									'key'   => $this::username_meta,
									'value' => $custom_field['value'],
								)),
							));

							// If username already exist, return an IXR error message
							if( ! empty( $query->posts ) ) {
								return new IXR_Error( 2001, __( 'Username already exists.' ) );
							}
						}
					}
				}
			}
			else {
				if( ! empty( $content_struct['custom_fields'] ) && is_array( $content_struct['custom_fields'] ) ) {
					foreach( $content_struct['custom_fields'] as $custom_field ) {
						if( ! empty( $custom_field['key'] ) && $this::tidal_id_meta == $custom_field['key'] && ! empty( $custom_field['value'] ) ) {
							$query = new WP_Query( array(
								'post_type'  => 'post',
								'meta_query' => array( array(
									'key'   => $this::tidal_id_meta,
									'value' => $custom_field['value'],
								)),
							));

							// If Tidal ID already exists, return an IXR error message
							if( ! empty( $query->posts ) ) {
								return new IXR_Error( 2002, __( 'Tidal ID already exists.' ) );
							}
						}
					}
				}
			}
		}

		return $wp_xmlrpc_server->wp_newPost( $args );
	}

	/**
	 * Retrieve a Tidal-contributed post or Tidal Contributor post by Tidal ID
	 * or a Tidal Contributor by slug
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $tidal_id
	 *
	 *  @return int Wordpress post ID.
	 */
	function tidal_getPostID( $args ) {
		global $wp_xmlrpc_server;

		if (  count( $args ) < 4 )
			return $wp_xmlrpc_server->error = new IXR_Error( 400, __( 'Insufficient arguments passed to this XML-RPC method.' ) );

		$wp_xmlrpc_server->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$tidal_id           = (string) $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		$post_id = 0;

		// Check if ID corresponds to a Tidal-contributed post
		$query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'meta_query'     => array( array(
				'key'   => $this::tidal_id_meta,
				'value' => $tidal_id,
			)),
		) );

		if( ! empty( $query->posts ) ) {
			$post_id = $query->posts[0]->ID;
		} else {
			// Check if ID corresponds to a Tidal contributor
			$query = new WP_Query( array(
				'post_type' => $this::post_type,
				'meta_query'     => array( array(
					'key'   => $this::username_meta,
					'value' => $tidal_id,
				)),
			) );

			if( ! empty( $query->posts ) ) {
				$post_id = $query->posts[0]->ID;
			} else {
				// Check if ID is a slug of a Tidal contributor
				$query = new WP_Query( array(
					'post_type' => $this::post_type,
					'name'      => $tidal_id,
				) );

				if( ! empty( $query->posts ) ) {
					$post_id = $query->posts[0]->ID;
				}
			}
		}
		if ( empty( $post_id ) )
			return new IXR_Error( 404, __( 'Invalid post Tidal ID.' ) );

		if ( ! current_user_can( 'edit_post', $post_id ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit this post.' ) );

		return $post_id;
	}

}
Tidal::instance();
