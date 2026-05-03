<?php
/**
 * Tab: History
 * İçe aktarma geçmişi sekmesi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$history_service = new Trendyol_History_Service();
$filters         = $history_service->get_filters_from_request( $_GET );
$page_data       = $history_service->get_history_page_data( $filters );

$logs        = $page_data['logs'];
$total_pages = $page_data['total_pages'];
$stats       = $page_data['stats'];
$log_type    = $filters['log_type'];
$status      = $filters['status'];
$page        = $filters['page'];
?>

<div class="trendyol-tab-content">
	<div class="stats-row">
		<div class="stat-card">
			<div class="stat-label">📦 Toplam İçe Aktarmalar</div>
			<div class="stat-value"><?php echo esc_html( $stats['total_imports'] ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label">✅ Başarılı</div>
			<div class="stat-value" style="color: #10b981;">
				<?php echo esc_html( $stats['successful_imports'] ); ?>
			</div>
		</div>
		<div class="stat-card">
			<div class="stat-label">❌ Başarısız</div>
			<div class="stat-value" style="color: #ef4444;">
				<?php echo esc_html( $stats['failed_imports'] ); ?>
			</div>
		</div>
		<div class="stat-card">
			<div class="stat-label">🔄 Senkronizasyonlar</div>
			<div class="stat-value"><?php echo esc_html( $stats['total_syncs'] ); ?></div>
		</div>
	</div>

	<div class="trendyol-card">
		<h3 style="margin-top: 0; margin-bottom: 20px;">📜 <?php echo esc_html__( 'İçe Aktarma Geçmişi', 'trendyol-woocommerce-importer' ); ?></h3>

		<div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
			<form method="get" class="trendyol-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
				<input type="hidden" name="page" value="trendyol-importer">
				<input type="hidden" name="tab" value="history">

				<div style="margin-bottom: 0;">
					<label for="type_filter"><?php echo esc_html__( 'Tür', 'trendyol-woocommerce-importer' ); ?></label>
					<select id="type_filter" name="type" class="form-control">
						<option value=""><?php echo esc_html__( 'Tüm Türler', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="import" <?php selected( $log_type, 'import' ); ?>>
							<?php echo esc_html__( 'İçe Aktarma', 'trendyol-woocommerce-importer' ); ?>
						</option>
						<option value="sync" <?php selected( $log_type, 'sync' ); ?>>
							<?php echo esc_html__( 'Senkronizasyon', 'trendyol-woocommerce-importer' ); ?>
						</option>
					</select>
				</div>

				<div style="margin-bottom: 0;">
					<label for="status_filter"><?php echo esc_html__( 'Durum', 'trendyol-woocommerce-importer' ); ?></label>
					<select id="status_filter" name="status" class="form-control">
						<option value=""><?php echo esc_html__( 'Tüm Durumlar', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="success" <?php selected( $status, 'success' ); ?>>
							<?php echo esc_html__( 'Başarılı', 'trendyol-woocommerce-importer' ); ?>
						</option>
						<option value="error" <?php selected( $status, 'error' ); ?>>
							<?php echo esc_html__( 'Hata', 'trendyol-woocommerce-importer' ); ?>
						</option>
						<option value="skipped" <?php selected( $status, 'skipped' ); ?>>
							<?php echo esc_html__( 'Atlandı', 'trendyol-woocommerce-importer' ); ?>
						</option>
					</select>
				</div>

				<button type="submit" class="btn btn-primary" style="margin-bottom: 0;">
					<?php echo esc_html__( '🔍 Filtrele', 'trendyol-woocommerce-importer' ); ?>
				</button>
			</form>
		</div>

		<?php if ( ! empty( $logs ) ) : ?>
			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th width="10%"><?php echo esc_html__( 'ID', 'trendyol-woocommerce-importer' ); ?></th>
							<th width="15%"><?php echo esc_html__( 'Tür', 'trendyol-woocommerce-importer' ); ?></th>
							<th width="15%"><?php echo esc_html__( 'İşlem', 'trendyol-woocommerce-importer' ); ?></th>
							<th width="12%"><?php echo esc_html__( 'Durum', 'trendyol-woocommerce-importer' ); ?></th>
							<th width="20%"><?php echo esc_html__( 'Ürün', 'trendyol-woocommerce-importer' ); ?></th>
							<th width="15%"><?php echo esc_html__( 'Tarih', 'trendyol-woocommerce-importer' ); ?></th>
							<th width="13%"><?php echo esc_html__( 'İşlemler', 'trendyol-woocommerce-importer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$product      = $log->product_id ? get_post( $log->product_id ) : null;
							$status_class = 'success' === $log->status ? 'success' : ( 'error' === $log->status ? 'danger' : 'warning' );
							$status_badge = 'success' === $log->status ? '✅' : ( 'error' === $log->status ? '❌' : '⏳' );
							?>
							<tr>
								<td><code><?php echo esc_html( $log->id ); ?></code></td>
								<td>
									<?php if ( 'import' === $log->log_type ) : ?>
										<span class="badge bg-primary">📥 <?php echo esc_html__( 'İçe Aktarma', 'trendyol-woocommerce-importer' ); ?></span>
									<?php else : ?>
										<span class="badge bg-secondary">🔄 <?php echo esc_html__( 'Senkronizasyon', 'trendyol-woocommerce-importer' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log->action ) ) ); ?></td>
								<td>
									<span class="badge bg-<?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( $status_badge . ' ' . ucfirst( $log->status ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( $product ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $product->ID ) ); ?>" target="_blank">
											<?php echo esc_html( substr( $product->post_title, 0, 50 ) ); ?>
										</a>
									<?php else : ?>
										<?php if ( ! empty( $log->product_url ) ) : ?>
											<a href="<?php echo esc_url( $log->product_url ); ?>" target="_blank" title="Trendyol'da Görüntüle">
												🔗 Trendyol
											</a>
										<?php else : ?>
											—
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $log->created_at ) ) ); ?></td>
								<td>
									<button type="button" class="button button-small" onclick='showLogDetails(<?php echo wp_json_encode( $log ); ?>)'>
										<?php echo esc_html__( 'Ayrıntılar', 'trendyol-woocommerce-importer' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div style="margin-top: 25px; text-align: center;">
					<?php
					$pagination = paginate_links(
						array(
							'total'     => $total_pages,
							'current'   => $page,
							'type'      => 'array',
							'prev_text' => '« ' . __( 'Önceki' ),
							'next_text' => __( 'Sonraki' ) . ' »',
							'add_args'  => array(
								'tab'    => 'history',
								'type'   => $log_type,
								'status' => $status,
							),
						)
					);

					if ( $pagination ) {
						echo '<div class="pagination">' . implode( ' ', $pagination ) . '</div>';
					}
					?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="alert alert-warning">
				<span style="font-size: 20px;">📭</span>
				<div>
					<strong><?php echo esc_html__( 'Günlük bulunamadı', 'trendyol-woocommerce-importer' ); ?></strong>
					<p><?php echo esc_html__( 'Henüz import veya senkronizasyon işlemi yapılmadı.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<div id="logDetailsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
	<div class="trendyol-card" style="max-width:600px; margin:20px;">
		<button onclick="document.getElementById('logDetailsModal').style.display='none'" style="float:right; background:none; border:none; font-size:24px; cursor:pointer;">×</button>
		<h3><?php echo esc_html__( 'Günlük Ayrıntıları', 'trendyol-woocommerce-importer' ); ?></h3>
		<div id="logDetailsContent"></div>
	</div>
</div>

<script>
function showLogDetails(log) {
	const modal = document.getElementById('logDetailsModal');
	const content = document.getElementById('logDetailsContent');

	let oldData = '';
	let newData = '';

	if (log.old_data) {
		try {
			const parsed = JSON.parse(log.old_data);
			oldData = '<pre style="background:#f5f5f5; padding:10px; border-radius:4px; font-size:12px; overflow-x:auto;">' + JSON.stringify(parsed, null, 2) + '</pre>';
		} catch(e) {
			oldData = '<pre>' + log.old_data + '</pre>';
		}
	}

	if (log.new_data) {
		try {
			const parsed = JSON.parse(log.new_data);
			newData = '<pre style="background:#f5f5f5; padding:10px; border-radius:4px; font-size:12px; overflow-x:auto;">' + JSON.stringify(parsed, null, 2) + '</pre>';
		} catch(e) {
			newData = '<pre>' + log.new_data + '</pre>';
		}
	}

	content.innerHTML = `
		<p><strong><?php echo esc_html__( 'Tür:', 'trendyol-woocommerce-importer' ); ?></strong> ${log.log_type}</p>
		<p><strong><?php echo esc_html__( 'İşlem:', 'trendyol-woocommerce-importer' ); ?></strong> ${log.action}</p>
		<p><strong><?php echo esc_html__( 'Durum:', 'trendyol-woocommerce-importer' ); ?></strong> ${log.status}</p>
		<p><strong><?php echo esc_html__( 'Mesaj:', 'trendyol-woocommerce-importer' ); ?></strong> ${log.message}</p>
		${log.product_url ? `<p><strong><?php echo esc_html__( 'Ürün URL\'si:', 'trendyol-woocommerce-importer' ); ?></strong> <a href="${log.product_url}" target="_blank">${log.product_url}</a></p>` : ''}
		${oldData ? `<p><strong><?php echo esc_html__( 'Eski Veriler:', 'trendyol-woocommerce-importer' ); ?></strong> ${oldData}</p>` : ''}
		${newData ? `<p><strong><?php echo esc_html__( 'Yeni Veriler:', 'trendyol-woocommerce-importer' ); ?></strong> ${newData}</p>` : ''}
	`;

	modal.style.display = 'flex';
}

document.getElementById('logDetailsModal').addEventListener('click', function(e) {
	if (e.target === this) {
		this.style.display = 'none';
	}
});
</script>

<style>
.stats-row {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}
.stat-card {
	background: white;
	padding: 20px;
	border-radius: 8px;
	border-left: 4px solid #2563eb;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.stat-label {
	color: #64748b;
	font-size: 13px;
	font-weight: 600;
	margin-bottom: 8px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.stat-value {
	color: #1e293b;
	font-size: 24px;
	font-weight: 700;
}
.wp-list-table {
	margin: 0 !important;
}
.pagination {
	text-align: center;
	margin-top: 25px;
}
.pagination a,
.pagination span {
	padding: 8px 12px;
	margin: 0 4px;
	border: 1px solid #e2e8f0;
	border-radius: 4px;
	text-decoration: none;
	color: #2563eb;
}
.pagination a:hover {
	background-color: #f8fafc;
}
.pagination .current {
	background-color: #2563eb;
	color: white;
	border-color: #2563eb;
}
</style>