<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Variant_Stock_Sync {

	private $service;

	public function __construct() {
		$this->service = new Trendyol_Stock_Sync_Service();
	}

	public function sync_products( $args = array() ) {
		return $this->service->sync_products( $args );
	}

	public function sync_single_product( $product_id ) {
		return $this->service->sync_single_product( $product_id );
	}
}