<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Title_Bulk_Update_Service {

	public function update_draft_titles() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$urunler     = get_posts( $args );
		$guncellenen = 0;

		foreach ( $urunler as $urun_id ) {
			$sku = get_post_meta( $urun_id, '_sku', true );
			if ( empty( $sku ) ) {
				$sku = $urun_id;
			}

			$cats     = wp_get_post_terms( $urun_id, 'product_cat', array( 'fields' => 'names' ) );
			$cat_name = ! empty( $cats ) ? $cats[0] : 'Urun';

			$yeni_baslik = $cat_name . ' #' . $sku;

			wp_update_post(
				array(
					'ID'         => $urun_id,
					'post_title' => $yeni_baslik,
				)
			);

			$guncellenen++;
		}

		return $guncellenen;
	}

	public function update_draft_categories( $new_category_id ) {
		$new_category_id = intval( $new_category_id );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$urunler     = get_posts( $args );
		$guncellenen = 0;

		foreach ( $urunler as $urun_id ) {
			wp_set_post_terms( $urun_id, array( $new_category_id ), 'product_cat', false );
			$guncellenen++;
		}

		$catobj   = get_term( $new_category_id, 'product_cat' );
		$cat_name = $catobj ? $catobj->name : '';

		return array(
			'count'    => $guncellenen,
			'cat_name' => $cat_name,
		);
	}

	public function get_trendyol_product_counts() {
		global $wpdb;

		$draft_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type='product'
			AND p.post_status='draft'
			AND pm.meta_key='trendyol_product_url'
			AND pm.meta_value != ''"
		);

		$publish_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type='product'
			AND p.post_status='publish'
			AND pm.meta_key='trendyol_product_url'
			AND pm.meta_value != ''"
		);

		return array(
			'draft'   => $draft_count,
			'publish' => $publish_count,
		);
	}
}