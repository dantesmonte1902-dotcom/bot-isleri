<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Store_Scraper_Service {

	private $fetch_debug = array();

	public function get_fetch_debug() {
		return $this->fetch_debug;
	}

	public function validate_store_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );

		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'Mağaza URL boş.', 'trendyol-woocommerce-importer' ) );
		}

		if ( ! preg_match( '#^https?://(www\.)?trendyol\.com/magaza/.+#i', $url ) ) {
			return new WP_Error( 'invalid_store_url', __( 'Geçerli bir Trendyol mağaza linki girin.', 'trendyol-woocommerce-importer' ) );
		}

		return true;
	}

	public function fetch_page( $url ) {
		$args = array(
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
			'timeout'    => 30,
			'redirection'=> 5,
			'headers'    => array(
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
				'Accept-Language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
				'Cache-Control'   => 'no-cache',
				'Pragma'          => 'no-cache',
				'Referer'         => 'https://www.trendyol.com/',
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->fetch_debug['wp_remote_get'] = array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		$this->fetch_debug['wp_remote_get'] = array(
			'ok'          => ( 200 === $status && ! empty( $body ) ),
			'status_code' => $status,
			'body_length' => strlen( (string) $body ),
			'source'      => 'wp_remote_get',
		);

		if ( 200 !== $status ) {
			return new WP_Error(
				'store_fetch_failed',
				sprintf( __( 'Mağaza sayfası alınamadı. HTTP: %d', 'trendyol-woocommerce-importer' ), $status )
			);
		}

		if ( empty( $body ) || strlen( $body ) < 1000 ) {
			return new WP_Error( 'store_empty_body', __( 'Mağaza HTML içeriği boş veya eksik.', 'trendyol-woocommerce-importer' ) );
		}

		return $body;
	}

	public function extract_product_urls( $html ) {
		$urls = array();

		if ( preg_match_all( '#href="(/[^\"]*-p-\d+[^\"]*)"#i', $html, $matches ) ) {
			foreach ( $matches[1] as $relative_url ) {
				$urls[] = $this->normalize_product_url( $relative_url );
			}
		}

		if ( preg_match_all( '#https://www\.trendyol\.com/[^"\']*-p-\d+[^"\']*#i', $html, $matches ) ) {
			foreach ( $matches[0] as $full_url ) {
				$urls[] = $this->normalize_product_url( $full_url );
			}
		}

		$urls = array_filter( $urls );
		$urls = array_values( array_unique( $urls ) );

		return $urls;
	}

	private function normalize_product_url( $url ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );

		if ( empty( $url ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '/' ) ) {
			$url = 'https://www.trendyol.com' . $url;
		}

		$url = preg_replace( '#\?.*$#', '', $url );

		if ( ! preg_match( '#^https?://(www\.)?trendyol\.com/.+-p-\d+$#i', $url ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	public function analyze_store( $url, $limit = 50 ) {
		$validation = $this->validate_store_url( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$limit = absint( $limit );
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$html = $this->fetch_page( $url );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$product_urls = $this->extract_product_urls( $html );

		if ( count( $product_urls ) > $limit ) {
			$product_urls = array_slice( $product_urls, 0, $limit );
		}

		return array(
			'store_url'     => $url,
			'product_urls'  => $product_urls,
			'product_count' => count( $product_urls ),
			'fetch_debug'   => $this->fetch_debug,
			'html_preview'  => $this->make_html_preview( $html ),
		);
	}

	private function make_html_preview( $html ) {
		$html = (string) $html;
		$html = wp_strip_all_tags( $html );
		$html = preg_replace( '/\s+/', ' ', $html );
		$html = trim( $html );

		if ( strlen( $html ) > 2000 ) {
			$html = substr( $html, 0, 2000 ) . '...';
		}

		return $html;
	}
}
