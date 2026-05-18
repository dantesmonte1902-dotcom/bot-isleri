<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Category_Service {

	private $data_dir;
	private $categories_file;
	private $maxpages_file;

	public function __construct() {
		$this->data_dir        = dirname( dirname( __DIR__ ) ) . '/data/';
		$this->categories_file = $this->data_dir . 'kategoriler.txt';
		$this->maxpages_file   = $this->data_dir . 'maxpages.txt';

		if ( ! is_dir( $this->data_dir ) ) {
			wp_mkdir_p( $this->data_dir );
		}
	}

	public function get_maxpages() {
		if ( file_exists( $this->maxpages_file ) ) {
			$value = intval( file_get_contents( $this->maxpages_file ) );
			if ( $value > 0 ) {
				return $value;
			}
		}

		return 50;
	}

	public function save_maxpages( $maxpages ) {
		$n = max( 1, min( 100, intval( $maxpages ) ) );
		file_put_contents( $this->maxpages_file, $n );
		return $n;
	}

	public function get_categories() {
		$categories = array();

		if ( file_exists( $this->categories_file ) ) {
			foreach ( file( $this->categories_file, FILE_IGNORE_NEW_LINES ) as $i => $line ) {
				$line = trim( $line );
				if ( $line ) {
					list( $n, $u ) = explode( '|', $line, 2 );
					$categories[] = array(
						'id'   => $i,
						'name' => $n,
						'url'  => $u,
					);
				}
			}
		}

		return $categories;
	}

	public function add_category( $name, $url ) {
		$name = trim( $name );
		$url  = trim( $url );

		if ( empty( $name ) || empty( $url ) ) {
			return new WP_Error( 'empty_fields', 'Kategori adı ve link zorunludur.' );
		}

		$lines = file_exists( $this->categories_file ) ? file( $this->categories_file, FILE_IGNORE_NEW_LINES ) : array();

		foreach ( $lines as $line ) {
			list( $n, $u ) = explode( '|', $line, 2 );
			if ( $n === $name || $u === $url ) {
				return new WP_Error( 'duplicate_category', 'Bu isimde veya linkte kategori zaten var.' );
			}
		}

		file_put_contents( $this->categories_file, $name . '|' . $url . "\n", FILE_APPEND );
		return true;
	}

	public function delete_category( $index ) {
		$index = intval( $index );
		$lines = file_exists( $this->categories_file ) ? file( $this->categories_file, FILE_IGNORE_NEW_LINES ) : array();

		if ( isset( $lines[ $index ] ) ) {
			unset( $lines[ $index ] );
			file_put_contents( $this->categories_file, implode( "\n", $lines ) . "\n" );
		}

		return true;
	}

	public function fetch_category_links( $index ) {
		$index = intval( $index );
		$lines = file_exists( $this->categories_file ) ? file( $this->categories_file, FILE_IGNORE_NEW_LINES ) : array();

		if ( ! isset( $lines[ $index ] ) ) {
			return new WP_Error( 'category_not_found', 'Kategori bulunamadı.' );
		}

		list( $name, $url ) = explode( '|', $lines[ $index ], 2 );
		$dst      = $this->data_dir . sanitize_title( $name ) . '-urunleri.txt';
		$maxpages = $this->get_maxpages();
		$count    = $this->fetch_product_links( $url, $dst, $maxpages );

		return array(
			'name'      => $name,
			'file'      => basename( $dst ),
			'count'     => $count,
			'maxpages'  => $maxpages,
			'full_path' => $dst,
		);
	}

	private function get_trendyol_html( $url ) {
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			)
		);

		$html     = curl_exec( $ch );
		$httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( false === $html || 200 !== intval( $httpcode ) ) {
			return false;
		}

		return $html;
	}

	private function fetch_product_links( $category_url, $filename, $maxpages = 50 ) {
		$all_links    = array();

		for ( $i = 1; $i <= $maxpages; $i++ ) {
			$url  = add_query_arg( 'pi', $i, $category_url );
			$html = $this->get_trendyol_html( $url );

			if ( ! $html ) {
				break;
			}

			$links = $this->extract_product_links_from_html( $html );

			if ( ! empty( $links ) ) {
				foreach ( $links as $link ) {
					$all_links[ $link ] = true;
				}
			}

			if ( empty( $links ) ) {
				break;
			}

			usleep( 300 * 1000 );
		}

		file_put_contents( $filename, implode( "\n", array_keys( $all_links ) ) );

		return count( $all_links );
	}

	private function extract_product_links_from_html( $html ) {
		$links   = array();
		$sources = array_unique(
			array(
				(string) $html,
				str_replace(
					array( '\\/', '\\u002F', '&quot;' ),
					array( '/', '/', '"' ),
					html_entity_decode( (string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
				),
			)
		);

		$patterns = array(
			'#href=(["\'])(/[^"\']+?-p-\d+)(?:\?[^"\']*)?\1#i',
			'#https?://(?:www\.)?trendyol\.com/[^"\'\\\\\s<>]+?-p-\d+#i',
			'#/[^"\'\\\\\s<>]+?-p-\d+#i',
		);

		foreach ( $sources as $source ) {
			foreach ( $patterns as $pattern ) {
				if ( ! preg_match_all( $pattern, $source, $matches ) ) {
					continue;
				}

				$candidates = isset( $matches[2] ) && ! empty( $matches[2] ) ? $matches[2] : $matches[0];

				foreach ( $candidates as $candidate ) {
					$normalized = $this->normalize_product_link( $candidate );

					if ( $normalized ) {
						$links[ $normalized ] = true;
					}
				}
			}
		}

		return array_keys( $links );
	}

	private function normalize_product_link( $url ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( '' === $url ) {
			return '';
		}

		$url = str_replace( array( '\\/', '\\u002F' ), '/', $url );

		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( 0 === strpos( $url, '/' ) ) {
			$url = 'https://www.trendyol.com' . $url;
		}

		$url = preg_replace( '#[?#].*$#', '', $url );
		$url = preg_replace( '#^https?://m\.trendyol\.com/#i', 'https://www.trendyol.com/', $url );

		if ( ! preg_match( '#^https?://(?:www\.)?trendyol\.com/.+?-p-\d+$#i', $url ) ) {
			return '';
		}

		return $url;
	}
}
