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

	public function extract_store_identity( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );

		$identity = array(
			'store_url'   => $url,
			'merchant_id' => 0,
			'store_slug'  => '',
		);

		if ( preg_match( '#/magaza/([^/?]+)-m-(\d+)#i', $url, $matches ) ) {
			$identity['store_slug']  = sanitize_title( $matches[1] );
			$identity['merchant_id'] = absint( $matches[2] );
			return $identity;
		}

		if ( preg_match( '#m-(\d+)#i', $url, $matches ) ) {
			$identity['merchant_id'] = absint( $matches[1] );
		}

		return $identity;
	}

	public function fetch_page( $url ) {
		$requests = array(
			array(
				'label' => 'browser_like_request_1',
				'args'  => array(
					'timeout'     => 35,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'headers'     => array(
						'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
						'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
						'Accept-Language'           => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
						'Cache-Control'             => 'no-cache',
						'Pragma'                    => 'no-cache',
						'Upgrade-Insecure-Requests' => '1',
						'Sec-Fetch-Dest'            => 'document',
						'Sec-Fetch-Mode'            => 'navigate',
						'Sec-Fetch-Site'            => 'none',
						'Sec-Fetch-User'            => '?1',
						'sec-ch-ua'                 => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
						'sec-ch-ua-mobile'          => '?0',
						'sec-ch-ua-platform'        => '"Windows"',
						'Referer'                   => 'https://www.trendyol.com/',
					),
				),
			),
			array(
				'label' => 'browser_like_request_2',
				'args'  => array(
					'timeout'     => 35,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'headers'     => array(
						'User-Agent'                => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
						'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'Accept-Language'           => 'tr-TR,tr;q=0.9,en;q=0.8',
						'Cache-Control'             => 'max-age=0',
						'Upgrade-Insecure-Requests' => '1',
						'Referer'                   => 'https://www.google.com/',
					),
				),
			),
		);

		foreach ( $requests as $request ) {
			$response = wp_remote_get( $url, $request['args'] );

			if ( is_wp_error( $response ) ) {
				$this->fetch_debug[ $request['label'] ] = array(
					'ok'      => false,
					'message' => $response->get_error_message(),
					'source'  => 'wp_remote_get',
				);
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = wp_remote_retrieve_body( $response );

			$this->fetch_debug[ $request['label'] ] = array(
				'ok'          => ( 200 === $status && ! empty( $body ) ),
				'status_code' => $status,
				'body_length' => strlen( (string) $body ),
				'source'      => 'wp_remote_get',
			);

			if ( 200 === $status && ! empty( $body ) && strlen( $body ) > 1000 ) {
				return $body;
			}
		}

		$last_debug = end( $this->fetch_debug );
		$last_code  = isset( $last_debug['status_code'] ) ? (int) $last_debug['status_code'] : 0;

		if ( 403 === $last_code ) {
			return new WP_Error(
				'store_fetch_forbidden',
				__( 'Mağaza sayfası alınamadı. HTTP: 403 (Trendyol mağaza sayfası bot korumasına takıldı.)', 'trendyol-woocommerce-importer' )
			);
		}

		if ( $last_code > 0 ) {
			return new WP_Error(
				'store_fetch_failed',
				sprintf( __( 'Mağaza sayfası alınamadı. HTTP: %d', 'trendyol-woocommerce-importer' ), $last_code )
			);
		}

		return new WP_Error(
			'store_fetch_failed_unknown',
			__( 'Mağaza sayfası alınamadı. İstek başarısız oldu.', 'trendyol-woocommerce-importer' )
		);
	}

	public function extract_product_urls( $html ) {
		$urls = array();

		if ( preg_match_all( '#href="(/[^"]*-p-\d+[^"]*)"#i', $html, $matches ) ) {
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

		$identity = $this->extract_store_identity( $url );

		$result = array(
			'store_url'     => $url,
			'merchant_id'   => $identity['merchant_id'],
			'store_slug'    => $identity['store_slug'],
			'product_urls'  => array(),
			'product_count' => 0,
			'fetch_debug'   => array(),
			'html_preview'  => '',
			'notes'         => array(),
		);

		if ( empty( $identity['merchant_id'] ) ) {
			$result['notes'][] = __( 'Mağaza linkinden merchant id çözülemedi.', 'trendyol-woocommerce-importer' );
		} else {
			$result['notes'][] = sprintf(
				__( 'Merchant ID tespit edildi: %d', 'trendyol-woocommerce-importer' ),
				$identity['merchant_id']
			);
		}

		if ( ! empty( $identity['store_slug'] ) ) {
			$result['notes'][] = sprintf(
				__( 'Mağaza slug tespit edildi: %s', 'trendyol-woocommerce-importer' ),
				$identity['store_slug']
			);
		}

		$html = $this->fetch_page( $url );

		$result['fetch_debug'] = $this->fetch_debug;

		if ( is_wp_error( $html ) ) {
			$result['notes'][] = $html->get_error_message();
			return $result;
		}

		$product_urls = $this->extract_product_urls( $html );

		if ( count( $product_urls ) > $limit ) {
			$product_urls = array_slice( $product_urls, 0, $limit );
		}

		$result['product_urls']  = $product_urls;
		$result['product_count'] = count( $product_urls );
		$result['html_preview']  = $this->make_html_preview( $html );

		if ( empty( $product_urls ) ) {
			$result['notes'][] = __( 'HTML alındı ama ürün linki bulunamadı.', 'trendyol-woocommerce-importer' );
		} else {
			$result['notes'][] = sprintf(
				__( '%d ürün linki bulundu.', 'trendyol-woocommerce-importer' ),
				count( $product_urls )
			);
		}

		return $result;
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