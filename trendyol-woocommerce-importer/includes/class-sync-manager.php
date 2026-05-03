<?php
/**
 * Sync Manager Class - Fixed Version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Sync_Manager {

	private $scraper;
	private $logger;
	private $settings;

	public function __construct() {
		$this->logger   = new Trendyol_Logger();
		$this->settings = new Trendyol_Settings();
	}

	/**
	 * Tek bir ürünü senkronize et
	 */
	public function sync_product( $product_id ) {
		// Yetki kontrolü
		if ( ! $this->settings::user_can_sync() ) {
			return new WP_Error( 
				'permission_denied', 
				esc_html__( 'Ürünleri senkronize etme izniniz yok.', 'trendyol-woocommerce-importer' ) 
			);
		}

		// Ürün kontrol et
		$product = get_post( $product_id );
		if ( ! $product || 'product' !== $product->post_type ) {
			return new WP_Error( 
				'invalid_product', 
				esc_html__( 'Geçersiz ürün.', 'trendyol-woocommerce-importer' ) 
			);
		}

		// Trendyol URL'sini getir
		$trendyol_url = get_post_meta( $product_id, 'trendyol_product_url', true );

		if ( empty( $trendyol_url ) ) {
			$this->logger->log(
				'sync',
				'sync_product',
				'error',
				esc_html__( 'Ürün için Trendyol URL\'si bulunamadı', 'trendyol-woocommerce-importer' ),
				$product_id
			);
			return new WP_Error( 
				'no_url', 
				esc_html__( 'Bu ürün için Trendyol URL\'si bulunamadı.', 'trendyol-woocommerce-importer' ) 
			);
		}

		// Scraper'ı başlat
		$this->scraper = new Trendyol_Scraper( $trendyol_url );
		$new_data      = $this->scraper->scrape();

		if ( is_wp_error( $new_data ) ) {
			$this->logger->log(
				'sync',
				'sync_product',
				'error',
				$new_data->get_error_message(),
				$product_id,
				$trendyol_url
			);
			return $new_data;
		}

		// Eski verileri getir (Diff detection için)
		$old_data = array(
			'name'    => $product->post_title,
			'price'   => (float) get_post_meta( $product_id, '_regular_price', true ),
			'sizes'   => wp_get_post_terms( $product_id, 'pa_beden', array( 'fields' => 'names' ) ),
			'stock_status' => get_post_meta( $product_id, '_stock_status', true ) ?: 'instock',
		);

		// Değişimleri algıla
		$diff_detector = new Trendyol_Diff_Detector( $product_id, $old_data, $new_data );
		$diff_detector->detect();

		if ( $diff_detector->has_changes() ) {
			// Bildirim gönder (Ürünü güncelleme! Sadece bildir)
			$notifier = new Trendyol_Notifier( 
				$product_id, 
				$product->post_title, 
				$diff_detector->get_differences() 
			);
			$notifier->send();
		}

		$this->logger->log(
			'sync',
			'sync_product',
			'success',
			esc_html__( 'Ürün kontrol edildi. Değişim algılandı: ', 'trendyol-woocommerce-importer' ) . ( $diff_detector->has_changes() ? 'Evet' : 'Hayır' ),
			$product_id,
			$trendyol_url,
			$old_data,
			$new_data
		);

		return true;
	}

	/**
	 * Tüm ürünleri senkronize et
	 */
	public function sync_all_products() {
		global $wpdb;

		// Trendyol URL'si olan tüm ürünleri getir
		$product_ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND pm.meta_key = 'trendyol_product_url'
			AND pm.meta_value != ''
			AND p.post_status = 'publish'
			LIMIT 50"
		);

		if ( empty( $product_ids ) ) {
			return array(
				'total'     => 0,
				'success'   => 0,
				'failed'    => 0,
				'products'  => array(),
			);
		}

		$results = array(
			'total'     => count( $product_ids ),
			'success'   => 0,
			'failed'    => 0,
			'products'  => array(),
		);

		foreach ( $product_ids as $product_id ) {
			$result = $this->sync_product( $product_id );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['products'][ $product_id ] = array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				);
			} else {
				$results['success']++;
				$results['products'][ $product_id ] = array(
					'status'  => 'success',
					'message' => esc_html__( 'Başarıyla kontrol edildi', 'trendyol-woocommerce-importer' ),
				);
			}
		}

		return $results;
	}
}
?>