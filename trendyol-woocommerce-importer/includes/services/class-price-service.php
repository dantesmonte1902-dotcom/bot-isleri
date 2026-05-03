<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Price_Service {

	private $default_kargo;
	private $default_marj;
	private $query_service;

	public function __construct() {
		$this->default_kargo = get_option( 'trendyol_default_kargo', 0 );
		$this->default_marj  = get_option( 'trendyol_default_marj', 1.3 );
		$this->query_service = new Trendyol_Product_Query_Service();
	}

	public function calculate_sale_price_rsd( $tl_price, $category ) {
		$tl_price = (float) $tl_price;
		$category = (string) $category;

		if ( $tl_price <= 0 ) {
			return 0;
		}

		$euro_kur = function_exists( 'get_trendyol_euro_kuru' ) ? get_trendyol_euro_kuru() : 0;
		$rsd_kur  = function_exists( 'get_trendyol_rsd_kuru' ) ? get_trendyol_rsd_kuru() : 0;

		return trendyol_final_fiyat_rsd(
			$tl_price,
			$category,
			$euro_kur,
			$rsd_kur,
			$this->default_kargo,
			$this->default_marj
		);
	}

	public function recalculate_product( $product_id ) {
		$product_id  = absint( $product_id );
		$product     = get_post( $product_id );
		$product_tl  = get_post_meta( $product_id, 'trendyol_original_price_tl', true );
		$category    = get_post_meta( $product_id, 'trendyol_product_category', true );
		$product_url = get_post_meta( $product_id, 'trendyol_product_url', true );

		$base_detail = array(
			'product_id'   => $product_id,
			'product_name' => $product ? $product->post_title : '',
			'product_url'  => $product_url,
			'old_price'    => '',
			'new_price'    => '',
			'status'       => 'skipped',
			'message'      => '',
		);

		if ( ! $product || 'product' !== $product->post_type ) {
			$base_detail['status']  = 'error';
			$base_detail['message'] = 'Ürün bulunamadı.';
			return $base_detail;
		}

		if ( '' === $product_tl || ! is_numeric( $product_tl ) || (float) $product_tl <= 0 ) {
			$base_detail['status']  = 'skipped';
			$base_detail['message'] = 'trendyol_original_price_tl eksik.';
			return $base_detail;
		}

		$new_price = $this->calculate_sale_price_rsd( (float) $product_tl, (string) $category );

		if ( ! is_numeric( $new_price ) || (float) $new_price <= 0 ) {
			$base_detail['status']  = 'error';
			$base_detail['message'] = 'Yeni fiyat hesaplanamadı.';
			return $base_detail;
		}

		$new_price = (float) $new_price;

		$variation_ids = get_posts(
			array(
				'post_type'   => 'product_variation',
				'post_parent' => $product_id,
				'numberposts' => -1,
				'fields'      => 'ids',
				'post_status' => array( 'publish', 'private' ),
			)
		);

		if ( ! empty( $variation_ids ) ) {
			$existing_prices = array();

			foreach ( $variation_ids as $variation_id ) {
				$old_variation_price = get_post_meta( $variation_id, '_price', true );
				if ( '' !== $old_variation_price && is_numeric( $old_variation_price ) ) {
					$existing_prices[] = (float) $old_variation_price;
				}

				update_post_meta( $variation_id, '_regular_price', $new_price );
				update_post_meta( $variation_id, '_price', $new_price );
			}

			update_post_meta( $product_id, '_regular_price', $new_price );
			update_post_meta( $product_id, '_price', $new_price );

			$base_detail['old_price'] = ! empty( $existing_prices ) ? min( $existing_prices ) : '';
			$base_detail['new_price'] = $new_price;
			$base_detail['status']    = 'updated';
			$base_detail['message']   = count( $variation_ids ) . ' varyasyon güncellendi.';

			return $base_detail;
		}

		$old_price = get_post_meta( $product_id, '_price', true );
		update_post_meta( $product_id, '_regular_price', $new_price );
		update_post_meta( $product_id, '_price', $new_price );

		$base_detail['old_price'] = ( '' !== $old_price && is_numeric( $old_price ) ) ? (float) $old_price : '';
		$base_detail['new_price'] = $new_price;
		$base_detail['status']    = 'updated';
		$base_detail['message']   = 'Simple ürün fiyatı güncellendi.';

		return $base_detail;
	}

	public function recalculate_products( $status_filter = 'both', $limit = 0 ) {
		$statuses = $this->normalize_status_filter( $status_filter );
		$offset   = 0;

		$product_ids = $this->query_service->get_trendyol_product_ids(
			array(
				'statuses' => $statuses,
				'limit'    => $limit,
				'offset'   => $offset,
			)
		);

		$result = array(
			'found'     => count( $product_ids ),
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'details'   => array(),
			'status'    => $status_filter,
			'euro_kur'  => function_exists( 'get_trendyol_euro_kuru' ) ? get_trendyol_euro_kuru() : 0,
			'rsd_kur'   => function_exists( 'get_trendyol_rsd_kuru' ) ? get_trendyol_rsd_kuru() : 0,
			'startedAt' => current_time( 'mysql' ),
		);

		foreach ( $product_ids as $product_id ) {
			$single = $this->recalculate_product( $product_id );

			if ( 'updated' === $single['status'] ) {
				$result['updated']++;
			} elseif ( 'skipped' === $single['status'] ) {
				$result['skipped']++;
			} else {
				$result['errors']++;
			}

			$result['details'][] = $single;
		}

		$result['finishedAt'] = current_time( 'mysql' );

		return $result;
	}

	public function calculate_net_profit( $post_id ) {
		$kategori          = get_post_meta( $post_id, 'trendyol_product_category', true );
		$alis_tl           = get_post_meta( $post_id, 'trendyol_original_price_tl', true );
		$alis_rsd_fallback = get_post_meta( $post_id, 'trendyol_original_price_rsd', true );
		$satis_rsd         = $this->get_product_sale_price_rsd( $post_id );

		if ( '' === $satis_rsd || null === $satis_rsd || ! is_numeric( $satis_rsd ) ) {
			return null;
		}

		$current_euro_kur = function_exists( 'get_trendyol_euro_kuru' ) ? (float) get_trendyol_euro_kuru() : 0;
		$current_rsd_kur  = function_exists( 'get_trendyol_rsd_kuru' ) ? (float) get_trendyol_rsd_kuru() : 0;

		if ( $current_euro_kur <= 0 || $current_rsd_kur <= 0 ) {
			return null;
		}

		$purchase_eur = null;

		if ( '' !== $alis_tl && null !== $alis_tl && is_numeric( $alis_tl ) && (float) $alis_tl > 0 ) {
			$purchase_eur = (float) $alis_tl / $current_euro_kur;
		} elseif ( '' !== $alis_rsd_fallback && null !== $alis_rsd_fallback && is_numeric( $alis_rsd_fallback ) && (float) $alis_rsd_fallback > 0 ) {
			$purchase_eur = (float) $alis_rsd_fallback / $current_rsd_kur;
		}

		if ( null === $purchase_eur ) {
			return null;
		}

		$kategori = function_exists( 'trendyol_normalize_cat' ) ? trendyol_normalize_cat( $kategori ) : sanitize_text_field( $kategori );

		$kargo_eur = (float) get_option( 'trendyol_default_kargo', 0 );
		$kargo_arr = get_option( 'trendyol_kargo_maliyetleri', array() );

		if ( is_array( $kargo_arr ) && ! empty( $kategori ) && isset( $kargo_arr[ $kategori ] ) && is_array( $kargo_arr[ $kategori ] ) ) {
			if ( isset( $kargo_arr[ $kategori ]['kargo'] ) ) {
				$kargo_eur = (float) str_replace( ',', '.', $kargo_arr[ $kategori ]['kargo'] );
			}
		}

		$sales_eur      = (float) $satis_rsd / $current_rsd_kur;
		$total_cost_eur = (float) $purchase_eur + (float) $kargo_eur;

		return (float) $sales_eur - (float) $total_cost_eur;
	}

	public function get_product_sale_price_rsd( $post_id ) {
		$price = get_post_meta( $post_id, '_price', true );
		if ( '' !== $price && null !== $price && is_numeric( $price ) ) {
			return (float) $price;
		}

		$price = get_post_meta( $post_id, '_regular_price', true );
		if ( '' !== $price && null !== $price && is_numeric( $price ) ) {
			return (float) $price;
		}

		$variation_ids = get_posts(
			array(
				'post_type'   => 'product_variation',
				'post_parent' => $post_id,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		if ( ! empty( $variation_ids ) ) {
			$prices = array();

			foreach ( $variation_ids as $variation_id ) {
				$v_price = get_post_meta( $variation_id, '_price', true );
				if ( '' !== $v_price && null !== $v_price && is_numeric( $v_price ) ) {
					$prices[] = (float) $v_price;
				}
			}

			if ( ! empty( $prices ) ) {
				return min( $prices );
			}

			$prices = array();

			foreach ( $variation_ids as $variation_id ) {
				$v_price = get_post_meta( $variation_id, '_regular_price', true );
				if ( '' !== $v_price && null !== $v_price && is_numeric( $v_price ) ) {
					$prices[] = (float) $v_price;
				}
			}

			if ( ! empty( $prices ) ) {
				return min( $prices );
			}
		}

		return '';
	}

	private function normalize_status_filter( $status_filter ) {
		$status_filter = sanitize_key( $status_filter );

		if ( 'draft' === $status_filter ) {
			return array( 'draft' );
		}

		if ( 'publish' === $status_filter ) {
			return array( 'publish' );
		}

		return array( 'draft', 'publish' );
	}
}