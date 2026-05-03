<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Scraper_Diagnostic_Service {

	public function analyze_url( $url, $mode = 'auto' ) {
		$url = esc_url_raw( trim( (string) $url ) );

		$allowed_modes = array( 'auto', 'standard', 'jsonld_first', 'fallback_only' );
		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			$mode = 'auto';
		}

		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'URL boş.', 'trendyol-woocommerce-importer' ) );
		}

		$scraper    = new Trendyol_Scraper( $url );
		$validation = $scraper->validate_url();

		if ( is_wp_error( $validation ) ) {
			return array(
				'url'            => $url,
				'mode'           => $mode,
				'valid_url'      => false,
				'html_ok'        => false,
				'parse_ok'       => false,
				'error_code'     => $validation->get_error_code(),
				'error_message'  => $validation->get_error_message(),
				'fields'         => array(),
				'field_sources'  => array(),
				'raw_data'       => array(),
				'html_preview'   => '',
				'notes'          => array( __( 'URL doğrulaması başarısız.', 'trendyol-woocommerce-importer' ) ),
				'suggestions'    => array(),
				'debug_signals'  => array(),
				'jsonld_blocks'  => array(),
				'regex_tests'    => array(),
				'fetch_debug'    => array(),
			);
		}

		$html        = $scraper->fetch_page();
		$fetch_debug = $scraper->get_fetch_debug();

		if ( is_wp_error( $html ) ) {
			return array(
				'url'            => $url,
				'mode'           => $mode,
				'valid_url'      => true,
				'html_ok'        => false,
				'parse_ok'       => false,
				'error_code'     => $html->get_error_code(),
				'error_message'  => $html->get_error_message(),
				'fields'         => array(),
				'field_sources'  => array(),
				'raw_data'       => array(),
				'html_preview'   => '',
				'notes'          => array( __( 'HTML çekme aşamasında hata oluştu.', 'trendyol-woocommerce-importer' ) ),
				'suggestions'    => array(
					__( 'Önce fetch aşamasını kontrol et: URL erişilebilir mi, koruma/redirect var mı?', 'trendyol-woocommerce-importer' ),
					__( 'Alt taraftaki fetch debug verisine bak: wp_remote_get mi başarısız, cURL mü başarısız?', 'trendyol-woocommerce-importer' ),
				),
				'debug_signals'  => array(),
				'jsonld_blocks'  => array(),
				'regex_tests'    => array(),
				'fetch_debug'    => $fetch_debug,
			);
		}

		$jsonld_blocks = $this->extract_jsonld_blocks( $html );
		$debug_signals = $this->extract_debug_signals( $html );
		$regex_tests   = $this->run_regex_tests( $html );
		$parsed        = $scraper->parse_page( $html, $mode );

		$result = array(
			'url'            => $url,
			'mode'           => $mode,
			'valid_url'      => true,
			'html_ok'        => true,
			'parse_ok'       => ! is_wp_error( $parsed ),
			'error_code'     => is_wp_error( $parsed ) ? $parsed->get_error_code() : '',
			'error_message'  => is_wp_error( $parsed ) ? $parsed->get_error_message() : '',
			'html_preview'   => $this->make_html_preview( $html ),
			'raw_data'       => is_wp_error( $parsed ) ? array() : $parsed,
			'fields'         => array(),
			'field_sources'  => array(),
			'notes'          => array(),
			'suggestions'    => array(),
			'debug_signals'  => $debug_signals,
			'jsonld_blocks'  => $jsonld_blocks,
			'regex_tests'    => $regex_tests,
			'fetch_debug'    => $fetch_debug,
		);

		if ( is_wp_error( $parsed ) ) {
			$result['notes'][] = __( 'Parser hata döndürdü.', 'trendyol-woocommerce-importer' );
			$result['suggestions'][] = sprintf(
				__( 'Seçilen mod: %s', 'trendyol-woocommerce-importer' ),
				$mode
			);
			return $result;
		}

		$sources  = isset( $parsed['__sources'] ) && is_array( $parsed['__sources'] ) ? $parsed['__sources'] : array();
		$name     = isset( $parsed['name'] ) ? trim( (string) $parsed['name'] ) : '';
		$price    = isset( $parsed['price'] ) ? (float) $parsed['price'] : 0;
		$category = isset( $parsed['category'] ) ? trim( (string) $parsed['category'] ) : '';
		$brand    = isset( $parsed['brand'] ) ? trim( (string) $parsed['brand'] ) : '';
		$sizes    = isset( $parsed['sizes'] ) && is_array( $parsed['sizes'] ) ? $parsed['sizes'] : array();
		$images   = isset( $parsed['images'] ) && is_array( $parsed['images'] ) ? $parsed['images'] : array();
		$content  = isset( $parsed['content'] ) ? trim( wp_strip_all_tags( (string) $parsed['content'] ) ) : '';

		$result['field_sources'] = array(
			'name'     => $sources['name'] ?? '',
			'price'    => $sources['price'] ?? '',
			'category' => $sources['category'] ?? '',
			'brand'    => $sources['brand'] ?? '',
			'sizes'    => $sources['sizes'] ?? '',
			'images'   => $sources['images'] ?? '',
			'content'  => $sources['content'] ?? '',
			'mode'     => $sources['mode'] ?? $mode,
		);

		$result['fields'] = array(
			'name' => array(
				'label'   => __( 'Ürün Başlığı', 'trendyol-woocommerce-importer' ),
				'ok'      => '' !== $name,
				'value'   => $name,
				'message' => '' !== $name ? __( 'Başlık bulundu.', 'trendyol-woocommerce-importer' ) : __( 'Başlık çekilemedi.', 'trendyol-woocommerce-importer' ),
			),
			'price' => array(
				'label'   => __( 'Fiyat', 'trendyol-woocommerce-importer' ),
				'ok'      => $price > 0,
				'value'   => $price,
				'message' => $price > 0 ? __( 'Fiyat bulundu.', 'trendyol-woocommerce-importer' ) : __( 'Fiyat çekilemedi.', 'trendyol-woocommerce-importer' ),
			),
			'category' => array(
				'label'   => __( 'Kategori', 'trendyol-woocommerce-importer' ),
				'ok'      => '' !== $category,
				'value'   => $category,
				'message' => '' !== $category ? __( 'Kategori bulundu.', 'trendyol-woocommerce-importer' ) : __( 'Kategori çekilemedi.', 'trendyol-woocommerce-importer' ),
			),
			'brand' => array(
				'label'   => __( 'Marka', 'trendyol-woocommerce-importer' ),
				'ok'      => '' !== $brand,
				'value'   => $brand,
				'message' => '' !== $brand ? __( 'Marka bulundu.', 'trendyol-woocommerce-importer' ) : __( 'Marka çekilemedi.', 'trendyol-woocommerce-importer' ),
			),
			'sizes' => array(
				'label'   => __( 'Varyant / Beden', 'trendyol-woocommerce-importer' ),
				'ok'      => ! empty( $sizes ),
				'value'   => $sizes,
				'message' => ! empty( $sizes ) ? sprintf( __( '%d varyant bulundu.', 'trendyol-woocommerce-importer' ), count( $sizes ) ) : __( 'Varyant bulunamadı.', 'trendyol-woocommerce-importer' ),
			),
			'images' => array(
				'label'   => __( 'Görseller', 'trendyol-woocommerce-importer' ),
				'ok'      => ! empty( $images ),
				'value'   => $images,
				'message' => ! empty( $images ) ? sprintf( __( '%d görsel bulundu.', 'trendyol-woocommerce-importer' ), count( $images ) ) : __( 'Görsel bulunamadı.', 'trendyol-woocommerce-importer' ),
			),
			'content' => array(
				'label'   => __( 'Ek İçerik', 'trendyol-woocommerce-importer' ),
				'ok'      => '' !== $content,
				'value'   => $content,
				'message' => '' !== $content ? __( 'Ek içerik bulundu.', 'trendyol-woocommerce-importer' ) : __( 'Ek içerik bulunamadı.', 'trendyol-woocommerce-importer' ),
			),
		);

		if ( empty( $result['notes'] ) ) {
			$result['notes'][] = sprintf(
				__( 'Analiz başarıyla tamamlandı. Seçilen mod: %s', 'trendyol-woocommerce-importer' ),
				$mode
			);
		}

		return $result;
	}

	private function make_html_preview( $html ) {
		$html = (string) $html;
		$html = wp_strip_all_tags( $html );
		$html = preg_replace( '/\s+/', ' ', $html );
		$html = trim( $html );

		if ( strlen( $html ) > 2500 ) {
			$html = substr( $html, 0, 2500 ) . '...';
		}

		return $html;
	}

	private function extract_jsonld_blocks( $html ) {
		$blocks = array();

		if ( preg_match_all( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches ) ) {
			foreach ( $matches[1] as $index => $block ) {
				$decoded = html_entity_decode( stripslashes( $block ) );
				$preview = trim( $decoded );
				if ( strlen( $preview ) > 1200 ) {
					$preview = substr( $preview, 0, 1200 ) . '...';
				}

				$blocks[] = array(
					'index'   => $index + 1,
					'preview' => $preview,
				);
			}
		}

		return $blocks;
	}

	private function extract_debug_signals( $html ) {
		return array(
			'html_length'            => strlen( (string) $html ),
			'has_product_pname'      => (bool) preg_match( '/"product_pname":"/', $html ),
			'has_price_keyword'      => (bool) preg_match( '/"price"\s*:|prc-slg|prc-dsc|discounted/i', $html ),
			'has_meta_price'         => (bool) preg_match( '/itemprop="price"/i', $html ),
			'has_jsonld'             => (bool) preg_match( '/application\/ld\+json/i', $html ),
			'has_breadcrumb_keyword' => (bool) preg_match( '/breadcrumb-link/i', $html ),
		);
	}

	private function run_regex_tests( $html ) {
		$tests = array();

		$tests['product_pname'] = array(
			'label'   => 'product_pname regex',
			'matched' => (bool) preg_match( '/"product_pname":"(.*?)"/', $html ),
			'value'   => '',
		);

		$tests['price_span'] = array(
			'label'   => 'price span regex',
			'matched' => (bool) preg_match( '/<span[^>]*class="[^"]*(?:discounted|prc-slg|prc-dsc|prc-org)[^"]*"[^>]*>([\d\.,]+)\s*TL<\/span>/i', $html ),
			'value'   => '',
		);

		$tests['price_json'] = array(
			'label'   => 'price json regex',
			'matched' => (bool) preg_match( '/"price"\s*:\s*"([\d\.]+)"/', $html ),
			'value'   => '',
		);

		$tests['price_meta'] = array(
			'label'   => 'meta price regex',
			'matched' => (bool) preg_match( '/<meta[^>]*itemprop="price"[^>]*content="([\d\.]+)"/i', $html ),
			'value'   => '',
		);

		return $tests;
	}
}