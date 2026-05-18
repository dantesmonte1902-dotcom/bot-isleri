<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Title_AI_Update_Service {

	const DEFAULT_MODEL = 'gemini-2.5-flash';

	private $product_query_service;

	public function __construct() {
		$this->product_query_service = new Trendyol_Product_Query_Service();
	}

	public function get_product_counts() {
		return array(
			'draft'   => $this->product_query_service->count_trendyol_products(
				array(
					'statuses' => array( 'draft' ),
				)
			),
			'publish' => $this->product_query_service->count_trendyol_products(
				array(
					'statuses' => array( 'publish' ),
				)
			),
		);
	}

	public function run( $status_filter = 'draft', $limit = 10 ) {
		$api_key = trim( (string) Trendyol_Settings::get( 'gemini_api_key', '' ) );

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'Gemini API anahtarı ayarlanmamış. Önce Ayarlar sekmesinden Gemini API Key girin.', 'trendyol-woocommerce-importer' ) );
		}

		$statuses = $this->map_status_filter_to_statuses( $status_filter );
		$limit    = max( 1, min( 100, intval( $limit ) ) );
		$ids      = $this->product_query_service->get_trendyol_product_ids(
			array(
				'statuses' => $statuses,
				'limit'    => $limit,
			)
		);

		$result = array(
			'total'   => count( $ids ),
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'items'   => array(),
		);

		foreach ( $ids as $product_id ) {
			$item            = $this->update_product_title( $product_id, $api_key );
			$result['items'][] = $item;

			if ( 'updated' === $item['status'] ) {
				$result['updated']++;
			} elseif ( 'skipped' === $item['status'] ) {
				$result['skipped']++;
			} else {
				$result['failed']++;
			}
		}

		return $result;
	}

	private function update_product_title( $product_id, $api_key ) {
		$post = get_post( $product_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return array(
				'status'  => 'failed',
				'title'   => '',
				'message' => __( 'Ürün bulunamadı.', 'trendyol-woocommerce-importer' ),
			);
		}

		$generated = $this->generate_title( $post, $api_key );

		if ( is_wp_error( $generated ) ) {
			return array(
				'status'   => 'failed',
				'title'    => $post->post_title,
				'message'  => $generated->get_error_message(),
				'edit_url' => get_edit_post_link( $product_id, 'raw' ),
			);
		}

		if ( $generated === $post->post_title ) {
			return array(
				'status'   => 'skipped',
				'title'    => $post->post_title,
				'message'  => __( 'AI aynı başlığı döndürdü, değişiklik yapılmadı.', 'trendyol-woocommerce-importer' ),
				'edit_url' => get_edit_post_link( $product_id, 'raw' ),
			);
		}

		$updated = wp_update_post(
			array(
				'ID'         => $product_id,
				'post_title' => $generated,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return array(
				'status'   => 'failed',
				'title'    => $post->post_title,
				'message'  => $updated->get_error_message(),
				'edit_url' => get_edit_post_link( $product_id, 'raw' ),
			);
		}

		return array(
			'status'    => 'updated',
			'title'     => $post->post_title,
			'new_title' => $generated,
			'message'   => __( 'Başlık güncellendi.', 'trendyol-woocommerce-importer' ),
			'edit_url'  => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	private function generate_title( $post, $api_key ) {
		$model      = sanitize_text_field( (string) Trendyol_Settings::get( 'gemini_model', self::DEFAULT_MODEL ) );
		$max_length = max( 40, min( 200, intval( Trendyol_Settings::get( 'gemini_title_max_length', 110 ) ) ) );
		$prompt     = $this->build_prompt( $post, $max_length );
		$endpoint   = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( '' !== $model ? $model : self::DEFAULT_MODEL ),
			rawurlencode( $api_key )
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
							array(
								'parts' => array(
									array(
										'text' => $prompt,
									),
								),
							),
						),
						'generationConfig' => array(
							'temperature'     => 0.4,
							'maxOutputTokens' => 120,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = __( 'Gemini isteği başarısız oldu.', 'trendyol-woocommerce-importer' );

			if ( ! empty( $body['error']['message'] ) ) {
				$message = sanitize_text_field( $body['error']['message'] );
			}

			return new WP_Error( 'gemini_request_failed', $message );
		}

		$text = '';

		if ( ! empty( $body['candidates'][0]['content']['parts'] ) && is_array( $body['candidates'][0]['content']['parts'] ) ) {
			foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
				if ( ! empty( $part['text'] ) ) {
					$text .= ' ' . $part['text'];
				}
			}
		}

		$text = $this->sanitize_generated_title( $text, $max_length );

		if ( '' === $text ) {
			return new WP_Error( 'empty_ai_title', __( 'Gemini geçerli bir başlık döndürmedi.', 'trendyol-woocommerce-importer' ) );
		}

		return $text;
	}

	private function build_prompt( $post, $max_length ) {
		$brand        = sanitize_text_field( (string) get_post_meta( $post->ID, 'trendyol_brand_name', true ) );
		$category     = sanitize_text_field( (string) get_post_meta( $post->ID, 'trendyol_product_category', true ) );
		$product_url  = esc_url_raw( (string) get_post_meta( $post->ID, 'trendyol_product_url', true ) );
		$sku          = sanitize_text_field( (string) get_post_meta( $post->ID, '_sku', true ) );
		$content      = wp_strip_all_tags( (string) $post->post_content );
		$terms        = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
		$wc_category  = ! empty( $terms ) ? sanitize_text_field( implode( ', ', $terms ) ) : '';
		$user_prompt  = sanitize_textarea_field( (string) Trendyol_Settings::get( 'gemini_title_prompt', '' ) );
		$content      = $this->truncate_text( preg_replace( '/\s+/', ' ', $content ), 500 );

		$lines   = array();
		$lines[] = 'Türkçe, satış odaklı ve doğal bir WooCommerce ürün başlığı üret.';
		$lines[] = 'Sadece tek satır başlık döndür. Açıklama, tırnak, emoji, madde işareti kullanma.';
		$lines[] = 'Başlık en fazla ' . intval( $max_length ) . ' karakter olsun.';
		$lines[] = 'Marka ve ürünün ana niteliğini mümkünse koru, gereksiz tekrar yapma.';

		if ( '' !== $user_prompt ) {
			$lines[] = 'Ek mağaza kuralı: ' . $user_prompt;
		}

		$lines[] = 'Mevcut başlık: ' . sanitize_text_field( $post->post_title );

		if ( '' !== $brand ) {
			$lines[] = 'Marka: ' . $brand;
		}

		if ( '' !== $category ) {
			$lines[] = 'Trendyol kategori: ' . $category;
		}

		if ( '' !== $wc_category ) {
			$lines[] = 'WooCommerce kategori: ' . $wc_category;
		}

		if ( '' !== $sku ) {
			$lines[] = 'SKU: ' . $sku;
		}

		if ( '' !== $content ) {
			$lines[] = 'Açıklama özeti: ' . $content;
		}

		if ( '' !== $product_url ) {
			$lines[] = 'Kaynak URL: ' . $product_url;
		}

		return implode( "\n", $lines );
	}

	private function sanitize_generated_title( $title, $max_length ) {
		$title = wp_strip_all_tags( (string) $title );
		$title = preg_replace( '/^["\'`\s]+|["\'`\s]+$/u', '', $title );
		$title = preg_replace( '/^başlık\s*:\s*/iu', '', $title );
		$title = preg_replace( '/\s+/', ' ', $title );
		$title = trim( (string) $title );

		return $this->truncate_text( $title, $max_length );
	}

	private function truncate_text( $text, $max_length ) {
		$text       = trim( (string) $text );
		$max_length = max( 1, intval( $max_length ) );

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text ) <= $max_length ) {
				return $text;
			}

			return trim( mb_substr( $text, 0, $max_length ) );
		}

		if ( strlen( $text ) <= $max_length ) {
			return $text;
		}

		return trim( substr( $text, 0, $max_length ) );
	}

	private function map_status_filter_to_statuses( $status_filter ) {
		$status_filter = sanitize_key( $status_filter );

		if ( 'publish' === $status_filter ) {
			return array( 'publish' );
		}

		if ( 'both' === $status_filter ) {
			return array( 'draft', 'publish' );
		}

		return array( 'draft' );
	}
}
