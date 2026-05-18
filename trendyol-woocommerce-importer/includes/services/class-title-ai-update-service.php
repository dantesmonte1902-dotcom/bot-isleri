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
'gemini_title_max_length'    => intval( Trendyol_Settings::get( 'gemini_title_max_length', 160 ) ),
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
$prompt           = $this->build_prompt( $posts, $settings );
$retry_limit      = intval( $settings['ai_retry_limit'] );
$providers        = $this->get_provider_order( $settings );
$total_wait       = 0;
$retries_used     = 0;
$api_requests     = 0;
$providers_used   = array();
$last_error       = null;

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

for ( $attempt = 0; $attempt <= $retry_limit; $attempt++ ) {
$budget_wait = $this->enforce_request_budget( intval( $settings['ai_requests_per_minute'] ) );
if ( $budget_wait > 0 ) {
$total_wait += $budget_wait;
}

$providers_used[] = $provider_key;
$api_requests++;
$this->request_timestamps[] = time();

$response = $provider->generate_text(
$prompt,
array(
'timeout'           => 60,
'max_output_tokens' => max( 200, count( $posts ) * 80 ),
)
);

if ( is_wp_error( $response ) ) {
$last_error = $response;
$response_error_data = (array) $response->get_error_data();
$this->log_batch_result(
$batch_number,
$batch_total,
'error',
$provider_key,
$attempt,
array(),
$response->get_error_message(),
count( $posts ),
intval( $response_error_data['output_count'] ?? 0 )
);

if ( $this->is_retryable_error( $response ) && $attempt < $retry_limit ) {
$wait_seconds = $this->determine_retry_wait( $response, intval( $settings['ai_request_pause_seconds'] ) );
$total_wait  += $this->sleep_for_seconds( $wait_seconds );
$retries_used++;
continue;
}

break;
}

$items = 1 === count( $posts )
? $this->map_single_generated_title_to_post( reset( $posts ), (string) $response['text'], $settings, $provider_key, $batch_number )
: $this->map_generated_titles_to_posts( $posts, (string) $response['text'], $settings, $provider_key, $batch_number );

if ( is_wp_error( $items ) ) {
$last_error = $items;
$items_error_data = (array) $items->get_error_data();
$this->log_batch_result(
$batch_number,
$batch_total,
'error',
$provider_key,
$attempt,
array(),
$items->get_error_message(),
count( $posts ),
intval( $items_error_data['output_count'] ?? 0 )
);

if ( $this->is_retryable_error( $items ) && $attempt < $retry_limit ) {
$wait_seconds = $this->determine_retry_wait( $items, intval( $settings['ai_request_pause_seconds'] ) );
$total_wait  += $this->sleep_for_seconds( $wait_seconds );
$retries_used++;
continue;
}

break;
}

$this->log_batch_result( $batch_number, $batch_total, 'success', $provider_key, $attempt, $items, '', count( $posts ), count( $items ) );

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
$available = array( 'gemini', 'openrouter', 'custom' );
$order     = array();
$primary   = sanitize_key( (string) $settings['ai_provider'] );
$fallback  = sanitize_key( (string) $settings['ai_fallback_provider'] );

if ( in_array( $primary, $available, true ) ) {
$order[] = $primary;
}

if ( in_array( $fallback, $available, true ) && ! in_array( $fallback, $order, true ) ) {
$order[] = $fallback;
}

foreach ( $available as $provider_key ) {
if ( ! in_array( $provider_key, $order, true ) ) {
$order[] = $provider_key;
}
}

return $order;
}

private function build_prompt( $posts, $settings ) {
if ( 1 === count( $posts ) ) {
return $this->build_single_prompt( reset( $posts ), $settings );
}

return $this->build_batch_prompt( $posts, $settings );
}

private function build_single_prompt( $post, $settings ) {
$max_length  = intval( $settings['gemini_title_max_length'] );
$user_prompt = trim( (string) $settings['gemini_title_prompt'] );
$language    = sanitize_text_field( (string) $settings['ai_output_language'] );
$brand       = sanitize_text_field( (string) get_post_meta( $post->ID, 'trendyol_brand_name', true ) );
$category    = sanitize_text_field( (string) get_post_meta( $post->ID, 'trendyol_product_category', true ) );
$product_url = esc_url_raw( (string) get_post_meta( $post->ID, 'trendyol_product_url', true ) );
$sku         = sanitize_text_field( (string) get_post_meta( $post->ID, '_sku', true ) );
$content     = trim( wp_strip_all_tags( (string) $post->post_content ) );
$lines       = array();

$lines[] = sprintf( '%s kısa e-ticaret başlığı üret.', $language );
$lines[] = 'Kurallar:';
$lines[] = '- Maksimum ' . $max_length . ' karakter';
$lines[] = '- SEO uyumlu';
$lines[] = '- Sadece başlık yaz';
$lines[] = '- Açıklama yazma';
$lines[] = '- Tırnak işareti kullanma';

if ( '' !== $user_prompt ) {
$lines[] = '- Ek mağaza kuralı: ' . $user_prompt;
}

$lines[] = 'Ürün: ' . sanitize_text_field( (string) $post->post_title );

if ( '' !== $brand ) {
$lines[] = 'Marka: ' . $brand;
}

if ( '' !== $category ) {
$lines[] = 'Kategori: ' . $category;
}

if ( '' !== $sku ) {
$lines[] = 'SKU: ' . $sku;
}

if ( '' !== $content ) {
$lines[] = 'Açıklama: ' . $this->truncate_text( preg_replace( '/\s+/', ' ', $content ), 220 );
}

if ( '' !== $product_url ) {
$lines[] = 'Kaynak URL: ' . $product_url;
}

return implode( "\n", $lines );
}

private function build_batch_prompt( $posts, $settings ) {
$max_length  = intval( $settings['gemini_title_max_length'] );
$user_prompt = trim( (string) $settings['gemini_title_prompt'] );
$language    = $settings['ai_output_language'];
$lines       = array();
$payload     = array();

foreach ( array_values( $posts ) as $index => $post ) {
$payload[] = array(
'id'    => $index + 1,
'input' => $this->build_product_input_line( $post ),
);
}

$lines[] = sprintf( 'Aşağıdaki ürünler için %s kısa e-ticaret başlığı üret.', $language );
$lines[] = '';
$lines[] = 'Sadece geçerli JSON döndür.';
$lines[] = 'JSON formatı zorunlu olarak şu yapıda olmalı:';
$lines[] = '[';
$lines[] = '  {';
$lines[] = '    "id": 1,';
$lines[] = '    "title": "Bosnaca ürün başlığı"';
$lines[] = '  }';
$lines[] = ']';
$lines[] = '';
$lines[] = 'Kurallar:';
$lines[] = '- Kesinlikle açıklama yazma';
$lines[] = '- Markdown kullanma';
$lines[] = '- Extra text yazma';
$lines[] = '- Sadece JSON output ver';
$lines[] = '- Her ürün için tam 1 item döndür';
$lines[] = '- Toplam item sayısı girişteki ürün sayısı ile aynı olmalı';
$lines[] = '- id alanını değiştirme';
$lines[] = '- title alanı maksimum ' . $max_length . ' karakter olsun';
$lines[] = '- title SEO uyumlu kısa e-ticaret başlığı olsun';

if ( '' !== $user_prompt ) {
$lines[] = '- Ek mağaza kuralı: ' . $user_prompt;
}

$lines[] = '';
$lines[] = 'Ürünler JSON:';
$lines[] = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

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

private function map_single_generated_title_to_post( $post, $raw_text, $settings, $provider_key, $batch_number ) {
$title = $this->sanitize_generated_title( (string) $raw_text, intval( $settings['gemini_title_max_length'] ) );

if ( '' === $title ) {
return new WP_Error(
'ai_empty_response',
__( 'AI tekli başlık üretiminde boş yanıt döndürdü.', 'trendyol-woocommerce-importer' ),
array(
'type'         => 'empty_response',
'provider'     => $provider_key,
'input_count'  => 1,
'output_count' => 0,
)
);
}

return array(
$this->apply_generated_title( $post, $title, $provider_key, $batch_number, 1 ),
);
}

private function map_generated_titles_to_posts( $posts, $raw_text, $settings, $provider_key, $batch_number ) {
$post_map = array();
$items    = array();

foreach ( array_values( $posts ) as $index => $post ) {
$post_map[ $index + 1 ] = $post;
}

$parsed_titles = $this->parse_batch_json_response(
$raw_text,
array_keys( $post_map ),
intval( $settings['gemini_title_max_length'] ),
$provider_key
);

if ( is_wp_error( $parsed_titles ) ) {
return $parsed_titles;
}

foreach ( $post_map as $position => $post ) {
$items[] = $this->apply_generated_title( $post, $parsed_titles[ $position ], $provider_key, $batch_number, $position );
}

return $items;
}

private function parse_batch_json_response( $raw_text, $expected_ids, $max_length, $provider_key ) {
$expected_ids = array_values( array_map( 'intval', (array) $expected_ids ) );
$input_count  = count( $expected_ids );
$raw_text     = trim( (string) $raw_text );

if ( '' === $raw_text ) {
return $this->build_invalid_batch_response_error(
__( 'AI boş JSON çıktısı döndürdü.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
0
);
}

if ( '[' !== substr( $raw_text, 0, 1 ) ) {
return $this->build_invalid_batch_response_error(
__( 'AI yalnızca JSON dizi döndürmeliydi.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
0
);
}

$decoded = json_decode( $raw_text, true );

if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısı geçerli JSON olarak parse edilemedi.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
0
);
}

if ( count( $decoded ) !== $input_count ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısındaki item sayısı giriş ürün sayısıyla eşleşmiyor.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
count( $decoded )
);
}

$parsed_titles = array();

foreach ( $decoded as $item ) {
if ( ! is_array( $item ) || ! array_key_exists( 'id', $item ) || ! array_key_exists( 'title', $item ) ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısındaki item yapısı geçersiz.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
count( $decoded )
);
}

$item_id = intval( $item['id'] );
if ( ! in_array( $item_id, $expected_ids, true ) ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısında beklenmeyen id bulundu.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
count( $decoded )
);
}

if ( isset( $parsed_titles[ $item_id ] ) ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısında aynı id birden fazla kez döndü.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
count( $decoded )
);
}

$title = $this->sanitize_generated_title( (string) $item['title'], $max_length );
if ( '' === $title ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısındaki title alanı boş.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
count( $decoded )
);
}

$parsed_titles[ $item_id ] = $title;
}

foreach ( $expected_ids as $expected_id ) {
if ( ! isset( $parsed_titles[ $expected_id ] ) ) {
return $this->build_invalid_batch_response_error(
__( 'AI çıktısında bazı ürün id değerleri eksik.', 'trendyol-woocommerce-importer' ),
$provider_key,
$input_count,
count( $decoded )
);
}
}

ksort( $parsed_titles );

return $parsed_titles;
}

private function build_invalid_batch_response_error( $message, $provider_key, $input_count, $output_count ) {
return new WP_Error(
'ai_invalid_batch_response',
$message,
array(
'type'         => 'invalid_response',
'provider'     => $provider_key,
'input_count'  => intval( $input_count ),
'output_count' => intval( $output_count ),
)
);
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
$provider = '';

if ( $error instanceof WP_Error ) {
$error_data = (array) $error->get_error_data();
$provider   = sanitize_key( (string) ( $error_data['provider'] ?? '' ) );
}

foreach ( array_values( $posts ) as $index => $post ) {
$items[] = array(
'status'      => 'failed',
'title'       => $post->post_title,
'message'     => $message,
'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
'provider'    => $provider,
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
return in_array( $type, array( 'rate_limit', 'transient', 'invalid_response' ), true );
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

private function log_batch_result( $batch_number, $batch_total, $status, $provider_key, $attempt, $items = array(), $message = '', $input_count = 0, $output_count = 0 ) {
$summary = sprintf(
'AI batch %1$d/%2$d provider=%3$s status=%4$s attempt=%5$d input_count=%6$d output_count=%7$d',
intval( $batch_number ),
intval( $batch_total ),
$provider_key,
$status,
intval( $attempt ) + 1,
intval( $input_count ),
intval( $output_count )
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
'input_count'  => intval( $input_count ),
'output_count' => intval( $output_count ),
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
