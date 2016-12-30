<?php

/**
 * CMB2 Theme Options
 * @version 0.1.0
 */
class Myprefix_Admin {
	/**
	 * Option key, and option page slug
	 * @var string
	 */
	private $key = 'cider_options';
	/**
	 * Options page metabox id
	 * @var string
	 */
	private $metabox_id = 'cider_option_metabox';
	/**
	 * Options Page title
	 * @var string
	 */
	protected $title = '';
	/**
	 * Options Page hook
	 * @var string
	 */
	protected $options_page = '';
	/**
	 * Holds an instance of the object
	 *
	 * @var Myprefix_Admin
	 **/
	private static $instance = NULL;

	/**
	 * Constructor
	 * @since 0.1.0
	 */
	private function __construct() {
		// Set our title
		$this->title = __( 'Cider Plugin Options', 'cider' );
	}

	/**
	 * Returns the running object
	 *
	 * @return Myprefix_Admin
	 **/
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Initiate our hooks
	 * @since 0.1.0
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'cmb2_admin_init', array(
			$this,
			'add_options_page_metabox'
		) );
	}

	/**
	 * Register our setting to WP
	 * @since  0.1.0
	 */
	public function init() {
		register_setting( $this->key, $this->key );
	}

	/**
	 * Add menu options page
	 * @since 0.1.0
	 */
	public function add_options_page() {
		$this->options_page = add_menu_page( $this->title, $this->title, 'manage_options', $this->key, array(
			$this,
			'admin_page_display'
		) );
		// Include CMB CSS in the head to avoid FOUC
		add_action( "admin_print_styles-{$this->options_page}", array(
			'CMB2_hookup',
			'enqueue_cmb_css'
		) );
	}

	/**
	 * Admin page markup. Mostly handled by CMB2
	 * @since  0.1.0
	 */
	public function admin_page_display() {
		?>
		<div class="wrap cmb2-options-page <?php echo $this->key; ?>">
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<?php cmb2_metabox_form( $this->metabox_id, $this->key ); ?>
		</div>
		<?php
	}

	/**
	 * Add the options metabox to the array of metaboxes
	 * @since  0.1.0
	 */
	function add_options_page_metabox() {
		// hook in our save notices
		add_action( "cmb2_save_options-page_fields_{$this->metabox_id}", array(
			$this,
			'settings_notices'
		), 10, 2 );
		$cmb = new_cmb2_box( array(
			'id'         => $this->metabox_id,
			'hookup'     => FALSE,
			'cmb_styles' => FALSE,
			'show_on'    => array(
				// These are important, don't remove
				'key'   => 'options-page',
				'value' => array( $this->key, )
			),
		) );
		// Set our CMB2 fields
		$group_field_id = $cmb->add_field( array(
			'id'          => 'cider_admin_repeat_group',
			'type'        => 'group',
			'description' => __( 'Define selectors for specific websites', 'cmb2' ),
			// 'repeatable'  => false, // use false if you want non-repeatable group
			'options'     => array(
				'group_title'   => __( 'Entry {#}', 'cmb2' ),
				// since version 1.1.4, {#} gets replaced by row number
				'add_button'    => __( 'Add Another Entry', 'cmb2' ),
				'remove_button' => __( 'Remove Entry', 'cmb2' ),
				'sortable'      => TRUE,
				// beta
				'closed'        => TRUE,
				// true to have the groups closed by default
				'key'           => 'options-page',
				'value'         => array( $this->key, )
			),
			'after_group' => 'cider_add_js_for_repeatable_titles',
		) );

// Id's for group's fields only need to be unique for the group. Prefix is not needed.
		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Website Domain',
			'description' => 'URLs with this domain will use the following selectors to identify meta',
			'id'          => 'website',
			'type'        => 'text',
			// 'repeatable' => true, // Repeatable fields are supported w/in repeatable groups (for most types)
		) );

		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Title Selector',
			'description' => 'Write a short description for this entry',
			'id'          => 'title',
			'type'        => 'text',
		) );
		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Author Selector',
			'description' => 'Write a short description for this entry',
			'id'          => 'author',
			'type'        => 'text',
		) );
		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Publication Title Selector',
			'description' => 'Write a short description for this entry',
			'id'          => 'publication',
			'type'        => 'text',
		) );
		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Source Selector',
			'description' => 'Write a short description for this entry',
			'id'          => 'source',
			'type'        => 'text',
		) );
		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Publisher Selector',
			'description' => 'Write a short description for this entry',
			'id'          => 'publisher',
			'type'        => 'text',
		) );
		$cmb->add_group_field( $group_field_id, array(
			'name'        => 'Link Selector',
			'description' => 'Write a short description for this entry',
			'id'          => 'link',
			'type'        => 'text',
		) );
	}

	function cider_add_js_for_repeatable_titles() {
		add_action( 'admin_footer', 'cider_add_admin_js_for_repeatable_titles_to_footer' );
	}

// Populate field group label with contents of first field -> https://github.com/WebDevStudios/CMB2-Snippet-Library/blob/master/javascript/dynamically-change-group-field-title-from-subfield.php
	function cider_add_admin_js_for_repeatable_titles_to_footer() {
		?>
		<script type="text/javascript">
			jQuery(function ($) {
				var $box = $(document.getElementById('cider_admin_repeat_group_repeat'));
				var replaceTitles = function () {
					$box.find('.cmb-group-title').each(function () {
						var $this = $(this);
						var txt = $this.next().find('[id$="title"]').val();
						var rowindex;
						if (!txt) {
							txt = $box.find('[data-grouptitle]').data('grouptitle');
							if (txt) {
								rowindex = $this.parents('[data-iterator]').data('iterator');
								txt = txt.replace('{#}', ( rowindex + 1 ));
							}
						}
						if (txt) {
							$this.text(txt);
						}
					});
				};
				var replaceOnKeyUp = function (evt) {
					var $this = $(evt.target);
					var id = 'title';
					if (evt.target.id.indexOf(id, evt.target.id.length - id.length) !== -1) {
						console.log('val', $this.val());
						$this.parents('.cmb-row.cmb-repeatable-grouping').find('.cmb-group-title').text($this.val());
					}
				};
				$box
					.on('cmb2_add_row cmb2_shift_rows_complete', function (evt) {
						replaceTitles();
					})
					.on('keyup', replaceOnKeyUp);
				replaceTitles();
				// Hide the first generated empty item, add row and remove row
				$('#cider_repeat_group_repeat').find('.cmb-add-row,button.cmb-remove-group-row').hide();
			});
		</script>
		<?php
	}

	/**
	 * Register settings notices for display
	 *
	 * @since  0.1.0
	 *
	 * @param  int $object_id Option key
	 * @param  array $updated Array of updated fields
	 *
	 * @return void
	 */
	public function settings_notices( $object_id, $updated ) {
		if ( $object_id !== $this->key || empty( $updated ) ) {
			return;
		}
		add_settings_error( $this->key . '-notices', '', __( 'Settings updated.', 'cider' ), 'updated' );
		settings_errors( $this->key . '-notices' );
	}

	/**
	 * Public getter method for retrieving protected/private variables
	 * @since  0.1.0
	 *
	 * @param  string $field Field to retrieve
	 *
	 * @return mixed          Field value or exception is thrown
	 */
	public function __get( $field ) {
		// Allowed fields to retrieve
		if ( in_array( $field, array(
			'key',
			'metabox_id',
			'title',
			'options_page'
		), TRUE ) ) {
			return $this->{$field};
		}
		throw new Exception( 'Invalid property: ' . $field );
	}
}

/**
 * Helper function to get/return the Myprefix_Admin object
 * @since  0.1.0
 * @return Myprefix_Admin object
 */
function cider_admin() {
	return Myprefix_Admin::get_instance();
}

/**
 * Wrapper function around cmb2_get_option
 * @since  0.1.0
 *
 * @param  string $key Options array key
 *
 * @return mixed        Option value
 */
function cider_get_option( $key = '' ) {
	return cmb2_get_option( cider_admin()->key, $key );
}

// Get it started
cider_admin();
