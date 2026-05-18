<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

abstract class Trendyol_AI_Provider {

protected $settings;

public function __construct( $settings = array() ) {
$this->settings = (array) $settings;
}

abstract public function get_provider_key();

abstract public function get_label();

abstract public function is_configured();

abstract public function generate_text( $prompt, $options = array() );

protected function get_setting( $key, $default = '' ) {
return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
}

protected function get_timeout( $options ) {
return max( 15, intval( $options['timeout'] ?? 60 ) );
}

protected function decode_json_body( $response ) {
$raw_body = wp_remote_retrieve_body( $response );

if ( '' === trim( (string) $raw_body ) ) {
return array();
}

$decoded = json_decode( $raw_body, true );

if ( JSON_ERROR_NONE !== json_last_error() ) {
return new WP_Error(
'ai_invalid_json',
__( 'AI sağlayıcısından geçersiz JSON döndü.', 'trendyol-woocommerce-importer' ),
array(
'type'     => 'invalid_json',
'provider' => $this->get_provider_key(),
'raw_body' => $raw_body,
)
);
}

return is_array( $decoded ) ? $decoded : array();
}

protected function build_http_error( $response, $default_message ) {
$status_code = intval( wp_remote_retrieve_response_code( $response ) );
$body        = $this->decode_json_body( $response );

if ( is_wp_error( $body ) ) {
return $body;
}

$message = $default_message;

if ( ! empty( $body['error']['message'] ) ) {
$message = sanitize_text_field( $body['error']['message'] );
} elseif ( ! empty( $body['message'] ) ) {
$message = sanitize_text_field( $body['message'] );
}

$type = 'permanent';
if ( 429 === $status_code ) {
$type = 'rate_limit';
} elseif ( $status_code >= 500 || in_array( $status_code, array( 408, 409 ), true ) ) {
$type = 'transient';
}

return new WP_Error(
'ai_request_failed',
$message,
array(
'type'        => $type,
'provider'    => $this->get_provider_key(),
'status_code' => $status_code,
'retry_after' => $this->extract_retry_after( $response, $body ),
'body'        => $body,
)
);
}

protected function build_transport_error( $error ) {
return new WP_Error(
'ai_transport_error',
$error instanceof WP_Error ? $error->get_error_message() : __( 'AI isteği başarısız oldu.', 'trendyol-woocommerce-importer' ),
array(
'type'     => 'transient',
'provider' => $this->get_provider_key(),
)
);
}

protected function extract_retry_after( $response, $body = array() ) {
$header = wp_remote_retrieve_header( $response, 'retry-after' );
if ( is_string( $header ) && '' !== trim( $header ) ) {
$header = trim( $header );
if ( is_numeric( $header ) ) {
return max( 1, (int) ceil( (float) $header ) );
}

$timestamp = strtotime( $header );
if ( false !== $timestamp ) {
return max( 1, $timestamp - time() );
}
}

$message_sources = array();
if ( ! empty( $body['error']['message'] ) ) {
$message_sources[] = (string) $body['error']['message'];
}
if ( ! empty( $body['message'] ) ) {
$message_sources[] = (string) $body['message'];
}

foreach ( $message_sources as $message ) {
if ( preg_match( '/retry\s+in\s+([0-9]+(?:\.[0-9]+)?)s/iu', $message, $matches ) ) {
return max( 1, (int) ceil( (float) $matches[1] ) );
}

if ( preg_match( '/retry[- ]after[^0-9]*([0-9]+)/iu', $message, $matches ) ) {
return max( 1, intval( $matches[1] ) );
}
}

return 0;
}
}

class Trendyol_Gemini_AI_Provider extends Trendyol_AI_Provider {

const DEFAULT_MODEL = 'gemini-2.5-flash';

public function get_provider_key() {
return 'gemini';
}

public function get_label() {
return 'Gemini';
}

public function is_configured() {
return '' !== trim( (string) $this->get_setting( 'gemini_api_key', '' ) );
}

public function generate_text( $prompt, $options = array() ) {
$api_key = trim( (string) $this->get_setting( 'gemini_api_key', '' ) );
$model   = sanitize_text_field( (string) $this->get_setting( 'gemini_model', self::DEFAULT_MODEL ) );

if ( '' === $api_key ) {
return new WP_Error(
'ai_provider_not_configured',
__( 'Gemini yapılandırması eksik.', 'trendyol-woocommerce-importer' ),
array(
'type'     => 'config',
'provider' => $this->get_provider_key(),
)
);
}

$endpoint = sprintf(
'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
rawurlencode( '' !== $model ? $model : self::DEFAULT_MODEL ),
rawurlencode( $api_key )
);

$response = wp_remote_post(
$endpoint,
array(
'timeout' => $this->get_timeout( $options ),
'headers' => array(
'Content-Type' => 'application/json',
),
'body'    => wp_json_encode(
array(
'contents'         => array(
array(
'parts' => array(
array(
'text' => (string) $prompt,
),
),
),
),
'generationConfig' => array(
'temperature'     => 0.4,
'maxOutputTokens' => max( 120, intval( $options['max_output_tokens'] ?? 512 ) ),
),
)
),
)
);

if ( is_wp_error( $response ) ) {
return $this->build_transport_error( $response );
}

$status_code = intval( wp_remote_retrieve_response_code( $response ) );
if ( $status_code < 200 || $status_code >= 300 ) {
return $this->build_http_error( $response, __( 'Gemini isteği başarısız oldu.', 'trendyol-woocommerce-importer' ) );
}

$body = $this->decode_json_body( $response );
if ( is_wp_error( $body ) ) {
return $body;
}

$text = '';
if ( ! empty( $body['candidates'][0]['content']['parts'] ) && is_array( $body['candidates'][0]['content']['parts'] ) ) {
foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
if ( ! empty( $part['text'] ) ) {
$text .= ' ' . $part['text'];
}
}
}

$text = trim( $text );
if ( '' === $text ) {
return new WP_Error(
'ai_empty_response',
__( 'Gemini geçerli bir metin döndürmedi.', 'trendyol-woocommerce-importer' ),
array(
'type'     => 'empty_response',
'provider' => $this->get_provider_key(),
)
);
}

return array(
'provider' => $this->get_provider_key(),
'text'     => $text,
);
}
}

class Trendyol_OpenAI_Compatible_AI_Provider extends Trendyol_AI_Provider {

private $provider_key;

public function __construct( $provider_key, $settings = array() ) {
$this->provider_key = sanitize_key( $provider_key );
parent::__construct( $settings );
}

public function get_provider_key() {
return $this->provider_key;
}

public function get_label() {
if ( 'openrouter' === $this->provider_key ) {
return 'OpenRouter';
}

return 'Custom AI';
}

public function is_configured() {
if ( 'openrouter' === $this->provider_key ) {
return '' !== trim( (string) $this->get_setting( 'openrouter_api_key', '' ) )
&& '' !== trim( (string) $this->get_setting( 'openrouter_model', '' ) );
}

return '' !== trim( (string) $this->get_setting( 'custom_ai_api_url', '' ) )
&& '' !== trim( (string) $this->get_setting( 'custom_ai_api_key', '' ) )
&& '' !== trim( (string) $this->get_setting( 'custom_ai_model', '' ) );
}

private function get_endpoint() {
if ( 'openrouter' === $this->provider_key ) {
return 'https://openrouter.ai/api/v1/chat/completions';
}

return esc_url_raw( (string) $this->get_setting( 'custom_ai_api_url', '' ) );
}

private function get_api_key() {
if ( 'openrouter' === $this->provider_key ) {
return trim( (string) $this->get_setting( 'openrouter_api_key', '' ) );
}

return trim( (string) $this->get_setting( 'custom_ai_api_key', '' ) );
}

private function get_model() {
if ( 'openrouter' === $this->provider_key ) {
return sanitize_text_field( (string) $this->get_setting( 'openrouter_model', '' ) );
}

return sanitize_text_field( (string) $this->get_setting( 'custom_ai_model', '' ) );
}

public function generate_text( $prompt, $options = array() ) {
$endpoint = $this->get_endpoint();
$api_key  = $this->get_api_key();
$model    = $this->get_model();

if ( '' === $endpoint || '' === $api_key || '' === $model ) {
return new WP_Error(
'ai_provider_not_configured',
__( 'OpenAI uyumlu AI sağlayıcısı yapılandırılmamış.', 'trendyol-woocommerce-importer' ),
array(
'type'     => 'config',
'provider' => $this->get_provider_key(),
)
);
}

$headers = array(
'Content-Type'  => 'application/json',
'Authorization' => 'Bearer ' . $api_key,
);

if ( 'openrouter' === $this->provider_key ) {
$headers['HTTP-Referer'] = home_url( '/' );
$headers['X-Title']      = 'Trendyol WooCommerce Importer';
}

$response = wp_remote_post(
$endpoint,
array(
'timeout' => $this->get_timeout( $options ),
'headers' => $headers,
'body'    => wp_json_encode(
array(
'model'       => $model,
'messages'    => array(
array(
'role'    => 'user',
'content' => (string) $prompt,
),
),
'temperature' => 0.4,
)
),
)
);

if ( is_wp_error( $response ) ) {
return $this->build_transport_error( $response );
}

$status_code = intval( wp_remote_retrieve_response_code( $response ) );
if ( $status_code < 200 || $status_code >= 300 ) {
return $this->build_http_error( $response, __( 'AI sağlayıcı isteği başarısız oldu.', 'trendyol-woocommerce-importer' ) );
}

$body = $this->decode_json_body( $response );
if ( is_wp_error( $body ) ) {
return $body;
}

$text = '';
if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
if ( is_string( $body['choices'][0]['message']['content'] ) ) {
$text = $body['choices'][0]['message']['content'];
} elseif ( is_array( $body['choices'][0]['message']['content'] ) ) {
foreach ( $body['choices'][0]['message']['content'] as $part ) {
if ( is_array( $part ) && ! empty( $part['text'] ) ) {
$text .= ' ' . $part['text'];
}
}
}
}

$text = trim( $text );
if ( '' === $text ) {
return new WP_Error(
'ai_empty_response',
__( 'AI sağlayıcısı geçerli bir metin döndürmedi.', 'trendyol-woocommerce-importer' ),
array(
'type'     => 'empty_response',
'provider' => $this->get_provider_key(),
)
);
}

return array(
'provider' => $this->get_provider_key(),
'text'     => $text,
);
}
}

class Trendyol_AI_Provider_Factory {

public static function create( $provider_key, $settings = array() ) {
$provider_key = sanitize_key( $provider_key );

if ( 'gemini' === $provider_key ) {
return new Trendyol_Gemini_AI_Provider( $settings );
}

if ( in_array( $provider_key, array( 'openrouter', 'custom' ), true ) ) {
return new Trendyol_OpenAI_Compatible_AI_Provider( $provider_key, $settings );
}

return null;
}
}
