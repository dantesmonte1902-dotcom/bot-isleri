<?php
/**
 * Notifier Class
 * Bildirim sistemi (Email, Telegram)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Notifier {

	private $product_id;
	private $product_name;
	private $differences;
	private $settings;

	public function __construct( $product_id, $product_name, $differences ) {
		$this->product_id   = $product_id;
		$this->product_name = $product_name;
		$this->differences  = $differences;
		$this->settings     = Trendyol_Settings::get_all();
	}

	/**
	 * Bildirimleri gönder
	 */
	public function send() {
		// Email gönder
		if ( $this->settings['notification_email'] ) {
			$this->send_email();
		}

		// Telegram gönder
		if ( $this->settings['notification_telegram'] ) {
			$this->send_telegram();
		}

		// Logger'a kaydet
		$this->log_notification();
	}

	/**
	 * Email gönder
	 */
	private function send_email() {
		$email = $this->settings['notification_email_address'];

		if ( empty( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		if ( empty( $email ) ) {
			return false;
		}

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$subject   = $this->get_email_subject();
		$message   = $this->get_email_body();
		$admin_url = admin_url( 'post.php?post=' . $this->product_id . '&action=edit' );

		$message .= '<br><br>';
		$message .= '<a href="' . esc_url( $admin_url ) . '" style="background-color: #2563eb; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; display: inline-block;">';
		$message .= esc_html__( '📝 Ürünü Düzenle', 'trendyol-woocommerce-importer' );
		$message .= '</a>';

		return wp_mail( $email, $subject, $message, $headers );
	}

	/**
	 * Telegram gönder
	 */
	private function send_telegram() {
		$bot_token = $this->settings['telegram_bot_token'];
		$chat_id   = $this->settings['telegram_chat_id'];

		if ( empty( $bot_token ) || empty( $chat_id ) ) {
			return false;
		}

		$message = $this->get_telegram_message();
		$url     = 'https://api.telegram.org/bot' . $bot_token . '/sendMessage';

		$response = wp_remote_post(
			$url,
			array(
				'body' => array(
					'chat_id'    => $chat_id,
					'text'       => $message,
					'parse_mode' => 'HTML',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Trendyol Telegram Error: ' . $response->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Email konusu oluştur
	 */
	private function get_email_subject() {
		$severity = isset( $this->differences['price'] ) || isset( $this->differences['stock'] ) 
			? '⚠️ ACİL' 
			: '📢';

		return sprintf(
			'%s Trendyol Ürün Değişimi - %s',
			$severity,
			$this->product_name
		);
	}

	/**
	 * Email içeriği oluştur
	 */
	private function get_email_body() {
		$html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';

		$html .= '<h2 style="color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 10px;">';
		$html .= esc_html__( '🔔 Trendyol Ürün Değişimi Algılandı', 'trendyol-woocommerce-importer' );
		$html .= '</h2>';

		$html .= '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0;">';
		$html .= '<h3 style="margin-top: 0; color: #333;">' . esc_html( $this->product_name ) . '</h3>';
		$html .= '<p style="color: #666; margin: 0;">ID: #' . esc_html( $this->product_id ) . '</p>';
		$html .= '</div>';

		foreach ( $this->differences as $diff ) {
			$html .= $this->get_diff_html( $diff );
		}

		$html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 12px;">';
		$html .= sprintf(
			esc_html__( 'Senkronizasyon Tarihi: %s', 'trendyol-woocommerce-importer' ),
			current_time( 'Y-m-d H:i:s' )
		);
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Fark HTML'i oluştur
	 */
	private function get_diff_html( $diff ) {
		$html = '<div style="border-left: 4px solid ';

		if ( 'high' === $diff['severity'] ) {
			$html .= '#ef4444';
		} elseif ( 'medium' === $diff['severity'] ) {
			$html .= '#f59e0b';
		} else {
			$html .= '#10b981';
		}

		$html .= '; padding: 15px; margin: 15px 0; background-color: #f9f9f9;">';

		if ( 'price' === $diff['type'] ) {
			$html .= '<strong>💰 Fiyat Değişimi</strong><br>';
			$html .= 'Eski Fiyat: ' . esc_html( $diff['old_value'] ) . ' TL<br>';
			$html .= 'Yeni Fiyat: ' . esc_html( $diff['new_value'] ) . ' TL<br>';
			$html .= 'Değişim: <strong style="color: ' . ( $diff['difference'] < 0 ? '#10b981' : '#ef4444' ) . ';">';
			$html .= ( $diff['difference'] >= 0 ? '+' : '' ) . esc_html( $diff['difference'] ) . ' TL (' . esc_html( $diff['percentage'] ) . '%)</strong>';
		} elseif ( 'stock' === $diff['type'] ) {
			$old_label = 'instock' === $diff['old_value'] ? esc_html__( 'Stokta', 'trendyol-woocommerce-importer' ) : esc_html__( 'Tükendi', 'trendyol-woocommerce-importer' );
			$new_label = 'instock' === $diff['new_value'] ? esc_html__( 'Stokta', 'trendyol-woocommerce-importer' ) : esc_html__( 'Tükendi', 'trendyol-woocommerce-importer' );
			$html .= '<strong>📊 Stok Durumu</strong><br>';
			$html .= 'Eski: ' . esc_html( $old_label ) . '<br>';
			$html .= 'Yeni: ' . esc_html( $new_label );
		} elseif ( 'sizes' === $diff['type'] ) {
			$html .= '<strong>📏 Beden Değişimi</strong><br>';
			if ( ! empty( $diff['added'] ) ) {
				$html .= 'Eklenen: ' . esc_html( implode( ', ', $diff['added'] ) ) . '<br>';
			}
			if ( ! empty( $diff['removed'] ) ) {
				$html .= 'Kaldırılan: ' . esc_html( implode( ', ', $diff['removed'] ) );
			}
		} else {
			$html .= '<strong>' . esc_html( $diff['label'] ) . '</strong>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Telegram mesajı oluştur
	 */
	private function get_telegram_message() {
		$message = '<b>🔔 TRENDYOL ÜRÜN DEĞİŞİMİ</b>' . "\n\n";
		$message .= '<b>📦 Ürün:</b> ' . esc_html( $this->product_name ) . "\n";
		$message .= '<b>ID:</b> #' . esc_html( $this->product_id ) . "\n\n";

		foreach ( $this->differences as $diff ) {
			if ( 'price' === $diff['type'] ) {
				$arrow = $diff['difference'] < 0 ? '📉' : '📈';
				$message .= $arrow . ' <b>FİYAT DEĞİŞİMİ</b>' . "\n";
				$message .= $diff['old_value'] . ' TL → <b>' . $diff['new_value'] . ' TL</b>' . "\n";
				$message .= '(' . ( $diff['difference'] >= 0 ? '+' : '' ) . $diff['difference'] . ' TL / ' . $diff['percentage'] . '%)</b>' . "\n\n";
			} elseif ( 'stock' === $diff['type'] ) {
				$old_label = 'instock' === $diff['old_value'] ? '✅ Stokta' : '❌ Tükendi';
				$new_label = 'instock' === $diff['new_value'] ? '✅ Stokta' : '❌ Tükendi';
				$message .= '<b>📊 STOK DEĞİŞİMİ</b>' . "\n";
				$message .= $old_label . ' → ' . $new_label . "\n\n";
			} elseif ( 'sizes' === $diff['type'] ) {
				$message .= '<b>📏 BEDEN DEĞİŞİMİ</b>' . "\n";
				if ( ! empty( $diff['added'] ) ) {
					$message .= '➕ Eklenen: ' . implode( ', ', $diff['added'] ) . "\n";
				}
				if ( ! empty( $diff['removed'] ) ) {
					$message .= '➖ Kaldırılan: ' . implode( ', ', $diff['removed'] ) . "\n";
				}
				$message .= "\n";
			}
		}

		$message .= '��� <i>' . current_time( 'Y-m-d H:i:s' ) . '</i>' . "\n";
		$message .= '<a href="' . esc_url( admin_url( 'post.php?post=' . $this->product_id . '&action=edit' ) ) . '">📝 Düzenle</a>';

		return $message;
	}

	/**
	 * Bildirimi logla
	 */
	private function log_notification() {
		$logger = new Trendyol_Logger();
		$logger->log(
			'notification',
			'send_notification',
			'success',
			'Bildirim gönderildi. Email: ' . ( $this->settings['notification_email'] ? 'Evet' : 'Hayır' ) . 
			', Telegram: ' . ( $this->settings['notification_telegram'] ? 'Evet' : 'Hayır' ),
			$this->product_id,
			null,
			null,
			$this->differences
		);
	}
}
?>