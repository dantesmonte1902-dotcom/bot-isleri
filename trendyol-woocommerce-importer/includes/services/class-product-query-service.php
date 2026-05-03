<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Product_Query_Service {

	public function get_trendyol_product_ids( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'statuses' => array( 'draft', 'publish' ),
			'limit'    => 0,
			'offset'   => 0,
		);

		$args     = wp_parse_args( $args, $defaults );
		$statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $args['statuses'] ) ) );
		$limit    = max( 0, intval( $args['limit'] ) );
		$offset   = max( 0, intval( $args['offset'] ) );

		if ( empty( $statuses ) ) {
			$statuses = array( 'draft', 'publish' );
		}

		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status IN ($status_placeholders)
			AND pm.meta_key = 'trendyol_product_url'
			AND pm.meta_value != ''
			ORDER BY p.ID ASC
		";

		$params = $statuses;

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		$prepared = $wpdb->prepare( $sql, $params );

		return array_map( 'intval', (array) $wpdb->get_col( $prepared ) );
	}

	public function get_imported_today_count() {
		global $wpdb;

		$today_start = current_time( 'Y-m-d 00:00:00' );
		$today_end   = current_time( 'Y-m-d 23:59:59' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'product'
				AND pm.meta_key = 'trendyol_product_url'
				AND pm.meta_value != ''
				AND p.post_date BETWEEN %s AND %s",
				$today_start,
				$today_end
			)
		);

		return intval( $count );
	}

	public function get_total_imported_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id)
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'trendyol_product_url'
			AND meta_value != ''"
		);

		return intval( $count );
	}
}