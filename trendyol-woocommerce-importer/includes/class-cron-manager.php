<?php
/**
 * Cron Manager Class
 * Zamanlanmış görevleri yöneten sınıf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Cron_Manager {

	const LEGACY_HOOK = 'trendyol_auto_sync';
	const PRICE_HOOK  = 'trendyol_auto_price_update';
	const STOCK_HOOK  = 'trendyol_auto_stock_sync';

	private $log_service;

	public function __construct() {
		$this->log_service = new Trendyol_Log_Service();

		add_filter( 'cron_schedules', array( $this, 'register_custom_intervals' ) );

		add_action( self::LEGACY_HOOK, array( $this, 'run_legacy_sync' ) );
		add_action( self::PRICE_HOOK, array( $this, 'run_price_update' ) );
		add_action( self::STOCK_HOOK, array( $this, 'run_stock_sync' ) );
	}

	public function register_custom_intervals( $schedules ) {
		if ( ! isset( $schedules['every_6_hours'] ) ) {
			$schedules['every_6_hours'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 6 Hours', 'trendyol-woocommerce-importer' ),
			);
		}

		if ( ! isset( $schedules['every_12_hours'] ) ) {
			$schedules['every_12_hours'] = array(
				'interval' => 12 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 12 Hours', 'trendyol-woocommerce-importer' ),
			);
		}

		return $schedules;
	}

	public static function schedule() {
		self::reschedule_all();
	}

	public static function reschedule_all() {
		self::unschedule();

		$price_interval = Trendyol_Settings::get( 'auto_price_update_interval', 'off' );
		$stock_interval = Trendyol_Settings::get( 'auto_stock_sync_interval', 'off' );

		if ( self::is_valid_interval( $price_interval ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $price_interval, self::PRICE_HOOK );
		}

		if ( self::is_valid_interval( $stock_interval ) ) {
			wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), $stock_interval, self::STOCK_HOOK );
		}

		$legacy_enabled  = Trendyol_Settings::get( 'enable_auto_sync', 0 );
		$legacy_interval = Trendyol_Settings::get( 'sync_interval', 'daily' );

		if (
			$legacy_enabled &&
			'off' === $price_interval &&
			'off' === $stock_interval &&
			self::is_valid_interval( $legacy_interval )
		) {
			wp_schedule_event( time(), $legacy_interval, self::LEGACY_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::LEGACY_HOOK );
		wp_clear_scheduled_hook( self::PRICE_HOOK );
		wp_clear_scheduled_hook( self::STOCK_HOOK );
	}

	public function run_legacy_sync() {
		if ( ! Trendyol_Settings::get( 'enable_auto_sync' ) ) {
			return;
		}

		$sync_manager = new Trendyol_Sync_Manager();
		$result       = $sync_manager->sync_all_products();

		$this->log_service->log_legacy_auto_sync( $result );

		$this->send_webhook(
			array(
				'type'   => 'legacy_auto_sync',
				'result' => $result,
			)
		);
	}

	public function run_price_update() {
		$interval = Trendyol_Settings::get( 'auto_price_update_interval', 'off' );
		if ( ! self::is_valid_interval( $interval ) ) {
			return;
		}

		$status_filter = Trendyol_Settings::get( 'auto_sync_product_status', 'both' );

		$updater = new Trendyol_Bulk_Price_Updater();
		$result  = $updater->run( $status_filter );

		$this->log_service->log_auto_price_update( $result );

		$this->send_webhook(
			array(
				'type'   => 'auto_price_update',
				'result' => $result,
			)
		);
	}

	public function run_stock_sync() {
		$interval = Trendyol_Settings::get( 'auto_stock_sync_interval', 'off' );
		if ( ! self::is_valid_interval( $interval ) ) {
			return;
		}

		$status_filter = Trendyol_Settings::get( 'auto_sync_product_status', 'both' );

		$stock_sync = new Trendyol_Variant_Stock_Sync();
		$result     = $stock_sync->sync_products(
			array(
				'status' => $status_filter,
				'limit'  => 50,
			)
		);

		$this->log_service->log_auto_stock_sync( $result );

		$this->send_webhook(
			array(
				'type'   => 'auto_stock_sync',
				'result' => $result,
			)
		);
	}

	private static function is_valid_interval( $interval ) {
		return in_array( $interval, array( 'every_6_hours', 'every_12_hours', 'daily' ), true );
	}

	private function send_webhook( $result ) {
		if ( ! Trendyol_Settings::get( 'enable_webhook' ) ) {
			return;
		}

		$webhook_url = Trendyol_Settings::get( 'webhook_url' );

		if ( empty( $webhook_url ) ) {
			return;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'    => wp_json_encode( $result ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Trendyol Webhook Error: ' . $response->get_error_message() );
		}
	}

	public static function clear_all_schedules() {
		self::unschedule();
	}
}