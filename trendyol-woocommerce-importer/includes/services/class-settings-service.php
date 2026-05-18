<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Settings_Service {

	public function save_from_post( $post_data ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
		}

		Trendyol_Settings::set( 'import_capability', sanitize_text_field( $post_data['import_capability'] ?? 'manage_woocommerce' ) );
		Trendyol_Settings::set( 'sync_capability', sanitize_text_field( $post_data['sync_capability'] ?? 'manage_woocommerce' ) );
		Trendyol_Settings::set( 'enable_auto_sync', isset( $post_data['enable_auto_sync'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'sync_interval', sanitize_text_field( $post_data['sync_interval'] ?? 'daily' ) );
		Trendyol_Settings::set( 'auto_price_update_interval', sanitize_text_field( $post_data['auto_price_update_interval'] ?? 'off' ) );
		Trendyol_Settings::set( 'auto_stock_sync_interval', sanitize_text_field( $post_data['auto_stock_sync_interval'] ?? 'off' ) );
		Trendyol_Settings::set( 'auto_sync_product_status', sanitize_text_field( $post_data['auto_sync_product_status'] ?? 'both' ) );
		Trendyol_Settings::set( 'max_images', intval( $post_data['max_images'] ?? 10 ) );
		Trendyol_Settings::set( 'price_markup', floatval( $post_data['price_markup'] ?? 0 ) );
		Trendyol_Settings::set( 'sync_price', isset( $post_data['sync_price'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'sync_stock', isset( $post_data['sync_stock'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'sync_description', isset( $post_data['sync_description'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'logs_retention_days', intval( $post_data['logs_retention_days'] ?? 30 ) );
		Trendyol_Settings::set( 'enable_webhook', isset( $post_data['enable_webhook'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'webhook_url', esc_url_raw( $post_data['webhook_url'] ?? '' ) );
		Trendyol_Settings::set( 'debug_mode', isset( $post_data['debug_mode'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'notification_enabled', isset( $post_data['notification_enabled'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'notification_email', isset( $post_data['notification_email'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'notification_email_address', sanitize_email( $post_data['notification_email_address'] ?? '' ) );
		Trendyol_Settings::set( 'notification_telegram', isset( $post_data['notification_telegram'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'telegram_bot_token', sanitize_text_field( $post_data['telegram_bot_token'] ?? '' ) );
		Trendyol_Settings::set( 'telegram_chat_id', sanitize_text_field( $post_data['telegram_chat_id'] ?? '' ) );
		Trendyol_Settings::set( 'price_change_threshold', floatval( $post_data['price_change_threshold'] ?? 5 ) );
		Trendyol_Settings::set( 'enable_change_detection', isset( $post_data['enable_change_detection'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'ai_provider', sanitize_key( $post_data['ai_provider'] ?? 'gemini' ) );
		Trendyol_Settings::set( 'ai_fallback_provider', sanitize_key( $post_data['ai_fallback_provider'] ?? 'none' ) );
		Trendyol_Settings::set( 'ai_batch_enabled', isset( $post_data['ai_batch_enabled'] ) ? 1 : 0 );
		Trendyol_Settings::set( 'ai_default_processing_mode', sanitize_key( $post_data['ai_default_processing_mode'] ?? 'single' ) );
		Trendyol_Settings::set( 'ai_batch_size', intval( $post_data['ai_batch_size'] ?? 10 ) );
		Trendyol_Settings::set( 'ai_retry_limit', intval( $post_data['ai_retry_limit'] ?? 2 ) );
		Trendyol_Settings::set( 'ai_request_pause_seconds', intval( $post_data['ai_request_pause_seconds'] ?? 12 ) );
		Trendyol_Settings::set( 'ai_requests_per_minute', intval( $post_data['ai_requests_per_minute'] ?? 5 ) );
		Trendyol_Settings::set( 'ai_output_language', sanitize_text_field( $post_data['ai_output_language'] ?? 'Boşnakça' ) );
		Trendyol_Settings::set( 'gemini_api_key', sanitize_text_field( $post_data['gemini_api_key'] ?? '' ) );
		Trendyol_Settings::set( 'gemini_model', sanitize_text_field( $post_data['gemini_model'] ?? 'gemini-2.5-flash' ) );
		Trendyol_Settings::set( 'gemini_title_prompt', sanitize_textarea_field( $post_data['gemini_title_prompt'] ?? '' ) );
		Trendyol_Settings::set( 'gemini_title_max_length', intval( $post_data['gemini_title_max_length'] ?? 160 ) );
		Trendyol_Settings::set( 'openrouter_api_key', sanitize_text_field( $post_data['openrouter_api_key'] ?? '' ) );
		Trendyol_Settings::set( 'openrouter_model', sanitize_text_field( $post_data['openrouter_model'] ?? '' ) );
		Trendyol_Settings::set( 'custom_ai_api_url', esc_url_raw( $post_data['custom_ai_api_url'] ?? '' ) );
		Trendyol_Settings::set( 'custom_ai_api_key', sanitize_text_field( $post_data['custom_ai_api_key'] ?? '' ) );
		Trendyol_Settings::set( 'custom_ai_model', sanitize_text_field( $post_data['custom_ai_model'] ?? '' ) );

		update_option(
			'trendyol_blocked_brands',
			isset( $post_data['trendyol_blocked_brands'] ) ? wp_unslash( $post_data['trendyol_blocked_brands'] ) : ''
		);

		if ( class_exists( 'Trendyol_Cron_Manager' ) ) {
			Trendyol_Cron_Manager::reschedule_all();
		}

		return true;
	}
}
