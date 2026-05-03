<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Stock_Sync_Service {

	private $query_service;

	public function __construct() {
		$this->query_service = new Trendyol_Product_Query_Service();
	}

	public function sync_products( $args = array() ) {
		$defaults = array(
			'status' => 'draft',
			'batch'  => 10,
			'page'   => 1,
			'limit'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$page   = max( 1, intval( $args['page'] ) );
		$batch  = max( 1, intval( $args['batch'] ) );
		$status = $this->normalize_status( $args['status'] );
		$limit  = max( 0, intval( $args['limit'] ) );

		$statuses = $this->expand_statuses( $status );
		$offset   = ( $page - 1 ) * $batch;

		$product_ids = $this->query_service->get_trendyol_product_ids(
			array(
				'statuses' => $statuses,
				'limit'    => $limit > 0 ? $limit : $batch,
				'offset'   => $limit > 0 ? 0 : $offset,
			)
		);

		$result = array(
			'total_products' => count( $product_ids ),
			'changed'        => array(),
			'messages'       => array(),
			'processed'      => 0,
			'updated'        => 0,
			'errors'         => 0,
			'more'           => false,
			'next_page'      => $page + 1,
		);

		if ( empty( $product_ids ) ) {
			$result['message'] = 'İşlenecek ürün bulunamadı.';
			return $result;
		}

		foreach ( $product_ids as $product_id ) {
			$single = $this->sync_single_product( $product_id );

			$result['processed']++;

			if ( ! empty( $single['error'] ) ) {
				$result['errors']++;
			}

			if ( ! empty( $single['changed'] ) ) {
				$result['updated']++;
				$result['changed'][] = $single['changed'];
			}

			if ( ! empty( $single['messages'] ) && is_array( $single['messages'] ) ) {
				$result['messages'] = array_merge( $result['messages'], $single['messages'] );
			}
		}

		if ( 0 === $limit && count( $product_ids ) === $batch ) {
			$result['more'] = true;
		}

		$result['message'] = sprintf(
			'İşlem tamamlandı. İşlenen ürün: %d, Güncellenen ürün: %d, Hata: %d',
			$result['processed'],
			$result['updated'],
			$result['errors']
		);

		return $result;
	}

	public function sync_single_product( $product_id ) {
		$product_id   = absint( $product_id );
		$messages     = array();
		$product      = get_post( $product_id );
		$changed_data = null;

		if ( ! $product || 'product' !== $product->post_type ) {
			return array(
				'error'    => true,
				'messages' => array( 'Geçersiz ürün: #' . $product_id ),
			);
		}

		$trendyol_url = get_post_meta( $product_id, 'trendyol_product_url', true );
		if ( empty( $trendyol_url ) ) {
			return array(
				'error'    => true,
				'messages' => array( '<b>' . esc_html( $product->post_title ) . '</b>: Trendyol URL bulunamadı.' ),
			);
		}

		$variation_ids = get_posts(
			array(
				'post_type'   => 'product_variation',
				'post_parent' => $product_id,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		if ( empty( $variation_ids ) ) {
			return array(
				'error'    => false,
				'messages' => array( '<b>' . esc_html( $product->post_title ) . '</b>: varyasyon bulunamadı.' ),
			);
		}

		$html = $this->get_trendyol_page_html( $trendyol_url );

		if ( is_wp_error( $html ) ) {
			return array(
				'error'    => true,
				'messages' => array( '<b>' . esc_html( $product->post_title ) . '</b>: <span style="color:red">' . esc_html( $html->get_error_message() ) . '</span>' ),
			);
		}

		if ( empty( $html ) ) {
			return array(
				'error'    => true,
				'messages' => array( '<b>' . esc_html( $product->post_title ) . '</b>: <span style="color:red">Boş cevap döndü.</span>' ),
			);
		}

		$variants = function_exists( 'trendyol_extract_variants_from_html' ) ? trendyol_extract_variants_from_html( $html ) : array();

		if ( empty( $variants ) ) {
			return array(
				'error'    => true,
				'messages' => array( '<b>' . esc_html( $product->post_title ) . '</b>: <span style="color:#f59e0b">Varyant verisi parse edilemedi.</span>' ),
			);
		}

		$stock_map = $this->build_stock_map( $variants );
		$product_changes = array();

		foreach ( $variation_ids as $variation_id ) {
			$size_raw = get_post_meta( $variation_id, 'attribute_pa_beden', true );
			$size     = is_array( $size_raw ) ? reset( $size_raw ) : $size_raw;

			if ( function_exists( 'trendyol_normalize_variant_value' ) ) {
				$size_key = trendyol_normalize_variant_value( $size );
			} else {
				$size_key = sanitize_title( $size );
			}

			if ( empty( $size_key ) ) {
				continue;
			}

			if ( ! array_key_exists( $size_key, $stock_map ) ) {
				$messages[] = '<span style="color:#d97706">Eşleşme yok</span>: ' . esc_html( $product->post_title ) . ' [' . esc_html( $size ) . ']';
				continue;
			}

			$variant_data     = $stock_map[ $size_key ];
			$variant_in_stock = ! empty( $variant_data['in_stock'] );
			$target_stock     = $variant_in_stock ? 'instock' : 'outofstock';
			$current_stock    = get_post_meta( $variation_id, '_stock_status', true );

			if ( $current_stock !== $target_stock ) {
				update_post_meta( $variation_id, '_stock_status', $target_stock );

				$product_changes[] = array(
					'variation_id' => $variation_id,
					'size'         => $size,
					'stock'        => $target_stock,
				);

				if ( $variant_in_stock ) {
					$messages[] = '<span style="color:green">STOK AÇILDI</span>: <b>' . esc_html( $product->post_title ) . '</b> [' . esc_html( $size ) . ']';
				} else {
					$messages[] = '<span style="color:#e11d48">STOK KAPANDI</span>: <b>' . esc_html( $product->post_title ) . '</b> [' . esc_html( $size ) . ']';
				}
			} else {
				$messages[] = '<span style="color:#64748b">Değişmedi</span>: <b>' . esc_html( $product->post_title ) . '</b> [' . esc_html( $size ) . '] zaten <b>' . esc_html( $target_stock ) . '</b>';
			}
		}

		if ( ! empty( $product_changes ) ) {
			$changed_data = array(
				'product_id' => $product_id,
				'title'      => $product->post_title,
				'edit_url'   => get_edit_post_link( $product_id ),
				'variations' => $product_changes,
			);
		}

		return array(
			'error'    => false,
			'changed'  => $changed_data,
			'messages' => $messages,
		);
	}

	private function expand_statuses( $status ) {
		if ( 'both' === $status ) {
			return array( 'draft', 'publish' );
		}

		if ( in_array( $status, array( 'draft', 'publish' ), true ) ) {
			return array( $status );
		}

		return array( 'draft' );
	}

	private function build_stock_map( $variants ) {
		$stock_map = array();

		foreach ( $variants as $variant ) {
			$variant_in_stock = ( isset( $variant['inStock'] ) && true === $variant['inStock'] );

			if ( ! empty( $variant['norm_value'] ) ) {
				$stock_map[ $variant['norm_value'] ] = array(
					'in_stock'   => $variant_in_stock,
					'raw_value'  => isset( $variant['value'] ) ? $variant['value'] : '',
					'raw_beauty' => isset( $variant['beautifiedValue'] ) ? $variant['beautifiedValue'] : '',
				);
			}

			if ( ! empty( $variant['norm_beautified'] ) ) {
				$stock_map[ $variant['norm_beautified'] ] = array(
					'in_stock'   => $variant_in_stock,
					'raw_value'  => isset( $variant['value'] ) ? $variant['value'] : '',
					'raw_beauty' => isset( $variant['beautifiedValue'] ) ? $variant['beautifiedValue'] : '',
				);
			}
		}

		return $stock_map;
	}

	private function normalize_status( $status ) {
		$status = sanitize_key( $status );

		if ( in_array( $status, array( 'draft', 'publish', 'both' ), true ) ) {
			return $status;
		}

		return 'draft';
	}

	private function get_trendyol_page_html( $url ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', 'URL boş' );
		}

		$args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			'headers'     => array(
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
				'Accept-Language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
				'Cache-Control'   => 'no-cache',
				'Pragma'          => 'no-cache',
				'Referer'         => 'https://www.trendyol.com/',
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 === $code && ! empty( $body ) && strlen( $body ) > 1000 ) {
				return $body;
			}
		}

		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();

			curl_setopt_array(
				$ch,
				array(
					CURLOPT_URL            => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 5,
					CURLOPT_TIMEOUT        => 30,
					CURLOPT_CONNECTTIMEOUT => 15,
					CURLOPT_ENCODING       => '',
					CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
					CURLOPT_HTTPHEADER     => array(
						'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
						'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
						'Cache-Control: no-cache',
						'Pragma: no-cache',
						'Referer: https://www.trendyol.com/',
					),
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false,
				)
			);

			$body = curl_exec( $ch );
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$err  = curl_error( $ch );
			curl_close( $ch );

			if ( 200 === $code && ! empty( $body ) && strlen( $body ) > 1000 ) {
				return $body;
			}

			if ( ! empty( $err ) ) {
				return new WP_Error( 'curl_error', 'cURL hatası: ' . $err );
			}
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = is_array( $response ) ? wp_remote_retrieve_response_code( $response ) : 0;
		return new WP_Error( 'empty_body', 'HTML boş döndü. HTTP: ' . $code );
	}
}