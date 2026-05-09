<?php
if ( ! function_exists( 'trendyol_normalize_cat' ) ) {
	function trendyol_normalize_cat( $txt ) {
		$txt = mb_strtolower( trim( $txt ), 'UTF-8' );
		$txt = str_replace( array( '-', '_' ), ' ', $txt );
		$txt = preg_replace( '/\s+/', ' ', $txt );
		$txt = strtr( $txt, array(
			'ı' => 'i',
			'İ' => 'i',
			'i̇' => 'i',
			'ü' => 'u',
			'ö' => 'o',
			'ş' => 's',
			'ç' => 'c',
			'ğ' => 'g',
			'â' => 'a',
			'î' => 'i',
			'û' => 'u',
		) );
		return trim( $txt );
	}
}

if ( ! function_exists( 'trendyol_normalize_variant_value' ) ) {
	function trendyol_normalize_variant_value( $txt ) {
		$txt = (string) $txt;
		$txt = html_entity_decode( $txt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$txt = trim( mb_strtolower( $txt, 'UTF-8' ) );
		$txt = strtr( $txt, array(
			'ı' => 'i',
			'İ' => 'i',
			'i̇' => 'i',
			'ü' => 'u',
			'ö' => 'o',
			'ş' => 's',
			'ç' => 'c',
			'ğ' => 'g',
			'â' => 'a',
			'î' => 'i',
			'û' => 'u',
		) );
		$txt = preg_replace( '/[^\p{L}\p{N}\s\-_]+/u', '', $txt );
		$txt = preg_replace( '/[\s\-_]+/u', '-', $txt );
		$txt = trim( $txt, '-' );
		return $txt;
	}
}

if ( ! function_exists( 'get_trendyol_euro_kuru' ) ) {
	function get_trendyol_euro_kuru() {
		$file = TRENDYOL_IMPORTER_PATH . 'data/euro_kur.txt';

		if ( file_exists( $file ) ) {
			$val = trim( (string) file_get_contents( $file ) );
			$val = str_replace( ',', '.', $val );

			if ( is_numeric( $val ) && (float) $val > 0 ) {
				return (float) $val;
			}
		}

		$val = get_option( 'trendyol_euro_kur', 53.0 );
		return ( is_numeric( $val ) && floatval( $val ) > 0 ) ? floatval( $val ) : 53.0;
	}
}

if ( ! function_exists( 'get_trendyol_rsd_kuru' ) ) {
	function get_trendyol_rsd_kuru() {
		$file = TRENDYOL_IMPORTER_PATH . 'data/rsd_kur.txt';

		if ( file_exists( $file ) ) {
			$val = trim( (string) file_get_contents( $file ) );
			$val = str_replace( ',', '.', $val );

			if ( is_numeric( $val ) && (float) $val > 0 ) {
				return (float) $val;
			}
		}

		$val = get_option( 'trendyol_rsd_kur', 117.38 );
		return ( is_numeric( $val ) && floatval( $val ) > 0 ) ? floatval( $val ) : 117.38;
	}
}

if ( ! function_exists( 'set_trendyol_euro_kuru' ) ) {
	function set_trendyol_euro_kuru( $kur ) {
		$kur = floatval( $kur );
		update_option( 'trendyol_euro_kur', $kur );
		$file = TRENDYOL_IMPORTER_PATH . 'data/euro_kur.txt';
		if ( is_writable( dirname( $file ) ) ) {
			file_put_contents( $file, (string) $kur );
		}
	}
}

if ( ! function_exists( 'set_trendyol_rsd_kuru' ) ) {
	function set_trendyol_rsd_kuru( $kur ) {
		$kur = floatval( $kur );
		update_option( 'trendyol_rsd_kur', $kur );
		$file = TRENDYOL_IMPORTER_PATH . 'data/rsd_kur.txt';
		if ( is_writable( dirname( $file ) ) ) {
			file_put_contents( $file, (string) $kur );
		}
	}
}

if ( ! function_exists( 'get_trendyol_bam_kuru' ) ) {
	function get_trendyol_bam_kuru() {
		$file = TRENDYOL_IMPORTER_PATH . 'data/bam_kur.txt';

		if ( file_exists( $file ) ) {
			$val = trim( (string) file_get_contents( $file ) );
			$val = str_replace( ',', '.', $val );

			if ( is_numeric( $val ) && (float) $val > 0 ) {
				return (float) $val;
			}
		}

		$val = get_option( 'trendyol_bam_kur', 1.93 );
		return ( is_numeric( $val ) && floatval( $val ) > 0 ) ? floatval( $val ) : 1.93;
	}
}

if ( ! function_exists( 'set_trendyol_bam_kuru' ) ) {
	function set_trendyol_bam_kuru( $kur ) {
		$kur = floatval( $kur );
		update_option( 'trendyol_bam_kur', $kur );
		$file = TRENDYOL_IMPORTER_PATH . 'data/bam_kur.txt';
		if ( is_writable( dirname( $file ) ) ) {
			file_put_contents( $file, (string) $kur );
		}
	}
}

if ( ! function_exists( 'trendyol_get_active_currency' ) ) {
	function trendyol_get_active_currency() {
		$currency = get_option( 'trendyol_price_currency', 'rsd' );
		if ( in_array( $currency, array( 'rsd', 'bam', 'eur' ), true ) ) {
			return $currency;
		}
		return 'rsd';
	}
}

if ( ! function_exists( 'trendyol_active_currency_price' ) ) {
	function trendyol_active_currency_price( $tl_fiyat, $kategori_ad, $euro_kur, $default_kargo = 0, $default_marj = 1.3 ) {
		$currency = trendyol_get_active_currency();

		if ( 'bam' === $currency ) {
			$bam_kur = get_trendyol_bam_kuru();
			return trendyol_final_fiyat_rsd( $tl_fiyat, $kategori_ad, $euro_kur, $bam_kur, $default_kargo, $default_marj );
		}

		if ( 'eur' === $currency ) {
			$tl_fiyat = floatval( str_replace( ',', '.', trim( $tl_fiyat ) ) );
			$euro_kur = floatval( str_replace( ',', '.', trim( $euro_kur ) ) );

			if ( $tl_fiyat <= 0 || $euro_kur < 0.01 ) {
				return 0;
			}

			$kargo    = $default_kargo;
			$marj     = $default_marj;
			$kat_norm = trendyol_normalize_cat( $kategori_ad );

			$arr = get_option( 'trendyol_kargo_maliyetleri', array() );
			if ( is_array( $arr ) && isset( $arr[ $kat_norm ] ) ) {
				$v = $arr[ $kat_norm ];
				if ( isset( $v['kargo'] ) ) {
					$kargo = floatval( str_replace( ',', '.', $v['kargo'] ) );
				}
				if ( isset( $v['marj'] ) ) {
					$marj = floatval( str_replace( ',', '.', $v['marj'] ) );
				}
			}

			$euro  = $tl_fiyat / $euro_kur;
			$euro2 = $euro + $kargo;
			$euro3 = $euro2 * $marj;

			return round( $euro3, 2 );
		}

		// Varsayılan: RSD
		$rsd_kur = get_trendyol_rsd_kuru();
		return trendyol_final_fiyat_rsd( $tl_fiyat, $kategori_ad, $euro_kur, $rsd_kur, $default_kargo, $default_marj );
	}
}

if ( ! function_exists( 'get_tcmb_euro_kuru' ) ) {
	function get_tcmb_euro_kuru() {
		$xml_url = 'https://www.tcmb.gov.tr/kurlar/today.xml';
		$xml     = @simplexml_load_file( $xml_url );

		if ( $xml === false ) {
			return false;
		}

		foreach ( $xml->Currency as $currency ) {
			if ( (string) $currency['CurrencyCode'] === 'EUR' ) {
				$kur = (float) str_replace( ',', '.', (string) $currency->ForexBuying );
				if ( $kur > 0 ) {
					return $kur;
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'trendyol_final_fiyat_rsd' ) ) {
	function trendyol_final_fiyat_rsd( $tl_fiyat, $kategori_ad, $euro_kur, $rsd_kur = null, $default_kargo = 0, $default_marj = 1.3 ) {
		$tl_fiyat = floatval( str_replace( ',', '.', trim( $tl_fiyat ) ) );
		$euro_kur = floatval( str_replace( ',', '.', trim( $euro_kur ) ) );

		if ( $euro_kur < 0.01 ) {
			$euro_kur = 32.0;
		}

		if ( null === $rsd_kur || ! is_numeric( $rsd_kur ) || (float) $rsd_kur <= 0 ) {
			$rsd_kur = get_trendyol_rsd_kuru();
		}

		$rsd_kur  = floatval( $rsd_kur );
		$kargo    = $default_kargo;
		$marj     = $default_marj;
		$kat_norm = trendyol_normalize_cat( $kategori_ad );

		$arr = get_option( 'trendyol_kargo_maliyetleri', array() );
		if ( is_array( $arr ) && isset( $arr[ $kat_norm ] ) ) {
			$v = $arr[ $kat_norm ];
			if ( isset( $v['kargo'] ) ) {
				$kargo = floatval( str_replace( ',', '.', $v['kargo'] ) );
			}
			if ( isset( $v['marj'] ) ) {
				$marj = floatval( str_replace( ',', '.', $v['marj'] ) );
			}
		}

		if ( $tl_fiyat <= 0 || $euro_kur <= 0 || $rsd_kur <= 0 ) {
			return 0;
		}

		$euro  = $tl_fiyat / $euro_kur;
		$euro2 = $euro + $kargo;
		$euro3 = $euro2 * $marj;
		$rsd   = $euro3 * $rsd_kur;

		return ceil( $rsd );
	}
}

if ( ! function_exists( 'trendyol_extract_variants_from_html' ) ) {
	function trendyol_extract_variants_from_html( $html ) {
		$final = array();

		if ( empty( $html ) || ! is_string( $html ) ) {
			return $final;
		}

		$decoded_html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$pattern = '/"itemNumber"\s*:\s*(\d+).*?"value"\s*:\s*"([^"]+)".*?"beautifiedValue"\s*:\s*"([^"]*)".*?"inStock"\s*:\s*(true|false)/u';

		if ( preg_match_all( $pattern, $decoded_html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$value      = isset( $m[2] ) ? (string) $m[2] : '';
				$beautified = isset( $m[3] ) ? (string) $m[3] : '';
				$in_stock   = isset( $m[4] ) && strtolower( (string) $m[4] ) === 'true';

				$norm_value      = trendyol_normalize_variant_value( $value );
				$norm_beautified = trendyol_normalize_variant_value( $beautified );

				$row = array(
					'value'           => $value,
					'beautifiedValue' => $beautified,
					'inStock'         => $in_stock,
					'norm_value'      => $norm_value,
					'norm_beautified' => $norm_beautified,
				);

				if ( ! empty( $norm_value ) ) {
					$final[ 'value:' . $norm_value ] = $row;
				}

				if ( ! empty( $norm_beautified ) ) {
					$final[ 'beauty:' . $norm_beautified ] = $row;
				}
			}
		}

		if ( empty( $final ) ) {
			$pattern2 = '/"value"\s*:\s*"([^"]+)".*?"beautifiedValue"\s*:\s*"([^"]*)".*?"inStock"\s*:\s*(true|false)/u';

			if ( preg_match_all( $pattern2, $decoded_html, $matches2, PREG_SET_ORDER ) ) {
				foreach ( $matches2 as $m ) {
					$value      = isset( $m[1] ) ? (string) $m[1] : '';
					$beautified = isset( $m[2] ) ? (string) $m[2] : '';
					$in_stock   = isset( $m[3] ) && strtolower( (string) $m[3] ) === 'true';

					$norm_value      = trendyol_normalize_variant_value( $value );
					$norm_beautified = trendyol_normalize_variant_value( $beautified );

					$row = array(
						'value'           => $value,
						'beautifiedValue' => $beautified,
						'inStock'         => $in_stock,
						'norm_value'      => $norm_value,
						'norm_beautified' => $norm_beautified,
					);

					if ( ! empty( $norm_value ) ) {
						$final[ 'value:' . $norm_value ] = $row;
					}

					if ( ! empty( $norm_beautified ) ) {
						$final[ 'beauty:' . $norm_beautified ] = $row;
					}
				}
			}
		}

		return array_values( $final );
	}
}

if ( ! function_exists( 'trendyol_normalize_text_for_match' ) ) {
	function trendyol_normalize_text_for_match( $txt ) {
		$txt = (string) $txt;
		$txt = html_entity_decode( $txt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$txt = mb_strtolower( trim( $txt ), 'UTF-8' );

		$txt = strtr( $txt, array(
			'ı' => 'i',
			'İ' => 'i',
			'i̇' => 'i',
			'ü' => 'u',
			'ö' => 'o',
			'ş' => 's',
			'ç' => 'c',
			'ğ' => 'g',
			'â' => 'a',
			'î' => 'i',
			'û' => 'u',
		) );

		return trim( $txt );
	}
}

if ( ! function_exists( 'trendyol_get_blocked_brands' ) ) {
	function trendyol_get_blocked_brands() {
		$raw = get_option( 'trendyol_blocked_brands', '' );

		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$list  = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$list[] = $line;
		}

		return array_values( array_unique( $list ) );
	}
}

if ( ! function_exists( 'trendyol_is_blocked_brand' ) ) {
	function trendyol_is_blocked_brand( $brand_name ) {
		$blocked_brands = trendyol_get_blocked_brands();

		if ( empty( $blocked_brands ) ) {
			return false;
		}

		$brand_name_norm = trendyol_normalize_text_for_match( $brand_name );

		if ( '' === $brand_name_norm ) {
			return false;
		}

		foreach ( $blocked_brands as $blocked_brand ) {
			$blocked_brand_norm = trendyol_normalize_text_for_match( $blocked_brand );

			if ( '' !== $blocked_brand_norm && $brand_name_norm === $blocked_brand_norm ) {
				return $blocked_brand;
			}
		}

		return false;
	}
}