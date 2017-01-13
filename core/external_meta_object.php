<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class ExternalMetaObject {
	//Values needed to create object
	public $_html;
	public $url;
	public $source_xpath;

	// Object State
	public $fail;
	public $transient_id;

	// Object Properties
	public $json_ld_meta;
	public $schema_meta;
	public $opem_graph_meta;
	public $cider_meta;
	public $custom_meta;

	/**
	 * ExternalMetaObject constructor.
	 * Populates meta objects
	 * @param $url
	 */
	public function __construct( $url ) {
		$this->url = $url;
		$this->create_transient_id();
		$this->get_json_ld_meta();
		$this->get_open_graph_meta();
		$this->get_twitter_meta();
		$this->get_jstor_meta();
		//$this->get_custom_meta();
	}

	/**
	 * Initiates http api requests if html has not been populated yet.
	 * Also sets up xpath object for parsing
	 */
	public function make_the_api_call() {
		if ( $this->_html != NULL ) {
			return;
		}
		$larb     = 'food';
		$this->fetch_external_resource();
		$this->create_source_xpath();
	}

	/**
	 * Sets up transient id for requesting saved meta values. Unique transient is based on the URL of the http api request.
	 */
	public function create_transient_id() {
		$this->transient_id = md5( $this->url );
	}

	/**
	 * This fires the http api request and uses it to populate the $this->html value if it is html and a successful request.
	 */
	public function fetch_external_resource() {
		$url      = rtrim( $this->url, "/" );
		$response = wp_safe_remote_get( esc_url_raw( $url ) );
		$type     = wp_remote_retrieve_header( $response, 'content-type' );
		if ( strpos( $type, 'html' ) === FALSE ) {
			$this->fail = TRUE;
		}
		if ( is_wp_error( $response ) ) {
			$this->fail = TRUE;
		}
		$html        = wp_remote_retrieve_body( $response );
		$this->_html = $html;
	}

	/**
	 * Populates cider meta with meta values collected.
	 * Currently it is based on a hierarchy of quality, json-LD being the highest and twitter being the lowest.
	 * This will be changed to merge data or account for other sources.
	 * @return null|void
	 */
	public function get_best_meta_data() {
		if ( $this->fail == TRUE ) {
			return;
		}
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
		if ( empty( $cider_meta ['cider_title'] ) ) {
			$this->fail = TRUE;
			return null;
		}
		return $cider_meta;
	}

	/**
	 * Creates the xpath object to be traversed with xpath to find meta data.
	 * Done once to save resources and make things reusable.
	 */
	public function create_source_xpath() {
		$dom = new DOMDocument();
		libxml_use_internal_errors( TRUE );
		@$dom->loadHTML( $this->_html );
		libxml_use_internal_errors( FALSE );
		$xpath              = new DOMXpath( $dom );
		$this->source_xpath = $xpath;
	}

	/**
	 *
	 * Filters out recognized sites.
	 * Not Currently used. Will be used when users add custom mapping for specific sites.
	 * Recognized sites will be handled using the custom meta handler.
	 * @param $url
	 * @param $known_sites
	 *
	 * @return bool
	 */
	public function has_custom_mapping( $url, $known_sites ) {
		$url_scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host       = wp_parse_url( $url, PHP_URL_HOST );
		$domain     = $url_scheme . '//' . $host;
		if ( in_array( $domain, $known_sites ) ) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Sets small transient to ensure the http api request only runs when there isn't one set.
	 * @param $meta_type
	 */
	public function has_been_run( $meta_type ) {
		$run_status = $meta_type . 'run_' . $this->transient_id;
		set_transient( $run_status, TRUE, WEEK_IN_SECONDS );
	}

	/**
	 * Used to set up transient for each meta property
	 * @param $meta_type
	 */
	public function set_up_transient( $meta_type ) {
		$transient_key = $meta_type . $this->transient_id;
		$cider_meta    = get_transient( $transient_key );
	}

	/**
	 * Extracts ld+json data and creates meta property. Stores it in a transient after it has run.
	 * @return bool
	 */
	public function get_json_ld_meta() {
		$meta_type     = 'json_ld_';
		$transient_key = $meta_type . $this->transient_id;
		$cider_meta    = get_transient( $transient_key );
		$run_status    = $meta_type . 'run_' . $this->transient_id;
		$has_it_run    = get_transient( $run_status );
		$larb          = 'food';
		if ( FALSE == $has_it_run && FALSE === $cider_meta ) {
			$this->has_been_run($meta_type);
			$this->make_the_api_call();
			$larb ='food';
			$xpath       = $this->source_xpath;
			$jsonScripts = $xpath->query( '//script[@type="application/ld+json"]' );
			if ( empty( $jsonScripts->length ) ) {
				return FALSE;
			}
			$json                            = trim( $jsonScripts->item( 0 )->nodeValue );
			$data                            = json_decode( $json );
			$cider_meta['cider_title']       = trim( $data->headline );
			$cider_meta['cider_contributor'] = trim( $data->author->name );
			if ( empty( $cider_meta['cider_contributor'] ) ) {
				$cider_meta['cider_contributor'] = trim( $data->creator[0] );
			}
			$cider_meta['cider_publication'] = trim( $data->publisher->name );
			$cider_meta['cider_link']        = esc_url( $this->url );
		}
		$larb               = 'food';
		$this->json_ld_meta = $cider_meta;
		set_transient( $transient_key, $cider_meta, WEEK_IN_SECONDS );
	}
	// TODO: add schema scraper https://blog.scrapinghub.com/2014/06/18/extracting-schema-org-microdata-using-scrapy-selectors-and-xpath/

	// TODO: add custom scraper that takes configurations from admin page

	/**
	 * Extracts open graph meta data and creates meta property. Stores it in a transient after it has run.
	 */
	public function get_open_graph_meta() {
		$meta_type     = 'og_meta_';
		$transient_key = $meta_type . $this->transient_id;
		$cider_meta    = get_transient( $transient_key );
		$run_status    = $meta_type . 'run_' . $this->transient_id;
		$has_it_run    = get_transient( $run_status );
		$larb          = 'food';
		if ( $has_it_run === FALSE && FALSE === $cider_meta ) {
			$this->has_been_run($meta_type);
			$this->make_the_api_call();
			$xpath      = $this->source_xpath;
			$query      = '//*/meta[starts-with(@property, \'og:\')]';
			$metas      = $xpath->query( $query );
			$cider_meta = array();
			foreach ( $metas as $meta ) {
				$property                = $meta->getAttribute( 'property' );
				$content                 = $meta->getAttribute( 'content' );
				$cider_meta[ $property ] = $content;
			}
			$cider_meta                      = array_filter( $cider_meta, function ( $og_meta ) {
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
			$cider_meta['cider_title']       = $cider_meta['og:title'];
			$cider_meta['cider_publication'] = $cider_meta['og:site_name'];
			$cider_meta['cider_link']        = $cider_meta['og:url'];
			unset( $cider_meta['og:title'] );
			unset( $cider_meta['og:site_name'] );
			unset( $cider_meta['og:url'] );
		}
		$this->opem_graph_meta = $cider_meta;
		set_transient( $transient_key, $cider_meta, WEEK_IN_SECONDS );
	}
	/**
	 * Extracts twitter meta data and creates meta property. Stores it in a transient after it has run.
	 */
	public function get_twitter_meta() {
		$meta_type     = 'twitter_meta_';
		$transient_key = $meta_type . $this->transient_id;
		$cider_meta    = get_transient( $transient_key );
		$run_status    = $meta_type . 'run_' . $this->transient_id;
		$has_it_run    = get_transient( $run_status );
		$larb          = 'food';
		if ( $has_it_run === FALSE && FALSE === $cider_meta ) {
			$this->has_been_run($meta_type);
			$this->make_the_api_call();
			$xpath      = $this->source_xpath;
			$query      = '//*/meta[starts-with(@property, \'twitter:\')]';
			$metas      = $xpath->query( $query );
			$cider_meta = array();

			foreach ( $metas as $meta ) {
				$property                = $meta->getAttribute( 'property' );
				$content                 = $meta->getAttribute( 'content' );
				$cider_meta[ $property ] = $content;
			}
			$cider_meta = array_filter( $cider_meta, function ( $twitter_meta ) {
				$allowed = [ 'twitter:site', 'twitter:title' ];

				return in_array( $twitter_meta, $allowed );
			},
				ARRAY_FILTER_USE_KEY
			);

			$cider_meta['cider_title']       = $cider_meta['twitter:title'];
			$cider_meta['cider_publication'] = $cider_meta['twitter:site'];
			$cider_meta['cider_link']        = esc_url( $this->url );;
			unset( $cider_meta['twitter:title'] );
			unset( $cider_meta['twitter:site'] );
		}

		$this->twitter_meta = $cider_meta;
		set_transient( $transient_key, $cider_meta, WEEK_IN_SECONDS );
	}

	/**
	 * Extracts jstor meta data and creates meta property. Stores it in a transient after it has run.
	 */
	public function get_jstor_meta() {
		if ( !strpos( $this->url, 'jstor.org/stable' ) !== FALSE ) {
			return;
		}
		$meta_type     = 'jstor_meta_';
		$transient_key = $meta_type . $this->transient_id;
		$cider_meta    = get_transient( $transient_key );
		$run_status    = $meta_type . 'run_' . $this->transient_id;
		$has_it_run    = get_transient( $run_status );
		$larb          = 'food';
		if ( $has_it_run === FALSE || FALSE === $cider_meta ) {
			$this->has_been_run($meta_type);
			$this->make_the_api_call();
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
			$cider_meta['cider_title']       = trim( $html->find( $locators[0], 0 )->plaintext );
			$cider_meta['cider_contributor'] = trim( $html->find( $locators[1], 0 )->plaintext );
			$cider_meta['cider_publication'] = trim( $html->find( $locators[2], 0 )->plaintext );
			$cider_meta['cider_source']      = trim( $html->find( $locators[3], 0 )->plaintext );
			$cider_meta['cider_publisher']   = trim( $html->find( $locators[4], 0 )->plaintext );
			$cider_meta['cider_link']        = esc_url( $this->url );
		}
		$this->custom_meta = $cider_meta;
		set_transient( $transient_key, $cider_meta, WEEK_IN_SECONDS );
	}
//	public function get_custom_meta( $url, $locators ) {
//		if ( ! strpos( $this->url, '$locators[0]' ) !== FALSE ) {
//			return;
//		}
//		$html                            = $this->_html;
//		$html                            = str_get_html( $html );
//		$cider_meta['cider_title']       = trim( $html->find( $locators[1], 0 )->plaintext );
//		$cider_meta['cider_contributor'] = trim( $html->find( $locators[2], 0 )->plaintext );
//		$cider_meta['cider_publication'] = trim( $html->find( $locators[3], 0 )->plaintext );
//		$cider_meta['cider_source']      = trim( $html->find( $locators[4], 0 )->plaintext );
//		$cider_meta['cider_publisher']   = trim( $html->find( $locators[5], 0 )->plaintext );
//		$cider_meta['cider_link']        = esc_url( $this->url );
//		$this->custom_meta               = $cider_meta;
//	}
}