<?php
/**
 * Logger Class
 * Tüm işlemleri veritabanına kaydeden sınıf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Logger {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'trendyol_logs';
	}

	/**
	 * Veritabanı tablosunu oluştur
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'trendyol_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id bigint(20) UNSIGNED,
			log_type varchar(20) NOT NULL DEFAULT 'import',
			action varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			message longtext,
			product_url varchar(500),
			old_data longtext,
			new_data longtext,
			user_id bigint(20) UNSIGNED,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY log_type (log_type),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log kaydet
	 */
	public function log( $type, $action, $status, $message, $product_id = null, $product_url = null, $old_data = null, $new_data = null ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$data = array(
			'log_type'    => $type,
			'action'      => $action,
			'status'      => $status,
			'message'     => $message,
			'product_id'  => $product_id,
			'product_url' => $product_url,
			'old_data'    => is_array( $old_data ) ? wp_json_encode( $old_data ) : $old_data,
			'new_data'    => is_array( $new_data ) ? wp_json_encode( $new_data ) : $new_data,
			'user_id'     => $user_id,
		);

		$result = $wpdb->insert( $this->table_name, $data );

		if ( false === $result ) {
			error_log( 'Trendyol Logger Error: ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Logları getir
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'log_type'   => null,
			'status'     => null,
			'product_id' => null,
			'limit'      => 50,
			'offset'     => 0,
			'order'      => 'DESC',
			'orderby'    => 'created_at',
		);

		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'created_at', 'updated_at', 'status', 'log_type', 'product_id' );
		$allowed_order   = array( 'ASC', 'DESC' );

		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] );
		$order   = in_array( $order, $allowed_order, true ) ? $order : 'DESC';
		$limit   = max( 1, intval( $args['limit'] ) );
		$offset  = max( 0, intval( $args['offset'] ) );

		$query  = "SELECT * FROM {$this->table_name} WHERE 1=1";
		$params = array();

		if ( $args['log_type'] ) {
			$query    .= ' AND log_type = %s';
			$params[] = $args['log_type'];
		}

		if ( $args['status'] ) {
			$query    .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( $args['product_id'] ) {
			$query    .= ' AND product_id = %d';
			$params[] = intval( $args['product_id'] );
		}

		$query .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		$prepared = $wpdb->prepare( $query, $params );

		return $wpdb->get_results( $prepared );
	}

	/**
	 * Log sayısını getir
	 */
	public function get_logs_count( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'log_type'   => null,
			'status'     => null,
			'product_id' => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$query  = "SELECT COUNT(*) as count FROM {$this->table_name} WHERE 1=1";
		$params = array();

		if ( $args['log_type'] ) {
			$query    .= ' AND log_type = %s';
			$params[] = $args['log_type'];
		}

		if ( $args['status'] ) {
			$query    .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( $args['product_id'] ) {
			$query    .= ' AND product_id = %d';
			$params[] = intval( $args['product_id'] );
		}

		$prepared = ! empty( $params ) ? $wpdb->prepare( $query, $params ) : $query;
		$result   = $wpdb->get_row( $prepared );

		return $result->count ?? 0;
	}

	/**
	 * Loglara göre istatistik getir
	 */
	public function get_statistics() {
		global $wpdb;

		return array(
			'total_imports'      => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE log_type = 'import'" ),
			'successful_imports' => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE log_type = 'import' AND status = 'success'" ),
			'failed_imports'     => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE log_type = 'import' AND status = 'error'" ),
			'total_syncs'        => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE log_type = 'sync'" ),
			'successful_syncs'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE log_type = 'sync' AND status = 'success'" ),
			'failed_syncs'       => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE log_type = 'sync' AND status = 'error'" ),
		);
	}

	/**
	 * Eski logları sil
	 */
	public function cleanup_old_logs( $days = 30 ) {
		global $wpdb;

		$date = date( 'Y-m-d H:i:s', strtotime( '-' . intval( $days ) . ' days' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE created_at < %s",
				$date
			)
		);
	}
}