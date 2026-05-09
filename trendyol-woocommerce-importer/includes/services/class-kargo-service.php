<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Kargo_Service {

	public function normalize_category_name( $txt ) {
		$txt = mb_strtolower( trim( (string) $txt ), 'UTF-8' );
		$txt = str_replace( array( '-', '_' ), ' ', $txt );
		$txt = preg_replace( '/\s+/', ' ', $txt );
		$txt = strtr(
			$txt,
			array(
				'ı' => 'i',
				'i' => 'i',
				'ü' => 'u',
				'ö' => 'o',
				'ş' => 's',
				'ç' => 'c',
				'ğ' => 'g',
				'â' => 'a',
				'î' => 'i',
				'û' => 'u',
			)
		);

		return trim( $txt );
	}

	public function get_categories_from_file() {
		$data_dir    = dirname( dirname( __DIR__ ) ) . '/data/';
		$catfile     = $data_dir . 'kategoriler.txt';
		$kategoriler = array();

		if ( file_exists( $catfile ) ) {
			foreach ( file( $catfile, FILE_IGNORE_NEW_LINES ) as $line ) {
				if ( false !== strpos( $line, '|' ) ) {
					list( $isim ) = explode( '|', $line, 2 );
					$kategoriler[] = trim( $isim );
				}
			}
		}

		return $kategoriler;
	}

	public function save_kargo_settings( $post_data ) {
		$kategoriler   = $this->get_categories_from_file();
		$yeni_kargo    = array();

		foreach ( $kategoriler as $isim ) {
			$key_norm = $this->normalize_category_name( $isim );
			$kargo    = isset( $post_data['kargo'][ $isim ] ) ? floatval( str_replace( ',', '.', $post_data['kargo'][ $isim ] ) ) : 0;
			$marj     = isset( $post_data['marj'][ $isim ] ) ? floatval( str_replace( ',', '.', $post_data['marj'][ $isim ] ) ) : 1.0;

			$yeni_kargo[ $key_norm ] = array(
				'kargo' => $kargo,
				'marj'  => $marj,
			);
		}

		update_option( 'trendyol_kargo_maliyetleri', $yeni_kargo );

		if ( isset( $post_data['default_kargo'] ) ) {
			update_option( 'trendyol_default_kargo', floatval( str_replace( ',', '.', $post_data['default_kargo'] ) ) );
		}

		if ( isset( $post_data['default_marj'] ) ) {
			update_option( 'trendyol_default_marj', floatval( str_replace( ',', '.', $post_data['default_marj'] ) ) );
		}

		return true;
	}

	public function save_manual_euro_rate( $rate ) {
		if ( function_exists( 'set_trendyol_euro_kuru' ) ) {
			set_trendyol_euro_kuru( $rate );
		}

		return true;
	}

	public function refresh_auto_euro_rate() {
		$otomatik_kur = function_exists( 'get_tcmb_euro_kuru' ) ? get_tcmb_euro_kuru() : false;

		if ( $otomatik_kur && function_exists( 'set_trendyol_euro_kuru' ) ) {
			set_trendyol_euro_kuru( $otomatik_kur );
			return $otomatik_kur;
		}

		return false;
	}

	public function save_manual_bam_rate( $rate ) {
		if ( function_exists( 'set_trendyol_bam_kuru' ) ) {
			set_trendyol_bam_kuru( $rate );
		}

		return true;
	}

	public function save_currency_setting( $currency ) {
		$currency = sanitize_key( $currency );
		if ( in_array( $currency, array( 'rsd', 'bam', 'eur' ), true ) ) {
			update_option( 'trendyol_price_currency', $currency );
			return true;
		}

		return false;
	}
}