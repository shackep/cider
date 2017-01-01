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

if ( file_exists( plugin_dir_path( __FILE__ ) . '/inc/simplehtmldom/simple_html_dom.php' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . '/inc/simplehtmldom/simple_html_dom.php' );
}
if ( file_exists( plugin_dir_path( __FILE__ ) . '/meta-boxes/cider-meta.php' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . '/meta-boxes/cider-meta.php' );
}

require_once( plugin_dir_path( __FILE__ ) . '/admin/cider_settings.php' );

// TODO: Look into meta parcer library https://github.com/jkphl/micrometa


class ExternalMetaObject {
	//Values needed to create object
	public $_html;
	public $url;
	public $source_xpath;

	// Object Properties
	public $fail;
	public $json_ld_meta;
	public $schema_meta;
	public $opem_graph_meta;
	public $twitter_meta;
	public $custom_meta;

	public function __construct( $url ) {
		$this->url = $url;
		$this->set_default_values();
		if ( $this->fail == TRUE ) {
			return;
		}
		$this->create_source_xpath();
		$this->get_json_ld_meta();
		$this->get_open_graph_meta();
		$this->get_twitter_meta();
		$this->get_jstor_meta();
		//$this->get_custom_meta();
	}

	public function set_default_values() {
		$url      = rtrim( $this->url, "/" );
		$response = wp_remote_get( esc_url_raw( $url ) );
		$type     = wp_remote_retrieve_header( $response, 'content-type' );
		$larb     = 'food';
		if ( strpos( $type, 'image' ) !== FALSE ) {
			$this->fail = TRUE;
		}
		if ( is_wp_error( $response ) ) {
			$this->fail = TRUE;
		}
		$body        = wp_remote_retrieve_body( $response );
		$html        = $body;
		$this->_html = $html;
	}

	public function get_best_meta_data() {
		if ( ! empty( $this->json_ld_meta ['cider_title'] ) ) {
			$cider_meta = $this->json_ld_meta;
		} elseif ( ! empty( $this->opem_graph_meta ['cider_title'] ) ) {
			$cider_meta = $this->opem_graph_meta;
		} elseif ( ! empty( $this->twitter_meta ['cider_title'] ) ) {
			$cider_meta = $this->twitter_meta;
		}
		if ( ! empty ( $this->custom_meta ['cider_title'] ) ) {
			$cider_meta = $this->custom_meta;
		}

		return $cider_meta;
	}

	public function create_source_xpath() {
		$dom = new DOMDocument();
		libxml_use_internal_errors( TRUE );
		@$dom->loadHTML( $this->_html );
		libxml_use_internal_errors( FALSE );
		$xpath              = new DOMXpath( $dom );
		$this->source_xpath = $xpath;
	}

	public function has_custom_mapping( $url, $known_sites ) {
		$url_scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host       = wp_parse_url( $url, PHP_URL_HOST );
		$domain     = $url_scheme . '//' . $host;
		if ( in_array( $domain, $known_sites ) ) {
			return TRUE;
		}

		return FALSE;
	}

	public function get_json_ld_meta() {
		$xpath       = $this->source_xpath;
		$jsonScripts = $xpath->query( '//script[@type="application/ld+json"]' );
		if ( empty( $jsonScripts->length ) ) {
			return FALSE;
		}
		$json                            = trim( $jsonScripts->item( 0 )->nodeValue );
		$data                            = json_decode( $json );
		$larb                            = 'food';
		$cider_meta['cider_title']       = trim( $data->headline );
		$cider_meta['cider_contributor'] = trim( $data->author->name );
		if ( empty( $cider_meta['cider_contributor'] ) ) {
			$cider_meta['cider_contributor'] = trim( $data->creator[0] );
		}
		$cider_meta['cider_publication'] = trim( $data->publisher->name );
		$cider_meta['cider_link']        = esc_url( $this->url );
		$this->json_ld_meta              = $cider_meta;
	}
	// TODO: add schema scraper https://blog.scrapinghub.com/2014/06/18/extracting-schema-org-microdata-using-scrapy-selectors-and-xpath/

	// TODO: add custom scraper that takes configurations from admin page

	public function get_open_graph_meta() {
		$html            = $this->_html;
		$xpath           = $this->source_xpath;
		$query           = '//*/meta[starts-with(@property, \'og:\')]';
		$metas           = $xpath->query( $query );
		$open_graph_meta = array();
		foreach ( $metas as $meta ) {
			$property                     = $meta->getAttribute( 'property' );
			$content                      = $meta->getAttribute( 'content' );
			$open_graph_meta[ $property ] = $content;
		}
		$open_graph_meta                      = array_filter( $open_graph_meta, function ( $og_meta ) {
			$allowed = [
				'og:site_name',
				'og:title',
				'og:url',
				'article:author'
			];

			return in_array( $og_meta, $allowed );
		},
			ARRAY_FILTER_USE_KEY
		);
		$open_graph_meta['cider_title']       = $open_graph_meta['og:title'];
		$open_graph_meta['cider_publication'] = $open_graph_meta['og:site_name'];
		$open_graph_meta['cider_link']        = $open_graph_meta['og:url'];
		unset( $open_graph_meta['og:title'] );
		unset( $open_graph_meta['og:site_name'] );
		unset( $open_graph_meta['og:url'] );
		$this->opem_graph_meta = $open_graph_meta;
	}

	public function get_twitter_meta() {
		$html         = $this->_html;
		$xpath        = $this->source_xpath;
		$query        = '//*/meta[starts-with(@property, \'twitter:\')]';
		$metas        = $xpath->query( $query );
		$twitter_meta = array();

		foreach ( $metas as $meta ) {
			$property                  = $meta->getAttribute( 'property' );
			$content                   = $meta->getAttribute( 'content' );
			$twitter_meta[ $property ] = $content;
		}
		$twitter_meta = array_filter( $twitter_meta, function ( $og_meta ) {
			$allowed = [ 'twitter:site', 'twitter:title' ];

			return in_array( $og_meta, $allowed );
		},
			ARRAY_FILTER_USE_KEY
		);

		$twitter_meta['cider_title']       = $twitter_meta['twitter:title'];
		$twitter_meta['cider_publication'] = $twitter_meta['twitter:site'];
		$twitter_meta['cider_link']        = esc_url( $this->url );;
		unset( $twitter_meta['twitter:title'] );
		unset( $twitter_meta['twitter:site'] );
		$this->twitter_meta = $twitter_meta;
	}

	public function get_jstor_meta() {
		if ( ! strpos( $this->url, 'jstor.org/stable' ) !== FALSE ) {
			return;
		}
		$html                            = $this->_html;
		$html                            = str_get_html( $html );
		$locators                        = array(
			'h1',
			'.contrib',
			'.journal',
			'.src',
			'.publisher-link',
			'.stable'
		);
		$larb                            = 'food';
		$cider_meta['cider_title']       = trim( $html->find( $locators[0], 0 )->plaintext );
		$cider_meta['cider_contributor'] = trim( $html->find( $locators[1], 0 )->plaintext );
		$cider_meta['cider_publication'] = trim( $html->find( $locators[2], 0 )->plaintext );
		$cider_meta['cider_source']      = trim( $html->find( $locators[3], 0 )->plaintext );
		$cider_meta['cider_publisher']   = trim( $html->find( $locators[4], 0 )->plaintext );
		$cider_meta['cider_link']        = esc_url( $this->url );
		$larb                            = 'food';
		$this->custom_meta               = $cider_meta;
		$larb                            = 'food';
	}

//	public function get_custom_meta( $url, $locators ) {
//		if ( ! strpos( $this->url, '$locators[0]' ) !== FALSE ) {
//			return;
//		}
//		$html                            = $this->_html;
//		$html                            = str_get_html( $html );
//		$larb                            = 'food';
//		$cider_meta['cider_title']       = trim( $html->find( $locators[1], 0 )->plaintext );
//		$cider_meta['cider_contributor'] = trim( $html->find( $locators[2], 0 )->plaintext );
//		$cider_meta['cider_publication'] = trim( $html->find( $locators[3], 0 )->plaintext );
//		$cider_meta['cider_source']      = trim( $html->find( $locators[4], 0 )->plaintext );
//		$cider_meta['cider_publisher']   = trim( $html->find( $locators[5], 0 )->plaintext );
//		$cider_meta['cider_link']        = esc_url( $this->url );
//		$larb                            = 'food';
//		$this->custom_meta               = $cider_meta;
//		$larb                            = 'food';
//	}
}

class MetaUtilities {
	public $cider_meta;

	public function __construct() {
		$this->cider_meta = [ ];
	}

	public function get_mapped_sites() {
		$options  = get_option( cider_options );
		$settings = $options['cider_admin_repeat_group'];
		$websites = array_column( $settings, 'website' );
		$larb     = 'food';

		return $websites;
	}

	public function array_value_recursive( $key, array $arr ) {
		$val = array();
		array_walk_recursive( $arr, function ( $v, $k ) use ( $key, &$val ) {
			if ( $k == $key ) {
				array_push( $val, $v );
			}
		} );

		return count( $val ) > 1 ? $val : array_pop( $val );
	}

	public function get_selectors_for_site() {
		$options        = get_option( cider_options );
		$settings       = $options['cider_admin_repeat_group'];
		$identify_array = array_combine( array_column( $settings, 'website' ), $settings );
		$selectors      = $identify_array[ $this->url ];
		$larb           = 'food';

		return $selectors;
	}

	public function check_content_for_external_urls( $post_id ) {
		$post     = get_post( $post_id );
		$site_url = site_url();
		$content  = $post->post_content;
		$dom      = new DOMDocument();
		@$dom->loadHTML( $post->post_content );
		$xpath = new DOMXPath( $dom );
		$hrefs = $xpath->evaluate( "/html/body//a" );
		for ( $i = 0; $i < $hrefs->length; $i ++ ) {
			$href   = $hrefs->item( $i );
			$urls[] = $href->getAttribute( 'href' );
		}
		$urls = array_filter( $urls, function ( $url ) use ( $site_url ) {
			if ( stripos( $url, $site_url ) === FALSE ) {
				return TRUE; // true to keep url.
			} else {
				return FALSE; // false to filter it out.
			}
		} );

		return $urls;
	}

	public function set_cider_meta_value( $value ) {
		$this->cider_meta = array_merge( $this->cider_meta, $value );
	}

	public function get_cider_meta_value() {
		return $this->cider_meta;
	}
}


$obj = new MetaUtilities;
//$websites = $obj->get_mapped_sites();
//$post = get_post( 1 );
//$urls = $obj->check_content_for_external_urls( 1 );
$larb = 'food';
//$external_item = new ExternalMetaObject( 'https://medium.com/@ericclemmons/javascript-fatigue-48d4011b6fc4#.a1yvowlk8' );
//$external_item = new ExternalMetaObject( 'https://aeon.co/ideas/how-refugees-have-the-power-to-change-the-society-they-join' );
//$test = $external_item->get_best_meta_data();
$larb = 'food';
//$external_item->set_default_values('www.jstor.org/stable/20020127');
//$external_item->get_json_ld_meta();
//$test = get_meta_tags('https://aeon.co/ideas/how-refugees-have-the-power-to-change-the-society-they-join');

function populate_cider_meta( $post_id ) {
	$larb       = 'food';
	$obj        = new MetaUtilities;
	$urls       = $obj->check_content_for_external_urls( $post_id );
	$cider_meta = [ ];
	array_filter( $urls, function ( $url ) use ( $post_id, $obj ) {
		$source = new ExternalMetaObject( $url );
		if ( $source->fail == TRUE ) {
			return;
		}
		$cider_meta[] = $source->get_best_meta_data();
		$obj->set_cider_meta_value( $cider_meta );
		$larb = 'food';
	} );
	delete_post_meta( $post_id, 'cider_repeat_group' );
	$final_value = $obj->get_cider_meta_value();
	update_post_meta( $post_id, 'cider_repeat_group', $final_value );
}

add_action( 'save_post', 'populate_cider_meta', 15, 2 );

