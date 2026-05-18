<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Trendyol_Browser_Automation_Service {

const OPTION_NODE_BINARY_PATH      = 'trendyol_browser_automation_node_binary_path';
const OPTION_WORKING_DIRECTORY     = 'trendyol_browser_automation_working_directory';
const OPTION_ENABLE_MANUAL_NODE    = 'trendyol_browser_automation_enable_manual_node_path';
const OPTION_LAST_TEST_RESULT      = 'trendyol_browser_automation_last_test_result';
const MARKER_PLAYWRIGHT_OK         = 'PLAYWRIGHT_OK';
const MARKER_CHROMIUM_OK           = 'CHROMIUM_OK';
const MARKER_PLAYWRIGHT_NOT_FOUND  = 'PLAYWRIGHT_MODULE_NOT_FOUND';
const MARKER_BROWSER_LAUNCH_FAILED = 'PLAYWRIGHT_BROWSER_LAUNCH_FAILED';

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

public function get_settings() {
return array(
'node_binary_path'         => (string) get_option( self::OPTION_NODE_BINARY_PATH, '' ),
'working_directory'        => (string) get_option( self::OPTION_WORKING_DIRECTORY, '' ),
'enable_manual_node_path'  => (bool) get_option( self::OPTION_ENABLE_MANUAL_NODE, false ),
);
}

public function save_settings( array $raw_data ) {
$settings = array(
'node_binary_path'        => $this->sanitize_command_path( isset( $raw_data['browser_automation_node_binary_path'] ) ? wp_unslash( $raw_data['browser_automation_node_binary_path'] ) : '' ),
'working_directory'       => $this->sanitize_command_path( isset( $raw_data['browser_automation_working_directory'] ) ? wp_unslash( $raw_data['browser_automation_working_directory'] ) : '' ),
'enable_manual_node_path' => ! empty( $raw_data['browser_automation_enable_manual_node_path'] ),
);

update_option( self::OPTION_NODE_BINARY_PATH, $settings['node_binary_path'] );
update_option( self::OPTION_WORKING_DIRECTORY, $settings['working_directory'] );
update_option( self::OPTION_ENABLE_MANUAL_NODE, $settings['enable_manual_node_path'] );

return $settings;
}

public function get_last_test_result() {
$result = get_option( self::OPTION_LAST_TEST_RESULT, array() );

if ( ! is_array( $result ) || empty( $result['message'] ) ) {
return null;
}

$result['status']            = ( isset( $result['status'] ) && 'success' === $result['status'] ) ? 'success' : 'error';
$result['message']           = (string) $result['message'];
$result['timestamp']         = isset( $result['timestamp'] ) ? (string) $result['timestamp'] : '';
$result['node_binary']       = isset( $result['node_binary'] ) ? (string) $result['node_binary'] : '';
$result['working_directory'] = isset( $result['working_directory'] ) ? (string) $result['working_directory'] : '';

return $result;
}

public function test_node_runtime( $persist_result = true ) {
$result = $this->run_node_command( '-v' );

if ( is_wp_error( $result ) ) {
if ( $persist_result ) {
$this->save_last_test_result( 'error', $result->get_error_message() );
}

return $result;
}

$version = $this->extract_first_non_empty_line( $result['stdout'], $result['stderr'] );
$message = 'Node.js bulundu: ' . $version;

if ( $persist_result ) {
$this->save_last_test_result( 'success', $message, $result );
}

$result['message'] = $message;

return $result;
}

public function test_playwright_runtime( $persist_result = true ) {
$node_check = $this->test_node_runtime( false );

if ( is_wp_error( $node_check ) ) {
if ( $persist_result ) {
$this->save_last_test_result( 'error', $node_check->get_error_message() );
}

return $node_check;
}

$playwright_check = $this->run_node_command(
array(
'script'          => $this->get_playwright_require_script(),
'filename_prefix' => 'playwright-require-test',
)
);

if ( is_wp_error( $playwright_check ) ) {
if ( $persist_result ) {
$this->save_last_test_result( 'error', $playwright_check->get_error_message() );
}

return $playwright_check;
}

if ( false === strpos( $playwright_check['stdout'], self::MARKER_PLAYWRIGHT_OK ) ) {
$error = new WP_Error( 'playwright_test_failed', 'Playwright testi beklenen çıktıyı vermedi.' );

if ( $persist_result ) {
$this->save_last_test_result( 'error', $error->get_error_message(), $playwright_check );
}

return $error;
}

$browser_check = $this->run_node_command(
array(
'script'          => $this->get_chromium_launch_script(),
'filename_prefix' => 'playwright-chromium-test',
)
);

if ( is_wp_error( $browser_check ) ) {
if ( $persist_result ) {
$this->save_last_test_result( 'error', $browser_check->get_error_message() );
}

return $browser_check;
}

if ( false === strpos( $browser_check['stdout'], self::MARKER_CHROMIUM_OK ) ) {
$error = new WP_Error( 'chromium_test_failed', 'Chromium testi beklenen çıktıyı vermedi.' );

if ( $persist_result ) {
$this->save_last_test_result( 'error', $error->get_error_message(), $browser_check );
}

return $error;
}

$result = array(
'stdout'            => trim( $playwright_check['stdout'] . "\n" . $browser_check['stdout'] ),
'stderr'            => trim( $playwright_check['stderr'] . "\n" . $browser_check['stderr'] ),
'node_binary'       => $browser_check['node_binary'],
'working_directory' => $browser_check['working_directory'],
);
$message = sprintf( 'Node.js bulundu: %s | Playwright bulundu | Chromium çalışıyor', $this->extract_first_non_empty_line( $node_check['stdout'], $node_check['stderr'] ) );

if ( $persist_result ) {
$this->save_last_test_result( 'success', $message, $result );
}

$result['message'] = $message;

return $result;
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
  const collectRawProductLinks = async () => {
    const anchorLinks = await page.locator('a').evaluateAll((anchors) =>
      anchors.flatMap((anchor) => [anchor.href || '', anchor.getAttribute('href') || '']).filter(Boolean)
    );
    const html = await page.content();
    const absoluteMatches = html.match(/https?:\/\/(?:www\.)?trendyol\.com\/[^"'\\\s<>]+?-p-\d+[^"'\\\s<>]*/gi) || [];
    const relativeMatches = html.match(/\/[^"'\\\s<>]+?-p-\d+[^"'\\\s<>]*/gi) || [];

    return Array.from(new Set(anchorLinks.concat(absoluteMatches, relativeMatches)))
      .map((link) => String(link).trim())
      .filter((link) => link.includes('-p-'));
  };

  for (let pageIndex = 1; pageIndex <= 50; pageIndex += 1) {
    const separator = startUrl.includes('?') ? '&' : '?';
    const currentUrl = `${startUrl}${separator}pi=${pageIndex}`;

    await page.goto(currentUrl, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(2500);

    const normalized = await collectRawProductLinks();

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
$links    = array();
$sources  = $this->get_html_sources( $html );
$patterns = array(
'#href=(["\'])(/[^"\']+?-p-\d+)(?:\?[^"\']*)?\1#i',
'#https?://(?:www\.)?trendyol\.com/[^"\'\\\s<>]+?-p-\d+#i',
'#/[^"\'\\\s<>]+?-p-\d+#i',
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

$runtime_check = $this->test_playwright_runtime( false );

if ( is_wp_error( $runtime_check ) ) {
return $runtime_check;
}

$script = $this->build_runtime_playwright_script( $source_url, $maxpages );
$result = $this->run_node_command(
array(
'script'          => $script,
'filename_prefix' => 'playwright-fetch-links',
)
);

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
$template         = <<<'SCRIPT'
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
  const collectRawProductLinks = async () => {
    const anchorLinks = await page.locator('a').evaluateAll((anchors) =>
      anchors.flatMap((anchor) => [anchor.href || '', anchor.getAttribute('href') || '']).filter(Boolean)
    );
    const html = await page.content();
    const absoluteMatches = html.match(/https?:\/\/(?:www\.)?trendyol\.com\/[^"'\\\s<>]+?-p-\d+[^"'\\\s<>]*/gi) || [];
    const relativeMatches = html.match(/\/[^"'\\\s<>]+?-p-\d+[^"'\\\s<>]*/gi) || [];

    return Array.from(new Set(anchorLinks.concat(absoluteMatches, relativeMatches)))
      .map((link) => String(link).trim())
      .filter((link) => link.includes('-p-'));
  };

  for (let pageIndex = 1; pageIndex <= maxPages; pageIndex += 1) {
    const currentUrl = new URL(startUrl);
    currentUrl.searchParams.set('pi', String(pageIndex));

    await page.goto(currentUrl.toString(), { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(2500);

    const normalized = await collectRawProductLinks();

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

private function run_node_command( $command, $working_directory = '' ) {
if ( ! function_exists( 'proc_open' ) ) {
return new WP_Error( 'proc_open_missing', 'Sunucuda proc_open kapalı. Gerçek tarayıcı otomasyonu için proc_open açık olmalı.' );
}

$node_binary = $this->resolve_node_binary();

if ( is_wp_error( $node_binary ) ) {
return $node_binary;
}

$working_directory = $this->resolve_working_directory( $working_directory );

if ( is_wp_error( $working_directory ) ) {
return $working_directory;
}

$temp_script_path = '';
$command_parts    = array( $node_binary );

if ( is_array( $command ) && isset( $command['script'] ) ) {
$temp_script_path = $this->create_temporary_node_script(
(string) $command['script'],
$working_directory,
isset( $command['filename_prefix'] ) ? (string) $command['filename_prefix'] : 'browser-automation-script'
);

if ( is_wp_error( $temp_script_path ) ) {
return $temp_script_path;
}

$command_parts[] = $temp_script_path;
} else {
$command = trim( (string) $command );

if ( '' === $command ) {
return new WP_Error( 'empty_node_command', 'Çalıştırılacak Node.js komutu boş olamaz.' );
}

$command_parts[] = $command;
}

$result = $this->run_process_command( $command_parts, $working_directory );

if ( '' !== $temp_script_path ) {
$this->cleanup_temporary_node_script( $temp_script_path );
}

if ( is_wp_error( $result ) ) {
return $result;
}

$result['command']           = $this->build_command_display( $command_parts );
$result['node_binary']       = $node_binary;
$result['working_directory'] = $working_directory;

if ( 0 !== $result['exit_code'] ) {
return new WP_Error( 'browser_automation_failed', $this->get_node_command_failure_message( $result ) );
}

return $result;
}

private function run_process_command( array $command_parts, $working_directory = '' ) {
$descriptors = array(
0 => array( 'pipe', 'r' ),
1 => array( 'pipe', 'w' ),
2 => array( 'pipe', 'w' ),
);
$cwd         = '' !== $working_directory ? $working_directory : null;
$process     = proc_open( $command_parts, $descriptors, $pipes, $cwd );

if ( ! is_resource( $process ) ) {
return new WP_Error( 'process_start_failed', 'Browser automation işlemi başlatılamadı.' );
}

fclose( $pipes[0] );
$stdout    = stream_get_contents( $pipes[1] );
$stderr    = stream_get_contents( $pipes[2] );
$close_out = fclose( $pipes[1] );
$close_err = fclose( $pipes[2] );

if ( false === $close_out || false === $close_err ) {
proc_close( $process );
return new WP_Error( 'process_pipe_failed', 'Browser automation çıktı kanalları kapatılamadı.' );
}

$exit_code = proc_close( $process );

return array(
'command'    => $this->build_command_display( $command_parts ),
'stdout'     => (string) $stdout,
'stderr'     => (string) $stderr,
'exit_code'  => (int) $exit_code,
);
}

private function create_temporary_node_script( $script, $working_directory, $filename_prefix ) {
$script = (string) $script;

if ( '' === trim( $script ) ) {
return new WP_Error( 'empty_node_script', 'Geçici Node.js script içeriği boş olamaz.' );
}

$target_directory = $this->get_temporary_script_directory( $working_directory );

if ( is_wp_error( $target_directory ) ) {
return $target_directory;
}

$filename    = sanitize_file_name( $filename_prefix . '-' . wp_generate_password( 8, false, false ) . '.js' );
$script_path = trailingslashit( $target_directory ) . $filename;
$written     = file_put_contents( $script_path, $script );

if ( false === $written ) {
return new WP_Error( 'temp_script_write_failed', 'Geçici Node.js script dosyası oluşturulamadı: ' . $script_path );
}

return wp_normalize_path( $script_path );
}

private function get_temporary_script_directory( $working_directory ) {
$candidates = array();

if ( '' !== $working_directory ) {
$candidates[] = $working_directory;
}

$candidates[] = $this->data_dir;
$candidates[] = sys_get_temp_dir();

foreach ( $candidates as $candidate ) {
$candidate = wp_normalize_path( (string) $candidate );

if ( '' === $candidate || ! is_dir( $candidate ) || ! is_writable( $candidate ) ) {
continue;
}

return $candidate;
}

return new WP_Error( 'temp_script_directory_missing', 'Geçici Node.js script dosyası için yazılabilir dizin bulunamadı.' );
}

private function cleanup_temporary_node_script( $script_path ) {
$script_path = wp_normalize_path( (string) $script_path );

if ( '' !== $script_path && file_exists( $script_path ) ) {
wp_delete_file( $script_path );
}
}

private function build_command_display( array $command_parts ) {
$display_parts = array();

foreach ( $command_parts as $command_part ) {
$display_parts[] = $this->quote_command_display_part( (string) $command_part );
}

return implode( ' ', $display_parts );
}

private function quote_command_display_part( $command_part ) {
if ( '' === $command_part ) {
return '""';
}

if ( preg_match( '/[\s"\']/', $command_part ) ) {
return '"' . str_replace( '"', '\"', $command_part ) . '"';
}

return $command_part;
}

private function resolve_node_binary() {
$settings   = $this->get_settings();
$candidates = array();

if ( $settings['enable_manual_node_path'] && '' !== $settings['node_binary_path'] ) {
$candidates[] = $settings['node_binary_path'];
}

$candidates[] = 'node';
$candidates   = array_merge( $candidates, $this->get_fallback_node_executable_candidates() );
$candidates   = array_values( array_unique( array_filter( $candidates ) ) );
$node_binary  = $this->find_executable( $candidates );

if ( '' === $node_binary ) {
return new WP_Error( 'node_missing', $this->get_node_missing_message( $candidates, $settings ) );
}

return $node_binary;
}

private function resolve_working_directory( $working_directory = '' ) {
$working_directory = trim( (string) $working_directory );

if ( '' === $working_directory ) {
$settings          = $this->get_settings();
$working_directory = trim( (string) $settings['working_directory'] );
}

if ( '' === $working_directory ) {
return '';
}

$normalized = wp_normalize_path( $working_directory );

if ( ! is_dir( $normalized ) ) {
return new WP_Error( 'invalid_working_directory', 'Working Directory bulunamadı: ' . $working_directory );
}

$realpath = realpath( $normalized );

if ( false !== $realpath ) {
return wp_normalize_path( $realpath );
}

return $normalized;
}

private function get_node_command_failure_message( array $result ) {
$message = trim( (string) $result['stderr'] );

if ( '' === $message ) {
$message = trim( (string) $result['stdout'] );
}

if ( false !== strpos( $message, self::MARKER_PLAYWRIGHT_NOT_FOUND ) ) {
$message = 'Node.js bulundu ama Playwright paketi bu Node.js ortamında görünmüyor. Working Directory içinde `npm install playwright` çalıştırılmalı.';
} elseif ( false !== strpos( $message, self::MARKER_BROWSER_LAUNCH_FAILED ) || false !== strpos( $message, 'Executable doesn\'t exist' ) ) {
$message = 'Playwright bulundu ama Chromium başlatılamadı. Gerekirse aynı ortamda `npx playwright install chromium` çalıştırın.';
}

$details = array();

if ( ! empty( $result['node_binary'] ) ) {
$details[] = 'Node.js Binary: ' . $result['node_binary'];
}

if ( isset( $result['working_directory'] ) && '' !== $result['working_directory'] ) {
$details[] = 'Working Directory: ' . $result['working_directory'];
}

if ( ! empty( $details ) ) {
$message .= ' (' . implode( ' | ', $details ) . ')';
}

return 'Gerçek tarayıcı otomasyonu başarısız oldu: ' . $message;
}

private function find_executable( array $candidates ) {
foreach ( $candidates as $candidate ) {
$path = trim( (string) $candidate );

if ( '' === $path ) {
continue;
}

if ( $this->path_looks_explicit( $path ) ) {
$resolved = $this->normalize_existing_executable( $path );

if ( '' !== $resolved ) {
return $resolved;
}

continue;
}

$resolved = $this->find_executable_in_path( $path );

if ( '' !== $resolved ) {
return $resolved;
}
}

return '';
}

private function get_fallback_node_executable_candidates() {
$candidates = array(
'/usr/bin/node',
'/usr/local/bin/node',
);

if ( $this->is_windows() ) {
$program_files     = getenv( 'ProgramFiles' );
$program_files_x86 = getenv( 'ProgramFiles(x86)' );
$local_app_data    = getenv( 'LocalAppData' );

if ( $program_files ) {
$candidates[] = wp_normalize_path( trailingslashit( $program_files ) . 'nodejs/node.exe' );
}

if ( $program_files_x86 ) {
$candidates[] = wp_normalize_path( trailingslashit( $program_files_x86 ) . 'nodejs/node.exe' );
}

if ( $local_app_data ) {
$candidates[] = wp_normalize_path( trailingslashit( $local_app_data ) . 'Programs/nodejs/node.exe' );
}

$candidates[] = 'C:/Program Files/nodejs/node.exe';
$candidates[] = 'C:/Program Files (x86)/nodejs/node.exe';
}

return array_values( array_unique( array_filter( $candidates ) ) );
}

private function find_executable_in_path( $binary_name ) {
$path_env = getenv( 'PATH' );

if ( ! is_string( $path_env ) || '' === trim( $path_env ) ) {
return '';
}

$path_items = array_filter( explode( PATH_SEPARATOR, $path_env ) );
$extensions = $this->get_binary_extensions( $binary_name );

foreach ( $path_items as $path_item ) {
$path_item = trim( (string) $path_item, " \t\n\r\0\x0B\"" );

if ( '' === $path_item ) {
continue;
}

$path_item = wp_normalize_path( $path_item );

foreach ( $extensions as $extension ) {
$candidate = trailingslashit( $path_item ) . $binary_name . $extension;
$resolved  = $this->normalize_existing_executable( $candidate );

if ( '' !== $resolved ) {
return $resolved;
}
}
}

return '';
}

private function get_binary_extensions( $binary_name ) {
$extensions = array( '' );

if ( ! $this->is_windows() ) {
return $extensions;
}

if ( preg_match( '/\.[a-z0-9]+$/i', $binary_name ) ) {
return $extensions;
}

$pathext = getenv( 'PATHEXT' );

if ( is_string( $pathext ) && '' !== trim( $pathext ) ) {
$extensions = array();

foreach ( explode( ';', $pathext ) as $extension ) {
$extension = trim( strtolower( (string) $extension ) );

if ( '' !== $extension ) {
$extensions[] = $extension;
}
}
}

if ( empty( $extensions ) ) {
$extensions = array( '.exe', '.cmd', '.bat', '.com' );
}

return array_values( array_unique( $extensions ) );
}

private function normalize_existing_executable( $path ) {
$normalized = wp_normalize_path( trim( (string) $path ) );

if ( '' === $normalized || ! file_exists( $normalized ) || ! is_file( $normalized ) ) {
return '';
}

if ( $this->is_windows() ) {
return $normalized;
}

if ( is_executable( $normalized ) ) {
return $normalized;
}

return '';
}

private function path_looks_explicit( $path ) {
return false !== strpos( $path, '/' ) || false !== strpos( $path, '\\' ) || preg_match( '/^[a-zA-Z]:/', $path );
}

private function get_node_missing_message( array $node_candidates, array $settings = array() ) {
$lines   = array();
$lines[] = 'Sunucuda Node.js bulunamadı.';
$lines[] = 'Browser automation, WordPress/PHP ile AYNI makinede çalışan Node.js kurulumunu görmelidir.';

if ( ! empty( $settings['enable_manual_node_path'] ) && ! empty( $settings['node_binary_path'] ) ) {
$lines[] = 'Manuel Node.js yolu denendi: ' . $settings['node_binary_path'];
}

$lines[] = 'Kontrol edilen konumlar: ' . implode( ', ', $node_candidates );

if ( $this->is_windows() ) {
$lines[] = 'Windows localhost/XAMPP için öneri: Node.js Windows tarafına kurulmalı; sadece Git Bash veya farklı terminale kurulu olması yetmez.';
$lines[] = 'Kurulumdan sonra Apache/XAMPP yeniden başlatılmalı; çünkü Apache eski PATH ile çalışıyor olabilir.';
$lines[] = 'Kontrol: XAMPP\'nin kullandığı aynı kullanıcıyla `where node` ve `node -v` komutları çalışmalı.';
$lines[] = 'Playwright için aynı ortamda `npm install playwright` ve gerekirse `npx playwright install chromium` çalıştırılmalı.';
} else {
$lines[] = 'Node.js PATH içinde olmalı veya sunucuda erişilebilir bir mutlak yol verilmelidir.';
$lines[] = 'Playwright için aynı Node.js ortamında `npm install playwright` çalıştırılmalı.';
}

return implode( ' ', $lines );
}

private function is_windows() {
return '\\' === DIRECTORY_SEPARATOR;
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

$candidate = str_replace( array( '\\/', '\\u002F' ), array( '/', '/' ), $candidate );
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

private function sanitize_command_path( $value ) {
$value = wp_strip_all_tags( (string) $value );
$value = trim( $value );

return preg_replace( '/[\r\n\t]+/', ' ', $value );
}

private function save_last_test_result( $status, $message, array $result = array() ) {
update_option(
self::OPTION_LAST_TEST_RESULT,
array(
'status'            => 'success' === $status ? 'success' : 'error',
'message'           => (string) $message,
'timestamp'         => current_time( 'mysql' ),
'node_binary'       => isset( $result['node_binary'] ) ? (string) $result['node_binary'] : '',
'working_directory' => isset( $result['working_directory'] ) ? (string) $result['working_directory'] : '',
)
);
}

private function extract_first_non_empty_line( $stdout, $stderr = '' ) {
$lines = preg_split( '/\r\n|\r|\n/', trim( (string) $stdout . "\n" . (string) $stderr ) );

foreach ( (array) $lines as $line ) {
$line = trim( (string) $line );

if ( '' !== $line ) {
return $line;
}
}

return 'Bilinmeyen sürüm';
}

private function get_playwright_require_script() {
return <<<'SCRIPT'
try {
  require('playwright');
  console.log('PLAYWRIGHT_OK');
} catch (error) {
  console.error('PLAYWRIGHT_MODULE_NOT_FOUND');
  console.error(error && error.message ? error.message : error);
  process.exit(2);
}
SCRIPT;
}

private function get_chromium_launch_script() {
return <<<'SCRIPT'
let playwright;

try {
  playwright = require('playwright');
} catch (error) {
  console.error('PLAYWRIGHT_MODULE_NOT_FOUND');
  console.error(error && error.message ? error.message : error);
  process.exit(2);
}

(async () => {
  const browser = await playwright.chromium.launch({ headless: true });
  await browser.close();
  console.log('CHROMIUM_OK');
})().catch((error) => {
  console.error('PLAYWRIGHT_BROWSER_LAUNCH_FAILED');
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
SCRIPT;
}
}
