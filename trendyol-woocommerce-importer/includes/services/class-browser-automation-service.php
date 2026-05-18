<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Browser_Automation_Service {

	private $data_dir;

	public function __construct() {
		$this->data_dir = dirname( dirname( __DIR__ ) ) . '/data/';

		if ( ! is_dir( $this->data_dir ) ) {
			wp_mkdir_p( $this->data_dir );
		}
	}

	public function save_collected_links( $category_name, $links_text = '', $html_text = '', $source_url = '' ) {
		$category_name = trim( (string) $category_name );
		$source_url    = trim( (string) $source_url );
		$all_links     = array();
		$sources       = array();

		if ( '' === $category_name ) {
			return new WP_Error( 'empty_category_name', 'Kategori adı zorunludur.' );
		}

		if ( '' !== trim( (string) $links_text ) ) {
			$all_links = array_merge( $all_links, $this->extract_links_from_text( $links_text ) );
			$sources[] = 'link-listesi';
		}

		if ( '' !== trim( (string) $html_text ) ) {
			$all_links = array_merge( $all_links, $this->extract_product_links_from_html( $html_text ) );
			$sources[] = 'html';
		}

		$all_links = array_values( array_unique( array_filter( $all_links ) ) );

		if ( empty( $all_links ) ) {
			return new WP_Error( 'no_links_found', 'Yapıştırılan içerikte kaydedilecek Trendyol ürün linki bulunamadı.' );
		}

		$filename = $this->data_dir . sanitize_title( $category_name ) . '-urunleri.txt';
		$content  = implode( "\n", $all_links ) . "\n";

		if ( false === file_put_contents( $filename, $content ) ) {
			return new WP_Error( 'write_failed', 'Ürün linkleri dosyaya yazılamadı.' );
		}

		return array(
			'name'         => $category_name,
			'file'         => basename( $filename ),
			'full_path'    => $filename,
			'count'        => count( $all_links ),
			'source_url'   => $source_url,
			'source_types' => $sources,
		);
	}

	public function get_playwright_script_template( $source_url = '' ) {
		$target_url = trim( (string) $source_url );

		if ( '' === $target_url ) {
			$target_url = 'https://www.trendyol.com/sr?q=ornek';
		}

		$encoded_url = wp_json_encode( $target_url );
		$template    = <<<'SCRIPT'
const { chromium } = require('playwright');

(async () => {
  const startUrl = __TARGET_URL__;
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width: 1440, height: 2200 },
    locale: 'tr-TR',
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
  });

  const allLinks = new Set();

  for (let pageIndex = 1; pageIndex <= 50; pageIndex += 1) {
    const separator = startUrl.includes('?') ? '&' : '?';
    const currentUrl = `${startUrl}${separator}pi=${pageIndex}`;

    await page.goto(currentUrl, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(2500);

    const links = await page.locator('a[href*="-p-"]').evaluateAll((anchors) =>
      anchors
        .map((anchor) => anchor.href || anchor.getAttribute('href') || '')
        .filter(Boolean)
    );

    const normalized = links
      .map((link) => link.split('?')[0])
      .filter((link) => /trendyol\\.com\\/.+-p-\\d+$/i.test(link));

    if (!normalized.length) {
      break;
    }

    normalized.forEach((link) => allLinks.add(link));
  }

  console.log(JSON.stringify(Array.from(allLinks), null, 2));
  await browser.close();
})();
SCRIPT;

		return str_replace( '__TARGET_URL__', $encoded_url, $template );
	}

	private function extract_links_from_text( $text ) {
		$text   = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text   = trim( $text );
		$links  = array();
		$parsed = json_decode( $text, true );

		if ( JSON_ERROR_NONE === json_last_error() && is_array( $parsed ) ) {
			array_walk_recursive(
				$parsed,
				function ( $value ) use ( &$links ) {
					if ( is_string( $value ) ) {
						$normalized = $this->normalize_product_link( $value );

						if ( $normalized ) {
							$links[] = $normalized;
						}
					}
				}
			);
		}

		if ( empty( $links ) ) {
			$chunks = preg_split( '/[\r\n,\s]+/', $text );

			foreach ( (array) $chunks as $chunk ) {
				$normalized = $this->normalize_product_link( $chunk );

				if ( $normalized ) {
					$links[] = $normalized;
				}
			}
		}

		return array_values( array_unique( $links ) );
	}

	private function extract_product_links_from_html( $html ) {
		$links   = array();
		$sources = $this->get_html_sources( $html );
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
						$links[] = $normalized;
					}
				}
			}
		}

		return array_values( array_unique( $links ) );
	}

	private function get_html_sources( $html ) {
		$html = (string) $html;

		return array_values(
			array_unique(
				array_filter(
					array(
						$html,
						html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
						stripslashes( $html ),
					)
				)
			)
		);
	}

	private function normalize_product_link( $candidate ) {
		$candidate = trim( html_entity_decode( (string) $candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( '' === $candidate ) {
			return '';
		}

		$candidate = str_replace( array( '\/', '\\u002F' ), array( '/', '/' ), $candidate );
		$candidate = preg_replace( '#^https?://m\.trendyol\.com/#i', 'https://www.trendyol.com/', $candidate );

		if ( 0 === strpos( $candidate, '//' ) ) {
			$candidate = 'https:' . $candidate;
		} elseif ( 0 === strpos( $candidate, '/' ) ) {
			$candidate = 'https://www.trendyol.com' . $candidate;
		}

		$candidate = preg_replace( '#[?#].*$#', '', $candidate );

		if ( ! preg_match( '#^https?://(?:www\.)?trendyol\.com/.+-p-\d+$#i', $candidate ) ) {
			return '';
		}

		return $candidate;
	}
}
