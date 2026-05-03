<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Log_Service {

	private $logger;

	public function __construct() {
		$this->logger = new Trendyol_Logger();
	}

	public function log_blocked_brand( $message, $url = null, $context = array(), $product_id = null ) {
		$this->logger->log(
			'import',
			'blocked_brand',
			'skipped',
			(string) $message,
			$product_id,
			$url,
			null,
			(array) $context
		);
	}

	public function log_duplicate_skip( $message, $url = null, $context = array(), $product_id = null ) {
		$this->logger->log(
			'import',
			'duplicate_skip',
			'skipped',
			(string) $message,
			$product_id,
			$url,
			null,
			(array) $context
		);
	}

	public function log_import_success( $message, $product_id = null, $url = null, $context = array() ) {
		$this->logger->log(
			'import',
			'import_product',
			'success',
			(string) $message,
			$product_id,
			$url,
			null,
			(array) $context
		);
	}

	public function log_bulk_price_update( $result ) {
		$this->logger->log(
			'sync',
			'bulk_price_update',
			'success',
			sprintf(
				'Bulk price update completed. Found: %d, Updated: %d, Skipped: %d, Errors: %d',
				isset( $result['found'] ) ? intval( $result['found'] ) : 0,
				isset( $result['updated'] ) ? intval( $result['updated'] ) : 0,
				isset( $result['skipped'] ) ? intval( $result['skipped'] ) : 0,
				isset( $result['errors'] ) ? intval( $result['errors'] ) : 0
			),
			null,
			null,
			null,
			(array) $result
		);
	}

	public function log_auto_price_update( $result ) {
		$this->logger->log(
			'sync',
			'auto_price_update',
			'success',
			sprintf(
				'Auto price update completed. Found: %d, Updated: %d, Skipped: %d, Errors: %d',
				isset( $result['found'] ) ? intval( $result['found'] ) : 0,
				isset( $result['updated'] ) ? intval( $result['updated'] ) : 0,
				isset( $result['skipped'] ) ? intval( $result['skipped'] ) : 0,
				isset( $result['errors'] ) ? intval( $result['errors'] ) : 0
			),
			null,
			null,
			null,
			(array) $result
		);
	}

	public function log_auto_stock_sync( $result ) {
		$this->logger->log(
			'sync',
			'auto_stock_sync',
			'success',
			sprintf(
				'Auto stock sync completed. Processed: %d, Updated: %d, Errors: %d',
				isset( $result['processed'] ) ? intval( $result['processed'] ) : 0,
				isset( $result['updated'] ) ? intval( $result['updated'] ) : 0,
				isset( $result['errors'] ) ? intval( $result['errors'] ) : 0
			),
			null,
			null,
			null,
			(array) $result
		);
	}

	public function log_legacy_auto_sync( $result ) {
		$this->logger->log(
			'sync',
			'auto_sync_cron',
			'success',
			sprintf(
				'Legacy auto sync completed. Success: %d, Failed: %d',
				isset( $result['success'] ) ? intval( $result['success'] ) : 0,
				isset( $result['failed'] ) ? intval( $result['failed'] ) : 0
			),
			null,
			null,
			null,
			(array) $result
		);
	}
}