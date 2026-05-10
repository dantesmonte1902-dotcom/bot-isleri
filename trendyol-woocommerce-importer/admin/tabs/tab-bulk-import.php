<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<script>
if(typeof ajaxurl==="undefined"){var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';}
</script>
<?php
$data_dir = dirname(dirname(__DIR__)) . '/data/';
$query_service = new Trendyol_Product_Query_Service();

$existing_txts = [];
foreach (glob($data_dir . '*-urunleri.txt') as $file) {
	$existing_txts[] = basename($file);
}

$bulkfile = '';
if (isset($_POST['choose_bulkfile']) && !empty($_POST['bulkfile_select'])) {
	$bulkfile = $data_dir . basename($_POST['bulkfile_select']);
	$_SESSION['trendyol_last_bulkfile'] = basename($_POST['bulkfile_select']);
} elseif (isset($_SESSION['trendyol_last_bulkfile'])) {
	$bulkfile = $data_dir . basename($_SESSION['trendyol_last_bulkfile']);
}

if (!empty($_SESSION['bulk_import_result'])) {
	echo '<div class="alert alert-info" style="margin-bottom:20px;max-height:340px;overflow:auto;font-size:14px;"><b>Sonuçlar:</b><br>' . $_SESSION['bulk_import_result'] . '</div>';
	unset($_SESSION['bulk_import_result']);
}

$kategori_ad = '';
if (!empty($bulkfile)) {
	$f = basename($bulkfile);
	if (preg_match('/^([^-]+)-urunleri\.txt$/i', $f, $m)) {
		$kategori_ad = function_exists('trendyol_normalize_cat') ? trendyol_normalize_cat(strtolower($m[1])) : sanitize_title($m[1]);
	}
}
?>

<div class="trendyol-card">
	<div class="trendyol-section">
		<h2>Toplu Ürün Ekle (Trendyol)</h2>
		<p>
			Her satıra bir Trendyol ürün linki girerek veya .txt dosya yükleyerek toplu otomatik ekleme yapın.<br>
			Aynı Trendyol linkinden ürün tekrar eklenmez.<br>
			<b>Kategori sekmesinden oluşturulan ürün .txt dosyalarını buradan anında içeri aktarabilirsiniz.</b>
		</p>
	</div>

	<?php if (!empty($existing_txts)): ?>
		<form method="post" style="margin-bottom:9px;display:flex;align-items:center;gap:8px;">
			<label for="bulkfile_select" style="margin-bottom:0;font-weight:500;color:#334155;">Kayıtlı Ürün Linki Dosyası:</label>
			<select name="bulkfile_select" id="bulkfile_select" class="form-control" style="max-width:220px;">
				<?php foreach($existing_txts as $filename): ?>
					<option value="<?php echo esc_attr($filename); ?>" <?php selected(isset($bulkfile) ? basename($bulkfile) : '', $filename); ?>>
						<?php echo esc_html($filename); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-secondary" name="choose_bulkfile" value="1">Bu Dosyayı Doldur</button>
		</form>
	<?php endif; ?>

	<form id="trendyol-bulk-import-form" method="post" action="" enctype="multipart/form-data" class="trendyol-form" onsubmit="return false;">
		<?php wp_nonce_field( 'trendyol_bulk_import_nonce' ); ?>
		<input type="hidden" name="trendyol_bulk_import" value="1" />
		<input type="hidden" name="bulk_kategori_ad" id="bulk_kategori_ad" value="<?php echo esc_attr($kategori_ad); ?>">

		<div class="form-group">
			<label for="bulk_links">Trendyol Ürün Linkleri (Her satır bir ürün linki olacak şekilde):</label>
			<textarea name="bulk_links" rows="7" id="bulk_links" class="form-control" placeholder="https://www.trendyol.com/marka/model-p-123..."><?php
				if ($bulkfile && file_exists($bulkfile)) {
					echo esc_textarea(file_get_contents($bulkfile));
				}
			?></textarea>
		</div>

		<div style="margin-top:-8px;margin-bottom:10px;text-align:center;">veya</div>

		<div class="form-group">
			<label for="bulk_file">.txt Dosyası (Her satır Trendyol ürün linki olacak):</label>
			<input type="file" name="bulk_file" id="bulk_file" accept=".txt" class="form-control" style="padding:6px;">
		</div>

		<div class="button-group">
			<button type="button" id="bulk-import-launcher" class="button button-primary button-large" style="width:260px;">🚀 Toplu Ekleme Başlat</button>
		</div>
	</form>

	<div id="bulk-import-status" style="font-size:14px; margin-top:18px;"></div>
</div>

<script>
async function readBulkFileLines(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.onload = function(e) {
			const text = e.target.result || '';
			const lines = text.split(/\r?\n/).map(x => x.trim()).filter(Boolean);
			resolve(lines);
		};
		reader.onerror = reject;
		reader.readAsText(file);
	});
}

document.getElementById('bulk-import-launcher').onclick = async function() {
	const status = document.getElementById('bulk-import-status');
	status.innerHTML = '<b>Hazırlanıyor...</b>';

	const ta = document.getElementById('bulk_links');
	let links = ta.value.split(/\r?\n/).map(x => x.trim()).filter(Boolean);

	if (links.length === 0) {
		const fileInput = document.getElementById('bulk_file');
		if (fileInput.files && fileInput.files[0]) {
			try {
				links = await readBulkFileLines(fileInput.files[0]);
			} catch (e) {
				status.innerHTML = '<span style="color:red">Dosya okunamadı.</span>';
				return;
			}
		}
	}

	if (links.length === 0) {
		status.innerHTML = '<span style="color:red">Lütfen en az 1 ürün linki giriniz veya .txt dosyası seçiniz.</span>';
		return;
	}

	if (!confirm('Toplu ürün ekleme başlatılsın mı?\nÜrünler 10’arlık gruplar halinde otomatik eklenecek.')) return;

	const nonce = document.querySelector('#trendyol-bulk-import-form input[name="_wpnonce"]').value;
	const kategori_ad = document.getElementById('bulk_kategori_ad').value || '';

	let toplam = links.length, eklendi = 0, skip = 0, hata = 0, start = 0, batchsize = 10;
	status.innerHTML = `<b>${toplam} ürün işlenecek, başladı...</b><br>`;

	while (start < toplam) {
		const thisLinks = links.slice(start, start + batchsize);
		status.innerHTML += `<span style="color:#2563eb">▶️ #${start+1}–${start+thisLinks.length} arası işleniyor...</span><br>`;

		const body = new URLSearchParams({
			action: "trendyol_bulk_import_ajax",
			_wpnonce: nonce,
			links: JSON.stringify(thisLinks),
			kategori_ad: kategori_ad
		});

		let resp;
		let data;

		try {
			resp = await fetch(ajaxurl, {method:"POST", credentials:"same-origin", body});
			data = await resp.json();
		} catch (e) {
			status.innerHTML += `<span style="color:red">AJAX/JSON hatası: ${e.message}</span><br>`;
			hata += thisLinks.length;
			start += batchsize;
			continue;
		}

		if (!data || !data.results) {
			status.innerHTML += `<span style="color:red">Geçersiz sunucu cevabı alındı.</span><br>`;
			hata += thisLinks.length;
			start += batchsize;
			continue;
		}

		for (let msg of data.results) {
			status.innerHTML += msg.text + "<br>";
			if (msg.ok === true) eklendi++;
			else if (msg.ok === 'skip') skip++;
			else hata++;
		}

		if (data.summary) {
			status.innerHTML += `<small style="color:#64748b">Batch Özeti → Başarılı: ${data.summary.success}, Atlanan: ${data.summary.skipped}, Hata: ${data.summary.failed}</small><br>`;
		}

		start += batchsize;
		await new Promise(res => setTimeout(res, 700));
	}

	status.innerHTML += `<hr><b style="color:green">Bitti. Başarıyla eklenen: ${eklendi}, atlanan: ${skip}, hata: ${hata}</b>`;
};
</script>
