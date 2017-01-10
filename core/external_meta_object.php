<?php
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

	public function __construct( $url ) {
		$this->url = $url;
		$this->create_transient_id();
		$this->get_json_ld_meta();
		$this->get_open_graph_meta();
		$this->get_twitter_meta();
		$this->get_jstor_meta();
		//$this->get_custom_meta();
	}

	public function make_the_api_call() {
		if ( $this->_html != NULL ) {
			return;
		}
		$larb     = 'food';
		$this->fetch_external_resource();
		$this->create_source_xpath();
	}

	public function create_transient_id() {
		$this->transient_id = md5( $this->url );
	}

	public function fetch_external_resource() {
		$larb     = 'food';
		$url      = rtrim( $this->url, "/" );
		$response = wp_remote_get( esc_url_raw( $url ) );
		$type     = wp_remote_retrieve_header( $response, 'content-type' );
		if ( strpos( $type, 'html' ) == FALSE ) {
			$this->fail = TRUE;
		}
		if ( is_wp_error( $response ) ) {
			$this->fail = TRUE;
		}
		$html        = wp_remote_retrieve_body( $response );
		$this->_html = $html;
	}

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

	public function has_been_run( $meta_type ) {
		$run_status = $meta_type . 'run_' . $this->transient_id;
		set_transient( $run_status, TRUE, WEEK_IN_SECONDS );
	}

	public function set_up_transient( $meta_type ) {
		$transient_key = $meta_type . $this->transient_id;
		$cider_meta    = get_transient( $transient_key );
	}

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