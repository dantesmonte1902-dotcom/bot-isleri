<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TRENDYOL_IMPORTER_PATH . 'includes/tools.php';

class Trendyol_Bulk_Price_Updater {

	private $price_service;

	public function __construct() {
		$this->price_service = new Trendyol_Price_Service();
	}

	public function run( $status_filter = 'both', $limit = 0 ) {
		return $this->price_service->recalculate_products( $status_filter, $limit );
	}
}