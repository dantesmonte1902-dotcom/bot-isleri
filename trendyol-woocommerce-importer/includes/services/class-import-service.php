<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Import_Service {

	private $log_service;

	public function __construct() {
		$this->log_service = new Trendyol_Log_Service();
	}

	public function preview_url( $url ) {
		$url = esc_url_raw( $url );

		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'URL boş.', 'trendyol-woocommerce-importer' ) );
		}

		$scraper = new Trendyol_Scraper( $url );
		$result  = $scraper->scrape();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$brand_name    = isset( $result['brand'] ) ? $result['brand'] : '';
		$blocked_brand = trendyol_is_blocked_brand( $brand_name );

		if ( false !== $blocked_brand ) {
			$this->log_service->log_blocked_brand(
				sprintf( 'Blocked brand detected during preview: %s', $blocked_brand ),
				$url,
				array(
					'brand' => $blocked_brand,
				)
			);

			return new WP_Error(
				'blocked_brand',
				sprintf(
					__( '🚫 İstenmeyen marka filtresi aktif. Tespit edilen marka: %s', 'trendyol-woocommerce-importer' ),
					$blocked_brand
				)
			);
		}

		return $result;
	}

	public function import_from_form_data( $product_data ) {
		$product_data = $this->normalize_product_data( $product_data );

		$importer   = new Trendyol_Product_Importer( $product_data );
		$product_id = $importer->import();

		if ( is_wp_error( $product_id ) ) {
			if ( 'already_exists' === $product_id->get_error_code() ) {
				$this->log_service->log_duplicate_skip(
					'Duplicate Trendyol product skipped during single import.',
					$product_data['url'],
					$product_data
				);
			} elseif ( 'blocked_brand' === $product_id->get_error_code() ) {
				$this->log_service->log_blocked_brand(
					$product_id->get_error_message(),
					$product_data['url'],
					$product_data
				);
			}

			return $product_id;
		}

		$this->log_service->log_import_success(
			__( 'Ürün başarıyla içe aktarıldı', 'trendyol-woocommerce-importer' ),
			$product_id,
			$product_data['url'],
			$product_data
		);

		return $product_id;
	}

	public function import_from_url( $url, $overrides = array() ) {
		$url = esc_url_raw( $url );

		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'URL boş.', 'trendyol-woocommerce-importer' ) );
		}

		$scraper = new Trendyol_Scraper( $url );
		$data    = $scraper->scrape();

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! empty( $overrides ) && is_array( $overrides ) ) {
			$data = array_merge( $data, $overrides );
		}

		$data['url'] = $url;

		$importer   = new Trendyol_Product_Importer( $data );
		$product_id = $importer->import();

		if ( is_wp_error( $product_id ) ) {
			if ( 'already_exists' === $product_id->get_error_code() ) {
				$this->log_service->log_duplicate_skip(
					'Duplicate Trendyol product skipped during URL import.',
					$url,
					$data
				);
			} elseif ( 'blocked_brand' === $product_id->get_error_code() ) {
				$this->log_service->log_blocked_brand(
					$product_id->get_error_message(),
					$url,
					$data
				);
			}

			return $product_id;
		}

		$this->log_service->log_import_success(
			__( 'Ürün başarıyla içe aktarıldı', 'trendyol-woocommerce-importer' ),
			$product_id,
			$url,
			$data
		);

		return $product_id;
	}

	private function normalize_product_data( $product_data ) {
		return array(
			'name'     => isset( $product_data['name'] ) ? sanitize_text_field( $product_data['name'] ) : '',
			'price'    => isset( $product_data['price'] ) ? floatval( $product_data['price'] ) : 0,
			'sizes'    => isset( $product_data['sizes'] ) ? (array) $product_data['sizes'] : array(),
			'images'   => isset( $product_data['images'] ) ? (array) $product_data['images'] : array(),
			'content'  => isset( $product_data['content'] ) ? wp_kses_post( $product_data['content'] ) : '',
			'url'      => isset( $product_data['url'] ) ? esc_url_raw( $product_data['url'] ) : '',
			'category' => isset( $product_data['category'] ) ? sanitize_text_field( $product_data['category'] ) : '',
			'brand'    => isset( $product_data['brand'] ) ? sanitize_text_field( $product_data['brand'] ) : '',
		);
	}
}