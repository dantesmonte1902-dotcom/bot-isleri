<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

// Stats
$draft_count  = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts p
    INNER JOIN $wpdb->postmeta pm ON p.ID=pm.post_id
    WHERE p.post_type='product' AND p.post_status='draft' 
    AND pm.meta_key='trendyol_product_url' AND pm.meta_value!=''
");
$publish_count  = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts p
    INNER JOIN $wpdb->postmeta pm ON p.ID=pm.post_id
    WHERE p.post_type='product' AND p.post_status='publish'
    AND pm.meta_key='trendyol_product_url' AND pm.meta_value!=''
");
?>
<div class="trendyol-card">
    <h2>🛒 Toplu Varyant Stok Kontrolü (Trendyol Eşleştirme)</h2>

    <div style="margin:15px 0 18px 0;">
        <b>• Taslak ürün sayısı:</b> <span style="color:#2563eb"><?php echo intval($draft_count); ?></span><br>
        <b>• Yayınlanmış ürün sayısı:</b> <span style="color:#22c55e"><?php echo intval($publish_count); ?></span><br>
        <span style="color:#64748b;font-size:13px;">Yalnızca trendyol_product_url meta verisi olan ürünler için çalışır. Her ürünün tüm varyantları Trendyol'dan kontrol edilir; stok dışı ise güncellenir.</span>
    </div>

    <form id="variant-stock-sync-form">
        <div>
            <label>
                <input type="radio" name="status" value="draft" checked>
                👷 <b>Taslak Ürünler</b>
            </label>
            &nbsp;&nbsp;
            <label>
                <input type="radio" name="status" value="publish">
                📦 Yayında Olanlar
            </label>
        </div>
        <div style="margin:14px 0;">
            <button type="submit" id="vss-start-btn" class="button button-primary" style="padding: 10px 20px; font-size:16px;">
                <span id="vss-btn-txt">🟢 Kontrolü Başlat</span>
                <span id="vss-loader" style="display:none">⏳</span>
            </button>
        </div>
        <div id="vss-result"></div>
    </form>
</div>
<script>
(function($){
    let currentPage = 1, finished = false, resultsChanged = [], batchSize = 10;
    let runParams = {};
    $('#variant-stock-sync-form').submit(function(e){
        e.preventDefault();
        if(finished) { window.location.reload(); return false; }
        $('#vss-result').html('');
        $('#vss-btn-txt').text('⏳ Başlatılıyor...');
        $('#vss-loader').show();
        currentPage = 1; finished = false; resultsChanged = [];
        runParams = {
            status: $('input[name="status"]:checked').val(),
            page: 1, batch: batchSize
        };
        doBatch();
    });
    function doBatch() {
        $.post(ajaxurl, {
            action: 'trendyol_variant_stock_sync',
            params: runParams,
            _wpnonce: '<?php echo wp_create_nonce('trendyol_variant_stock_sync'); ?>'
        }, function(resp){
            if (resp.success) {
                if (resp.data && resp.data.changed && resp.data.changed.length) {
                    resultsChanged = resultsChanged.concat(resp.data.changed);
                }
                let html = '';
                if(resp.data && resp.data.message) html += '<b>'+resp.data.message+'</b><br>';
                if(resp.data && resp.data.thisBatch && resp.data.thisBatch.length) {
                    html += '<ul>';
                    resp.data.thisBatch.forEach(x=>{
                        html+=`<li>${x}</li>`;
                    });
                    html+='</ul>';
                }
                $('#vss-result').append(html);
                if (resp.data.more) {
                    runParams.page = resp.data.page;
                    setTimeout(doBatch, 350);
                } else {
                    finished = true;
                    $('#vss-loader').hide();
                    $('#vss-btn-txt').text('🔁 Yeniden başlat');
                    if(resultsChanged.length){
                        $('#vss-result').append('<hr><b>Güncellenen Ürünler (<span style="color:red">'+resultsChanged.length+'</span>):</b><div style="max-height:220px;overflow:auto;"><ul>' + resultsChanged.map(function(x){return '<li>'+x+'</li>'}).join('')+'</ul></div>');
                    }
                }
            }
            else {
                $('#vss-result').html('<span style="color:red">'+((resp.data&&resp.data.message)?resp.data.message:'Hata oldu')+'</span>');
                $('#vss-loader').hide();
            }
        });
    }
})(jQuery);
</script>