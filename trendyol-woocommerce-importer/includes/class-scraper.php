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
			'name'                => '',
			'price'               => '',
			'price_regular'       => '',
			'price_discounted'    => '',
			'price_basket'        => '',
			'price_selected_type' => '',
			'category'            => '',
			'brand'               => '',
			'sizes'               => '',
			'images'              => '',
			'content'             => '',
			'mode'                => $mode,
		);

		$product_name    = '';
		$kategori_ad     = '';
		$brand_name      = '';
		$sizes           = array();
		$product_images  = array();
		$product_content = '';

		$price_candidates = array(
			'regular_price'    => null,
			'discounted_price' => null,
			'basket_price'     => null,
		);

		preg_match_all( '/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $json_matches );

		$jsonld_items = array();
		foreach ( $json_matches[1] as $json_content ) {
			$data = json_decode( html_entity_decode( stripslashes( $json_content ) ), true );
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

			if ( empty( $kategori_ad ) && isset( $item['category'] ) && ! empty( $item['category'] ) ) {
				$kategori_ad         = trim( (string) $item['category'] );
				$sources['category'] = 'jsonld product.category';
			}

			if ( empty( $product_name ) && ! empty( $item['name'] ) ) {
				$product_name    = trim( wp_strip_all_tags( (string) $item['name'] ) );
				$sources['name'] = 'jsonld name';
			}

			if ( empty( $product_images ) ) {
				$jsonld_images = $this->extract_images_from_jsonld_item( $item );
				if ( ! empty( $jsonld_images ) ) {
					$product_images    = array_merge( $product_images, $jsonld_images );
					$sources['images'] = 'jsonld image fields';
				}
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
		}

		$envoy_data      = $this->extract_envoy_product_data( $html );
		$datalayer_data  = $this->extract_product_datalayer_data( $html );
		$envoy_prices    = $this->extract_prices_from_envoy_data( $envoy_data );
		$datalayer_prices= $this->extract_prices_from_datalayer_data( $datalayer_data );
		$envoy_images    = $this->extract_images_from_envoy_data( $envoy_data );

		if ( ! empty( $envoy_data['product']['name'] ) ) {
			$product_name    = trim( (string) $envoy_data['product']['name'] );
			$sources['name'] = 'envoy product.name';
		}

		if ( empty( $product_name ) && ! empty( $datalayer_data['product_pname'] ) ) {
			$product_name    = trim( (string) $datalayer_data['product_pname'] );
			$sources['name'] = 'product detail datalayer product_pname';
		}

		if ( empty( $brand_name ) && ! empty( $envoy_data['product']['brand']['name'] ) ) {
			$brand_name       = trim( (string) $envoy_data['product']['brand']['name'] );
			$sources['brand'] = 'envoy product.brand.name';
		}

		if ( empty( $brand_name ) && ! empty( $datalayer_data['product_brand'] ) ) {
			$brand_name       = trim( (string) $datalayer_data['product_brand'] );
			$sources['brand'] = 'product detail datalayer product_brand';
		}

		if ( empty( $kategori_ad ) && ! empty( $envoy_data['product']['category']['name'] ) ) {
			$kategori_ad         = trim( (string) $envoy_data['product']['category']['name'] );
			$sources['category'] = 'envoy product.category.name';
		}

		if ( empty( $kategori_ad ) && ! empty( $datalayer_data['product_categoryname'] ) ) {
			$kategori_ad         = trim( (string) $datalayer_data['product_categoryname'] );
			$sources['category'] = 'product detail datalayer product_categoryname';
		}

		if ( null !== $envoy_prices['basket_price'] ) {
			$price_candidates['basket_price'] = $envoy_prices['basket_price'];
			$sources['price_basket']          = $envoy_prices['sources']['basket_price'] ?? 'envoy basket';
		}
		if ( null !== $envoy_prices['discounted_price'] ) {
			$price_candidates['discounted_price'] = $envoy_prices['discounted_price'];
			$sources['price_discounted']          = $envoy_prices['sources']['discounted_price'] ?? 'envoy discounted';
		}
		if ( null !== $envoy_prices['regular_price'] ) {
			$price_candidates['regular_price'] = $envoy_prices['regular_price'];
			$sources['price_regular']          = $envoy_prices['sources']['regular_price'] ?? 'envoy regular';
		}

		if ( null === $price_candidates['basket_price'] && null !== $datalayer_prices['basket_price'] ) {
			$price_candidates['basket_price'] = $datalayer_prices['basket_price'];
			$sources['price_basket']          = $datalayer_prices['sources']['basket_price'] ?? 'datalayer basket';
		}
		if ( null === $price_candidates['discounted_price'] && null !== $datalayer_prices['discounted_price'] ) {
			$price_candidates['discounted_price'] = $datalayer_prices['discounted_price'];
			$sources['price_discounted']          = $datalayer_prices['sources']['discounted_price'] ?? 'datalayer discounted';
		}
		if ( null === $price_candidates['regular_price'] && null !== $datalayer_prices['regular_price'] ) {
			$price_candidates['regular_price'] = $datalayer_prices['regular_price'];
			$sources['price_regular']          = $datalayer_prices['sources']['regular_price'] ?? 'datalayer regular';
		}

		if ( ! empty( $envoy_images ) ) {
			$product_images    = array_merge( $product_images, $envoy_images );
			$sources['images'] = empty( $sources['images'] ) ? 'envoy product.images' : $sources['images'];
		}

		$html_prices = $this->extract_prices_from_html_markup( $html );
		if ( null === $price_candidates['basket_price'] && null !== $html_prices['basket_price'] ) {
			$price_candidates['basket_price'] = $html_prices['basket_price'];
			$sources['price_basket']          = $html_prices['sources']['basket_price'] ?? 'html basket';
		}
		if ( null === $price_candidates['discounted_price'] && null !== $html_prices['discounted_price'] ) {
			$price_candidates['discounted_price'] = $html_prices['discounted_price'];
			$sources['price_discounted']          = $html_prices['sources']['discounted_price'] ?? 'html discounted';
		}
		if ( null === $price_candidates['regular_price'] && null !== $html_prices['regular_price'] ) {
			$price_candidates['regular_price'] = $html_prices['regular_price'];
			$sources['price_regular']          = $html_prices['sources']['regular_price'] ?? 'html regular';
		}

		if ( empty( $product_name ) && preg_match( '/"product_pname":"(.*?)"/', $html, $matches ) ) {
			$product_name    = html_entity_decode( wp_strip_all_tags( stripslashes( $matches[1] ) ), ENT_QUOTES, 'UTF-8' );
			$sources['name'] = 'product_pname regex';
		}

		if ( empty( $product_name ) && preg_match( '/<title>(.*?)<\/title>/is', $html, $title_match ) ) {
			$product_name    = trim( wp_strip_all_tags( html_entity_decode( $title_match[1], ENT_QUOTES, 'UTF-8' ) ) );
			$product_name    = preg_replace( '/\s*-\s*Trendyol.*$/i', '', $product_name );
			$sources['name'] = 'title tag fallback';
		}

		if ( empty( $kategori_ad ) && preg_match_all( '#<a[^>]+class=".*?breadcrumb-link.*?"[^>]*>([^<]+)</a>#i', $html, $breads ) ) {
			$kategori_ad         = end( $breads[1] );
			$sources['category'] = 'breadcrumb regex';
		}

		if ( function_exists( 'trendyol_extract_variants_from_html' ) ) {
			$variants = trendyol_extract_variants_from_html( $html );
			if ( ! empty( $variants ) ) {
				foreach ( $variants as $variant ) {
					if ( ! empty( $variant['value'] ) ) {
						$sizes[] = $variant['value'];
					}
				}
			}
		}

		if ( empty( $sizes ) && ! empty( $envoy_data['product']['variants'] ) && is_array( $envoy_data['product']['variants'] ) ) {
			foreach ( $envoy_data['product']['variants'] as $variant ) {
				if ( ! empty( $variant['value'] ) ) {
					$sizes[] = $variant['value'];
				}
			}
			if ( ! empty( $sizes ) ) {
				$sources['sizes'] = 'envoy product.variants';
			}
		}

		$sizes = array_values( array_unique( array_filter( $sizes ) ) );

		if ( empty( $product_images ) ) {
			$dom_images = $this->extract_images_from_html( $html );
			if ( ! empty( $dom_images ) ) {
				$product_images    = array_merge( $product_images, $dom_images );
				$sources['images'] = empty( $sources['images'] ) ? 'html image extraction' : $sources['images'];
			}
		}

		$selected_price      = null;
		$selected_price_type = '';

		if ( null !== $price_candidates['basket_price'] ) {
			$selected_price      = $price_candidates['basket_price'];
			$selected_price_type = 'basket_price';
		} elseif ( null !== $price_candidates['discounted_price'] ) {
			$selected_price      = $price_candidates['discounted_price'];
			$selected_price_type = 'discounted_price';
		} elseif ( null !== $price_candidates['regular_price'] ) {
			$selected_price      = $price_candidates['regular_price'];
			$selected_price_type = 'regular_price';
		}

		$product_images = $this->normalize_image_urls( $product_images );
		$product_images = array_values( array_unique( array_filter( $product_images ) ) );

		if ( empty( $product_name ) ) {
			return new WP_Error( 'parse_name_failed', __( 'Ürün adı çözülemedi.', 'trendyol-woocommerce-importer' ) );
		}

		if ( null === $selected_price || $selected_price <= 0 ) {
			return new WP_Error( 'parse_price_failed', __( 'Ürün fiyatı çözülemedi.', 'trendyol-woocommerce-importer' ) );
		}

		$sources['price']               = $selected_price_type ? ( $sources[ 'price_' . str_replace( '_price', '', $selected_price_type ) ] ?? $selected_price_type ) : '';
		$sources['price_selected_type'] = $selected_price_type;

		$this->product_data = array(
			'name'             => $product_name,
			'price'            => (float) $selected_price,
			'price_type'       => $selected_price_type,
			'price_candidates' => $price_candidates,
			'sizes'            => $sizes,
			'images'           => $product_images,
			'content'          => $product_content,
			'url'              => $this->url,
			'category'         => $kategori_ad,
			'brand'            => $brand_name,
			'__sources'        => $sources,
			'__fetch_debug'    => $this->fetch_debug,
		);

		return $this->product_data;
	}

	private function normalize_price_value( $raw_price ) {
		if ( is_null( $raw_price ) || '' === $raw_price ) {
			return null;
		}

		$raw_price = wp_strip_all_tags( (string) $raw_price );
		$raw_price = html_entity_decode( $raw_price, ENT_QUOTES, 'UTF-8' );
		$raw_price = str_replace( array( 'TL', '₺', ' ' ), '', $raw_price );
		$raw_price = preg_replace( '/[^\d\,\.]/u', '', $raw_price );

		if ( '' === $raw_price ) {
			return null;
		}

		if ( false !== strpos( $raw_price, ',' ) ) {
			$raw_price = str_replace( '.', '', $raw_price );
			$raw_price = str_replace( ',', '.', $raw_price );
		}

		$value = (float) $raw_price;

		return $value > 0 ? $value : null;
	}

	private function extract_first_json_object( $html, $marker ) {
		$pos = strpos( $html, $marker );
		if ( false === $pos ) {
			return null;
		}

		$start = strpos( $html, '{', $pos );
		if ( false === $start ) {
			return null;
		}

		$length    = strlen( $html );
		$depth     = 0;
		$in_string = false;
		$escape    = false;

		for ( $i = $start; $i < $length; $i++ ) {
			$char = $html[ $i ];

			if ( $escape ) {
				$escape = false;
				continue;
			}

			if ( '\\' === $char ) {
				$escape = true;
				continue;
			}

			if ( '"' === $char ) {
				$in_string = ! $in_string;
				continue;
			}

			if ( $in_string ) {
				continue;
			}

			if ( '{' === $char ) {
				$depth++;
			} elseif ( '}' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					return substr( $html, $start, $i - $start + 1 );
				}
			}
		}

		return null;
	}

	private function extract_envoy_product_data( $html ) {
		$markers = array(
			'window["__envoy__PROPS"]=',
			'window["__envoy_product-detail__PROPS"]=',
			'window["__envoy_product-image-gallery__PROPS"]=',
		);

		foreach ( $markers as $marker ) {
			$json = $this->extract_first_json_object( $html, $marker );
			if ( empty( $json ) ) {
				continue;
			}

			$data = json_decode( $json, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		return array();
	}

	private function extract_product_datalayer_data( $html ) {
		$marker = 'PuzzleJs.emit("4", "envoy", "__PRODUCT_DETAIL__DATALAYER", ';
		$pos    = strpos( $html, $marker );

		if ( false === $pos ) {
			return array();
		}

		$start = strpos( $html, '{', $pos );
		if ( false === $start ) {
			return array();
		}

		$length    = strlen( $html );
		$depth     = 0;
		$in_string = false;
		$escape    = false;

		for ( $i = $start; $i < $length; $i++ ) {
			$char = $html[ $i ];

			if ( $escape ) {
				$escape = false;
				continue;
			}

			if ( '\\' === $char ) {
				$escape = true;
				continue;
			}

			if ( '"' === $char ) {
				$in_string = ! $in_string;
				continue;
			}

			if ( $in_string ) {
				continue;
			}

			if ( '{' === $char ) {
				$depth++;
			} elseif ( '}' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					$json = substr( $html, $start, $i - $start + 1 );
					$data = json_decode( $json, true );
					return is_array( $data ) ? $data : array();
				}
			}
		}

		return array();
	}

	private function extract_prices_from_envoy_data( $envoy_data ) {
		$results = array(
			'regular_price'    => null,
			'discounted_price' => null,
			'basket_price'     => null,
			'sources'          => array(),
		);

		if ( empty( $envoy_data['product'] ) || ! is_array( $envoy_data['product'] ) ) {
			return $results;
		}

		$product = $envoy_data['product'];

		if ( ! empty( $product['winnerVariant']['price'] ) && is_array( $product['winnerVariant']['price'] ) ) {
			$price_data = $product['winnerVariant']['price'];

			if ( isset( $price_data['discountedPrice']['value'] ) ) {
				$results['basket_price'] = $this->normalize_price_value( $price_data['discountedPrice']['value'] );
				$results['sources']['basket_price'] = 'envoy product.winnerVariant.price.discountedPrice.value';
			}

			if ( isset( $price_data['sellingPrice']['value'] ) ) {
				$results['discounted_price'] = $this->normalize_price_value( $price_data['sellingPrice']['value'] );
				$results['sources']['discounted_price'] = 'envoy product.winnerVariant.price.sellingPrice.value';
			}

			if ( isset( $price_data['originalPrice']['value'] ) ) {
				$results['regular_price'] = $this->normalize_price_value( $price_data['originalPrice']['value'] );
				$results['sources']['regular_price'] = 'envoy product.winnerVariant.price.originalPrice.value';
			}
		}

		return $results;
	}

	private function extract_prices_from_datalayer_data( $data ) {
		$results = array(
			'regular_price'    => null,
			'discounted_price' => null,
			'basket_price'     => null,
			'sources'          => array(),
		);

		if ( empty( $data ) || ! is_array( $data ) ) {
			return $results;
		}

		if ( isset( $data['product_discounted_price'] ) ) {
			$results['basket_price'] = $this->normalize_price_value( $data['product_discounted_price'] );
			$results['sources']['basket_price'] = 'product detail datalayer product_discounted_price';
		}

		if ( isset( $data['product_price'] ) ) {
			$results['discounted_price'] = $this->normalize_price_value( $data['product_price'] );
			$results['sources']['discounted_price'] = 'product detail datalayer product_price';
		}

		if ( isset( $data['product_original_price'] ) ) {
			$results['regular_price'] = $this->normalize_price_value( $data['product_original_price'] );
			$results['sources']['regular_price'] = 'product detail datalayer product_original_price';
		}

		return $results;
	}

	private function extract_prices_from_html_markup( $html ) {
		$results = array(
			'regular_price'    => null,
			'discounted_price' => null,
			'basket_price'     => null,
			'sources'          => array(),
		);

		if ( preg_match( '/<p[^>]*class="[^"]*new-price[^"]*"[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
			$results['basket_price'] = $this->normalize_price_value( wp_strip_all_tags( $m[1] ) );
			$results['sources']['basket_price'] = 'html .new-price';
		}

		if ( preg_match( '/<p[^>]*class="[^"]*old-price[^"]*"[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
			$results['discounted_price'] = $this->normalize_price_value( wp_strip_all_tags( $m[1] ) );
			$results['sources']['discounted_price'] = 'html .old-price';
		}

		return $results;
	}

	private function extract_images_from_envoy_data( $envoy_data ) {
		$images = array();

		if ( ! empty( $envoy_data['product']['images'] ) && is_array( $envoy_data['product']['images'] ) ) {
			$images = array_merge( $images, $envoy_data['product']['images'] );
		}

		return $this->normalize_image_urls( $images );
	}

	private function extract_images_from_jsonld_item( $item ) {
		$images = array();

		if ( ! is_array( $item ) ) {
			return $images;
		}

		if ( isset( $item['image'] ) ) {
			if ( is_string( $item['image'] ) ) {
				$images[] = $item['image'];
			} elseif ( is_array( $item['image'] ) ) {
				if ( isset( $item['image']['contentUrl'] ) ) {
					if ( is_array( $item['image']['contentUrl'] ) ) {
						$images = array_merge( $images, $item['image']['contentUrl'] );
					} else {
						$images[] = $item['image']['contentUrl'];
					}
				} else {
					foreach ( $item['image'] as $image_item ) {
						if ( is_string( $image_item ) ) {
							$images[] = $image_item;
						} elseif ( is_array( $image_item ) && ! empty( $image_item['contentUrl'] ) ) {
							$images[] = $image_item['contentUrl'];
						}
					}
				}
			}
		}

		return $this->normalize_image_urls( $images );
	}

	private function extract_images_from_html( $html ) {
		$images = array();

		if ( preg_match_all( '/https?:\/\/[^"\']+cdn\.dsmcdn\.com[^"\']+\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?/i', $html, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$images[] = $match;
			}
		}

		if ( preg_match_all( '/<img[^>]+(?:src|data-src|data-original-src|data-image)="([^"]+)"/i', $html, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$images[] = $match;
			}
		}

		return $this->normalize_image_urls( $images );
	}

	private function normalize_image_urls( $images ) {
		$normalized = array();

		foreach ( (array) $images as $image_url ) {
			$image_url = html_entity_decode( trim( (string) $image_url ), ENT_QUOTES, 'UTF-8' );
			if ( '' === $image_url ) {
				continue;
			}

			if ( 0 === strpos( $image_url, '//' ) ) {
				$image_url = 'https:' . $image_url;
			}

			$image_url = str_replace( '\u002F', '/', $image_url );
			$image_url = str_replace( '\\/', '/', $image_url );

			if ( false === strpos( $image_url, 'http' ) ) {
				continue;
			}

			if ( false === strpos( $image_url, 'cdn.dsmcdn.com' ) ) {
				continue;
			}

			if ( false !== stripos( $image_url, 'product-placeholder' ) || false !== stripos( $image_url, 'placeholder-v2' ) ) {
				continue;
			}

			$normalized[] = esc_url_raw( $image_url );
		}

		return array_values( array_unique( array_filter( $normalized ) ) );
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