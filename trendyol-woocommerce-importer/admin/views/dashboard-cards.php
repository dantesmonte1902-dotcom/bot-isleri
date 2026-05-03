<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="trendyol-dashboard-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:20px 0 24px 0;">
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#64748b;margin-bottom:6px;"><?php esc_html_e( 'Toplam import edilen', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:28px;font-weight:700;color:#111827;"><?php echo esc_html( intval( $dashboard_stats['total_imported'] ?? 0 ) ); ?></div>
	</div>

	<div style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#64748b;margin-bottom:6px;"><?php esc_html_e( 'Bugün import edilen', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:28px;font-weight:700;color:#111827;"><?php echo esc_html( intval( $dashboard_stats['imported_today'] ?? 0 ) ); ?></div>
	</div>

	<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#9a3412;margin-bottom:6px;"><?php esc_html_e( 'Bloklanan marka', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:28px;font-weight:700;color:#9a3412;"><?php echo esc_html( intval( $dashboard_stats['blocked_brand_count'] ?? 0 ) ); ?></div>
	</div>

	<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#92400e;margin-bottom:6px;"><?php esc_html_e( 'Duplicate atlanan', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:28px;font-weight:700;color:#92400e;"><?php echo esc_html( intval( $dashboard_stats['duplicate_skipped'] ?? 0 ) ); ?></div>
	</div>

	<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#1d4ed8;margin-bottom:6px;"><?php esc_html_e( 'Ortalama net kâr (€)', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:28px;font-weight:700;color:#1d4ed8;"><?php echo esc_html( number_format( (float) ( $dashboard_stats['average_net_profit'] ?? 0 ), 2, '.', '' ) ); ?></div>
	</div>

	<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#991b1b;margin-bottom:6px;"><?php esc_html_e( 'Zarardaki ürün', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:28px;font-weight:700;color:#991b1b;"><?php echo esc_html( intval( $dashboard_stats['loss_making_products'] ?? 0 ) ); ?></div>
	</div>

	<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#475569;margin-bottom:6px;"><?php esc_html_e( 'Güncel Euro kur', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:24px;font-weight:700;color:#0f172a;"><?php echo esc_html( number_format( (float) ( $dashboard_stats['current_euro_kur'] ?? 0 ), 4, '.', '' ) ); ?></div>
	</div>

	<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;">
		<div style="font-size:12px;color:#475569;margin-bottom:6px;"><?php esc_html_e( 'Güncel RSD kur', 'trendyol-woocommerce-importer' ); ?></div>
		<div style="font-size:24px;font-weight:700;color:#0f172a;"><?php echo esc_html( number_format( (float) ( $dashboard_stats['current_rsd_kur'] ?? 0 ), 4, '.', '' ) ); ?></div>
	</div>
</div>