Trendyol WooCommerce İçe Aktarıcı - Güncelleme Notları
======================================================

Tarih: 2026-05-18

AI Başlık Güncelle özelliğinde yapılan son geliştirmeler
--------------------------------------------------------

1. Toplu AI başlık üretim akışı JSON tabanlı hale getirildi.
   Batch modunda AI artık sadece JSON dizi döndürmek zorunda.

2. Batch prompt yapısı değiştirildi.
   AI'den yalnızca şu yapıda cevap isteniyor:
   [{"id":1,"title":"..."},{"id":2,"title":"..."}]

3. Batch eşleştirme sıra numarasına göre değil id alanına göre yapılıyor.
   AI sıralamayı bozsa bile doğru ürün doğru başlıkla eşleşiyor.

4. JSON validation katmanı eklendi.
   AI çıktısı için şu kontroller yapılıyor:
   - JSON geçerli mi
   - input ürün sayısı ile output item sayısı eşit mi
   - id alanları eksiksiz mi
   - duplicate veya beklenmeyen id var mı
   - title alanı boş mu

5. JSON parse / item count / eksik-fazla kayıt hatalarında batch otomatik retry alıyor.
   Retry limiti mevcut ayar üzerinden çalışıyor.

6. Retry sonrasında batch hâlâ başarısızsa provider fallback zinciri güçlendirildi.
   Batch modunda provider sırası genişletilerek Gemini -> OpenRouter -> Custom AI
   hattı da devreye girebiliyor.

7. Her batch denemesi için log detayları artırıldı.
   Loglara artık şu alanlar yazılıyor:
   - batch id
   - provider adı
   - input count
   - output count
   - success / fail durumu
   - deneme numarası

8. Tekli ürün başlığı üretim akışı korunarak bırakıldı.
   Sadece toplu batch işleme sistemi JSON tabanlı yeni mantığa taşındı.

9. Rate limit, retry-after ve dakika başı istek limiti desteği korunmaya devam ediyor.

10. Başarılı batch sonrası bekleme ve throttling davranışı korunarak mevcut sistem bozulmadı.

11. Toplu AI ürün başlığı üretimindeki "ürün sırasıyla eşleştirilemedi" hatasına neden olan
    numaralı liste bağımlılığı kaldırıldı.

12. Admin paneldeki mevcut AI batch ayarları kullanılmaya devam ediyor.

13. Batch fallback zinciri güvenli hale getirildi.
    Eksik yapılandırmalı provider'lar artık request atmadan otomatik skip ediliyor.

14. Provider skip sebepleri loglanıyor.
    Örnek nedenler:
    - missing api key
    - missing endpoint
    - missing model

15. Batch modunda fallback listesi sadece geçerli provider'lardan kuruluyor.
    Bu sayede boş Custom AI ayarları tüm batch işlemini fail etmiyor.

16. OpenAI uyumlu provider istekleri artık kontrollü output token limiti ile gönderiliyor.
    Bu sayede OpenRouter varsayılan çok yüksek max token isteği yüzünden batch hata vermez.

Değişen dosyalar
----------------

- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/services/class-title-ai-update-service.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/readme.txt
