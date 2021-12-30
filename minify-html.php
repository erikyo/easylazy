<?php

add_action( 'get_header', 'easylazy_html_compression_start' );
function easylazy_html_compression_start() {
	ob_start( 'easylazy_html_compression_finish' );
}

function easylazy_html_compression_finish( $html ) {
	return new easylazy_compress_HTML( $html );
}


class easylazy_compress_HTML {

	// Variables
	protected $html;

	public function __construct( $html ) {
		if ( ! empty( $html ) ) {
			$this->parseHTML( $html );
		}
	}

	public function parseHTML( $html ) {
		$new_html = $this->minifyHTML( $html );

		if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
			header( "Content-Encoding: gzip" );
			$new_html = gzencode($new_html);
		}

		$this->html .= $new_html . "\n" . $this->bottomComment( $html, $new_html );
	}

	protected function minifyHTML( $html ) {

		$search = array(
			'/\/\*([\s\S]*?)\*\/|\s+\/\/.*|<!--.*-->/', // Remove JS, CSS and HTML comments
			'/\t/', // remove tabs
			'/(?!<script.*?>([\s\S]*?)<\/script>)^(\r|\n)/', // remove newline
			'/>\s+</',    // remove empty space between tags
			'/ {2,}/',    // shorten multiple whitespace sequences
		);

		$replace = array(
			'',
			'',
			'',
			'><',
			' ',
		);

		return preg_replace($search, $replace, $html);
	}

	protected function bottomComment( $raw, $compressed ) {
		$raw        = strlen( $raw );
		$compressed = strlen( $compressed );

		$savings = round( ( $raw - $compressed ) / $raw * 100, 2 );

		return '<!-- EasyLazy - Compressed HTML ' . $this->toKB($compressed) . 'KB - Original ' . $this->toKB($raw) . 'KB (saved ' . $savings . '%)-->';
	}

	protected function toKB( $bytes ) {
		return number_format($bytes / 1024, 2);
	}

	public function __toString() {
		return $this->html;
	}
}


