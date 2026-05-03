<?php
/**
 * Tek ürün varyant stok debug dosyası
 * Kullanım:
 * wp-content/plugins/trendyol-woocommerce-importer/varyant_stok_debug.php?product_id=8845
 */

define('WP_USE_THEMES', false);
require_once dirname(__FILE__, 4) . '/wp-load.php';

if ( ! current_user_can('manage_woocommerce') ) {
    wp_die('Yetkisiz erişim');
}

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
if (!$product_id) {
    wp_die('Lütfen product_id verin. Örnek: ?product_id=8845');
}

function vss_debug_norm($txt) {
    $txt = (string) $txt;
    $txt = mb_strtolower(trim($txt), 'UTF-8');
    $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = str_replace(array('ı','İ','i̇'), 'i', $txt);
    $txt = str_replace(array('ü'), 'u', $txt);
    $txt = str_replace(array('ö'), 'o', $txt);
    $txt = str_replace(array('ş'), 's', $txt);
    $txt = str_replace(array('ç'), 'c', $txt);
    $txt = str_replace(array('ğ'), 'g', $txt);
    $txt = preg_replace('/[^\p{L}\p{N}\s\-]+/u', '', $txt);
    $txt = preg_replace('/[\s\-_]+/u', '-', $txt);
    $txt = trim($txt, '-');
    return $txt;
}

$product = get_post($product_id);

echo '<html><head><meta charset="utf-8"><title>Varyant Stok Debug</title>';
echo '<style>
body{font-family:Arial,sans-serif;background:#f8fafc;color:#1e293b;padding:20px}
.box{background:#fff;border:1px solid #ddd;border-radius:10px;padding:18px;margin-bottom:18px}
h1,h2{margin-top:0}
pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto}
.ok{color:green;font-weight:bold}
.err{color:#dc2626;font-weight:bold}
.warn{color:#d97706;font-weight:bold}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #ddd;padding:8px;text-align:left}
code{background:#eef2ff;padding:2px 6px;border-radius:4px}
</style></head><body>';

echo '<h1>Tek Ürün Varyant Stok Debug</h1>';

echo '<div class="box">';
echo '<h2>1) WordPress Ürün Kontrolü</h2>';

if (!$product) {
    echo '<div class="err">Ürün bulunamadı: ' . esc_html($product_id) . '</div>';
    echo '</div></body></html>';
    exit;
}

echo '<table>';
echo '<tr><th>Alan</th><th>Değer</th></tr>';
echo '<tr><td>Ürün ID</td><td>' . esc_html($product_id) . '</td></tr>';
echo '<tr><td>Başlık</td><td>' . esc_html($product->post_title) . '</td></tr>';
echo '<tr><td>Post Type</td><td>' . esc_html($product->post_type) . '</td></tr>';
echo '<tr><td>Post Status</td><td>' . esc_html($product->post_status) . '</td></tr>';
echo '</table>';
echo '</div>';

$trendyol_url = get_post_meta($product_id, 'trendyol_product_url', true);

echo '<div class="box">';
echo '<h2>2) trendyol_product_url Meta</h2>';
if ($trendyol_url) {
    echo '<div class="ok">Bulundu</div>';
    echo '<p><a href="' . esc_url($trendyol_url) . '" target="_blank">' . esc_html($trendyol_url) . '</a></p>';
} else {
    echo '<div class="err">trendyol_product_url bulunamadı</div>';
}
echo '</div>';

$variation_ids = get_posts(array(
    'post_type'   => 'product_variation',
    'post_parent' => $product_id,
    'numberposts' => -1,
    'fields'      => 'ids',
));

echo '<div class="box">';
echo '<h2>3) WooCommerce Varyasyonları</h2>';

if (empty($variation_ids)) {
    echo '<div class="warn">Bu üründe varyasyon yok</div>';
} else {
    echo '<div class="ok">' . count($variation_ids) . ' varyasyon bulundu</div>';
    echo '<table>';
    echo '<tr><th>Variation ID</th><th>attribute_pa_beden</th><th>Normalized</th><th>_stock_status</th><th>Tüm attribute meta</th></tr>';

    foreach ($variation_ids as $vid) {
        $beden = get_post_meta($vid, 'attribute_pa_beden', true);
        $stock = get_post_meta($vid, '_stock_status', true);

        $all_meta = get_post_meta($vid);
        $attr_meta = array();
        foreach ($all_meta as $k => $v) {
            if (strpos($k, 'attribute_') === 0) {
                $attr_meta[$k] = is_array($v) ? reset($v) : $v;
            }
        }

        echo '<tr>';
        echo '<td>' . esc_html($vid) . '</td>';
        echo '<td>' . esc_html(is_array($beden) ? reset($beden) : $beden) . '</td>';
        echo '<td><code>' . esc_html(vss_debug_norm(is_array($beden) ? reset($beden) : $beden)) . '</code></td>';
        echo '<td>' . esc_html($stock) . '</td>';
        echo '<td><pre>' . esc_html(print_r($attr_meta, true)) . '</pre></td>';
        echo '</tr>';
    }

    echo '</table>';
}
echo '</div>';

echo '<div class="box">';
echo '<h2>4) Trendyol Sayfasını Çekme</h2>';

if (!$trendyol_url) {
    echo '<div class="err">URL olmadığı için devam edemem</div>';
    echo '</div></body></html>';
    exit;
}

$response = wp_remote_get($trendyol_url, array(
    'timeout'    => 25,
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
));

if (is_wp_error($response)) {
    echo '<div class="err">wp_remote_get hatası: ' . esc_html($response->get_error_message()) . '</div>';
    echo '</div></body></html>';
    exit;
}

$code = wp_remote_retrieve_response_code($response);
$html = wp_remote_retrieve_body($response);

echo '<table>';
echo '<tr><th>HTTP Kod</th><td>' . esc_html($code) . '</td></tr>';
echo '<tr><th>HTML Uzunluğu</th><td>' . esc_html(strlen($html)) . '</td></tr>';
echo '</table>';

if ($code !== 200 || empty($html)) {
    echo '<div class="err">HTML boş veya başarısız</div>';
    echo '</div></body></html>';
    exit;
}
echo '<div class="ok">HTML başarıyla çekildi</div>';
echo '</div>';

echo '<div class="box">';
echo '<h2>5) Trendyol Varyant JSON Tespiti</h2>';

$stock_map = array();
$raw_found = array();

preg_match_all(
    '/\{[^{}]*("value"\s*:\s*".+?")[^{}]*("beautifiedValue"\s*:\s*".+?")?[^{}]*"inStock"\s*:\s*(true|false)[^{}]*\}/',
    $html,
    $matches,
    PREG_SET_ORDER
);

if (!empty($matches)) {
    foreach ($matches as $m) {
        $value = '';
        $beautified = '';

        if (preg_match('/"value"\s*:\s*"([^"]+?)"/', $m[0], $mv)) {
            $value = $mv[1];
        }
        if (preg_match('/"beautifiedValue"\s*:\s*"([^"]+?)"/', $m[0], $mb)) {
            $beautified = $mb[1];
        }

        $inStock = (strpos($m[0], '"inStock":false') === false && strpos($m[0], '"inStock" : false') === false);

        $raw_found[] = array(
            'value' => $value,
            'beautifiedValue' => $beautified,
            'inStock' => $inStock ? 'true' : 'false',
            'norm_value' => vss_debug_norm($value),
            'norm_beautified' => vss_debug_norm($beautified),
        );

        if ($value !== '') {
            $stock_map[vss_debug_norm($value)] = $inStock;
        }
        if ($beautified !== '') {
            $stock_map[vss_debug_norm($beautified)] = $inStock;
        }
    }
}

if (empty($raw_found)) {
    echo '<div class="err">Trendyol varyant verisi bulunamadı</div>';
} else {
    echo '<div class="ok">' . count($raw_found) . ' varyant girdisi bulundu</div>';
    echo '<table>';
    echo '<tr><th>value</th><th>beautifiedValue</th><th>norm(value)</th><th>norm(beautified)</th><th>inStock</th></tr>';
    foreach ($raw_found as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['value']) . '</td>';
        echo '<td>' . esc_html($row['beautifiedValue']) . '</td>';
        echo '<td><code>' . esc_html($row['norm_value']) . '</code></td>';
        echo '<td><code>' . esc_html($row['norm_beautified']) . '</code></td>';
        echo '<td>' . esc_html($row['inStock']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

echo '<div class="box">';
echo '<h2>6) Eşleşme Testi</h2>';

if (empty($variation_ids) || empty($stock_map)) {
    echo '<div class="warn">Eşleşme testi için yeterli veri yok</div>';
} else {
    echo '<table>';
    echo '<tr><th>Variation ID</th><th>Woo beden</th><th>Normalized</th><th>Trendyol eşleşti mi?</th><th>Trendyol stok</th><th>Mevcut stok</th><th>Değişmeli mi?</th></tr>';

    foreach ($variation_ids as $vid) {
        $beden = get_post_meta($vid, 'attribute_pa_beden', true);
        $stock = get_post_meta($vid, '_stock_status', true);
        $beden_raw = is_array($beden) ? reset($beden) : $beden;
        $beden_norm = vss_debug_norm($beden_raw);

        $matched = array_key_exists($beden_norm, $stock_map);
        $tr_stock = $matched ? ($stock_map[$beden_norm] ? 'instock' : 'outofstock') : '-';
        $change = ($matched && $tr_stock !== $stock) ? 'EVET' : 'HAYIR';

        echo '<tr>';
        echo '<td>' . esc_html($vid) . '</td>';
        echo '<td>' . esc_html($beden_raw) . '</td>';
        echo '<td><code>' . esc_html($beden_norm) . '</code></td>';
        echo '<td>' . ($matched ? '<span class="ok">Evet</span>' : '<span class="err">Hayır</span>') . '</td>';
        echo '<td>' . esc_html($tr_stock) . '</td>';
        echo '<td>' . esc_html($stock) . '</td>';
        echo '<td>' . ( $change === 'EVET' ? '<span class="warn">EVET</span>' : 'HAYIR' ) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
}
echo '</div>';

echo '<div class="box">';
echo '<h2>7) Ham HTML içinde hızlı arama</h2>';
echo '<p>Aşağıda sadece kontrol amaçlı ilk 20 adet <code>inStock</code> geçen parça gösterilir:</p>';

preg_match_all('/.{0,120}inStock.{0,120}/u', $html, $raw_lines);
$raw_lines = array_slice(array_unique($raw_lines[0]), 0, 20);

if (!empty($raw_lines)) {
    echo '<pre>' . esc_html(implode("\n\n---\n\n", $raw_lines)) . '</pre>';
} else {
    echo '<div class="warn">inStock içeren satır/parça bulunamadı.</div>';
}
echo '</div>';

echo '</body></html>';