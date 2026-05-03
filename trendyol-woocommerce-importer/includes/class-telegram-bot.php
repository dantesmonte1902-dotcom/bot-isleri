<?php
/**
 * Telegram Bot Class
 * Telegram entegrasyonu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Telegram_Bot {

	private $bot_token;
	private $chat_id;

	public function __construct( $bot_token = '', $chat_id = '' ) {
		$this->bot_token = $bot_token ?: Trendyol_Settings::get( 'telegram_bot_token' );
		$this->chat_id   = $chat_id ?: Trendyol_Settings::get( 'telegram_chat_id' );
	}

	/**
	 * Botu test et
	 */
	public function test_connection() {
		if ( empty( $this->bot_token ) || empty( $this->chat_id ) ) {
			return new WP_Error( 'missing_config', esc_html__( 'Bot Token veya Chat ID boş', 'trendyol-woocommerce-importer' ) );
		}

		$message = '✅ Trendyol Plugin - Telegram Botu Test Mesajı\n\n' .
				   'Eğer bu mesajı görüyorsanız, bot doğru şekilde yapılandırılmıştır.\n\n' .
				   '🕐 Zaman: ' . current_time( 'Y-m-d H:i:s' );

		return $this->send_message( $message );
	}

	/**
	 * Mesaj gönder
	 */
	public function send_message( $text ) {
		if ( empty( $this->bot_token ) || empty( $this->chat_id ) ) {
			return false;
		}

		$url = 'https://api.telegram.org/bot' . $this->bot_token . '/sendMessage';

		$response = wp_remote_post(
			$url,
			array(
				'body' => array(
					'chat_id'    => $this->chat_id,
					'text'       => $text,
					'parse_mode' => 'HTML',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['ok'] ) || ! $body['ok'] ) {
			return new WP_Error( 'telegram_error', isset( $body['description'] ) ? $body['description'] : esc_html__( 'Telegram hatası', 'trendyol-woocommerce-importer' ) );
		}

		return true;
	}

	/**
	 * Inline butonlu mesaj gönder
	 */
	public function send_message_with_buttons( $text, $buttons ) {
		if ( empty( $this->bot_token ) || empty( $this->chat_id ) ) {
			return false;
		}

		$url = 'https://api.telegram.org/bot' . $this->bot_token . '/sendMessage';

		$inline_keyboard = array();
		foreach ( $buttons as $button ) {
			$inline_keyboard[] = array(
				array(
					'text'       => $button['text'],
					'url'        => $button['url'],
					'callback_data' => $button['callback_data'] ?? null,
				),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'body' => json_encode(
					array(
						'chat_id'           => $this->chat_id,
						'text'              => $text,
						'parse_mode'        => 'HTML',
						'reply_markup'      => array(
							'inline_keyboard' => $inline_keyboard,
						),
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['ok'] ) && $body['ok'];
	}

	/**
	 * Dosya gönder
	 */
	public function send_file( $file_path, $caption = '' ) {
		if ( empty( $this->bot_token ) || empty( $this->chat_id ) ) {
			return false;
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', esc_html__( 'Dosya bulunamadı', 'trendyol-woocommerce-importer' ) );
		}

		$url = 'https://api.telegram.org/bot' . $this->bot_token . '/sendDocument';

		$response = wp_remote_post(
			$url,
			array(
				'body' => array(
					'chat_id'  => $this->chat_id,
					'document' => new CurlFile( $file_path ),
					'caption'  => $caption,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}
}
?>