<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Trendyol_Title_AI_Update_Service {

const DEFAULT_MODEL           = 'gemini-2.5-flash';
const DEFAULT_OUTPUT_LANGUAGE = 'Boşnakça';

private $product_query_service;
private $logger;
private $request_timestamps = array();

public function __construct() {
$this->product_query_service = new Trendyol_Product_Query_Service();
$this->logger                = new Trendyol_Logger();
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

public function get_provider_labels() {
return array(
'gemini'     => 'Gemini',
'openrouter' => 'OpenRouter',
'custom'     => 'Custom AI',
'none'       => __( 'Kapalı', 'trendyol-woocommerce-importer' ),
);
}

public function get_provider_statuses() {
$settings = $this->get_runtime_settings();
$statuses = array();

foreach ( array( 'gemini', 'openrouter', 'custom' ) as $provider_key ) {
$provider = Trendyol_AI_Provider_Factory::create( $provider_key, $settings );
$statuses[ $provider_key ] = $provider ? $provider->is_configured() : false;
}

return $statuses;
}

public function has_any_configured_provider() {
$statuses = $this->get_provider_statuses();

foreach ( $statuses as $status ) {
if ( $status ) {
return true;
}
}

return false;
}

public function get_runtime_settings( $overrides = array() ) {
$settings = array(
'ai_provider'                => sanitize_key( (string) Trendyol_Settings::get( 'ai_provider', 'gemini' ) ),
'ai_fallback_provider'       => sanitize_key( (string) Trendyol_Settings::get( 'ai_fallback_provider', 'none' ) ),
'ai_batch_enabled'           => intval( Trendyol_Settings::get( 'ai_batch_enabled', 0 ) ),
'ai_default_processing_mode' => sanitize_key( (string) Trendyol_Settings::get( 'ai_default_processing_mode', 'single' ) ),
'ai_batch_size'              => intval( Trendyol_Settings::get( 'ai_batch_size', 10 ) ),
'ai_retry_limit'             => intval( Trendyol_Settings::get( 'ai_retry_limit', 2 ) ),
'ai_request_pause_seconds'   => intval( Trendyol_Settings::get( 'ai_request_pause_seconds', 12 ) ),
'ai_requests_per_minute'     => intval( Trendyol_Settings::get( 'ai_requests_per_minute', 5 ) ),
'ai_output_language'         => sanitize_text_field( (string) Trendyol_Settings::get( 'ai_output_language', self::DEFAULT_OUTPUT_LANGUAGE ) ),
'gemini_api_key'             => (string) Trendyol_Settings::get( 'gemini_api_key', '' ),
'gemini_model'               => sanitize_text_field( (string) Trendyol_Settings::get( 'gemini_model', self::DEFAULT_MODEL ) ),
'gemini_title_prompt'        => sanitize_textarea_field( (string) Trendyol_Settings::get( 'gemini_title_prompt', '' ) ),
'gemini_title_max_length'    => intval( Trendyol_Settings::get( 'gemini_title_max_length', 110 ) ),
'openrouter_api_key'         => (string) Trendyol_Settings::get( 'openrouter_api_key', '' ),
'openrouter_model'           => sanitize_text_field( (string) Trendyol_Settings::get( 'openrouter_model', '' ) ),
'custom_ai_api_url'          => esc_url_raw( (string) Trendyol_Settings::get( 'custom_ai_api_url', '' ) ),
'custom_ai_api_key'          => (string) Trendyol_Settings::get( 'custom_ai_api_key', '' ),
'custom_ai_model'            => sanitize_text_field( (string) Trendyol_Settings::get( 'custom_ai_model', '' ) ),
);

foreach ( (array) $overrides as $key => $value ) {
$settings[ $key ] = $value;
}

$settings['ai_provider']              = in_array( $settings['ai_provider'], array( 'gemini', 'openrouter', 'custom' ), true ) ? $settings['ai_provider'] : 'gemini';
$settings['ai_fallback_provider']     = in_array( $settings['ai_fallback_provider'], array( 'none', 'gemini', 'openrouter', 'custom' ), true ) ? $settings['ai_fallback_provider'] : 'none';
$settings['ai_default_processing_mode'] = ( 1 === intval( $settings['ai_batch_enabled'] ) && 'batch' === sanitize_key( (string) $settings['ai_default_processing_mode'] ) ) ? 'batch' : 'single';
$settings['ai_batch_size']            = max( 2, min( 20, intval( $settings['ai_batch_size'] ) ) );
$settings['ai_retry_limit']           = max( 0, min( 5, intval( $settings['ai_retry_limit'] ) ) );
$settings['ai_request_pause_seconds'] = max( 0, min( 120, intval( $settings['ai_request_pause_seconds'] ) ) );
$settings['ai_requests_per_minute']   = max( 1, min( 60, intval( $settings['ai_requests_per_minute'] ) ) );
$settings['gemini_title_max_length']  = max( 40, min( 200, intval( $settings['gemini_title_max_length'] ) ) );
$settings['ai_output_language']       = '' !== $settings['ai_output_language'] ? $settings['ai_output_language'] : self::DEFAULT_OUTPUT_LANGUAGE;

return $settings;
}

public function run( $status_filter = 'draft', $limit = 10, $options = array() ) {
$settings = $this->get_runtime_settings( $options );
$statuses = $this->map_status_filter_to_statuses( $status_filter );
$limit    = max( 1, min( 500, intval( $limit ) ) );
$ids      = $this->product_query_service->get_trendyol_product_ids(
array(
'statuses' => $statuses,
'limit'    => $limit,
)
);

$processing_mode = sanitize_key( (string) ( $options['processing_mode'] ?? $settings['ai_default_processing_mode'] ) );
if ( 'batch' === $processing_mode && 1 !== intval( $settings['ai_batch_enabled'] ) ) {
$processing_mode = 'single';
}
if ( ! in_array( $processing_mode, array( 'single', 'batch' ), true ) ) {
$processing_mode = 'single';
}
$settings['ai_default_processing_mode'] = $processing_mode;
$settings['ai_batch_size']              = 'batch' === $processing_mode ? max( 2, min( 20, intval( $options['batch_size'] ?? $settings['ai_batch_size'] ) ) ) : 1;

if ( empty( $ids ) ) {
return array(
'total'          => 0,
'updated'        => 0,
'skipped'        => 0,
'failed'         => 0,
'items'          => array(),
'mode'           => $processing_mode,
'batch_size'     => $settings['ai_batch_size'],
'batches_total'  => 0,
'batches_done'   => 0,
'retries_used'   => 0,
'waited_seconds' => 0,
'api_requests'   => 0,
'providers_used' => array(),
);
}

$queue = $this->build_queue( $ids, $settings['ai_batch_size'] );

$result = array(
'total'          => count( $ids ),
'updated'        => 0,
'skipped'        => 0,
'failed'         => 0,
'items'          => array(),
'mode'           => $processing_mode,
'batch_size'     => $settings['ai_batch_size'],
'batches_total'  => count( $queue ),
'batches_done'   => 0,
'retries_used'   => 0,
'waited_seconds' => 0,
'api_requests'   => 0,
'providers_used' => array(),
);

foreach ( $queue as $batch_index => $batch_ids ) {
$posts = $this->hydrate_posts( $batch_ids );
if ( empty( $posts ) ) {
continue;
}

$batch_result = $this->process_batch( $posts, $settings, $batch_index + 1, count( $queue ) );

$result['items']          = array_merge( $result['items'], $batch_result['items'] );
$result['batches_done']  += 1;
$result['retries_used']  += intval( $batch_result['retries_used'] );
$result['waited_seconds'] += intval( $batch_result['waited_seconds'] );
$result['api_requests']  += intval( $batch_result['api_requests'] );
$result['providers_used'] = array_values( array_unique( array_merge( $result['providers_used'], $batch_result['providers_used'] ) ) );

foreach ( $batch_result['items'] as $item ) {
if ( 'updated' === $item['status'] ) {
$result['updated']++;
} elseif ( 'skipped' === $item['status'] ) {
$result['skipped']++;
} else {
$result['failed']++;
}
}
}

return $result;
}

private function build_queue( $ids, $batch_size ) {
$queue      = array();
$batch_size = max( 1, intval( $batch_size ) );

foreach ( array_chunk( array_values( array_map( 'intval', $ids ) ), $batch_size ) as $chunk ) {
if ( ! empty( $chunk ) ) {
$queue[] = $chunk;
}
}

return $queue;
}

private function hydrate_posts( $ids ) {
$posts = array();

foreach ( $ids as $product_id ) {
$post = get_post( $product_id );
if ( $post && 'product' === $post->post_type ) {
$posts[] = $post;
}
}

return $posts;
}

private function process_batch( $posts, $settings, $batch_number, $batch_total ) {
$prompt           = $this->build_batch_prompt( $posts, $settings );
$retry_limit      = intval( $settings['ai_retry_limit'] );
$providers        = $this->get_provider_order( $settings );
$attempt          = 0;
$total_wait       = 0;
$retries_used     = 0;
$api_requests     = 0;
$providers_used   = array();
$last_error       = null;

while ( $attempt <= $retry_limit ) {
foreach ( $providers as $provider_key ) {
$provider = Trendyol_AI_Provider_Factory::create( $provider_key, $settings );
if ( ! $provider || ! $provider->is_configured() ) {
$last_error = new WP_Error(
'ai_provider_not_configured',
sprintf( __( '%s yapılandırması eksik.', 'trendyol-woocommerce-importer' ), $this->get_provider_label( $provider_key ) ),
array(
'type'     => 'config',
'provider' => $provider_key,
)
);
continue;
}

$budget_wait = $this->enforce_request_budget( intval( $settings['ai_requests_per_minute'] ) );
if ( $budget_wait > 0 ) {
$total_wait += $budget_wait;
}

$providers_used[] = $provider_key;
$api_requests++;

$response = $provider->generate_text(
$prompt,
array(
'timeout'           => 60,
'max_output_tokens' => max( 200, count( $posts ) * 80 ),
)
);

if ( ! is_wp_error( $response ) ) {
$this->request_timestamps[] = time();
$items = $this->map_generated_titles_to_posts( $posts, (string) $response['text'], $settings, $provider_key, $batch_number );
$this->log_batch_result( $batch_number, $batch_total, 'success', $provider_key, $attempt, $items );

$pause_wait = $this->apply_pause_between_batches( intval( $settings['ai_request_pause_seconds'] ) );
if ( $pause_wait > 0 ) {
$total_wait += $pause_wait;
}

return array(
'items'          => $items,
'retries_used'   => $retries_used,
'waited_seconds' => $total_wait,
'api_requests'   => $api_requests,
'providers_used' => array_values( array_unique( $providers_used ) ),
);
}

$last_error = $response;
$error_data = (array) $response->get_error_data();
$this->log_batch_result( $batch_number, $batch_total, 'error', $provider_key, $attempt, array(), $response->get_error_message() );

if ( 'config' === ( $error_data['type'] ?? '' ) ) {
continue;
}

if ( ! $this->is_retryable_error( $response ) ) {
continue;
}

if ( $attempt < $retry_limit ) {
$wait_seconds = $this->determine_retry_wait( $response, intval( $settings['ai_request_pause_seconds'] ) );
$total_wait  += $this->sleep_for_seconds( $wait_seconds );
$retries_used++;
break 2;
}
}

$attempt++;
if ( $attempt > $retry_limit ) {
break;
}
}

return array(
'items'          => $this->build_failed_items_from_error( $posts, $last_error, $batch_number ),
'retries_used'   => $retries_used,
'waited_seconds' => $total_wait,
'api_requests'   => $api_requests,
'providers_used' => array_values( array_unique( $providers_used ) ),
);
}

private function get_provider_order( $settings ) {
$order = array();
$primary = sanitize_key( (string) $settings['ai_provider'] );
$fallback = sanitize_key( (string) $settings['ai_fallback_provider'] );

if ( in_array( $primary, array( 'gemini', 'openrouter', 'custom' ), true ) ) {
$order[] = $primary;
}

if ( in_array( $fallback, array( 'gemini', 'openrouter', 'custom' ), true ) && $fallback !== $primary ) {
$order[] = $fallback;
}

if ( empty( $order ) ) {
$order[] = 'gemini';
}

return $order;
}

private function build_batch_prompt( $posts, $settings ) {
$max_length  = intval( $settings['gemini_title_max_length'] );
$user_prompt = trim( (string) $settings['gemini_title_prompt'] );
$language    = $settings['ai_output_language'];
$lines       = array();

$lines[] = sprintf( 'Aşağıdaki ürünler için %s kısa e-ticaret başlığı üret.', $language );
$lines[] = '';
$lines[] = 'Kurallar:';
$lines[] = '';
$lines[] = '* Maksimum ' . $max_length . ' karakter';
$lines[] = '* SEO uyumlu';
$lines[] = '* Sadece başlık yaz';
$lines[] = '* Numaralandırmayı koru';
$lines[] = '* Açıklama yazma';
$lines[] = '* Tırnak işareti kullanma';
$lines[] = '* Ürün sırasını değiştirme';

if ( '' !== $user_prompt ) {
$lines[] = '* Ek mağaza kuralı: ' . $user_prompt;
}

$lines[] = '';

foreach ( array_values( $posts ) as $index => $post ) {
$lines[] = sprintf( '%d. %s', $index + 1, $this->build_product_input_line( $post ) );
}

$lines[] = '';
$lines[] = 'Beklenen çıktı formatı:';
$lines[] = '1. Başlık';
$lines[] = '2. Başlık';

return implode( "\n", $lines );
}

private function build_product_input_line( $post ) {
$segments = array();
$title    = sanitize_text_field( (string) $post->post_title );
$brand    = sanitize_text_field( (string) get_post_meta( $post->ID, 'trendyol_brand_name', true ) );
$category = sanitize_text_field( (string) get_post_meta( $post->ID, 'trendyol_product_category', true ) );
$content  = trim( wp_strip_all_tags( (string) $post->post_content ) );

if ( '' !== $title ) {
$segments[] = $title;
}
if ( '' !== $brand ) {
$segments[] = 'Marka: ' . $brand;
}
if ( '' !== $category ) {
$segments[] = 'Kategori: ' . $category;
}
if ( '' !== $content ) {
$segments[] = 'Açıklama: ' . $this->truncate_text( preg_replace( '/\s+/', ' ', $content ), 140 );
}

return implode( ' | ', array_filter( $segments ) );
}

private function map_generated_titles_to_posts( $posts, $raw_text, $settings, $provider_key, $batch_number ) {
$parsed_titles = $this->parse_batch_response( $raw_text, count( $posts ), intval( $settings['gemini_title_max_length'] ) );
$items         = array();

foreach ( array_values( $posts ) as $index => $post ) {
$position = $index + 1;
$title    = isset( $parsed_titles[ $position ] ) ? $parsed_titles[ $position ] : '';

if ( '' === $title ) {
$items[] = array(
'status'      => 'failed',
'title'       => $post->post_title,
'message'     => __( 'AI çıktısı ürün sırasıyla eşleştirilemedi.', 'trendyol-woocommerce-importer' ),
'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
'provider'    => $provider_key,
'batch'       => $batch_number,
'batch_index' => $position,
);
continue;
}

$items[] = $this->apply_generated_title( $post, $title, $provider_key, $batch_number, $position );
}

return $items;
}

private function parse_batch_response( $raw_text, $expected_count, $max_length ) {
$lines   = preg_split( '/\r\n|\r|\n/', (string) $raw_text );
$parsed  = array();
$fallback = array();

foreach ( (array) $lines as $line ) {
$line = trim( wp_strip_all_tags( (string) $line ) );
if ( '' === $line ) {
continue;
}

if ( preg_match( '/^(\d+)[\.)\-:]\s*(.+)$/u', $line, $matches ) ) {
$position = intval( $matches[1] );
if ( $position >= 1 && $position <= $expected_count ) {
$parsed[ $position ] = $this->sanitize_generated_title( $matches[2], $max_length );
continue;
}
}

$fallback[] = $this->sanitize_generated_title( $line, $max_length );
}

if ( count( $parsed ) < $expected_count && count( $fallback ) >= $expected_count ) {
for ( $i = 1; $i <= $expected_count; $i++ ) {
if ( empty( $parsed[ $i ] ) && ! empty( $fallback[ $i - 1 ] ) ) {
$parsed[ $i ] = $fallback[ $i - 1 ];
}
}
}

ksort( $parsed );
return $parsed;
}

private function apply_generated_title( $post, $generated_title, $provider_key, $batch_number, $position ) {
if ( $generated_title === $post->post_title ) {
return array(
'status'      => 'skipped',
'title'       => $post->post_title,
'message'     => __( 'AI aynı başlığı döndürdü, değişiklik yapılmadı.', 'trendyol-woocommerce-importer' ),
'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
'provider'    => $provider_key,
'batch'       => $batch_number,
'batch_index' => $position,
);
}

$updated = wp_update_post(
array(
'ID'         => $post->ID,
'post_title' => $generated_title,
),
true
);

if ( is_wp_error( $updated ) ) {
return array(
'status'      => 'failed',
'title'       => $post->post_title,
'message'     => $updated->get_error_message(),
'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
'provider'    => $provider_key,
'batch'       => $batch_number,
'batch_index' => $position,
);
}

return array(
'status'      => 'updated',
'title'       => $post->post_title,
'new_title'   => $generated_title,
'message'     => __( 'Başlık güncellendi.', 'trendyol-woocommerce-importer' ),
'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
'provider'    => $provider_key,
'batch'       => $batch_number,
'batch_index' => $position,
);
}

private function build_failed_items_from_error( $posts, $error, $batch_number ) {
$message = $error instanceof WP_Error ? $error->get_error_message() : __( 'AI batch işlemi başarısız oldu.', 'trendyol-woocommerce-importer' );
$items   = array();

foreach ( array_values( $posts ) as $index => $post ) {
$items[] = array(
'status'      => 'failed',
'title'       => $post->post_title,
'message'     => $message,
'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
'batch'       => $batch_number,
'batch_index' => $index + 1,
);
}

return $items;
}

private function is_retryable_error( $error ) {
if ( ! ( $error instanceof WP_Error ) ) {
return false;
}

$type = $error->get_error_data()['type'] ?? '';
return in_array( $type, array( 'rate_limit', 'transient' ), true );
}

private function determine_retry_wait( $error, $default_pause ) {
$retry_after = 0;
if ( $error instanceof WP_Error ) {
$error_data  = (array) $error->get_error_data();
$retry_after = intval( $error_data['retry_after'] ?? 0 );
}

if ( $retry_after > 0 ) {
return min( 120, $retry_after );
}

return max( 1, intval( $default_pause ) );
}

private function enforce_request_budget( $requests_per_minute ) {
$requests_per_minute = max( 1, intval( $requests_per_minute ) );
$now                 = time();
$window_start        = $now - 60;
$this->request_timestamps = array_values(
array_filter(
$this->request_timestamps,
function ( $timestamp ) use ( $window_start ) {
return intval( $timestamp ) > $window_start;
}
)
);

if ( count( $this->request_timestamps ) < $requests_per_minute ) {
return 0;
}

$oldest = min( $this->request_timestamps );
$wait   = max( 1, ( $oldest + 60 ) - $now );

return $this->sleep_for_seconds( $wait );
}

private function apply_pause_between_batches( $pause_seconds ) {
$pause_seconds = max( 0, intval( $pause_seconds ) );
if ( $pause_seconds < 1 ) {
return 0;
}

return $this->sleep_for_seconds( $pause_seconds );
}

private function sleep_for_seconds( $seconds ) {
$seconds = max( 0, intval( ceil( $seconds ) ) );
if ( $seconds < 1 ) {
return 0;
}

sleep( $seconds );
return $seconds;
}

private function log_batch_result( $batch_number, $batch_total, $status, $provider_key, $attempt, $items = array(), $message = '' ) {
$summary = sprintf(
'AI batch %1$d/%2$d provider=%3$s status=%4$s attempt=%5$d',
intval( $batch_number ),
intval( $batch_total ),
$provider_key,
$status,
intval( $attempt ) + 1
);

if ( '' !== $message ) {
$summary .= ' message=' . $message;
}

$this->logger->log(
'sync',
'ai_title_batch',
'success' === $status ? 'success' : 'error',
$summary,
null,
null,
null,
array(
'batch_number' => intval( $batch_number ),
'batch_total'  => intval( $batch_total ),
'provider'     => $provider_key,
'attempt'      => intval( $attempt ) + 1,
'items'        => $items,
)
);
}

private function get_provider_label( $provider_key ) {
$labels = $this->get_provider_labels();
return $labels[ $provider_key ] ?? ucfirst( $provider_key );
}

private function sanitize_generated_title( $title, $max_length ) {
$title = wp_strip_all_tags( (string) $title );
$title = preg_replace( "/^[\\s\"']+|[\\s\"']+$/u", '', $title );
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
