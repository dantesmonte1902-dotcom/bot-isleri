<?php
/**
 * Settings Class - Updated with Notifications & Kar Marjı Kolonu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Settings {

	const OPTION_PREFIX = 'trendyol_';

	private static $defaults = array(
		// İzinler
		'import_capability'         => 'manage_woocommerce',
		'sync_capability'           => 'manage_woocommerce',

		// Otomatik Senkronizasyon
		'enable_auto_sync'          => false,
		'sync_interval'             => 'daily',

		// İçe Aktarma Seçenekleri
		'max_images'                => 10,
		'price_markup'              => 0,

		// Senkronizasyon Seçenekleri
		'sync_price'                => true,
		'sync_stock'                => true,
		'sync_description'          => false,

		// Değişim Algılama Eşiği
		'price_change_threshold'    => 5,      // %5 değişimde uyarı
		'enable_change_detection'   => true,

		// Bildirim Seçenekleri
		'notification_enabled'      => true,
		'notification_email'        => true,
		'notification_email_address'=> '',
		'notification_telegram'     => false,
		'telegram_bot_token'        => '',
		'telegram_chat_id'          => '',

		// Diğer
		'logs_retention_days'       => 30,
		'enable_webhook'            => false,
		'webhook_url'               => '',
		'debug_mode'                => false,

		// YENİ: Kar Marjı Kolonu
		'show_rsd_kar_column'       => 0,
	);

	/**
	 * Ayar getir
	 */
	public static function get( $key, $default = null ) {
		$option_name = self::OPTION_PREFIX . $key;
		$value       = get_option( $option_name );

		if ( false === $value && isset( self::$defaults[ $key ] ) ) {
			return self::$defaults[ $key ];
		}

		return false === $value ? $default : $value;
	}

	/**
	 * Ayar kaydet
	 */
	public static function set( $key, $value ) {
		$option_name = self::OPTION_PREFIX . $key;
		return update_option( $option_name, $value );
	}

	/**
	 * Tüm ayarları getir
	 */
	public static function get_all() {
		$settings = array();
		foreach ( self::$defaults as $key => $default ) {
			$settings[ $key ] = self::get( $key );
		}
		return $settings;
	}

	/**
	 * Varsayılan ayarları kaydet
	 */
	public static function init_defaults() {
		foreach ( self::$defaults as $key => $default ) {
			if ( false === get_option( self::OPTION_PREFIX . $key ) ) {
				self::set( $key, $default );
			}
		}
	}

	/**
	 * User import yapabilir mi?
	 */
	public static function user_can_import( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		return user_can( $user_id, 'manage_woocommerce' ) || user_can( $user_id, 'edit_products' );
	}

	/**
	 * User senkronizasyon yapabilir mi?
	 */
	public static function user_can_sync( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		return user_can( $user_id, 'manage_woocommerce' ) || user_can( $user_id, 'edit_products' );
	}

	/**
	 * Bildirimleri kontrol et
	 */
	public static function notifications_enabled() {
		return self::get( 'notification_enabled' ) && ( self::get( 'notification_email' ) || self::get( 'notification_telegram' ) );
	}
}
?>