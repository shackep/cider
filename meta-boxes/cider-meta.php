<?php
if ( file_exists( plugin_dir_path( __FILE__ ) . '../inc/cmb2/init.php' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . '../inc/cmb2/init.php' );
}
add_action( 'cmb2_admin_init', 'cmb2_cider_admin_metaboxes' );
/**
 * Define the metabox and field configurations.
 */
function cmb2_cider_admin_metaboxes() {

	// Start with an underscore to hide fields from custom fields list
	$prefix = '_cider_';

	/**
	 * Initiate the metabox
	 */
	$cmb = new_cmb2_box( array(
		'id'           => $prefix . 'source_metabox',
		'title'        => __( 'Sources Found', 'cmb2' ),
		'object_types' => array( 'post', ), // Post type
		'context'      => 'normal',
		'priority'     => 'high',
		'show_names'   => TRUE, // Show field names on the left
		// 'cmb_styles' => false, // false to disable the CMB stylesheet
		// 'closed'     => true, // Keep the metabox closed by default
	) );

	$group_field_id = $cmb->add_field( array(
		'id'          => 'cider_repeat_group',
		'type'        => 'group',
		//'description' => __( 'Citations from content', 'cmb2' ),
		'options'     => array(
			'group_title' => __( 'Citation {#}', 'cmb2' ),
			// since version 1.1.4, {#} gets replaced by row number
			//'add_button'    => __( 'Add Another Entry', 'cmb2' ),
			//'remove_button' => __( 'Remove Entry', 'cmb2' ),
			'sortable'    => FALSE,
			// beta
			'closed'      => TRUE,
			// true to have the groups closed by default
		),
		'after_group' => 'cider_add_js_for_repeatable_titles',
	) );


// Id's for group's fields only need to be unique for the group. Prefix is not needed.
	$cmb->add_group_field( $group_field_id, array(
		'name' => 'Title',
		'id'   => 'cider_title',
		'type' => 'text',
	) );
	$cmb->add_group_field( $group_field_id, array(
		'name' => 'Author',
		'id'   => 'cider_contributor',
		'type' => 'text',
	) );
	$cmb->add_group_field( $group_field_id, array(
		'name' => 'Publication Title',
		'id'   => 'cider_publication',
		'type' => 'text',
	) );
	$cmb->add_group_field( $group_field_id, array(
		'name' => 'Source',
		'id'   => 'cider_source',
		'type' => 'text',
	) );
	$cmb->add_group_field( $group_field_id, array(
		'name' => 'Publisher',
		'id'   => 'cider_publisher',
		'type' => 'text',
	) );
	$cmb->add_group_field( $group_field_id, array(
		'name' => 'Link',
		'id'   => 'cider_link',
		'type' => 'text',
	) );

}

function cmb2_button_after_form() {
	echo '<input name="update-citations" type="submit" class="button button-primary button-large" id="update-citations" value="Regenerate Citations">';
}
add_action( 'cmb2_after_post_form__cider_source_metabox', 'cmb2_button_after_form' );

function cider_add_js_for_repeatable_titles() {
	add_action( 'admin_footer', 'cider_add_js_for_repeatable_titles_to_footer' );
}

// Populate field group label with contents of first field, hide unused elements
function cider_add_js_for_repeatable_titles_to_footer() {
	wp_enqueue_script( 'cider-meta', plugins_url( '../js/cider_meta.js' , __FILE__ ),'jquery'  );
}