<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TRENDYOL_IMPORTER_PATH . 'includes/tools.php';

class Trendyol_Product_Importer {

	private $product_data;
	private $default_kargo;
	private $default_marj;

	public function __construct( $product_data ) {
		$this->product_data  = $product_data;
		$this->default_kargo = get_option( 'trendyol_default_kargo', 0 );
		$this->default_marj  = get_option( 'trendyol_default_marj', 1.3 );
	}

	public function import() {
		$product_name      = $this->product_data['name'] ?? 'Trendyol Ürünü';
		$tl_fiyat          = $this->product_data['price'] ?? 0;
		$sizes             = $this->product_data['sizes'] ?? array();
		$images            = $this->product_data['images'] ?? array();
		$content           = $this->product_data['content'] ?? '';
		$trendyol_url      = $this->product_data['url'] ?? '';
		$kategori_ad       = $this->product_data['category'] ?? '';
		$brand_name        = $this->product_data['brand'] ?? '';
		$price_type        = $this->product_data['price_type'] ?? '';
		$price_candidates  = $this->product_data['price_candidates'] ?? array();

		$product_name = trim( wp_strip_all_tags( $product_name ) );
		$tl_fiyat     = floatval( $tl_fiyat );

		if ( empty( $product_name ) ) {
			return new WP_Error( 'invalid_name', __( 'Ürün adı boş geldi.', 'trendyol-woocommerce-importer' ) );
		}

		if ( $tl_fiyat <= 0 ) {
			return new WP_Error( 'invalid_price', __( 'Ürün fiyatı geçersiz geldi.', 'trendyol-woocommerce-importer' ) );
		}

		$blocked_brand = trendyol_is_blocked_brand( $brand_name );
		if ( false !== $blocked_brand ) {
			return new WP_Error(
				'blocked_brand',
				sprintf(
					__( 'İstenmeyen marka filtresi aktif. Tespit edilen marka: %s', 'trendyol-woocommerce-importer' ),
					$blocked_brand
				)
			);
		}

		if ( ! empty( $trendyol_url ) ) {
			global $wpdb;
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key='trendyol_product_url' AND meta_value=%s LIMIT 1",
					$trendyol_url
				)
			);

			if ( $existing ) {
				return new WP_Error( 'already_exists', __( 'Bu Trendyol linkine sahip bir ürün zaten eklenmiş!', 'trendyol-woocommerce-importer' ) );
			}
		}

		$kategori_ad_norm = function_exists( 'trendyol_normalize_cat' ) ? trendyol_normalize_cat( $kategori_ad ) : sanitize_text_field( $kategori_ad );

		$euro_kur = get_trendyol_euro_kuru();
		$euro_kur = ( is_numeric( $euro_kur ) && (float) $euro_kur > 0 ) ? (float) $euro_kur : 32.0;

		$rsd_kur = get_trendyol_rsd_kuru();
		$rsd_kur = ( is_numeric( $rsd_kur ) && (float) $rsd_kur > 0 ) ? (float) $rsd_kur : 117.38;

		$new_price = trendyol_final_fiyat_rsd(
			$tl_fiyat,
			$kategori_ad_norm,
			$euro_kur,
			$rsd_kur,
			$this->default_kargo,
			$this->default_marj
		);

		if ( ! is_numeric( $new_price ) || (float) $new_price <= 0 ) {
			return new WP_Error( 'price_calc_failed', __( 'Satış fiyatı hesaplanamadı.', 'trendyol-woocommerce-importer' ) );
		}

		$trendyol_alish_rsd = 0;
		if ( $tl_fiyat > 0 && $euro_kur > 0 && $rsd_kur > 0 ) {
			$euro               = (float) $tl_fiyat / (float) $euro_kur;
			$trendyol_alish_rsd = ceil( $euro * $rsd_kur );
		}

		$product_id = wp_insert_post(
			array(
				'post_title'   => $product_name,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'product',
			),
			true
		);

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		update_post_meta( $product_id, 'trendyol_product_url', $trendyol_url );
		update_post_meta( $product_id, 'trendyol_product_category', $kategori_ad_norm );
		update_post_meta( $product_id, 'trendyol_original_price_rsd', $trendyol_alish_rsd );
		update_post_meta( $product_id, 'trendyol_original_price_tl', $tl_fiyat );

		if ( ! empty( $brand_name ) ) {
			update_post_meta( $product_id, 'trendyol_brand_name', sanitize_text_field( $brand_name ) );
		}

		if ( ! empty( $price_type ) ) {
			update_post_meta( $product_id, 'trendyol_price_type', sanitize_text_field( $price_type ) );
		}

		if ( is_array( $price_candidates ) ) {
			if ( isset( $price_candidates['basket_price'] ) && null !== $price_candidates['basket_price'] ) {
				update_post_meta( $product_id, 'trendyol_price_basket_tl', (float) $price_candidates['basket_price'] );
			}

			if ( isset( $price_candidates['discounted_price'] ) && null !== $price_candidates['discounted_price'] ) {
				update_post_meta( $product_id, 'trendyol_price_discounted_tl', (float) $price_candidates['discounted_price'] );
			}

			if ( isset( $price_candidates['regular_price'] ) && null !== $price_candidates['regular_price'] ) {
				update_post_meta( $product_id, 'trendyol_price_regular_tl', (float) $price_candidates['regular_price'] );
			}
		}

		if ( ! empty( $sizes ) && is_array( $sizes ) ) {
			$variable_result = $this->create_variable_product( $product_id, $product_name, (float) $new_price, $sizes );
			if ( is_wp_error( $variable_result ) ) {
				wp_delete_post( $product_id, true );
				return $variable_result;
			}
		} else {
			$simple_result = $this->create_simple_product( $product_id, (float) $new_price );
			if ( is_wp_error( $simple_result ) ) {
				wp_delete_post( $product_id, true );
				return $simple_result;
			}
		}

		if ( ! empty( $images ) && is_array( $images ) ) {
			$this->upload_images( $product_id, $images );
		}

		return $product_id;
	}

	private function create_simple_product( $product_id, $price ) {
		wp_set_object_terms( $product_id, 'simple', 'product_type' );
		update_post_meta( $product_id, '_regular_price', $price );
		update_post_meta( $product_id, '_price', $price );
		update_post_meta( $product_id, '_stock_status', 'instock' );
		update_post_meta( $product_id, '_manage_stock', 'no' );

		return true;
	}

	private function create_variable_product( $product_id, $product_name, $price, $sizes ) {
		if ( ! taxonomy_exists( 'pa_beden' ) ) {
			register_taxonomy(
				'pa_beden',
				'product',
				array(
					'hierarchical' => false,
					'label'        => 'Beden',
					'show_ui'      => true,
					'query_var'    => true,
					'rewrite'      => false,
				)
			);
		}

		wp_set_object_terms( $product_id, 'variable', 'product_type' );

		$size_slugs = array();

		foreach ( $sizes as $size ) {
			$size = trim( (string) $size );
			if ( '' === $size ) {
				continue;
			}

			$slug         = sanitize_title( $size );
			$size_slugs[] = $slug;

			if ( ! term_exists( $slug, 'pa_beden' ) ) {
				$term = wp_insert_term( $size, 'pa_beden', array( 'slug' => $slug ) );
				if ( is_wp_error( $term ) && 'term_exists' !== $term->get_error_code() ) {
					return $term;
				}
			}
		}

		if ( empty( $size_slugs ) ) {
			return new WP_Error( 'no_valid_sizes', __( 'Geçerli varyasyon bedeni bulunamadı.', 'trendyol-woocommerce-importer' ) );
		}

		$attribute = array(
			'name'         => 'pa_beden',
			'value'        => implode( ' | ', $size_slugs ),
			'is_visible'   => 1,
			'is_variation' => 1,
			'is_taxonomy'  => 1,
		);

		update_post_meta( $product_id, '_product_attributes', array( 'pa_beden' => $attribute ) );
		wp_set_object_terms( $product_id, $size_slugs, 'pa_beden', false );

		foreach ( $sizes as $size ) {
			$size = trim( (string) $size );
			if ( '' === $size ) {
				continue;
			}

			$slug = sanitize_title( $size );

			$variation_post = array(
				'post_title'  => $product_name . ' - ' . $size,
				'post_name'   => 'product-' . $product_id . '-variation-' . $slug,
				'post_status' => 'publish',
				'post_parent' => $product_id,
				'post_type'   => 'product_variation',
				'menu_order'  => 0,
			);

			$variation_id = wp_insert_post( $variation_post, true );

			if ( is_wp_error( $variation_id ) ) {
				return $variation_id;
			}

			update_post_meta( $variation_id, 'attribute_pa_beden', $slug );
			update_post_meta( $variation_id, '_regular_price', $price );
			update_post_meta( $variation_id, '_price', $price );
			update_post_meta( $variation_id, '_stock_status', 'instock' );
			update_post_meta( $variation_id, '_manage_stock', 'no' );
		}

		update_post_meta( $product_id, '_regular_price', $price );
		update_post_meta( $product_id, '_price', $price );

		return true;
	}

	private function upload_images( $product_id, $images ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$gallery_ids = array();

		foreach ( $images as $key => $image_url ) {
			$image_data = wp_remote_get( $image_url, array( 'timeout' => 30 ) );
			if ( is_wp_error( $image_data ) ) {
				continue;
			}

			$body = wp_remote_retrieve_body( $image_data );
			if ( empty( $body ) ) {
				continue;
			}

			$filename   = $product_id . '-' . $key . '.jpg';
			$upload_dir = wp_upload_dir();
			$file_path  = $upload_dir['path'] . '/' . $filename;

			$result = @file_put_contents( $file_path, $body );
			if ( false === $result ) {
				continue;
			}

			$attachment = array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attachment_id = wp_insert_attachment( $attachment, $file_path, $product_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file_path ) );

				if ( 0 === $key ) {
					set_post_thumbnail( $product_id, $attachment_id );
				} else {
					$gallery_ids[] = $attachment_id;
				}
			}
		}

		if ( ! empty( $gallery_ids ) ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
		}
	}
}