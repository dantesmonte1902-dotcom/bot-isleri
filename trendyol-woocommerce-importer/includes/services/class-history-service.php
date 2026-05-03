<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_History_Service {

	private $logger;

	public function __construct() {
		$this->logger = new Trendyol_Logger();
	}

	public function get_filters_from_request( $request ) {
		return array(
			'log_type' => isset( $request['type'] ) ? sanitize_text_field( $request['type'] ) : null,
			'status'   => isset( $request['status'] ) ? sanitize_text_field( $request['status'] ) : null,
			'page'     => isset( $request['paged'] ) ? max( 1, intval( $request['paged'] ) ) : 1,
			'per_page' => 20,
		);
	}

	public function get_history_page_data( $filters ) {
		$page     = max( 1, intval( $filters['page'] ) );
		$per_page = max( 1, intval( $filters['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$log_args = array(
			'log_type' => $filters['log_type'],
			'status'   => $filters['status'],
			'limit'    => $per_page,
			'offset'   => $offset,
			'order'    => 'DESC',
		);

		$logs        = $this->logger->get_logs( $log_args );
		$total_logs  = $this->logger->get_logs_count( $log_args );
		$total_pages = $total_logs > 0 ? (int) ceil( $total_logs / $per_page ) : 1;
		$stats       = $this->logger->get_statistics();

		return array(
			'filters'     => $filters,
			'logs'        => $logs,
			'total_logs'  => $total_logs,
			'total_pages' => $total_pages,
			'stats'       => $stats,
		);
	}
}