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

		if ( is_wp_error( $count ) ) {
			return $count;
		}

		return array(
			'name'      => $name,
			'file'      => basename( $dst ),
			'count'     => $count,
			'maxpages'  => $maxpages,
			'full_path' => $dst,
		);
	}

	private function get_trendyol_html( $url ) {
		if ( class_exists( 'Trendyol_Scraper' ) ) {
			$scraper = new Trendyol_Scraper( $url );
			return $scraper->fetch_page();
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 35,
				'redirection' => 5,
				'httpversion' => '1.1',
				'headers'     => array(
					'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
					'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
					'Accept-Language'           => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
					'Cache-Control'             => 'no-cache',
					'Pragma'                    => 'no-cache',
					'Upgrade-Insecure-Requests' => '1',
					'Referer'                   => 'https://www.trendyol.com/',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( 200 !== $status || empty( $body ) || strlen( $body ) < 1000 ) {
			return new WP_Error( 'category_fetch_failed', sprintf( 'Kategori sayfası alınamadı. HTTP: %d', $status ) );
		}

		return $body;
	}

	private function fetch_product_links( $category_url, $filename, $maxpages = 50 ) {
		$all_links    = array();

		for ( $i = 1; $i <= $maxpages; $i++ ) {
			$url  = add_query_arg( 'pi', $i, $category_url );
			$html = $this->get_trendyol_html( $url );

			if ( is_wp_error( $html ) ) {
				if ( 1 === $i ) {
					return new WP_Error( 'category_fetch_failed', $html->get_error_message() );
				}

				break;
			}

			$links = $this->extract_product_links_from_html( $html );

			if ( ! empty( $links ) ) {
				foreach ( $links as $link ) {
					$all_links[ $link ] = true;
				}
			}

			if ( empty( $links ) ) {
				if ( 1 === $i ) {
					return new WP_Error( 'category_links_not_found', 'Kategori sayfasında Trendyol ürün linki bulunamadı.' );
				}

				break;
			}

			usleep( 300 * 1000 );
		}

		if ( false === file_put_contents( $filename, implode( "\n", array_keys( $all_links ) ) ) ) {
			return new WP_Error( 'category_file_write_failed', 'Ürün linkleri dosyaya yazılamadı.' );
		}

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

		foreach ( $this->extract_json_ld_product_links( $html ) as $link ) {
			$links[ $link ] = true;
		}

		return array_keys( $links );
	}

	private function extract_json_ld_product_links( $html ) {
		$links = array();
		$html  = (string) $html;

		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches ) ) {
			foreach ( $matches[1] as $json_ld ) {
				$json_ld = trim( html_entity_decode( (string) $json_ld, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

				if ( '' === $json_ld ) {
					continue;
				}

				$decoded = json_decode( $json_ld, true );

				if ( JSON_ERROR_NONE === json_last_error() ) {
					foreach ( $this->collect_product_urls_from_schema( $decoded ) as $url ) {
						$links[ $url ] = true;
					}
				}

				if ( preg_match_all( '#"url"\s*:\s*"((?:https?:)?\\\\?/\\\\?/(?:www\\\\?\.)?trendyol\\\\?\.com/[^"]+?-p-\d+|https?://(?:www\.)?trendyol\.com/[^"]+?-p-\d+)"#i', $json_ld, $url_matches ) ) {
					foreach ( $url_matches[1] as $url ) {
						$normalized = $this->normalize_product_link( $url );

						if ( $normalized ) {
							$links[ $normalized ] = true;
						}
					}
				}
			}
		}

		return array_keys( $links );
	}

	private function collect_product_urls_from_schema( $data ) {
		$links = array();

		if ( ! is_array( $data ) ) {
			return $links;
		}

		$type = isset( $data['@type'] ) ? $data['@type'] : '';
		$url  = isset( $data['url'] ) ? $data['url'] : '';

		if ( is_string( $type ) ) {
			$type = array( $type );
		}

		if ( is_array( $type ) ) {
			$is_supported = in_array( 'Product', $type, true ) || in_array( 'ItemList', $type, true );

			if ( $is_supported && is_string( $url ) ) {
				$normalized = $this->normalize_product_link( $url );

				if ( $normalized ) {
					$links[ $normalized ] = true;
				}
			}
		}

		if ( isset( $data['itemListElement'] ) && is_array( $data['itemListElement'] ) ) {
			foreach ( $data['itemListElement'] as $item ) {
				if ( is_array( $item ) && isset( $item['url'] ) && is_string( $item['url'] ) ) {
					$normalized = $this->normalize_product_link( $item['url'] );

					if ( $normalized ) {
						$links[ $normalized ] = true;
					}
				}
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				foreach ( $this->collect_product_urls_from_schema( $value ) as $url_value ) {
					$links[ $url_value ] = true;
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
