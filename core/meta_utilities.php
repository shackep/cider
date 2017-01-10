<?php
class MetaUtilities {
public $cider_meta;

public function __construct() {
$this->cider_meta = [ ];
}

public function get_mapped_sites() {
$options  = get_option( cider_options );
$settings = $options['cider_admin_repeat_group'];
$websites = array_column( $settings, 'website' );

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

return $selectors;
}

public function check_content_for_external_urls( $post_id ) {
$post     = get_post( $post_id );
$site_url = site_url();
$content  = $post->post_content;
if($content === ''){
return null;
}
$dom      = new DOMDocument();
@$dom->loadHTML( $content );
$xpath = new DOMXPath( $dom );
$hrefs = $xpath->evaluate( "/html/body//a" );
for ( $i = 0; $i < $hrefs->length; $i ++ ) {
$href   = $hrefs->item( $i );
$urls[] = $href->getAttribute( 'href' );
}
if(is_array( $urls )){
$urls = array_filter( $urls, function ( $url ) use ( $site_url ) {
if ( stripos( $url, $site_url ) === FALSE ) {
return TRUE; // true to keep url.
} else {
return FALSE; // false to filter it out.
}
} );
}

return $urls;
}

public function set_cider_meta_value( $value ) {
$this->cider_meta = array_merge( $this->cider_meta, $value );
}

public function get_cider_meta_value() {
return $this->cider_meta;
}
}