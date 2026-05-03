<?php
/**
 * Diff Detector Class
 * Ürün verilerindeki değişimleri algılayan sınıf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Diff_Detector {

	private $product_id;
	private $old_data;
	private $new_data;
	private $differences = array();

	public function __construct( $product_id, $old_data, $new_data ) {
		$this->product_id = $product_id;
		$this->old_data   = $old_data;
		$this->new_data   = $new_data;
	}

	/**
	 * Değişimleri tespit et
	 */
	public function detect() {
		// Fiyat karşılaştırması
		$this->detect_price_change();

		// Stok karşılaştırması
		$this->detect_stock_change();

		// Bedenler/varyantlar karşılaştırması
		$this->detect_sizes_change();

		// İçerik/açıklama karşılaştırması
		$this->detect_content_change();

		// Resimleri karşılaştırması
		$this->detect_images_change();

		return $this->differences;
	}

	/**
	 * Fiyat değişimini tespit et
	 */
	private function detect_price_change() {
		$old_price = isset( $this->old_data['price'] ) ? floatval( $this->old_data['price'] ) : 0;
		$new_price = isset( $this->new_data['price'] ) ? floatval( $this->new_data['price'] ) : 0;

		if ( $old_price !== $new_price ) {
			$percentage = $old_price > 0 ? ( ( $new_price - $old_price ) / $old_price ) * 100 : 0;

			$this->differences['price'] = array(
				'type'       => 'price',
				'severity'   => 'high',
				'old_value'  => $old_price,
				'new_value'  => $new_price,
				'difference' => $new_price - $old_price,
				'percentage' => round( $percentage, 2 ),
				'label'      => sprintf(
					esc_html__( 'Fiyat Değişimi: %s TL → %s TL (%s%%)', 'trendyol-woocommerce-importer' ),
					$old_price,
					$new_price,
					round( $percentage, 2 )
				),
			);
		}
	}

	/**
	 * Stok değişimini tespit et
	 */
	private function detect_stock_change() {
		$old_stock = isset( $this->old_data['stock_status'] ) ? $this->old_data['stock_status'] : 'instock';
		$new_stock = isset( $this->new_data['stock_status'] ) ? $this->new_data['stock_status'] : 'instock';

		// Ürün bulunabilir durumuna göre stok belirle
		if ( ! isset( $this->new_data['stock_status'] ) && isset( $this->new_data['name'] ) ) {
			$new_stock = ! empty( $this->new_data['name'] ) ? 'instock' : 'outofstock';
		}

		if ( $old_stock !== $new_stock ) {
			$old_label = 'instock' === $old_stock ? esc_html__( 'Stokta', 'trendyol-woocommerce-importer' ) : esc_html__( 'Tükendi', 'trendyol-woocommerce-importer' );
			$new_label = 'instock' === $new_stock ? esc_html__( 'Stokta', 'trendyol-woocommerce-importer' ) : esc_html__( 'Tükendi', 'trendyol-woocommerce-importer' );

			$severity = 'outofstock' === $new_stock ? 'high' : 'medium';

			$this->differences['stock'] = array(
				'type'       => 'stock',
				'severity'   => $severity,
				'old_value'  => $old_stock,
				'new_value'  => $new_stock,
				'label'      => sprintf(
					esc_html__( 'Stok Durumu: %s → %s', 'trendyol-woocommerce-importer' ),
					$old_label,
					$new_label
				),
			);
		}
	}

	/**
	 * Bedenler değişimini tespit et
	 */
	private function detect_sizes_change() {
		$old_sizes = isset( $this->old_data['sizes'] ) ? (array) $this->old_data['sizes'] : array();
		$new_sizes = isset( $this->new_data['sizes'] ) ? (array) $this->new_data['sizes'] : array();

		$added   = array_diff( $new_sizes, $old_sizes );
		$removed = array_diff( $old_sizes, $new_sizes );

		if ( ! empty( $added ) || ! empty( $removed ) ) {
			$this->differences['sizes'] = array(
				'type'     => 'sizes',
				'severity' => 'medium',
				'added'    => $added,
				'removed'  => $removed,
				'label'    => sprintf(
					esc_html__( 'Beden Değişimi: +%d, -%d', 'trendyol-woocommerce-importer' ),
					count( $added ),
					count( $removed )
				),
			);
		}
	}

	/**
	 * İçerik/açıklama değişimini tespit et
	 */
	private function detect_content_change() {
		$old_content = isset( $this->old_data['content'] ) ? sanitize_text_field( $this->old_data['content'] ) : '';
		$new_content = isset( $this->new_data['content'] ) ? sanitize_text_field( $this->new_data['content'] ) : '';

		if ( $old_content !== $new_content && ! empty( $new_content ) ) {
			$this->differences['content'] = array(
				'type'       => 'content',
				'severity'   => 'low',
				'old_value'  => substr( $old_content, 0, 100 ),
				'new_value'  => substr( $new_content, 0, 100 ),
				'label'      => esc_html__( 'Ürün Açıklaması Değişti', 'trendyol-woocommerce-importer' ),
			);
		}
	}

	/**
	 * Resimleri karşılaştır
	 */
	private function detect_images_change() {
		$old_images = isset( $this->old_data['images'] ) ? (array) $this->old_data['images'] : array();
		$new_images = isset( $this->new_data['images'] ) ? (array) $this->new_data['images'] : array();

		$old_count = count( $old_images );
		$new_count = count( $new_images );

		if ( $old_count !== $new_count ) {
			$this->differences['images'] = array(
				'type'       => 'images',
				'severity'   => 'low',
				'old_count'  => $old_count,
				'new_count'  => $new_count,
				'label'      => sprintf(
					esc_html__( 'Resim Sayısı Değişti: %d → %d', 'trendyol-woocommerce-importer' ),
					$old_count,
					$new_count
				),
			);
		}
	}

	/**
	 * Değişimler var mı?
	 */
	public function has_changes() {
		return ! empty( $this->differences );
	}

	/**
	 * Değişimleri getir
	 */
	public function get_differences() {
		return $this->differences;
	}

	/**
	 * Ağırlığı getir (high, medium, low)
	 */
	public function get_severity() {
		if ( isset( $this->differences['price'] ) || isset( $this->differences['stock'] ) ) {
			return 'high';
		}
		if ( isset( $this->differences['sizes'] ) ) {
			return 'medium';
		}
		return 'low';
	}
}
?>