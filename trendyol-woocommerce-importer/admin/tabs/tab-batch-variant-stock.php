<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2>Toplu Varyant Stok Kontrolü</h2>

<form id="variant-stock-form" onsubmit="return false;">
	<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'trendyol_variant_stock_sync' ) ); ?>">

	<label>
		<b>Ürün Statüsü:</b>
		<select name="status">
			<option value="draft">Taslak (önerilen)</option>
			<option value="publish">Yayında</option>
			<option value="both">İkisi</option>
		</select>
	</label>

	<label style="margin-left:30px">
		<b>Batch Boyutu:</b>
		<input type="number" name="batch" value="10" min="1" max="50" style="width:70px">
	</label>

	<button id="start-sync" class="button button-primary">✔️ Başlat</button>
</form>

<div id="variant-sync-progress" style="margin:18px 0 0 0"></div>
<div id="variant-sync-updated"></div>

<script>
jQuery(function($){
	let page = 1;
	let running = false;
	let allChanged = [];
	let allLog = [];

	$('#start-sync').on('click', function(){
		if (running) return;

		running = true;
		page = 1;
		allChanged = [];
		allLog = [];

		$('#variant-sync-progress').html('<b>Başladı...</b>');
		$('#variant-sync-updated').empty();

		callNext();
	});

	function callNext(){
		let status = $('#variant-stock-form select[name="status"]').val();
		let batch = parseInt($('#variant-stock-form input[name="batch"]').val(), 10) || 10;
		let nonce = $('#variant-stock-form input[name="_wpnonce"]').val();

		$.post(ajaxurl, {
			action: 'trendyol_variant_stock_sync',
			_wpnonce: nonce,
			params: {
				page: page,
				batch: batch,
				status: status
			}
		}, function(resp){
			if (resp.success && resp.data) {
				let data = resp.data;

				if (data.changed && data.changed.length) {
					allChanged = allChanged.concat(data.changed);
				}

				if (data.thisBatch && data.thisBatch.length) {
					allLog = allLog.concat(data.thisBatch);
				}

				$('#variant-sync-progress').html('<b>Durum:</b> ' + (data.message ? data.message : 'Devam ediyor...'));

				if (data.more) {
					page = data.page || (page + 1);
					setTimeout(callNext, 250);
				} else {
					showResults(data.message ? data.message : 'İşlem tamamlandı.');
					running = false;
					$('#variant-sync-progress').html('<b>Tamamlandı.</b> ' + (data.message ? data.message : ''));
				}
			} else {
				let msg = resp && resp.data && resp.data.message ? resp.data.message : 'Bilinmeyen hata';
				$('#variant-sync-progress').html('Hata: ' + msg);
				running = false;
			}
		}).fail(function(){
			$('#variant-sync-progress').html('Hata: AJAX isteği başarısız oldu.');
			running = false;
		});
	}

	function showResults(finalMessage){
		let html = '<h3>Stok durumu değişenler:</h3>';
		html += '<p><b>' + finalMessage + '</b></p>';

		if (allChanged.length) {
			html += '<ul>';
			allChanged.forEach(function(product){
				if (product.variations && product.variations.length) {
					product.variations.forEach(function(v){
						html += '<li>Ürün: <b>#' + product.product_id + '</b> | Varyasyon: <b>#' + v.variation_id + '</b> (' + (v.size || '-') + ') → <b>' + v.stock + '</b></li>';
					});
				}
			});
			html += '</ul>';
		} else {
			html += '<i>Kayda değer değişiklik yok.</i>';
		}

		if (allLog.length) {
			html += '<h4>Log:</h4><pre style="background:#f8fafc;padding:10px;border:1px solid #ddd;max-height:260px;overflow:auto;">' + allLog.join('\n') + '</pre>';
		}

		$('#variant-sync-updated').html(html);
	}
});
</script>

<style>
#variant-sync-updated ul { margin-left: 20px; }
</style>