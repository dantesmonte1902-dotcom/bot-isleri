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

17. Trendyol arama / listeleme sayfalarında (örn: /sr?wg=2&wc=109&fp=true&sst=BEST_SELLER)
    ürün linklerini regex ile ayıklamak teorik olarak mümkün olsa da asıl sorun fetch aşamasında oluşuyor.
    Mevcut wp_remote_get ve cURL istekleri bu sayfalarda HTTP 403 dönüyor.
    Tespit: sorun parser değil, Trendyol'un arama/listeleme sayfalarındaki bot koruması / WAF engeli.
    Yani WordPress sunucusundan gelen basit browser-benzeri istekler HTML'yi alamadan engelleniyor.
    /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/ty.html dosyası ise tarayıcıdan
    manuel kaydedilmiş bir örnek olduğu için içinde ürün linki patternleri bulunabiliyor; bu canlı fetch'in
    çalıştığını değil, sadece HTML elde edilirse linklerin ayıklanabileceğini gösteriyor.
    Sonuç: /sr tabanlı arama sayfaları için mevcut scraper ile doğrudan canlı HTML çekmek güvenilir değil.
    Bu tip sayfalarda veri almak için farklı bir kaynak (izinli API, tarayıcı otomasyonu veya önceden alınmış HTML)
    gerekir.

18. "Sadece PHP ile kesin çözüm üretebilir miyiz?" sorusunun cevabı pratikte hayır.
    Normal PHP istekleri (wp_remote_get, cURL, header/cookie taklidi) Trendyol bot korumasını aşmayı garanti etmez.
    Yani salt PHP ile çalışan ve her zaman stabil sonuç veren bir çözüm burada garanti edilemez.
    PHP tarafında ancak şu senaryolar güvenilir kabul edilebilir:
    - Trendyol'un izin verdiği resmi / yasal bir API kullanılırsa
    - HTML başka bir katmanda gerçek tarayıcı ile alınıp PHP'ye verilirse
    - Önceden kaydedilmiş HTML veya ürün link listesi PHP içinde işlenirse
    Bunun dışında "PHP tek başına canlı /sr sayfasını her zaman çeker" şeklinde kesin bir çözüm bu vaka için doğru değildir.

19. Kesin çözüm önerisi burada kalıcı dursun:
    Eğer bu iş için gerçekten stabil ve tekrar edilebilir bir çözüm isteniyorsa öneri şudur:
    - En doğru çözüm: Trendyol'un izin verdiği resmi / yasal bir API kullanmak
    - İkinci sağlam çözüm: HTML'i gerçek tarayıcı ile başka bir katmanda alıp PHP tarafına vermek
    - PHP hosting içinde en pratik çözüm: önceden alınmış HTML veya doğrudan ürün link listesi ile import yapmak
    Kısa özet:
    "Canlı /sr sayfasını sadece PHP ile sürekli ve garantili çekmek" bu vaka için kesin çözüm değildir.
    "Kalıcı ve anlaşılır çözüm" olarak bu dosyada tutulması gereken öneri, API / browser katmanı / hazır link listesi üçlüsüdür.

Değişen dosyalar
----------------

- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/services/class-title-ai-update-service.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/readme.txt
