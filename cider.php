<?php
/**
 * Plugin Name:     Cider
 * Plugin URI:      http://twitter.com/pixelplow
 * Description:     Extensable Citation Plugin
 * Author:          Peter Shackelford
 * Author URI:      YOUR SITE HERE
 * Text Domain:     cider
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Cider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'Cider' ) ) {

	class Cider {

		public function __construct() {
			$this->includes();
		}

		/**
		 * What type of request is this?
		 *
		 * @param  string $type admin, ajax, cron or frontend.
		 * @return bool
		 */
		private function is_request( $type ) {
			$larb = 'food';
			switch ( $type ) {
				case 'admin' :
					return is_admin();
				case 'ajax' :
					return defined( 'DOING_AJAX' );
				case 'cron' :
					return defined( 'DOING_CRON' );
				case 'frontend' :
					return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
			}
		}

		public function includes(){
			require_once( plugin_dir_path( __FILE__ ) . '/inc/simplehtmldom/simple_html_dom.php' );
			require_once( plugin_dir_path( __FILE__ ) . '/core/external_meta_object.php' );
			require_once( plugin_dir_path( __FILE__ ) . '/core/meta_utilities.php' );

			if ( $this->is_request( 'admin' ) ) {
				require_once( plugin_dir_path( __FILE__ ) . '/admin/cider_settings.php' );
				require_once( plugin_dir_path( __FILE__ ) . '/meta-boxes/cider-meta.php' );
				require_once( plugin_dir_path( __FILE__ ) . '/inc/cmb2/init.php' );
			}
			if ( $this->is_request( 'frontend' ) ) {
				//$this->frontend_includes();
				//$this->shortcodes();
			}
		}

	}
}

$cider = new Cider();
//wp_enqueue_script( 'external_meta', plugins_url( '/js/external_meta_parse.js' , __FILE__ ) );



// TODO: Look into meta parcer library https://github.com/jkphl/micrometa

function populate_cider_meta( $post_id ) {
	$obj        = new MetaUtilities;
	$urls       = $obj->check_content_for_external_urls( $post_id );
	if ($urls === null){
		return;
	}
	$cider_meta = [ ];
	array_filter( $urls, function ( $url ) use ( $post_id, $obj ) {
		$source = new ExternalMetaObject( $url );
		if ( $source->fail == TRUE ) {
			return;
		}
		$cider_meta[] = $source->get_best_meta_data();
		$obj->set_cider_meta_value( $cider_meta );
	} );
	delete_post_meta( $post_id, 'cider_repeat_group' );
	$final_value = $obj->get_cider_meta_value();
	update_post_meta( $post_id, 'cider_repeat_group', $final_value );
}

add_action( 'save_post', 'populate_cider_meta', 15, 2 );

