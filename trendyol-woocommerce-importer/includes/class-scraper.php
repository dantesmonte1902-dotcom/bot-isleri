<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Scraper {

	private $url;
	private $product_data = array();
	private $fetch_debug = array();

	public function __construct( $url ) {
		$this->url = $url;
	}

	public function validate_url() {
		if ( ! preg_match( '#^https?://(www\.)?trendyol\.com/.+#i', $this->url ) ) {
			return new WP_Error( 'invalid_url', __( 'Only Trendyol.com product links are allowed.', 'trendyol-woocommerce-importer' ) );
		}
		return true;
	}

	public function get_fetch_debug() {
		return $this->fetch_debug;
	}

	public function fetch_page() {
		$wp_result = $this->fetch_with_wp_remote();

		if ( ! is_wp_error( $wp_result ) ) {
			return $wp_result;
		}

		$curl_result = $this->fetch_with_curl();

		if ( ! is_wp_error( $curl_result ) ) {
			return $curl_result;
		}

		$wp_message   = $wp_result instanceof WP_Error ? $wp_result->get_error_message() : '';
		$curl_message = $curl_result instanceof WP_Error ? $curl_result->get_error_message() : '';

		return new WP_Error(
			'fetch_failed_all',
			sprintf(
				__( 'HTML alınamadı. wp_remote_get: %1$s | cURL: %2$s', 'trendyol-woocommerce-importer' ),
				$wp_message ? $wp_message : 'unknown',
				$curl_message ? $curl_message : 'unknown'
			)
		);
	}

	private function fetch_with_wp_remote() {
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

		$response = wp_remote_get( $this->url, $args );

		if ( is_wp_error( $response ) ) {
			$this->fetch_debug['wp_remote_get'] = array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
			return $response;
		}

		$status    = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$final_url = wp_remote_retrieve_header( $response, 'location' );

		$this->fetch_debug['wp_remote_get'] = array(
			'ok'          => ( 200 === (int) $status && ! empty( $body ) ),
			'status_code' => (int) $status,
			'body_length' => strlen( (string) $body ),
			'final_url'   => $final_url ? (string) $final_url : $this->url,
			'source'      => 'wp_remote_get',
		);

		if ( 200 !== (int) $status ) {
			return new WP_Error(
				'fetch_failed_wp',
				sprintf( __( 'wp_remote_get başarısız. HTTP durum kodu: %d', 'trendyol-woocommerce-importer' ), $status )
			);
		}

		if ( empty( $body ) || strlen( $body ) < 1000 ) {
			return new WP_Error( 'empty_body_wp', __( 'wp_remote_get body boş veya eksik.', 'trendyol-woocommerce-importer' ) );
		}

		$this->fetch_debug['selected_source'] = 'wp_remote_get';

		return $body;
	}

	private function fetch_with_curl() {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->fetch_debug['curl'] = array(
				'ok'      => false,
				'message' => 'cURL extension not available',
			);

			return new WP_Error( 'curl_missing', __( 'Sunucuda cURL uzantısı yok.', 'trendyol-woocommerce-importer' ) );
		}

		$ch = curl_init();

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => $this->url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
				CURLOPT_HTTPHEADER     => array(
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
					'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
					'Cache-Control: no-cache',
					'Pragma: no-cache',
					'Referer: https://www.trendyol.com/',
				),
			)
		);

		$body        = curl_exec( $ch );
		$curl_error  = curl_error( $ch );
		$status      = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$effective   = (string) curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
		$content_len = strlen( (string) $body );

		curl_close( $ch );

		$this->fetch_debug['curl'] = array(
			'ok'          => empty( $curl_error ) && 200 === $status && ! empty( $body ),
			'status_code' => $status,
			'body_length' => $content_len,
			'final_url'   => $effective ? $effective : $this->url,
			'message'     => $curl_error,
			'source'      => 'curl',
		);

		if ( ! empty( $curl_error ) ) {
			return new WP_Error( 'curl_error', sprintf( __( 'cURL hatası: %s', 'trendyol-woocommerce-importer' ), $curl_error ) );
		}

		if ( 200 !== $status ) {
			return new WP_Error(
				'curl_http_error',
				sprintf( __( 'cURL başarısız. HTTP durum kodu: %d', 'trendyol-woocommerce-importer' ), $status )
			);
		}

		if ( empty( $body ) || $content_len < 1000 ) {
			return new WP_Error( 'empty_body_curl', __( 'cURL body boş veya eksik.', 'trendyol-woocommerce-importer' ) );
		}

		$this->fetch_debug['selected_source'] = 'curl';

		return $body;
	}

	public function parse_page( $html, $mode = 'auto' ) {
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$allowed_modes = array( 'auto', 'standard', 'jsonld_first', 'fallback_only' );
		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			$mode = 'auto';
		}

		$sources = array(
			'name'     => '',
			'price'    => '',
			'category' => '',
			'brand'    => '',
			'sizes'    => '',
			'images'   => '',
			'content'  => '',
			'mode'     => $mode,
		);

		$product_name   = '';
		$price          = '';
		$kategori_ad    = '';
		$brand_name     = '';
		$sizes          = array();
		$product_images = array();
		$product_content= '';

		preg_match_all( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $json_matches );

		$jsonld_items = array();
		foreach ( $json_matches[1] as $json_content ) {
			$data = @json_decode( html_entity_decode( stripslashes( $json_content ) ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$items = isset( $data[0] ) ? $data : array( $data );
			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$jsonld_items[] = $item;
				}
			}
		}

		if ( in_array( $mode, array( 'auto', 'jsonld_first' ), true ) ) {
			foreach ( $jsonld_items as $item ) {
				if ( empty( $brand_name ) && isset( $item['brand'] ) ) {
					if ( is_array( $item['brand'] ) && ! empty( $item['brand']['name'] ) ) {
						$brand_name       = $item['brand']['name'];
						$sources['brand'] = 'jsonld product.brand.name';
					} elseif ( is_string( $item['brand'] ) && '' !== trim( $item['brand'] ) ) {
						$brand_name       = $item['brand'];
						$sources['brand'] = 'jsonld product.brand';
					}
				}

				if ( empty( $kategori_ad ) && isset( $item['@type'] ) && strtolower( $item['@type'] ) === 'product' ) {
					if ( isset( $item['category'] ) && ! empty( $item['category'] ) ) {
						$kategori_ad         = $item['category'];
						$sources['category'] = 'jsonld product.category';
					}
				}

				if ( empty( $product_images ) && isset( $item['image']['contentUrl'] ) && is_array( $item['image']['contentUrl'] ) ) {
					$product_images    = array_merge( $product_images, $item['image']['contentUrl'] );
					$sources['images'] = 'jsonld image.contentUrl';
				}

				if ( empty( $product_images ) && isset( $item['image'] ) && is_array( $item['image'] ) && isset( $item['image'][0] ) ) {
					$product_images    = array_merge( $product_images, $item['image'] );
					$sources['images'] = 'jsonld image array';
				}

				if ( empty( $product_content ) && isset( $item['additionalProperty'] ) && is_array( $item['additionalProperty'] ) ) {
					foreach ( $item['additionalProperty'] as $prop ) {
						$name  = $prop['name'] ?? '';
						$value = isset( $prop['value'] ) && '' !== $prop['value'] ? $prop['value'] : ( $prop['unitText'] ?? '' );
						if ( $name && $value ) {
							$product_content .= '<b>' . esc_html( $name ) . ':</b> ' . esc_html( $value ) . '<br>';
						}
					}
					if ( ! empty( $product_content ) ) {
						$sources['content'] = 'jsonld additionalProperty';
					}
				}

				if ( empty( $brand_name ) && isset( $item['hasVariant'] ) && is_array( $item['hasVariant'] ) ) {
					foreach ( $item['hasVariant'] as $variant_item ) {
						if ( is_array( $variant_item ) && isset( $variant_item['brand'] ) ) {
							if ( is_array( $variant_item['brand'] ) && ! empty( $variant_item['brand']['name'] ) ) {
								$brand_name       = $variant_item['brand']['name'];
								$sources['brand'] = 'jsonld hasVariant.brand.name';
								break;
							} elseif ( is_string( $variant_item['brand'] ) && '' !== trim( $variant_item['brand'] ) ) {
								$brand_name       = $variant_item['brand'];
								$sources['brand'] = 'jsonld hasVariant.brand';
								break;
							}
						}
					}
				}
			}
		}

		if ( in_array( $mode, array( 'auto', 'standard' ), true ) ) {
			if ( preg_match( '/"product_pname":"(.*?)"/', $html, $matches ) ) {
				$product_name    = html_entity_decode( wp_strip_all_tags( stripslashes( $matches[1] ) ), ENT_QUOTES, 'UTF-8' );
				$sources['name'] = 'product_pname regex';
			}

			if ( preg_match( '/<span[^>]*class="[^"]*(?:discounted|prc-slg|prc-dsc|prc-org)[^"]*"[^>]*>([\d\.,]+)\s*TL<\/span>/i', $html, $matches ) ) {
				$price            = str_replace( ',', '.', $matches[1] );
				$sources['price'] = 'price span regex';
			} elseif ( preg_match( '/"price"\s*:\s*"([\d\.]+)"/', $html, $m2 ) ) {
				$price            = $m2[1];
				$sources['price'] = 'price json regex';
			} elseif ( preg_match( '/<meta[^>]*itemprop="price"[^>]*content="([\d\.]+)"/i', $html, $m3 ) ) {
				$price            = $m3[1];
				$sources['price'] = 'meta itemprop price';
			}

			if ( empty( $kategori_ad ) && preg_match_all( '#<a[^>]+class=".*?breadcrumb-link.*?"[^>]*>([^<]+)</a>#i', $html, $breads ) ) {
				$kategori_ad         = end( $breads[1] );
				$sources['category'] = 'breadcrumb regex';
			}

			if ( preg_match_all( '/<img[^>]+src="([^"]+)"[^>]*class="[^"]*gallery-item[^"]*"[^>]*>/i', $html, $img_matches ) ) {
				$product_images = array_merge( $product_images, $img_matches[1] );
				if ( empty( $sources['images'] ) && ! empty( $img_matches[1] ) ) {
					$sources['images'] = 'gallery image regex';
				}
			}
		}

		if ( in_array( $mode, array( 'auto', 'fallback_only' ), true ) ) {
			if ( empty( $product_name ) && preg_match( '/<title>(.*?)<\/title>/is', $html, $title_match ) ) {
				$product_name    = trim( wp_strip_all_tags( html_entity_decode( $title_match[1], ENT_QUOTES, 'UTF-8' ) ) );
				$product_name    = preg_replace( '/\s*-\s*Trendyol.*$/i', '', $product_name );
				$sources['name'] = 'title tag fallback';
			}

			if ( empty( $price ) && preg_match( '/<meta[^>]*itemprop="price"[^>]*content="([\d\.]+)"/i', $html, $m3 ) ) {
				$price            = $m3[1];
				$sources['price'] = 'meta itemprop price';
			}

			if ( empty( $kategori_ad ) && preg_match_all( '#<a[^>]+class=".*?breadcrumb-link.*?"[^>]*>([^<]+)</a>#i', $html, $breads ) ) {
				$kategori_ad         = end( $breads[1] );
				$sources['category'] = 'breadcrumb regex';
			}
		}

		if ( function_exists( 'trendyol_extract_variants_from_html' ) ) {
			$variants = trendyol_extract_variants_from_html( $html );
			if ( ! empty( $variants ) ) {
				foreach ( $variants as $variant ) {
					if ( ! empty( $variant['value'] ) ) {
						$sizes[] = $variant['value'];
					}
				}
				$sizes = array_values( array_unique( $sizes ) );
				if ( ! empty( $sizes ) ) {
					$sources['sizes'] = 'trendyol_extract_variants_from_html';
				}
			}
		}

		$product_images = array_values( array_unique( array_filter( $product_images ) ) );
		$price          = floatval( $price );

		if ( empty( $product_name ) ) {
			return new WP_Error( 'parse_name_failed', __( 'Ürün adı çözülemedi.', 'trendyol-woocommerce-importer' ) );
		}

		if ( $price <= 0 ) {
			return new WP_Error( 'parse_price_failed', __( 'Ürün fiyatı çözülemedi.', 'trendyol-woocommerce-importer' ) );
		}

		$this->product_data = array(
			'name'          => $product_name,
			'price'         => $price,
			'sizes'         => $sizes,
			'images'        => $product_images,
			'content'       => $product_content,
			'url'           => $this->url,
			'category'      => $kategori_ad,
			'brand'         => $brand_name,
			'__sources'     => $sources,
			'__fetch_debug' => $this->fetch_debug,
		);

		return $this->product_data;
	}

	public function get_product_data() {
		return $this->product_data;
	}

	public function scrape() {
		$validation = $this->validate_url();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$html = $this->fetch_page();
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		return $this->parse_page( $html, 'auto' );
	}
}