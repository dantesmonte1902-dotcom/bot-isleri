<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! session_id() ) {
	session_start();
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'import';

$primary_tabs = array(
	'import' => array(
		'icon'  => '📥',
		'label' => __( 'İçe Aktar', 'trendyol-woocommerce-importer' ),
	),
	'bulk-import' => array(
		'icon'  => '📦',
		'label' => __( 'Toplu Ekle', 'trendyol-woocommerce-importer' ),
	),
	'categories' => array(
		'icon'  => '📂',
		'label' => __( 'Kategoriler', 'trendyol-woocommerce-importer' ),
	),
	'history' => array(
		'icon'  => '📜',
		'label' => __( 'Geçmiş', 'trendyol-woocommerce-importer' ),
	),
	'settings' => array(
		'icon'  => '⚙️',
		'label' => __( 'Ayarlar', 'trendyol-woocommerce-importer' ),
	),
);

$tool_tabs = array(
	'kargo' => array(
		'icon'  => '🚚💶',
		'label' => __( 'Kargo & Döviz', 'trendyol-woocommerce-importer' ),
	),
	'bulk-price-update' => array(
		'icon'  => '💶',
		'label' => __( 'Toplu Fiyat', 'trendyol-woocommerce-importer' ),
	),
	'title-bulk-update' => array(
		'icon'  => '📝',
		'label' => __( 'Başlık Güncelle', 'trendyol-woocommerce-importer' ),
	),
	'batch-variant-stock' => array(
		'icon'  => '📊',
		'label' => __( 'Varyant Stok', 'trendyol-woocommerce-importer' ),
	),
	'scraper-diagnostic' => array(
		'icon'  => '🩺',
		'label' => __( 'Scraper Tanı', 'trendyol-woocommerce-importer' ),
	),
);

$all_tabs = $primary_tabs + $tool_tabs;

$tab_files = array(
	'import'              => 'admin/tabs/tab-import.php',
	'bulk-import'         => 'admin/tabs/tab-bulk-import.php',
	'categories'          => 'admin/tabs/tab-categories.php',
	'history'             => 'admin/tabs/tab-history.php',
	'settings'            => 'admin/tabs/tab-settings.php',
	'kargo'               => 'admin/tabs/tab-kargo.php',
	'bulk-price-update'   => 'admin/tabs/tab-bulk-price-update.php',
	'title-bulk-update'   => 'admin/tabs/tab-title-bulk-update.php',
	'batch-variant-stock' => 'admin/tabs/tab-batch-variant-stock.php',
	'scraper-diagnostic'  => 'admin/tabs/tab-scraper-diagnostic.php',
);

if ( ! array_key_exists( $active_tab, $all_tabs ) ) {
	$active_tab = 'import';
}

$bulk_price_result = isset( $_SESSION['trendyol_bulk_price_update_result'] ) ? $_SESSION['trendyol_bulk_price_update_result'] : null;
unset( $_SESSION['trendyol_bulk_price_update_result'] );

$dashboard_stats = class_exists( 'Trendyol_Dashboard_Stats' ) ? Trendyol_Dashboard_Stats::get_summary() : array();

$is_tool_active = array_key_exists( $active_tab, $tool_tabs );
?>

<div class="wrap trendyol-admin-wrapper">

	<div class="trendyol-header">
		<div>
			<h1><?php echo esc_html__( 'Trendyol WooCommerce İçe Aktarıcı', 'trendyol-woocommerce-importer' ); ?></h1>
			<p class="subtitle"><?php echo esc_html__( 'Trendyol ürünlerini içe aktarın, yönetin ve bakım araçlarını tek panelden kullanın.', 'trendyol-woocommerce-importer' ); ?></p>
		</div>
	</div>

	<div class="trendyol-top-summary">
		<?php include TRENDYOL_IMPORTER_PATH . 'admin/views/dashboard-cards.php'; ?>
	</div>

	<div class="trendyol-admin-nav-shell">

		<div class="trendyol-primary-nav">
			<div class="trendyol-nav-section-title"><?php echo esc_html__( 'Ana İşlemler', 'trendyol-woocommerce-importer' ); ?></div>
			<div class="trendyol-primary-tabs">
				<?php foreach ( $primary_tabs as $tab_key => $tab ) : ?>
					<a
						href="<?php echo esc_url( admin_url( 'admin.php?page=trendyol-importer&tab=' . $tab_key ) ); ?>"
						class="trendyol-primary-tab <?php echo ( $active_tab === $tab_key ) ? 'active' : ''; ?>"
					>
						<span class="tab-icon"><?php echo esc_html( $tab['icon'] ); ?></span>
						<span class="tab-label"><?php echo esc_html( $tab['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="trendyol-tools-bar <?php echo $is_tool_active ? 'is-active-context' : ''; ?>">
			<div class="trendyol-nav-section-title"><?php echo esc_html__( 'Bakım & Araçlar', 'trendyol-woocommerce-importer' ); ?></div>
			<div class="trendyol-tool-pills">
				<?php foreach ( $tool_tabs as $tab_key => $tab ) : ?>
					<a
						href="<?php echo esc_url( admin_url( 'admin.php?page=trendyol-importer&tab=' . $tab_key ) ); ?>"
						class="trendyol-tool-pill <?php echo ( $active_tab === $tab_key ) ? 'active' : ''; ?>"
					>
						<span class="tab-icon"><?php echo esc_html( $tab['icon'] ); ?></span>
						<span class="tab-label"><?php echo esc_html( $tab['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

	</div>

	<div class="trendyol-tabs-content">
		<div class="trendyol-tab-pane active">
			<?php
			$current_file = isset( $tab_files[ $active_tab ] ) ? TRENDYOL_IMPORTER_PATH . $tab_files[ $active_tab ] : '';

			if ( ! empty( $current_file ) && file_exists( $current_file ) ) {
				include $current_file;
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Sekme dosyası bulunamadı.', 'trendyol-woocommerce-importer' ) . '</p></div>';
			}
			?>
		</div>
	</div>

</div>

<style>
.trendyol-admin-wrapper {
	max-width: 1280px;
}

.trendyol-header {
	margin: 18px 0 18px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.trendyol-header h1 {
	margin: 0 0 6px;
	font-size: 28px;
	line-height: 1.2;
}

.trendyol-header .subtitle {
	margin: 0;
	color: #64748b;
	font-size: 14px;
}

.trendyol-top-summary {
	display: flex;
	flex-direction: column;
	gap: 18px;
	margin-bottom: 22px;
}

.trendyol-admin-nav-shell {
	display: flex;
	flex-direction: column;
	gap: 14px;
	margin: 0 0 18px;
}

.trendyol-primary-nav,
.trendyol-tools-bar {
	background: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	padding: 16px 18px;
	box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

.trendyol-tools-bar {
	background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
}

.trendyol-tools-bar.is-active-context {
	border-color: #bfdbfe;
	box-shadow: 0 6px 18px rgba(37, 99, 235, 0.08);
}

.trendyol-nav-section-title {
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .06em;
	text-transform: uppercase;
	color: #64748b;
	margin-bottom: 12px;
}

.trendyol-primary-tabs {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.trendyol-primary-tab {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 12px 16px;
	border-radius: 12px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	color: #0f172a;
	text-decoration: none;
	font-weight: 600;
	transition: all .18s ease;
	min-height: 44px;
}

.trendyol-primary-tab:hover {
	background: #eff6ff;
	border-color: #bfdbfe;
	color: #1d4ed8;
	transform: translateY(-1px);
}

.trendyol-primary-tab.active {
	background: linear-gradient(135deg, #2563eb, #1d4ed8);
	border-color: #1d4ed8;
	color: #fff;
	box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
}

.trendyol-tool-pills {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
}

.trendyol-tool-pill {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 9px 13px;
	border-radius: 999px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	color: #334155;
	text-decoration: none;
	font-weight: 500;
	font-size: 13px;
	transition: all .18s ease;
}

.trendyol-tool-pill:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
	color: #0f172a;
}

.trendyol-tool-pill.active {
	background: #0f172a;
	border-color: #0f172a;
	color: #fff;
	box-shadow: 0 8px 16px rgba(15, 23, 42, 0.18);
}

.trendyol-tab-pane.active {
	margin-top: 18px;
}

.tab-icon {
	line-height: 1;
	font-size: 15px;
}

.tab-label {
	line-height: 1.2;
}

@media (max-width: 782px) {
	.trendyol-primary-tab,
	.trendyol-tool-pill {
		width: 100%;
		justify-content: flex-start;
	}

	.trendyol-header {
		align-items: flex-start;
	}
}
</style>
