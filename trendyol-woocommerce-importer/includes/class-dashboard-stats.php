<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Dashboard_Stats {

	public static function get_summary() {
		$service = new Trendyol_Dashboard_Service();
		return $service->get_summary();
	}
}