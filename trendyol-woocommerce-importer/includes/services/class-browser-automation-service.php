<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Browser_Automation_Service {

	private $data_dir;
	private $categories_file;
	private $maxpages_file;

	public function __construct() {
		$this->data_dir        = dirname( dirname( __DIR__ ) ) . '/data/';
		$this->categories_file = $this->data_dir . 'browser-kategoriler.txt';
		$this->maxpages_file   = $this->data_dir . 'browser-maxpages.txt';

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
		$value = max( 1, min( 100, intval( $maxpages ) ) );
		file_put_contents( $this->maxpages_file, $value );

		return $value;
	}

	public function get_categories() {
		$categories = array();

		if ( file_exists( $this->categories_file ) ) {
			foreach ( file( $this->categories_file, FILE_IGNORE_NEW_LINES ) as $index => $line ) {
				$line = trim( (string) $line );

				if ( '' === $line ) {
					continue;
				}

				list( $name, $url ) = explode( '|', $line, 2 );

				$categories[] = array(
					'id'   => $index,
					'name' => $name,
					'url'  => $url,
				);
			}
		}

		return $categories;
	}

	public function add_category( $name, $url ) {
		$name = trim( (string) $name );
		$url  = trim( (string) $url );

		if ( '' === $name || '' === $url ) {
			return new WP_Error( 'empty_fields', 'Kategori adı ve link zorunludur.' );
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Geçerli bir kategori linki girin.' );
		}

		$lines = file_exists( $this->categories_file ) ? file( $this->categories_file, FILE_IGNORE_NEW_LINES ) : array();

		foreach ( $lines as $line ) {
			list( $saved_name, $saved_url ) = explode( '|', $line, 2 );

			if ( $saved_name === $name || $saved_url === $url ) {
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

		return $this->collect_links_from_live_url( $name, $url, $this->get_maxpages() );
	}

	public function collect_links_from_live_url( $category_name, $source_url, $maxpages = 50 ) {
		$category_name = trim( (string) $category_name );
		$source_url    = trim( (string) $source_url );
		$maxpages      = max( 1, min( 100, intval( $maxpages ) ) );

		if ( '' === $category_name ) {
			return new WP_Error( 'empty_category_name', 'Kategori adı zorunludur.' );
		}

		if ( '' === $source_url || ! filter_var( $source_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_source_url', 'Geçerli bir kategori linki girin.' );
		}

		$links = $this->fetch_links_with_browser_automation( $source_url, $maxpages );

		if ( is_wp_error( $links ) ) {
			return $links;
		}

		return $this->save_links_to_file( $category_name, $links, $source_url, array( 'playwright' ), $maxpages );
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

		return $this->save_links_to_file( $category_name, $all_links, $source_url, $sources );
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

	private function save_links_to_file( $category_name, array $links, $source_url = '', array $source_types = array(), $maxpages = 0 ) {
		$filename = $this->data_dir . sanitize_title( $category_name ) . '-urunleri.txt';
		$content  = implode( "\n", array_values( array_unique( array_filter( $links ) ) ) ) . "\n";

		if ( false === file_put_contents( $filename, $content ) ) {
			return new WP_Error( 'write_failed', 'Ürün linkleri dosyaya yazılamadı.' );
		}

		$result = array(
			'name'         => $category_name,
			'file'         => basename( $filename ),
			'full_path'    => $filename,
			'count'        => count( array_filter( $links ) ),
			'source_url'   => $source_url,
			'source_types' => $source_types,
		);

		if ( $maxpages > 0 ) {
			$result['maxpages'] = $maxpages;
		}

		return $result;
	}

	private function fetch_links_with_browser_automation( $source_url, $maxpages ) {
		if ( ! function_exists( 'proc_open' ) ) {
			return new WP_Error( 'proc_open_missing', 'Sunucuda proc_open kapalı. Gerçek tarayıcı otomasyonu için proc_open açık olmalı.' );
		}

		$node_binary = $this->find_executable( array( 'node', '/usr/bin/node', '/usr/local/bin/node' ) );

		if ( '' === $node_binary ) {
			return new WP_Error( 'node_missing', 'Sunucuda Node.js bulunamadı. Browser automation için Node.js ve Playwright kurulmalı.' );
		}

		$temp_script = tempnam( get_temp_dir(), 'trendyol-browser-' );

		if ( false === $temp_script ) {
			return new WP_Error( 'temp_file_failed', 'Geçici Playwright dosyası oluşturulamadı.' );
		}

		$script_file = $temp_script . '.js';
		rename( $temp_script, $script_file );

		$script_contents = $this->build_runtime_playwright_script( $source_url, $maxpages );

		if ( false === file_put_contents( $script_file, $script_contents ) ) {
			@unlink( $script_file );
			return new WP_Error( 'script_write_failed', 'Playwright script dosyası yazılamadı.' );
		}

		$command = escapeshellarg( $node_binary ) . ' ' . escapeshellarg( $script_file );
		$result  = $this->run_shell_command( $command );

		@unlink( $script_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$links = json_decode( $result['stdout'], true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $links ) ) {
			return new WP_Error(
				'browser_automation_invalid_output',
				'Playwright çıktısı okunamadı. Stdout: ' . substr( trim( (string) $result['stdout'] ), 0, 500 )
			);
		}

		$normalized_links = array();

		foreach ( $links as $link ) {
			if ( ! is_string( $link ) ) {
				continue;
			}

			$normalized = $this->normalize_product_link( $link );

			if ( $normalized ) {
				$normalized_links[] = $normalized;
			}
		}

		$normalized_links = array_values( array_unique( $normalized_links ) );

		if ( empty( $normalized_links ) ) {
			return new WP_Error( 'browser_automation_no_links', 'Gerçek tarayıcı otomasyonu çalıştı ancak ürün linki bulunamadı.' );
		}

		return $normalized_links;
	}

	private function build_runtime_playwright_script( $source_url, $maxpages ) {
		$encoded_url      = wp_json_encode( (string) $source_url );
		$encoded_maxpages = (int) $maxpages;

		$template = <<<'SCRIPT'
const startUrl = __TARGET_URL__;
const maxPages = __MAX_PAGES__;

let browserLib;

try {
  browserLib = require('playwright');
} catch (playwrightError) {
  console.error('PLAYWRIGHT_MODULE_NOT_FOUND');
  console.error(playwrightError && playwrightError.message ? playwrightError.message : playwrightError);
  process.exit(2);
}

(async () => {
  const browser = await browserLib.chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width: 1440, height: 2200 },
    locale: 'tr-TR',
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
  });

  const allLinks = new Set();

  for (let pageIndex = 1; pageIndex <= maxPages; pageIndex += 1) {
    const currentUrl = new URL(startUrl);
    currentUrl.searchParams.set('pi', String(pageIndex));

    await page.goto(currentUrl.toString(), { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(2500);

    const links = await page.locator('a[href*="-p-"]').evaluateAll((anchors) =>
      anchors
        .map((anchor) => anchor.href || anchor.getAttribute('href') || '')
        .filter(Boolean)
    );

    const normalized = links
      .map((link) => String(link).split('?')[0])
      .filter((link) => /trendyol\.com\/.+-p-\d+$/i.test(link));

    if (!normalized.length) {
      break;
    }

    normalized.forEach((link) => allLinks.add(link));
  }

  console.log(JSON.stringify(Array.from(allLinks)));
  await browser.close();
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
SCRIPT;

		$template = str_replace( '__TARGET_URL__', $encoded_url, $template );

		return str_replace( '__MAX_PAGES__', (string) $encoded_maxpages, $template );
	}

	private function run_shell_command( $command ) {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process     = proc_open( $command, $descriptors, $pipes );

		if ( ! is_resource( $process ) ) {
			return new WP_Error( 'process_start_failed', 'Browser automation işlemi başlatılamadı.' );
		}

		fclose( $pipes[0] );

		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		if ( 0 !== $exit_code ) {
			$message = trim( (string) $stderr );

			if ( '' === $message ) {
				$message = trim( (string) $stdout );
			}

			if ( false !== strpos( $message, 'PLAYWRIGHT_MODULE_NOT_FOUND' ) ) {
				$message = 'Node.js bulundu ama Playwright paketi kurulu değil. Sunucuda `npm install playwright` veya eşdeğeri kurulmalı.';
			}

			return new WP_Error( 'browser_automation_failed', 'Gerçek tarayıcı otomasyonu başarısız oldu: ' . $message );
		}

		return array(
			'stdout' => (string) $stdout,
			'stderr' => (string) $stderr,
		);
	}

	private function find_executable( array $candidates ) {
		$can_shell_exec = function_exists( 'shell_exec' );

		foreach ( $candidates as $candidate ) {
			$path = trim( (string) $candidate );

			if ( '' === $path ) {
				continue;
			}

			if ( false !== strpos( $path, DIRECTORY_SEPARATOR ) ) {
				if ( is_executable( $path ) ) {
					return $path;
				}

				continue;
			}

			if ( ! $can_shell_exec ) {
				continue;
			}

			$resolved = trim( (string) shell_exec( 'command -v ' . escapeshellarg( $path ) . ' 2>/dev/null' ) );

			if ( '' !== $resolved && is_executable( $resolved ) ) {
				return $resolved;
			}
		}

		return '';
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
