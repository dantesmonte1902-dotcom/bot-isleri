<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Dashboard_Service {

	private $query_service;
	private $price_service;

	public function __construct() {
		$this->query_service = new Trendyol_Product_Query_Service();
		$this->price_service = new Trendyol_Price_Service();
	}

	public function get_summary() {
		return array(
			'total_imported'       => $this->query_service->get_total_imported_count(),
			'imported_today'       => $this->query_service->get_imported_today_count(),
			'blocked_brand_count'  => $this->get_logger_count_by_action( 'blocked_brand' ),
			'duplicate_skipped'    => $this->get_logger_count_by_action( 'duplicate_skip' ),
			'average_net_profit'   => $this->get_average_net_profit(),
			'loss_making_products' => $this->get_loss_making_products_count(),
			'current_euro_kur'     => function_exists( 'get_trendyol_euro_kuru' ) ? (float) get_trendyol_euro_kuru() : 0,
			'current_rsd_kur'      => function_exists( 'get_trendyol_rsd_kuru' ) ? (float) get_trendyol_rsd_kuru() : 0,
		);
	}

	private function get_logger_count_by_action( $action ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'trendyol_logs';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists !== $table_name ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE action = %s",
				$action
			)
		);

		return intval( $count );
	}

	private function get_average_net_profit() {
		$product_ids = $this->query_service->get_trendyol_product_ids(
			array(
				'statuses' => array( 'draft', 'publish' ),
			)
		);

		if ( empty( $product_ids ) ) {
			return 0;
		}

		$profits = array();

		foreach ( $product_ids as $product_id ) {
			$profit = $this->price_service->calculate_net_profit( $product_id );
			if ( null !== $profit ) {
				$profits[] = $profit;
			}
		}

		if ( empty( $profits ) ) {
			return 0;
		}

		return array_sum( $profits ) / count( $profits );
	}

	private function get_loss_making_products_count() {
		$product_ids = $this->query_service->get_trendyol_product_ids(
			array(
				'statuses' => array( 'draft', 'publish' ),
			)
		);

		if ( empty( $product_ids ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $product_ids as $product_id ) {
			$profit = $this->price_service->calculate_net_profit( $product_id );
			if ( null !== $profit && $profit < 0 ) {
				$count++;
			}
		}

		return $count;
	}
}