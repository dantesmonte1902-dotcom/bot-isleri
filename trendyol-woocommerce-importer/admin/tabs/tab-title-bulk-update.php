<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$service = new Trendyol_Title_Bulk_Update_Service();

if (
	isset( $_POST['trendyol_update_titles'] ) &&
	isset( $_POST['_wpnonce_titlebulk'] ) &&
	wp_verify_nonce( $_POST['_wpnonce_titlebulk'], 'trendyol_update_titles_nonce' )
) {
	$guncellenen = $service->update_draft_titles();

	echo '<div class="notice notice-success" style="margin:15px 0 18px 0;"><b>' . esc_html( $guncellenen ) . ' taslak ürün başlığı güncellendi!</b></div>';
}

if (
	isset( $_POST['trendyol_bulk_cat_update'] ) &&
	! empty( $_POST['new_bulk_cat'] ) &&
	isset( $_POST['_wpnonce_bulkcat'] ) &&
	wp_verify_nonce( $_POST['_wpnonce_bulkcat'], 'trendyol_bulk_cat_update' )
) {
	$result = $service->update_draft_categories( intval( $_POST['new_bulk_cat'] ) );

	echo '<div class="notice notice-success" style="margin:15px 0 18px 0;"><b>' . esc_html( $result['count'] ) . ' taslak ürün kategorisi "' . esc_html( $result['cat_name'] ) . '" olarak güncellendi!</b></div>';
}

$wc_kategoriler = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);

$counts        = $service->get_trendyol_product_counts();
$draft_count   = $counts['draft'];
$publish_count = $counts['publish'];
?>

<div class="trendyol-flex-row" style="display:flex; gap:32px; flex-wrap:wrap;">

	<div class="trendyol-card" style="padding:36px 28px; min-width:320px; flex:1 1 320px;">
		<h2 style="margin-top:0">Toplu Başlık Güncelle (Taslak Ürünler)</h2>
		<form method="post" onsubmit="return confirm('Tüm taslak ürün başlıkları güncellenecek. Emin misin?');">
			<?php wp_nonce_field( 'trendyol_update_titles_nonce', '_wpnonce_titlebulk' ); ?>
			<p>Taslak ürün başlıklarını şu şekilde günceller:<br>
				<code>[Kategori Adı] #Stok Kodu</code>
			</p>
			<button type="submit" name="trendyol_update_titles" class="button button-primary" style="font-size:16px; margin-top:15px; width:100%;">Güncelle!</button>
		</form>
		<div style="color:#64748b; margin-top:18px; font-size:13px;">Yalnızca “Taslak” ürünlerde işlem yapar.</div>
	</div>

	<div class="trendyol-card" style="padding:36px 28px; min-width:320px; flex:1 1 320px;">
		<h2 style="margin-top:0">Taslak Ürünlerin Kategorisini Toplu Güncelle</h2>
		<form method="post" onsubmit="return confirm('Tüm taslak ürünler için kategori değiştirilecek. Emin misiniz?');" style="display:flex; flex-direction:column; gap:18px;">
			<?php wp_nonce_field( 'trendyol_bulk_cat_update', '_wpnonce_bulkcat' ); ?>
			<label for="new_bulk_cat" style="font-size:15px; font-weight:500;">Yeni Kategori:</label>
			<select id="new_bulk_cat" name="new_bulk_cat" class="widefat" required style="max-width:300px;">
				<option value="">-- Seçiniz --</option>
				<?php foreach ( $wc_kategoriler as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>">
						<?php echo esc_html( $cat->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" name="trendyol_bulk_cat_update" class="button button-primary" style="font-size:16px; width:100%;">Kategoriyi Güncelle!</button>
		</form>
		<div style="color:#64748b; margin-top:12px; font-size:13px;">
			Seçilen kategori sadece taslak ürünlere uygulanır.
		</div>
	</div>

	<div class="trendyol-card trendyol-variant-sync-card" style="padding:36px 28px; min-width:380px; flex:1 1 420px;">
		<h2 style="margin-top:0">Toplu Varyant Stok Kontrolü</h2>

		<div class="trendyol-variant-sync-stats">
			<div class="sync-stat-box">
				<div class="sync-stat-number"><?php echo esc_html( $draft_count ); ?></div>
				<div class="sync-stat-label">Taslak ürün</div>
			</div>
			<div class="sync-stat-box">
				<div class="sync-stat-number"><?php echo esc_html( $publish_count ); ?></div>
				<div class="sync-stat-label">Yayındaki ürün</div>
			</div>
		</div>

		<p style="margin-bottom:18px; color:#64748b;">
			Trendyol’daki <code>value</code> / <code>beautifiedValue</code> ile WooCommerce varyasyonlarını eşleştirir.
			İşlem küçük batchler halinde çalışır.
		</p>

		<form id="trendyol-variant-sync-form" onsubmit="return false;">
			<div class="form-group" style="margin-bottom:16px;">
				<label for="variant_stock_status"><b>Kontrol edilecek ürün grubu</b></label>
				<select id="variant_stock_status" class="form-control" style="max-width:280px;">
					<option value="draft">Yalnızca TASLAK ürünler</option>
					<option value="publish">Yalnızca YAYINDA ürünler</option>
				</select>
			</div>

			<div class="form-group" style="margin-bottom:16px;">
				<label for="variant_stock_batch"><b>Batch boyutu</b></label>
				<input id="variant_stock_batch" type="number" min="1" max="50" value="10" class="form-control" style="max-width:120px;">
			</div>

			<div class="button-group" style="margin-top:0;">
				<button type="button" id="trendyol-variant-sync-start" class="button button-primary">
					<span class="vss-btn-text">Stokları Kontrol Et</span>
				</button>
			</div>
		</form>

		<div id="trendyol-variant-sync-progress" class="trendyol-variant-sync-progress" style="display:none;">
			<div class="trendyol-progress-header">
				<div><b>İşlem durumu</b></div>
				<div id="trendyol-variant-sync-status-text">Başlatılıyor...</div>
			</div>
			<div class="trendyol-progress-bar">
				<div id="trendyol-variant-sync-bar" class="trendyol-progress-bar-fill" style="width:0%;"></div>
			</div>
		</div>

		<div id="trendyol-variant-sync-live-log" class="trendyol-variant-sync-live-log" style="display:none;"></div>
		<div id="trendyol-variant-sync-summary" class="trendyol-variant-sync-summary" style="display:none;"></div>
	</div>
</div>

<script>
jQuery(function($){
	let syncRunning = false;
	let currentPage = 1;
	let batchSize = 10;
	let totalChangedProducts = [];
	let totalMessages = [];
	let approxTotal = 0;
	let processedApprox = 0;

	function escapeHtml(text) {
		return $('<div>').text(text).html();
	}

	function renderSummary(finalMessage) {
		let html = '';
		html += '<div class="variant-sync-summary-box">';
		html += '<h3 style="margin-top:0;">İşlem Tamamlandı</h3>';
		html += '<p style="margin-bottom:15px;">' + finalMessage + '</p>';

		if (totalChangedProducts.length > 0) {
			html += '<div class="variant-sync-result-title">Güncellenen ürünler</div>';
			html += '<ul class="variant-sync-result-list">';
			totalChangedProducts.forEach(function(product){
				let variations = '';
				if (product.variations && product.variations.length) {
					variations = product.variations.map(function(v){
						return '<span class="variant-pill">' + escapeHtml(v.size ? v.size : '-') + ' → ' + escapeHtml(v.stock) + '</span>';
					}).join(' ');
				}
				html += '<li>';
				html += '<a target="_blank" href="' + product.edit_url + '"><b>' + escapeHtml(product.title) + '</b></a>';
				html += '<div style="margin-top:8px;">' + variations + '</div>';
				html += '</li>';
			});
			html += '</ul>';
		} else {
			html += '<div class="variant-sync-empty">Stok değişikliği bulunan ürün çıkmadı.</div>';
		}

		$('#trendyol-variant-sync-summary').html(html).show();
	}

	function appendLiveLog(messages) {
		if (!messages || !messages.length) return;
		let html = '';
		messages.forEach(function(msg){
			html += '<div class="variant-sync-log-line">' + msg + '</div>';
		});
		$('#trendyol-variant-sync-live-log').append(html).show();
		let logBox = document.getElementById('trendyol-variant-sync-live-log');
		logBox.scrollTop = logBox.scrollHeight;
	}

	function updateProgressText(text, percent) {
		$('#trendyol-variant-sync-progress').show();
		$('#trendyol-variant-sync-status-text').text(text);
		$('#trendyol-variant-sync-bar').css('width', percent + '%');
	}

	function runBatch() {
		$.post(ajaxurl, {
			action: 'trendyol_variant_stock_sync',
			_wpnonce: '<?php echo wp_create_nonce( 'trendyol_variant_stock_sync' ); ?>',
			params: {
				page: currentPage,
				batch: batchSize,
				status: $('#variant_stock_status').val()
			}
		})
		.done(function(resp){
			if (!resp || !resp.success || !resp.data) {
				updateProgressText('Beklenmeyen hata oluştu.', 100);
				syncRunning = false;
				$('#trendyol-variant-sync-start').prop('disabled', false);
				return;
			}

			let data = resp.data;

			if (data.thisBatch && data.thisBatch.length) {
				totalMessages = totalMessages.concat(data.thisBatch);
				appendLiveLog(data.thisBatch);
			}

			if (data.changed && data.changed.length) {
				totalChangedProducts = totalChangedProducts.concat(data.changed);
			}

			processedApprox += batchSize;
			let percent = approxTotal > 0 ? Math.min(99, Math.round((processedApprox / approxTotal) * 100)) : 50;
			updateProgressText(data.message ? data.message : ('Batch ' + currentPage + ' işlendi...'), percent);

			if (data.more) {
				currentPage = data.page;
				setTimeout(runBatch, 350);
			} else {
				updateProgressText('Tüm batchler tamamlandı.', 100);
				renderSummary(data.message ? data.message : 'İşlem tamamlandı.');
				syncRunning = false;
				$('#trendyol-variant-sync-start').prop('disabled', false);
				$('#trendyol-variant-sync-start .vss-btn-text').text('Tekrar Çalıştır');
			}
		})
		.fail(function(){
			updateProgressText('AJAX isteği başarısız oldu.', 100);
			syncRunning = false;
			$('#trendyol-variant-sync-start').prop('disabled', false);
		});
	}

	$('#trendyol-variant-sync-start').on('click', function(){
		if (syncRunning) return;

		syncRunning = true;
		currentPage = 1;
		batchSize = parseInt($('#variant_stock_batch').val(), 10) || 10;
		totalChangedProducts = [];
		totalMessages = [];
		processedApprox = 0;

		let selectedStatus = $('#variant_stock_status').val();
		approxTotal = selectedStatus === 'publish' ? <?php echo (int) $publish_count; ?> : <?php echo (int) $draft_count; ?>;

		$('#trendyol-variant-sync-start').prop('disabled', true);
		$('#trendyol-variant-sync-start .vss-btn-text').text('Çalışıyor...');
		$('#trendyol-variant-sync-live-log').empty().hide();
		$('#trendyol-variant-sync-summary').empty().hide();

		updateProgressText('İşlem başlatıldı...', 3);
		runBatch();
	});
});
</script>