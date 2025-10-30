# DOKUMENTASI PROSES BISNIS SISTEM CARBON CREDIT MARKETPLACE

## RINGKASAN SISTEM

Sistem ini adalah marketplace untuk jual-beli kredit karbon (carbon credits) yang terintegrasi dengan monitoring emisi kendaraan melalui sensor IoT/MQTT. Sistem melibatkan 3 aktor utama:
- **User (Pemilik Kendaraan)**: Mendapatkan kuota karbon, dapat menjual kelebihan kuota
- **Admin**: Membeli kuota dari user, menjual kembali ke user lain
- **Payment Gateway (Midtrans)**: Memproses pembayaran dan payout

---

## 1. PROSES PENDAFTARAN & ALOKASI KUOTA KARBON

### 1.1 Registrasi Kendaraan (User)
**File**: `CarbonCreditController.php` → `store()`

**Alur Proses**:
1. User mendaftarkan kendaraan dengan data:
   - Nomor Kartu Keluarga
   - NIK e-KTP
   - NRKB (Nomor Registrasi Kendaraan Bermotor)
   - Nomor Rangka (5 digit)
   - Tipe kendaraan (mobil/motor)
   - Kepemilikan kendaraan (milik sendiri/keluarga)

2. Status awal:
   - User → `pending` (menunggu approval admin)
   - Admin → `available` (langsung tersedia)

3. Data disimpan ke tabel `carbon_credits` dengan:
   - `price_per_unit` = 100 (harga tetap)
   - `amount` = 0 (belum ada kuota)
   - `quantity_to_sell` = 0

### 1.2 Approval & Alokasi Kuota (Admin)
**File**: `CarbonCreditController.php` → `approve()`

**Alur Proses**:
1. Admin mereview pendaftaran kendaraan user
2. Jika disetujui, sistem memberikan kuota awal:
   - **Mobil**: 800 kg CO2eq
   - **Motor**: 500 kg CO2eq

3. Status berubah menjadi `available`
4. Field yang diupdate:
   - `status` = 'available'
   - `amount` = kuota awal (800/500 kg)

**Catatan**: Kuota ini adalah alokasi maksimal emisi yang diperbolehkan. User yang menggunakan lebih sedikit dari kuota dapat menjual sisanya.

---

## 2. PROSES PENJUALAN KUOTA KARBON (USER → ADMIN)

### 2.1 Monitoring Emisi Real-time
**File**: `CarbonCredit.php` → `updateEmissionData()`

**Alur Proses**:
1. Sensor IoT pada kendaraan mengirim data emisi via MQTT
2. Sistem mencatat:
   - `current_co2e_mg_m3`: Emisi saat ini
   - `daily_emissions_kg`: Total emisi hari ini
   - `monthly_emissions_kg`: Total emisi bulan ini
   - `total_emissions_kg`: Total emisi keseluruhan

3. Sistem menghitung **Effective Quota**:
   ```
   Effective Quota = Total Kuota - Emisi Harian
   ```
   Contoh: Kuota 800 kg - Emisi 50 kg = 750 kg tersedia untuk dijual

### 2.2 Pengajuan Penjualan (User)
**File**: `CarbonCreditController.php` → `submitSaleRequest()`

**Alur Proses**:
1. User mengajukan penjualan dengan menentukan:
   - `quantity_to_sell`: Jumlah kuota yang ingin dijual
   - Maksimal = Effective Quota (kuota - emisi harian)

2. Validasi sistem:
   - Quantity tidak boleh melebihi effective quota
   - Harga per unit tetap = 100

3. Status berubah menjadi `pending_sale`
4. Field yang diupdate:
   - `status` = 'pending_sale'
   - `sale_price_per_unit` = 100
   - `quantity_to_sell` = jumlah yang diajukan
   - `sale_requested_at` = timestamp pengajuan

### 2.3 Approval Penjualan (Admin)
**File**: `CarbonCreditController.php` → `approveSaleRequest()`

**Alur Proses**:
1. Admin mereview pengajuan penjualan
2. Sistem melakukan validasi ulang:
   - Recalculate effective quota saat approval
   - Cap quantity_to_sell jika emisi bertambah

3. Jika disetujui:
   - `status` = 'available' (masuk marketplace)
   - `sale_approved_at` = timestamp approval
   - `price_per_unit` = 100

4. Kuota sekarang tersedia di marketplace untuk dibeli admin

### 2.4 Validasi Marketplace
**File**: `MarketplaceController.php` → `validateMarketplaceItem()`

**Logika Validasi**:
```
Sisa Kuota = Total Kuota - Emisi Harian
```

**Kondisi**:
- **VALID**: Jika Sisa Kuota ≥ Quantity To Sell → Tetap di marketplace
- **INVALID**: Jika Sisa Kuota < Quantity To Sell → Dihapus dari marketplace

**Contoh**:
- Punya 800 kg, jual 750 kg, pakai 50 kg → sisa 750 kg ≥ 750 kg = VALID ✅
- Punya 800 kg, jual 750 kg, pakai 100 kg → sisa 700 kg < 750 kg = INVALID ❌

---

## 3. PROSES PEMBELIAN KUOTA KARBON

### 3.1 Pembelian oleh Admin (ADMIN → USER)
**File**: `TransactionController.php` → `store()` (Admin flow)

**Alur Proses**:
1. Admin melihat kuota yang dijual user di marketplace admin
2. Admin membeli **seluruh quantity_to_sell** secara otomatis
3. Tidak perlu pilih kendaraan tujuan

**Validasi**:
```php
$pendingAmount = transaksi pending yang belum selesai
$availableAmount = quantity_to_sell - pendingAmount
```

**Proses Transaksi**:
1. Buat record `Transaction`:
   - `seller_id` = user_id (pemilik kendaraan)
   - `buyer_id` = admin_id
   - `transaction_id` = 'TXN-' + random string
   - `amount` = quantity_to_buy
   - `price_per_unit` = 100
   - `total_amount` = amount × price_per_unit
   - `status` = 'pending'

2. Buat record `TransactionDetail`:
   - `transaction_id` = ID transaksi
   - `carbon_credit_id` = ID kuota karbon
   - `amount` = jumlah yang dibeli
   - `price` = 100
   - `vehicle_id` = NULL (admin tidak perlu vehicle)

3. **Reserve Quota** (kurangi dari marketplace):
   ```php
   quantity_to_sell -= purchaseAmount
   amount -= purchaseAmount
   ```
   Jika amount ≤ 0 → status = 'sold'

4. Generate Midtrans Snap Token untuk pembayaran

### 3.2 Pembelian oleh User (USER → ADMIN)
**File**: `TransactionController.php` → `store()` (User flow)

**Alur Proses**:
1. User melihat kuota yang dijual admin di marketplace
2. User menentukan:
   - `quantity_to_sell`: Jumlah yang ingin dibeli
   - `vehicle_id`: Kendaraan tujuan (wajib)

**Validasi**:
```php
$totalAvailable = sum semua kuota admin yang available
if (quantity_to_buy > totalAvailable) → ERROR
```

**Proses Transaksi**:
1. Buat record `Transaction` (sama seperti admin)
2. **Distribusi Pembelian** ke multiple admin credits:
   ```php
   foreach (adminCredits as credit) {
       purchaseAmount = min(availableAmount, remainingQuantity)
       // Buat TransactionDetail untuk setiap credit
       // Include vehicle_id untuk user
   }
   ```

3. Reserve quota dari semua admin credits yang digunakan
4. Generate Midtrans Snap Token

**Perbedaan Admin vs User**:
| Aspek | Admin | User |
|-------|-------|------|
| Quantity | Otomatis (full quantity_to_sell) | Manual input |
| Vehicle Selection | Tidak perlu | Wajib pilih kendaraan |
| Distribution | Single credit | Multiple admin credits |
| TransactionDetail.vehicle_id | NULL | Required |

---

## 4. PROSES PEMBAYARAN (MIDTRANS INTEGRATION)

### 4.1 Inisiasi Pembayaran
**File**: `MidtransService.php` → `createTransaction()`

**Alur Proses**:
1. Sistem membuat Snap Token Midtrans:
   ```php
   $params = [
       'transaction_details' => [
           'order_id' => 'TXN-xxxxx',
           'gross_amount' => total_amount
       ],
       'customer_details' => [
           'first_name' => buyer_name,
           'email' => buyer_email
       ]
   ]
   ```

2. Snap Token disimpan ke database:
   - `midtrans_snap_token` = token dari Midtrans

3. User diarahkan ke halaman pembayaran dengan Snap Token

### 4.2 Metode Pembayaran
User dapat memilih berbagai metode:
- **E-Wallet**: GoPay, OVO, DANA, ShopeePay
- **Bank Transfer**: BCA, BNI, BRI, Mandiri, Permata
- **Credit Card**: Visa, Mastercard
- **Convenience Store**: Indomaret, Alfamart

### 4.3 Notifikasi Pembayaran
**File**: `TransactionController.php` → `handlePaymentNotification()`

**Alur Proses**:
1. Midtrans mengirim HTTP notification ke webhook
2. Sistem menerima notification dengan data:
   - `order_id`: Transaction ID
   - `transaction_status`: Status pembayaran
   - `fraud_status`: Status fraud detection
   - `payment_type`: Metode pembayaran

**Status Mapping**:
```php
'capture' + 'accept' → SUCCESS
'settlement' → SUCCESS
'pending' → PENDING
'cancel' / 'deny' / 'expire' → FAILED
```

### 4.4 Penyelesaian Transaksi (Success)
**File**: `TransactionController.php` → `completeTransaction()`

**Alur Proses**:

**A. Update Transaction**:
```php
status = 'success'
paid_at = now()
midtrans_transaction_id = notification.transaction_id
payment_method = notification.payment_type
```

**B. Jika Buyer = Admin**:
1. Admin mendapatkan kuota yang dibeli
2. Cek apakah admin sudah punya kuota dari proyek sama:
   - **Ada**: Increment amount & quantity_to_sell
   - **Tidak ada**: Buat CarbonCredit baru untuk admin

3. Kuota admin siap dijual kembali ke user lain

**C. Jika Buyer = User**:
1. Kuota ditambahkan ke kendaraan yang dipilih:
   ```php
   userVehicle->increment('amount', purchaseAmount)
   ```
2. User TIDAK otomatis dapat menjual (quantity_to_sell tidak diupdate)
3. User harus request sale dulu jika ingin jual

**D. Buat Payout untuk Seller** (jika seller = user):
```php
admin_fee = total_amount × 5%
net_amount = total_amount - admin_fee

Payout::create([
    'transaction_id' => transaction.id,
    'user_id' => seller_id,
    'payout_id' => 'PYT-' + random,
    'amount' => total_amount,
    'net_amount' => net_amount,
    'status' => 'pending'
])
```

### 4.5 Pembatalan/Gagal Pembayaran
**File**: `TransactionController.php` → `handlePaymentNotification()`

**Alur Proses**:
1. Status transaksi = 'failed'
2. **Restore Reserved Quota**:
   ```php
   foreach (transactionDetails as detail) {
       carbonCredit->increment('quantity_to_sell', detail.amount)
       carbonCredit->increment('amount', detail.amount)
       if (status was 'sold') → status = 'available'
   }
   ```
3. Kuota dikembalikan ke marketplace

---

## 4A. ALUR UANG PADA PAYMENT GATEWAY (DETAIL)

### 🔄 FLOW UANG: DARI PEMBELI KE PENJUAL

```
[PEMBELI] → [MIDTRANS] → [REKENING MERCHANT] → [PAYOUT SYSTEM] → [PENJUAL]
```

### **TAHAP 1: PEMBELI BAYAR - AUTHORIZATION (Otorisasi)**

**Proses Otorisasi** = "Minta Izin" ke Bank (Terjadi dalam hitungan detik)

**Langkah Detail:**

1. **Pembeli Input Detail Pembayaran**
   - Pembeli memilih metode di Snap Midtrans (misal: Virtual Account BCA)
   - Input nomor VA atau pilih e-wallet

2. **Midtrans → Bank Penerbit (Issuing Bank)**
   - Midtrans mengirim permintaan terenkripsi ke Bank Penerbit (Bank pembeli)
   - Melalui jaringan: Visa/Mastercard (kartu kredit) atau jaringan VA (bank transfer)
   
3. **Bank Penerbit Melakukan Pengecekan**
   - Apakah akun valid?
   - Apakah dana/limit cukup untuk Rp 50.000?
   - Apakah ada risiko fraud?
   - **PENTING**: Bank MENAHAN (hold) dana, tapi BELUM TRANSFER

4. **Respons Authorization**
   - Bank Penerbit kirim respons: "APPROVED" atau "DECLINED"
   - Jika APPROVED: Dana di-hold (ditahan) untuk Midtrans
   - Jika DECLINED: Transaksi gagal

5. **Midtrans Notifikasi Pembeli**
   - Tampilkan "Pembayaran Berhasil" ke pembeli
   - **CATATAN PENTING**: Uang BELUM PINDAH, hanya di-HOLD

**Contoh:**
```
User membeli 500kg × Rp 100 = Rp 50.000
↓
User pilih VA BCA dan transfer
↓
Midtrans → Bank BCA: "Cek dana Rp 50.000"
↓
Bank BCA: "OK, dana cukup, saya HOLD Rp 50.000"
↓
Midtrans: "Pembayaran Berhasil" (tapi uang masih di Bank BCA)
```

**Status pada tahap ini:**
- Dana: Masih di Bank Pembeli (di-hold/ditahan)
- Status Transaksi: "Authorized" atau "Pending"
- Uang belum masuk Midtrans

---

### **TAHAP 2: CLEARING (Kliring) - Proses Penagihan**

**Proses Clearing** = "Penagihan" yang terjadi di belakang layar (Batch Processing)

**Langkah Detail:**

1. **Pengumpulan Transaksi (End of Day)**
   - Di penghujung hari (atau interval tertentu)
   - Midtrans mengumpulkan SEMUA transaksi yang "APPROVED" hari itu
   - Dikumpulkan dalam satu BATCH (kumpulan)

2. **Pengiriman Tagihan ke Bank**
   - Midtrans (melalui Bank Akuirer) mengirim batch tagihan
   - Ke semua Bank Penerbit (BCA, BNI, Mandiri, dll)
   - Isi tagihan: "Transaksi tadi sudah approved, sekarang tagih uangnya"

3. **Bank Penerbit Proses Tagihan**
   - Bank Penerbit terima batch tagihan
   - Verifikasi setiap transaksi
   - Siapkan dana untuk ditransfer

**Contoh:**
```
Pukul 23:00 WIB (End of Day)
↓
Midtrans kumpulkan 10.000 transaksi hari ini
- Transaksi #1: Rp 50.000 dari BCA
- Transaksi #2: Rp 100.000 dari BNI
- ... dst
↓
Midtrans → Bank BCA: "Tagih Rp 50.000 untuk TXN-abc123"
Midtrans → Bank BNI: "Tagih Rp 100.000 untuk TXN-xyz456"
↓
Bank BCA: "OK, saya siapkan Rp 50.000"
Bank BNI: "OK, saya siapkan Rp 100.000"
```

**Status pada tahap ini:**
- Dana: Masih di Bank Pembeli (tapi sudah ditagih)
- Status Transaksi: "Clearing" atau "Processing"
- Uang belum masuk Midtrans

---

### **TAHAP 3: SETTLEMENT (Penyelesaian) - Transfer Uang Sebenarnya**

**Proses Settlement** = Perpindahan uang yang SEBENARNYA terjadi (T+1 hingga T+3 hari kerja)

**Langkah Detail:**

1. **Transfer Antar Bank**
   - Bank Penerbit (BCA pembeli) transfer dana yang sudah di-hold
   - Ke Bank Akuirer (rekening penampung Midtrans)
   - Melalui sistem kliring Bank Indonesia (BI-FAST/SKN/RTGS)

2. **Uang Masuk ke Rekening Midtrans**
   - Dana dari ribuan transaksi terkumpul di rekening agregat Midtrans
   - Rekening ini ada di Bank Partner Midtrans (Bank Permata/CIMB)
   - **INI MOMEN UANG BENAR-BENAR MASUK KE MIDTRANS**

3. **Midtrans Potong Fee**
   - Midtrans potong fee mereka (2.9% atau flat fee)
   - Sisanya siap untuk di-settle ke merchant

4. **Settlement ke Rekening Merchant**
   - Midtrans transfer dari rekening agregat mereka
   - Ke rekening merchant (rekening bisnis sistem)
   - Melalui kliring Bank Indonesia lagi

**Contoh:**
```
T+1 Hari Kerja (Pagi hari)
↓
Bank BCA transfer Rp 50.000
↓
Melalui Bank Indonesia (Kliring)
↓
Masuk ke Rekening Midtrans (Bank Permata)
↓
Midtrans potong fee 2.9% = Rp 1.450
↓
Sisa: Rp 48.550
↓
Midtrans transfer ke Rekening Merchant (Bank BCA sistem)
↓
Rekening Merchant terima Rp 48.550
```

**Waktu Settlement:**
- **E-Wallet/Credit Card**: T+0 hingga T+1 hari kerja
- **Bank Transfer**: T+1 hingga T+2 hari kerja
- **Convenience Store**: T+2 hingga T+3 hari kerja

**Status pada tahap ini:**
- Dana: Sudah di Rekening Merchant
- Status Transaksi: "Settlement" atau "Success"
- Uang sudah bisa dipakai merchant

---

### **TAHAP 4: MIDTRANS KIRIM NOTIFIKASI KE SISTEM**

**Proses:**
1. Setelah settlement berhasil, Midtrans kirim HTTP POST ke webhook:
   ```
   POST https://yourdomain.com/api/midtrans/notification
   ```

2. Payload notification:
   ```json
   {
       "order_id": "TXN-abc123",
       "transaction_status": "settlement",
       "gross_amount": "50000",
       "payment_type": "bank_transfer",
       "transaction_id": "midtrans-xyz789",
       "settlement_time": "2024-01-15 10:30:00"
   }
   ```

3. Sistem menerima dan memproses:
   - Verifikasi signature untuk keamanan
   - Update status transaksi menjadi 'success'
   - Update paid_at timestamp
   - Trigger proses payout otomatis

**Contoh:**
```
Midtrans: "Halo Sistem, transaksi TXN-abc123 sudah SETTLED!"
↓
Sistem: "OK, saya update status jadi SUCCESS"
↓
Sistem: "Saya buat record PAYOUT untuk penjual"
```

### **TAHAP 4: SISTEM BUAT PAYOUT REQUEST**

**Proses:**
1. Setelah transaksi success, sistem otomatis membuat record Payout:
   ```php
   Payout::create([
       'user_id' => seller_id,
       'amount' => 50000,
       'admin_fee' => 2500,  // 5% dari 50000
       'net_amount' => 47500, // 50000 - 2500
       'status' => 'pending'
   ])
   ```

2. Payout menunggu di database dengan status 'pending'

### **TAHAP 5: PENJUAL REQUEST PENCAIRAN**

**File**: `PayoutController.php` → `create()`

**Proses:**
1. Penjual (User) klik tombol "Cairkan Dana" di dashboard
2. Sistem validasi informasi bank penjual:
   - `bank_name`: Nama bank (BCA, BNI, BRI, dll)
   - `bank_account`: Nomor rekening
   - `account_holder`: Nama pemilik rekening

3. Sistem kirim request ke **Midtrans Payout API**:
   ```php
   POST https://app.sandbox.midtrans.com/iris/api/v1/payouts
   
   {
       "payouts": [{
           "beneficiary_name": "John Doe",
           "beneficiary_account": "1234567890",
           "beneficiary_bank": "bca",
           "amount": "47500",
           "notes": "Payout for PYT-xyz123"
       }]
   }
   ```

4. Midtrans response dengan `reference_no` (ID payout)
5. Status payout berubah menjadi 'created'

### **TAHAP 6: APPROVAL DENGAN OTP**

**File**: `PayoutController.php` → `approve()`

**Proses:**
1. Midtrans mengirim **OTP** ke email/SMS yang terdaftar
2. Penjual memasukkan OTP di form approval
3. Sistem kirim approval request ke Midtrans:
   ```php
   POST https://app.sandbox.midtrans.com/iris/api/v1/payouts/approve
   
   {
       "reference_nos": ["ref-abc123"],
       "otp": "123456"
   }
   ```

4. Jika OTP valid:
   - Status payout → 'processing'
   - Midtrans mulai proses transfer

**Keamanan OTP:**
- OTP berlaku 5 menit
- Maksimal 3x salah input
- Hanya pemilik akun yang dapat approve

### **TAHAP 7: MIDTRANS TRANSFER KE REKENING PENJUAL**

**Proses:**
1. Midtrans melakukan transfer dari **rekening merchant** ke **rekening penjual**
2. Metode transfer:
   - **Real-time**: BCA, BNI, BRI, Mandiri (instant)
   - **SKN**: Bank lain (1 hari kerja)
   - **RTGS**: Jumlah besar >Rp 100jt (instant, biaya lebih tinggi)

3. Waktu proses:
   - **Instant**: 1-5 menit
   - **SKN**: 1 hari kerja
   - **RTGS**: 1-2 jam

**Contoh:**
```
Rekening Merchant: Rp 50.000
↓
Transfer ke rekening penjual (BCA)
↓
Rekening Penjual: +Rp 47.500
(Sudah dikurangi admin fee 5%)
```

### **TAHAP 8: NOTIFIKASI PAYOUT SELESAI**

**File**: `PayoutController.php` → `handlePayoutNotification()`

**Proses:**
1. Midtrans kirim notification ke webhook:
   ```json
   {
       "reference_no": "ref-abc123",
       "status": "completed",
       "amount": "47500",
       "beneficiary_account": "1234567890"
   }
   ```

2. Sistem update status payout:
   - `status` = 'completed'
   - `completed_at` = timestamp

3. Penjual menerima notifikasi:
   - Email: "Dana Rp 47.500 telah ditransfer ke rekening Anda"
   - Dashboard: Status payout berubah menjadi "Selesai"

---

## 📊 DIAGRAM ALUR UANG LENGKAP

```
┌─────────────────────────────────────────────────────────────────┐
│                    ALUR UANG PAYMENT GATEWAY                     │
└─────────────────────────────────────────────────────────────────┘

[1] PEMBELI BAYAR
    User: "Beli 500kg × Rp 100 = Rp 50.000"
    ↓
    Pilih metode: GoPay
    ↓
    Bayar Rp 50.000
    ↓
    ┌─────────────────┐
    │ REKENING MIDTRANS│ ← Uang masuk sini dulu (escrow)
    │  Rp 50.000      │
    └─────────────────┘

[2] MIDTRANS NOTIFIKASI
    Midtrans → Webhook Sistem
    ↓
    "Transaction settlement"
    ↓
    Sistem: Update status = 'success'
    Sistem: Buat Payout record

[3] SETTLEMENT KE MERCHANT
    ┌─────────────────┐
    │ REKENING MIDTRANS│
    │  Rp 50.000      │
    └────────┬────────┘
             ↓ (1-2 hari kerja)
             ↓ (Dikurangi fee Midtrans ~2.9%)
    ┌─────────────────┐
    │ REKENING MERCHANT│ ← Rekening bisnis sistem
    │  Rp 48.550      │   (50.000 - 1.450 fee)
    └─────────────────┘

[4] PENJUAL REQUEST PAYOUT
    Penjual: "Cairkan Dana"
    ↓
    Sistem → Midtrans Payout API
    ↓
    Create Payout Request
    ↓
    Status: 'created'

[5] APPROVAL OTP
    Midtrans → Email/SMS OTP
    ↓
    Penjual: Input OTP "123456"
    ↓
    Sistem → Midtrans Approve API
    ↓
    Status: 'processing'

[6] TRANSFER KE PENJUAL
    ┌─────────────────┐
    │ REKENING MERCHANT│
    │  Rp 48.550      │
    └────────┬────────┘
             ↓ (Instant/1 hari kerja)
             ↓ (Dikurangi admin fee 5%)
    ┌─────────────────┐
    │ REKENING PENJUAL │ ← Rekening bank penjual
    │  Rp 47.500      │   (50.000 - 2.500 admin fee)
    └─────────────────┘

[7] NOTIFIKASI SELESAI
    Midtrans → Webhook Sistem
    ↓
    "Payout completed"
    ↓
    Sistem: Update status = 'completed'
    ↓
    Email ke Penjual: "Dana telah ditransfer"
```

---

## 💰 BREAKDOWN BIAYA

### Contoh Transaksi: Rp 100.000

**1. Pembeli Bayar:**
```
Jumlah: Rp 100.000
Metode: GoPay
Total Bayar: Rp 100.000
```

**2. Midtrans Fee (Payment):**
```
Fee GoPay: 2.9%
Fee: Rp 100.000 × 2.9% = Rp 2.900
Masuk Merchant: Rp 97.100
```

**3. Admin Fee Sistem:**
```
Admin Fee: 5%
Fee: Rp 100.000 × 5% = Rp 5.000
Net untuk Penjual: Rp 95.000
```

**4. Midtrans Fee (Payout):**
```
Fee Transfer: Rp 2.500 (flat)
Diterima Penjual: Rp 92.500
```

**TOTAL DITERIMA PENJUAL:**
```
Pembeli Bayar:     Rp 100.000
- Midtrans Fee:    Rp   2.900
- Admin Fee:       Rp   5.000
- Payout Fee:      Rp   2.500
─────────────────────────────
Penjual Terima:    Rp  89.600
```

**CATATAN**: Dalam implementasi saat ini, admin fee 5% sudah dipotong dari amount, jadi penjual terima net_amount = amount - 5%.

---

## 🔐 KEAMANAN ALUR UANG

### 1. Escrow System
- Uang pembeli **tidak langsung** ke penjual
- Uang ditahan di rekening Midtrans dulu
- Baru ditransfer setelah transaksi confirmed

### 2. Signature Verification
```php
$signature = hash('sha512', 
    $orderId . $statusCode . $grossAmount . $serverKey
);

if ($signature !== $receivedSignature) {
    throw new Exception('Invalid signature');
}
```

### 3. OTP Approval
- Setiap payout butuh OTP
- OTP dikirim ke email/SMS terdaftar
- Mencegah payout unauthorized

### 4. Idempotency Check
```php
if ($transaction->status === 'success' && $transaction->paid_at !== null) {
    // Skip duplicate processing
    return;
}
```

### 5. Database Transaction
```php
DB::beginTransaction();
try {
    // Process payment
    // Update quota
    // Create payout
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
}
```

---

## ⏱️ TIMELINE ALUR UANG

### Skenario Tercepat (E-Wallet + Instant Transfer)
```
T+0 menit:    Pembeli bayar via GoPay
T+1 menit:    Midtrans notifikasi → Status success
T+1 menit:    Sistem buat payout record
T+2 menit:    Penjual request payout
T+3 menit:    Penjual input OTP
T+5 menit:    Uang masuk rekening penjual
─────────────────────────────────────────
TOTAL: ~5 menit
```

### Skenario Normal (Bank Transfer + SKN)
```
T+0 hari:     Pembeli bayar via Bank Transfer
T+1 hari:     Midtrans settlement → Status success
T+1 hari:     Sistem buat payout record
T+2 hari:     Penjual request payout + OTP
T+3 hari:     Uang masuk rekening penjual (SKN)
─────────────────────────────────────────
TOTAL: ~3 hari kerja
```

### Skenario Terlama (Convenience Store + Weekend)
```
T+0 (Jumat):  Pembeli bayar di Indomaret
T+3 (Senin):  Midtrans settlement (lewat weekend)
T+3 (Senin):  Sistem buat payout record
T+4 (Selasa): Penjual request payout + OTP
T+5 (Rabu):   Uang masuk rekening penjual
─────────────────────────────────────────
TOTAL: ~5 hari kerja
```

---

## 5. PROSES PAYOUT (PENCAIRAN DANA)

### 5.1 Pembuatan Payout Request
**File**: `PayoutController.php` → `create()`

**Alur Proses**:
1. Setelah transaksi success, sistem otomatis buat record Payout
2. User/Admin dapat request pencairan dana

**Validasi**:
- User harus punya informasi bank:
  - `bank_account`: Nomor rekening
  - `bank_name`: Nama bank (BCA, BNI, BRI, dll)

**Proses**:
1. Kirim request ke Midtrans Payout API:
   ```php
   $payload = [
       'payouts' => [
           'beneficiary_name' => user.name,
           'beneficiary_account' => user.bank_account,
           'beneficiary_bank' => bank_code,
           'amount' => net_amount,
           'notes' => 'Payout for PYT-xxxxx'
       ]
   ]
   ```

2. Update payout status:
   - `status` = 'created'
   - `midtrans_payout_id` = reference_no dari Midtrans
   - `midtrans_response` = full response JSON

3. Redirect ke form OTP untuk approval

### 5.2 Approval Payout (OTP)
**File**: `PayoutController.php` → `approve()`

**Alur Proses**:
1. User memasukkan OTP yang diterima dari Midtrans
2. Sistem kirim approval request:
   ```php
   $payload = [
       'reference_nos' => [payout_reference_no],
       'otp' => user_otp
   ]
   ```

3. Jika OTP valid:
   - `status` = 'processing'
   - `processed_at` = now()

4. Midtrans memproses transfer ke rekening bank

### 5.3 Notifikasi Payout
**File**: `PayoutController.php` → `handlePayoutNotification()`

**Status Payout**:
- **completed/success**: Dana berhasil dikirim ke rekening
- **processing/pending**: Sedang diproses oleh bank
- **failed/rejected**: Gagal (rekening invalid, dll)

**Update Status**:
```php
switch (notification.status) {
    case 'completed': status = 'completed'
    case 'failed': status = 'failed'
    case 'processing': status = 'processing'
}
```

---

## 6. FLOW DIAGRAM LENGKAP

### 6.1 Flow Penjualan (User → Admin)
```
[User Register Kendaraan]
         ↓
[Admin Approve] → Alokasi Kuota Awal (800/500 kg)
         ↓
[Monitoring Emisi Real-time via MQTT]
         ↓
[User Request Sale] → quantity_to_sell ≤ effective_quota
         ↓
[Admin Approve Sale] → Masuk Marketplace
         ↓
[Admin Beli Kuota]
         ↓
[Pembayaran via Midtrans]
         ↓
[Success] → Admin dapat kuota + User dapat payout
```

### 6.2 Flow Pembelian (User → Admin)
```
[Admin punya kuota di marketplace]
         ↓
[User pilih quantity + vehicle]
         ↓
[Sistem distribusi ke multiple admin credits]
         ↓
[Pembayaran via Midtrans]
         ↓
[Success] → Kuota masuk ke vehicle user
         ↓
[User bisa request sale jika ada sisa]
```

### 6.3 Flow Pembayaran
```
[Transaction Created] → status = 'pending'
         ↓
[Generate Snap Token]
         ↓
[User pilih metode pembayaran]
         ↓
[Midtrans process payment]
         ↓
[Webhook Notification]
         ↓
    ┌────┴────┐
    ↓         ↓
[SUCCESS]  [FAILED]
    ↓         ↓
Complete   Restore
Transaction Quota
    ↓
[Create Payout]
```

### 6.4 Flow Payout
```
[Transaction Success]
         ↓
[Auto Create Payout] → status = 'pending'
         ↓
[User Request Payout]
         ↓
[Create Payout via Midtrans API] → status = 'created'
         ↓
[User Input OTP]
         ↓
[Approve Payout] → status = 'processing'
         ↓
[Bank Process Transfer]
         ↓
[Notification from Midtrans]
         ↓
[Status = 'completed'] → Dana masuk rekening
```

---

## 7. STRUKTUR DATABASE

### 7.1 Tabel: carbon_credits
**Menyimpan data kuota karbon dan kendaraan**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| id | bigint | Primary key |
| owner_id | bigint | FK ke users |
| nomor_kartu_keluarga | string | Nomor KK |
| nik_e_ktp | string | NIK pemilik |
| nrkb | string | Nomor kendaraan |
| nomor_rangka_5digit | string | 5 digit nomor rangka |
| vehicle_type | enum | car/motorcycle |
| amount | decimal | Total kuota yang dimiliki |
| price_per_unit | decimal | Harga per unit (100) |
| quantity_to_sell | decimal | Kuota yang dijual |
| status | enum | pending/available/sold/pending_sale |
| sale_requested_at | timestamp | Waktu request sale |
| sale_approved_at | timestamp | Waktu approve sale |
| device_id | string | ID device IoT |
| daily_emissions_kg | decimal | Emisi harian |
| monthly_emissions_kg | decimal | Emisi bulanan |
| total_emissions_kg | decimal | Total emisi |

### 7.2 Tabel: transactions
**Menyimpan data transaksi jual-beli**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| id | bigint | Primary key |
| transaction_id | string | TXN-xxxxx |
| seller_id | bigint | FK ke users (penjual) |
| buyer_id | bigint | FK ke users (pembeli) |
| amount | decimal | Jumlah kuota |
| price_per_unit | decimal | Harga per unit |
| total_amount | decimal | Total pembayaran |
| status | enum | pending/success/failed |
| midtrans_snap_token | string | Token pembayaran |
| midtrans_transaction_id | string | ID dari Midtrans |
| payment_method | string | Metode pembayaran |
| paid_at | timestamp | Waktu pembayaran |

### 7.3 Tabel: transaction_details
**Detail transaksi per carbon credit**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| id | bigint | Primary key |
| transaction_id | bigint | FK ke transactions |
| carbon_credit_id | bigint | FK ke carbon_credits |
| vehicle_id | bigint | FK ke carbon_credits (kendaraan tujuan) |
| amount | decimal | Jumlah kuota |
| price | decimal | Harga per unit |

### 7.4 Tabel: payouts
**Menyimpan data pencairan dana**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| id | bigint | Primary key |
| payout_id | string | PYT-xxxxx |
| transaction_id | bigint | FK ke transactions |
| user_id | bigint | FK ke users |
| amount | decimal | Total amount |
| net_amount | decimal | Amount - admin fee (5%) |
| status | enum | pending/created/processing/completed/failed |
| midtrans_payout_id | string | Reference no dari Midtrans |
| processed_at | timestamp | Waktu diproses |

---

## 8. ROLE & PERMISSION

### 8.1 User (Pemilik Kendaraan)
**Dapat melakukan**:
- ✅ Daftar kendaraan
- ✅ Lihat kuota sendiri
- ✅ Request penjualan kuota
- ✅ Beli kuota dari admin
- ✅ Request payout
- ✅ Approve payout dengan OTP

**Tidak dapat**:
- ❌ Approve pendaftaran kendaraan
- ❌ Approve penjualan kuota
- ❌ Lihat semua transaksi
- ❌ Beli dari user lain (hanya dari admin)

### 8.2 Admin
**Dapat melakukan**:
- ✅ Approve pendaftaran kendaraan
- ✅ Approve penjualan kuota user
- ✅ Beli kuota dari user
- ✅ Jual kuota ke user
- ✅ Lihat semua transaksi
- ✅ Lihat semua payout
- ✅ Validasi marketplace

**Tidak dapat**:
- ❌ Beli dari admin lain

---

## 9. BUSINESS RULES

### 9.1 Pricing
- Harga per unit **TETAP** = Rp 100
- Tidak ada negosiasi harga
- Admin fee untuk payout = 5%

### 9.2 Quota Management
- Kuota awal: Mobil 800 kg, Motor 500 kg
- Effective Quota = Total Kuota - Emisi Harian
- User hanya bisa jual ≤ Effective Quota
- Quota reserved saat transaksi pending
- Quota restored jika pembayaran gagal

### 9.3 Transaction Flow
- Admin beli dari user → otomatis full quantity
- User beli dari admin → manual input quantity
- User harus pilih vehicle tujuan
- Admin tidak perlu pilih vehicle
- Pembayaran via Midtrans (wajib)

### 9.4 Marketplace Validation
- Validasi setiap kali marketplace diakses
- Item invalid dihapus otomatis
- Grace period 1 jam setelah approval
- Status berubah ke pending_sale jika invalid

### 9.5 Payout Rules
- Otomatis dibuat setelah transaksi success
- Hanya untuk seller yang bukan admin
- Butuh OTP untuk approval
- Net amount = amount - 5% admin fee

---

## 10. INTEGRATION POINTS

### 10.1 Midtrans Payment Gateway
**Endpoint**: https://app.midtrans.com

**Services**:
- **Snap API**: Generate payment token
- **Notification**: Webhook untuk status pembayaran
- **Transaction Status**: Check status transaksi

**Configuration**:
```php
Config::$serverKey = env('MIDTRANS_SERVER_KEY')
Config::$isProduction = env('MIDTRANS_IS_PRODUCTION')
Config::$is3ds = true
```

### 10.2 Midtrans Payout API
**Endpoint**: https://app.sandbox.midtrans.com/iris/api/v1/payouts

**Services**:
- **Create Payout**: Buat request pencairan
- **Approve Payout**: Approve dengan OTP
- **Get Details**: Cek status payout
- **Notification**: Webhook untuk status payout

**Authentication**:
- Creator Key: Untuk create payout
- Approver Key: Untuk approve payout
- Server Key: Untuk get details

### 10.3 MQTT Integration
**Purpose**: Real-time emission monitoring

**Data Flow**:
```
[IoT Sensor] → MQTT Broker → Laravel Queue → Database
```

**Data Captured**:
- CO2e concentration (mg/m³)
- GPS location (latitude, longitude)
- Speed (km/h)
- Timestamp

---

## 11. ERROR HANDLING

### 11.1 Transaction Errors
```php
// Insufficient quota
if (quantity > availableAmount) {
    return error('Jumlah melebihi kuota tersedia')
}

// Self-purchase
if (seller_id === buyer_id) {
    return error('Tidak dapat membeli kuota sendiri')
}

// Invalid status
if (status !== 'available') {
    return error('Kuota tidak tersedia')
}
```

### 11.2 Payment Errors
```php
// Payment failed
if (status === 'failed') {
    transaction->status = 'failed'
    restoreQuota()
}

// Payment expired
if (status === 'expire') {
    transaction->status = 'failed'
    restoreQuota()
}
```

### 11.3 Payout Errors
```php
// Missing bank info
if (!user->bank_account) {
    return error('Informasi bank belum lengkap')
}

// Invalid OTP
if (otp_invalid) {
    return error('OTP tidak valid')
}

// Payout failed
if (status === 'failed') {
    payout->status = 'failed'
    payout->notes = error_message
}
```

---

## 12. LOGGING & MONITORING

### 12.1 Transaction Logs
```php
Log::info('Transaction created', [
    'transaction_id' => $transaction->transaction_id,
    'buyer_id' => $buyer->id,
    'seller_id' => $seller->id,
    'amount' => $amount,
    'total' => $total_amount
])
```

### 12.2 Payment Logs
```php
Log::info('[PAYMENT GATEWAY] Notification received', [
    'order_id' => $order_id,
    'status' => $transaction_status,
    'payment_method' => $payment_type
])
```

### 12.3 Marketplace Validation Logs
```php
Log::info('MARKETPLACE VALIDATION', [
    'device_id' => $device_id,
    'total_quota' => $totalQuota,
    'quantity_sold' => $quantityBeingSold,
    'daily_emissions' => $dailyEmissions,
    'remaining_quota' => $sisaKuota,
    'status' => 'VALID/INVALID'
])
```

---

## 13. SECURITY CONSIDERATIONS

### 13.1 Authorization
- Middleware untuk role checking (admin/user)
- Owner verification untuk akses data
- CSRF protection untuk form submission

### 13.2 Payment Security
- Signature verification untuk Midtrans notification
- HTTPS untuk semua payment endpoints
- Idempotency check untuk duplicate transactions

### 13.3 Data Validation
- Input validation untuk semua form
- Sanitization untuk bank account data
- Regex validation untuk notes (alphanumeric only)

---

## 14. EMPAT KONDISI PROSES BISNIS UTAMA

Bagian ini menjelaskan secara eksplisit 4 kondisi proses bisnis yang menjadi inti dari sistem Carbon Credit Marketplace, dengan fokus pada alur pengajuan, pembelian, dan money flow.

---

### 14.1 KONDISI 1: PENGAJUAN PENJUALAN KUOTA KARBON

**Deskripsi**: Proses dimana pemilik kendaraan (User) mengajukan kuota karbon yang ingin dijual ke marketplace.

#### Aktor yang Terlibat
- **User (Pemilik Kendaraan)**: Penjual kuota karbon
- **Admin**: Pihak yang mereview dan menyetujui pengajuan
- **Sistem**: Melakukan validasi otomatis

#### Prasyarat (Prerequisites)
1. ✅ User sudah mendaftarkan kendaraan
2. ✅ Kendaraan sudah disetujui admin (status = 'available')
3. ✅ User sudah mendapat alokasi kuota awal (800 kg untuk mobil, 500 kg untuk motor)
4. ✅ Sistem monitoring emisi sudah aktif (MQTT integration)
5. ✅ User memiliki effective quota > 0

#### Langkah-langkah Proses

**TAHAP 1: Perhitungan Effective Quota**
```
Effective Quota = Total Kuota - Emisi Harian

Contoh:
- Total Kuota: 800 kg (mobil)
- Emisi Harian: 50 kg
- Effective Quota: 800 - 50 = 750 kg
```

**TAHAP 2: User Mengajukan Penjualan**

File: `CarbonCreditController.php` → `submitSaleRequest()`

1. User mengakses halaman "Request Sale"
2. User melihat informasi:
   - Total Kuota: 800 kg
   - Emisi Harian: 50 kg
   - Kuota Tersedia untuk Dijual: 750 kg
3. User menentukan `quantity_to_sell` (maksimal 750 kg)
4. User submit form

**TAHAP 3: Validasi Sistem**

```php
// Validasi 1: Quantity tidak boleh melebihi effective quota
$effectiveQuota = $carbonCredit->amount - $carbonCredit->daily_emissions_kg;

if ($request->quantity_to_sell > $effectiveQuota) {
    return error('Jumlah melebihi kuota yang tersedia');
}

// Validasi 2: Quantity harus > 0
if ($request->quantity_to_sell <= 0) {
    return error('Jumlah harus lebih dari 0');
}

// Validasi 3: Status harus 'available'
if ($carbonCredit->status !== 'available') {
    return error('Kuota tidak dapat dijual saat ini');
}
```

**TAHAP 4: Update Database**

```php
$carbonCredit->update([
    'status' => 'pending_sale',
    'quantity_to_sell' => $request->quantity_to_sell,
    'sale_price_per_unit' => 100, // Harga tetap
    'sale_requested_at' => now()
]);
```

**TAHAP 5: Admin Review & Approval**

File: `CarbonCreditController.php` → `approveSaleRequest()`

1. Admin melihat daftar pengajuan penjualan (status = 'pending_sale')
2. Admin mereview:
   - Data kendaraan
   - Jumlah kuota yang diajukan
   - Emisi terkini
3. Sistem melakukan **recalculate** effective quota saat approval:
   ```php
   $currentEffectiveQuota = $carbonCredit->amount - $carbonCredit->daily_emissions_kg;
   
   // Cap quantity jika emisi bertambah
   if ($carbonCredit->quantity_to_sell > $currentEffectiveQuota) {
       $carbonCredit->quantity_to_sell = $currentEffectiveQuota;
   }
   ```
4. Admin approve atau reject

**TAHAP 6: Masuk Marketplace**

Jika disetujui:
```php
$carbonCredit->update([
    'status' => 'available', // Masuk marketplace
    'sale_approved_at' => now(),
    'price_per_unit' => 100
]);
```

#### Validasi Berkelanjutan

Sistem melakukan validasi setiap kali marketplace diakses:

File: `MarketplaceController.php` → `validateMarketplaceItem()`

```php
$sisaKuota = $totalQuota - $dailyEmissions;

if ($sisaKuota < $quantityBeingSold) {
    // INVALID: Emisi sudah melebihi batas
    $carbonCredit->update([
        'status' => 'pending_sale',
        'quantity_to_sell' => 0
    ]);
    // Item dihapus dari marketplace
}
```

#### Hasil Akhir (Outcome)

✅ **Berhasil**:
- Status berubah menjadi 'available'
- Kuota muncul di marketplace admin
- Siap dibeli oleh admin

❌ **Gagal**:
- Quantity melebihi effective quota
- Status tidak valid
- Emisi bertambah setelah approval

#### Diagram Alur Kondisi 1

```
┌─────────────────────────────────────────────────────────────────┐
│           KONDISI 1: PENGAJUAN PENJUALAN KUOTA KARBON           │
└─────────────────────────────────────────────────────────────────┘

[START] User memiliki kuota karbon
    ↓
┌───────────────────────────────┐
│ Sistem Hitung Effective Quota │
│ = Total Kuota - Emisi Harian  │
└───────────────┬───────────────┘
                ↓
┌───────────────────────────────┐
│ User Input Quantity to Sell   │
│ (Maksimal = Effective Quota)  │
└───────────────┬───────────────┘
                ↓
┌───────────────────────────────┐
│   VALIDASI SISTEM             │
│ ✓ Quantity ≤ Effective Quota? │
│ ✓ Quantity > 0?               │
│ ✓ Status = 'available'?       │
└───────────┬───────────────────┘
            ↓
        [VALID?]
       ↙        ↘
    [YA]        [TIDAK]
      ↓            ↓
┌─────────────┐  ┌──────────────┐
│ Status =    │  │ Return Error │
│pending_sale │  │ & Reject     │
└──────┬──────┘  └──────────────┘
       ↓
┌─────────────────────────────┐
│ Admin Review Pengajuan      │
│ - Cek data kendaraan        │
│ - Cek quantity              │
│ - Recalculate effective     │
└──────────┬──────────────────┘
           ↓
    [ADMIN DECISION]
       ↙        ↘
  [APPROVE]   [REJECT]
      ↓           ↓
┌─────────────┐ ┌──────────────┐
│ Status =    │ │ Status =     │
│ available   │ │ available    │
│             │ │ quantity = 0 │
└──────┬──────┘ └──────────────┘
       ↓
┌─────────────────────────────┐
│ MASUK MARKETPLACE           │
│ - Tampil di admin dashboard │
│ - Siap dibeli               │
└─────────────────────────────┘
       ↓
┌─────────────────────────────┐
│ VALIDASI BERKELANJUTAN      │
│ Setiap marketplace diakses: │
│ Cek: Sisa Kuota ≥ Quantity? │
└──────────┬──────────────────┘
           ↓
    [MASIH VALID?]
       ↙        ↘
    [YA]        [TIDAK]
      ↓            ↓
┌─────────────┐  ┌──────────────────┐
│ Tetap di    │  │ Hapus dari       │
│ Marketplace │  │ Marketplace      │
└─────────────┘  │ Status = pending │
                 └──────────────────┘

[END]
```

#### Contoh Kasus

**Kasus 1: Pengajuan Berhasil**
```
User: Budi
Kendaraan: Mobil (B 1234 XYZ)
Total Kuota: 800 kg
Emisi Harian: 50 kg
Effective Quota: 750 kg

Budi mengajukan: 700 kg
✅ 700 kg ≤ 750 kg → VALID
Status: pending_sale

Admin approve
✅ Recalculate: Emisi masih 50 kg, sisa 750 kg
Status: available
Muncul di marketplace
```

**Kasus 2: Pengajuan Ditolak (Emisi Bertambah)**
```
User: Ani
Kendaraan: Motor (B 5678 ABC)
Total Kuota: 500 kg
Emisi Harian: 100 kg (saat pengajuan)
Effective Quota: 400 kg

Ani mengajukan: 380 kg
✅ 380 kg ≤ 400 kg → VALID
Status: pending_sale

Saat admin review:
Emisi Harian: 150 kg (bertambah!)
Effective Quota: 350 kg
❌ 380 kg > 350 kg → INVALID

Sistem auto-cap: quantity_to_sell = 350 kg
Admin approve dengan quantity baru
Status: available dengan 350 kg
```

**Kasus 3: Dihapus dari Marketplace**
```
User: Citra
Sudah di marketplace: 450 kg
Emisi Harian: 100 kg (saat approval)

Kemudian:
Emisi Harian: 200 kg (bertambah!)
Sisa Kuota: 500 - 200 = 300 kg
❌ 300 kg < 450 kg → INVALID

Sistem otomatis:
- Hapus dari marketplace
- Status: pending_sale
- quantity_to_sell: 0
- User harus request sale ulang
```

---

### 14.2 KONDISI 2: PEMBELIAN KUOTA KARBON

**Deskripsi**: Proses dimana pembeli (Admin atau User) membeli kuota karbon dari marketplace.

#### Aktor yang Terlibat
- **Buyer (Admin/User)**: Pihak yang membeli kuota
- **Seller (User/Admin)**: Pihak yang menjual kuota
- **Payment Gateway (Midtrans)**: Memproses pembayaran
- **Sistem**: Mengelola transaksi dan distribusi kuota

#### Prasyarat (Prerequisites)

**Untuk Admin sebagai Buyer:**
1. ✅ Ada kuota user di marketplace (status = 'available')
2. ✅ Admin memiliki akses ke admin marketplace
3. ✅ Kuota masih valid (sisa kuota ≥ quantity_to_sell)

**Untuk User sebagai Buyer:**
1. ✅ Ada kuota admin di marketplace (status = 'available')
2. ✅ User memiliki kendaraan terdaftar
3. ✅ User memiliki metode pembayaran

#### Skenario A: Admin Membeli dari User

**TAHAP 1: Admin Melihat Marketplace**

File: `MarketplaceController.php` → `adminIndex()`

```php
// Tampilkan semua kuota user yang available
$availableCredits = CarbonCredit::where('status', 'available')
    ->where('quantity_to_sell', '>', 0)
    ->whereHas('owner', function($q) {
        $q->where('role', 'user');
    })
    ->get();
```

**TAHAP 2: Admin Klik "Beli"**

File: `TransactionController.php` → `store()` (Admin flow)

- Admin **TIDAK** perlu input quantity (otomatis full quantity_to_sell)
- Admin **TIDAK** perlu pilih kendaraan tujuan

**TAHAP 3: Validasi Available Amount**

```php
// Hitung pending transactions
$pendingAmount = Transaction::where('carbon_credit_id', $creditId)
    ->where('status', 'pending')
    ->sum('amount');

// Hitung available amount
$availableAmount = $carbonCredit->quantity_to_sell - $pendingAmount;

if ($availableAmount <= 0) {
    return error('Kuota sudah habis atau sedang dalam transaksi');
}

// Admin beli full available amount
$purchaseAmount = $availableAmount;
```

**TAHAP 4: Buat Transaksi**

```php
$transaction = Transaction::create([
    'transaction_id' => 'TXN-' . Str::random(10),
    'seller_id' => $carbonCredit->owner_id, // User
    'buyer_id' => auth()->id(), // Admin
    'amount' => $purchaseAmount,
    'price_per_unit' => 100,
    'total_amount' => $purchaseAmount * 100,
    'status' => 'pending'
]);

TransactionDetail::create([
    'transaction_id' => $transaction->id,
    'carbon_credit_id' => $carbonCredit->id,
    'vehicle_id' => null, // Admin tidak perlu vehicle
    'amount' => $purchaseAmount,
    'price' => 100
]);
```

**TAHAP 5: Reserve Quota**

```php
// Kurangi dari marketplace
$carbonCredit->decrement('quantity_to_sell', $purchaseAmount);
$carbonCredit->decrement('amount', $purchaseAmount);

// Jika habis, ubah status
if ($carbonCredit->amount <= 0) {
    $carbonCredit->update(['status' => 'sold']);
}
```

**TAHAP 6: Generate Payment**

```php
$snapToken = MidtransService::createTransaction($transaction);
$transaction->update(['midtrans_snap_token' => $snapToken]);

// Redirect ke halaman pembayaran
return redirect()->to($paymentUrl);
```

#### Skenario B: User Membeli dari Admin

**TAHAP 1: User Melihat Marketplace**

File: `MarketplaceController.php` → `index()`

```php
// Tampilkan semua kuota admin yang available
$availableCredits = CarbonCredit::where('status', 'available')
    ->where('quantity_to_sell', '>', 0)
    ->whereHas('owner', function($q) {
        $q->where('role', 'admin');
    })
    ->get();

// Hitung total available
$totalAvailable = $availableCredits->sum('quantity_to_sell');
```

**TAHAP 2: User Input Quantity & Pilih Vehicle**

File: `TransactionController.php` → `store()` (User flow)

```php
// User HARUS input:
// 1. quantity_to_buy (manual)
// 2. vehicle_id (wajib pilih kendaraan tujuan)

$request->validate([
    'quantity_to_buy' => 'required|numeric|min:1',
    'vehicle_id' => 'required|exists:carbon_credits,id'
]);
```

**TAHAP 3: Validasi Total Available**

```php
$totalAvailable = CarbonCredit::where('status', 'available')
    ->whereHas('owner', function($q) {
        $q->where('role', 'admin');
    })
    ->sum('quantity_to_sell');

if ($request->quantity_to_buy > $totalAvailable) {
    return error('Jumlah melebihi kuota yang tersedia');
}
```

**TAHAP 4: Distribusi Pembelian ke Multiple Admin Credits**

```php
$adminCredits = CarbonCredit::where('status', 'available')
    ->where('quantity_to_sell', '>', 0)
    ->whereHas('owner', function($q) {
        $q->where('role', 'admin');
    })
    ->orderBy('created_at', 'asc')
    ->get();

$remainingQuantity = $request->quantity_to_buy;

foreach ($adminCredits as $credit) {
    if ($remainingQuantity <= 0) break;
    
    $availableAmount = $credit->quantity_to_sell;
    $purchaseAmount = min($availableAmount, $remainingQuantity);
    
    // Buat transaction detail untuk setiap credit
    TransactionDetail::create([
        'transaction_id' => $transaction->id,
        'carbon_credit_id' => $credit->id,
        'vehicle_id' => $request->vehicle_id, // WAJIB untuk user
        'amount' => $purchaseAmount,
        'price' => 100
    ]);
    
    // Reserve quota
    $credit->decrement('quantity_to_sell', $purchaseAmount);
    $credit->decrement('amount', $purchaseAmount);
    
    $remainingQuantity -= $purchaseAmount;
}
```

**TAHAP 5: Generate Payment**

Sama seperti admin, generate Midtrans Snap Token.

#### Perbedaan Admin vs User sebagai Buyer

| Aspek | Admin Buyer | User Buyer |
|-------|-------------|------------|
| **Quantity Input** | Otomatis (full quantity_to_sell) | Manual input |
| **Vehicle Selection** | Tidak perlu (NULL) | Wajib pilih kendaraan |
| **Distribution** | Single credit | Multiple admin credits (FIFO) |
| **TransactionDetail.vehicle_id** | NULL | Required |
| **Seller** | User only | Admin only |
| **Marketplace** | Admin marketplace | User marketplace |

#### Diagram Alur Kondisi 2

```
┌─────────────────────────────────────────────────────────────────┐
│              KONDISI 2: PEMBELIAN KUOTA KARBON                  │
└─────────────────────────────────────────────────────────────────┘

                    [START] Buyer ingin beli kuota
                              ↓
                    ┌─────────────────┐
                    │  Siapa Buyer?   │
                    └────────┬────────┘
                             ↓
                    ┌────────┴────────┐
                    ↓                 ↓
            ┌───────────────┐  ┌──────────────┐
            │ ADMIN BUYER   │  │  USER BUYER  │
            └───────┬───────┘  └──────┬───────┘
                    ↓                 ↓
        ┌───────────────────┐  ┌─────────────────────┐
        │ Lihat Marketplace │  │ Lihat Marketplace   │
        │ (User's Credits)  │  │ (Admin's Credits)   │
        └─────────┬─────────┘  └──────────┬──────────┘
                  ↓                       ↓
        ┌───────────────────┐  ┌─────────────────────┐
        │ Klik "Beli"       │  │ Input:              │
        │ (Auto full qty)   │  │ - Quantity to Buy   │
        │                   │  │ - Vehicle ID (wajib)│
        └─────────┬─────────┘  └──────────┬──────────┘
                  ↓                       ↓
        ┌───────────────────┐  ┌─────────────────────┐
        │ VALIDASI:         │  │ VALIDASI:           │
        │ Available Amount  │  │ Total Available     │
        │ (single credit)   │  │ (all admin credits) │
        └─────────┬─────────┘  └──────────┬──────────┘
                  ↓                       ↓
            [VALID?]                [VALID?]
           ↙        ↘             ↙        ↘
        [YA]      [TIDAK]      [YA]      [TIDAK]
          ↓          ↓          ↓          ↓
    ┌─────────┐  ┌──────┐  ┌─────────┐  ┌──────┐
    │ Lanjut  │  │Error │  │ Lanjut  │  │Error │
    └────┬────┘  └──────┘  └────┬────┘  └──────┘
         ↓                       ↓
    ┌─────────────────┐  ┌──────────────────────┐
    │ Buat Transaction│  │ Buat Transaction     │
    │ - seller = user │  │ - seller = admin     │
    │ - buyer = admin │  │ - buyer = user       │
    └────────┬────────┘  └──────────┬───────────┘
             ↓                      ↓
    ┌─────────────────┐  ┌──────────────────────┐
    │ Buat 1 Detail:  │  │ Distribusi ke        │
    │ - vehicle = NULL│  │ Multiple Credits:    │
    │ - amount = full │  │ - vehicle = selected │
    │                 │  │ - FIFO distribution  │
    └────────┬────────┘  └──────────┬───────────┘
             ↓                      ↓
             └──────────┬───────────┘
                        ↓
              ┌─────────────────────┐
              │ RESERVE QUOTA       │
              │ - Decrement qty     │
              │ - Decrement amount  │
              │ - Update status     │
              └──────────┬──────────┘
                         ↓
              ┌─────────────────────┐
              │ Generate Midtrans   │
              │ Snap Token          │
              └──────────┬──────────┘
                         ↓
              ┌─────────────────────┐
              │ Redirect ke         │
              │ Payment Gateway     │
              └─────────────────────┘
                         ↓
                    [LANJUT KE KONDISI 3 atau 4]
```

#### Contoh Kasus

**Kasus 1: Admin Membeli dari User**
```
Marketplace Admin:
- User Budi: 700 kg @ Rp 100 = Rp 70.000
- User Ani: 350 kg @ Rp 100 = Rp 35.000

Admin klik "Beli" pada kuota Budi
↓
Sistem otomatis:
- Quantity: 700 kg (full)
- Vehicle: NULL (admin tidak perlu)
- Total: Rp 70.000
↓
Reserve quota:
- Budi quantity_to_sell: 700 → 0
- Budi amount: 800 → 100
- Status: sold
↓
Generate payment → Admin bayar
```

**Kasus 2: User Membeli dari Admin**
```
Marketplace User:
- Admin Credit 1: 500 kg
- Admin Credit 2: 300 kg
- Total Available: 800 kg

User Doni input:
- Quantity: 600 kg
- Vehicle: Mobil B 9999 ZZZ
↓
Distribusi:
1. Credit 1: 500 kg (habis)
2. Credit 2: 100 kg (sisa 200 kg)
↓
Buat 2 TransactionDetail:
- Detail 1: 500 kg, vehicle = B 9999 ZZZ
- Detail 2: 100 kg, vehicle = B 9999 ZZZ
↓
Total: Rp 60.000
↓
Generate payment → User bayar
```

**Kasus 3: Pembelian Gagal (Insufficient Quota)**
```
User Eka input: 900 kg
Total Available: 800 kg
❌ 900 > 800 → ERROR

Return: "Jumlah melebihi kuota yang tersedia"
```

---

### 14.3 KONDISI 3: MONEY FLOW - USER SEBAGAI PENJUAL & ADMIN SEBAGAI PEMBELI

**Deskripsi**: Aliran uang dari Admin (pembeli) ke User (penjual) melalui payment gateway dan payout system.

#### Aktor yang Terlibat
- **Admin**: Pembeli kuota (pihak yang membayar)
- **User**: Penjual kuota (pihak yang menerima uang)
- **Midtrans Payment**: Memproses pembayaran dari admin
- **Midtrans Payout**: Memproses pencairan ke user
- **Bank**: Institusi keuangan yang memfasilitasi transfer

#### Visualisasi Alur Uang Garis Besar

```
KONDISI 3: USER SEBAGAI PENJUAL & ADMIN SEBAGAI PEMBELI
═══════════════════════════════════════════════════════════

[ADMIN PEMBELI] → [ESCROW MIDTRANS] → [MERCHANT SISTEM] → [REKENING USER PENJUAL]
     Rp 70.000         Rp 70.000          Rp 67.970            Rp 64.000
                                        (settlement)           (payout)
```

**Penjelasan Alur:**
1. **Admin bayar** Rp 70.000 → Masuk escrow Midtrans
2. **Settlement** → Midtrans transfer ke merchant (potong fee 2.9%)
3. **Payout** → Merchant transfer ke user (potong admin fee 5% + payout fee)
4. **User terima** Rp 64.000 di rekening bank

---

#### Alur Money Flow Lengkap

```
┌─────────────────────────────────────────────────────────────────┐
│   KONDISI 3: MONEY FLOW (USER PENJUAL ← ADMIN PEMBELI)         │
└─────────────────────────────────────────────────────────────────┘

[TAHAP 1] ADMIN BAYAR KE MIDTRANS
    ┌──────────────────┐
    │  ADMIN (Pembeli) │
    │  Bayar: Rp 70.000│
    └────────┬─────────┘
             ↓ (Pilih metode: GoPay/Bank Transfer/dll)
    ┌────────────────────────┐
    │  MIDTRANS PAYMENT      │
    │  Terima: Rp 70.000     │
    │  (Escrow/Penampungan)  │
    └────────┬───────────────┘
             ↓
    [Midtrans Notifikasi Sistem]
    Status: settlement

[TAHAP 2] SETTLEMENT KE REKENING MERCHANT
    ┌────────────────────────┐
    │  MIDTRANS PAYMENT      │
    │  Rp 70.000             │
    └────────┬───────────────┘
             ↓ (T+1 hari kerja)
             ↓ (Potong fee Midtrans ~2.9%)
    ┌────────────────────────┐
    │  REKENING MERCHANT     │
    │  (Rekening Sistem)     │
    │  Terima: Rp 67.970     │
    │  (70.000 - 2.030)      │
    └────────┬───────────────┘
             ↓
    [Sistem Update Transaction]
    Status: success
    paid_at: timestamp

[TAHAP 3] SISTEM BUAT PAYOUT OTOMATIS
    ┌────────────────────────┐
    │  SISTEM                │
    │  Hitung Payout:        │
    │  - Amount: Rp 70.000   │
    │  - Admin Fee: Rp 3.500 │
    │    (5% dari 70.000)    │
    │  - Net: Rp 66.500      │
    └────────┬───────────────┘
             ↓
    ┌────────────────────────┐
    │  PAYOUT RECORD         │
    │  Status: pending       │
    │  Net Amount: Rp 66.500 │
    └────────────────────────┘

[TAHAP 4] USER REQUEST PENCAIRAN
    ┌────────────────────────┐
    │  USER (Penjual)        │
    │  Klik "Cairkan Dana"   │
    │  Input info bank:      │
    │  - Bank: BCA           │
    │  - No Rek: 1234567890  │
    │  - Nama: Budi          │
    └────────┬───────────────┘
             ↓
    ┌────────────────────────┐
    │  MIDTRANS PAYOUT API   │
    │  Create Payout Request │
    │  Amount: Rp 66.500     │
    └────────┬───────────────┘
             ↓
    [Midtrans Kirim OTP]
    Status: created

[TAHAP 5] APPROVAL DENGAN OTP
    ┌────────────────────────┐
    │  USER                  │
    │  Terima OTP: 123456    │
    │  Input OTP di form     │
    └────────┬───────────────┘
             ↓
    ┌────────────────────────┐
    │  MIDTRANS PAYOUT API   │
    │  Approve dengan OTP    │
    └────────┬───────────────┘
             ↓
    [Status: processing]

[TAHAP 6] TRANSFER KE REKENING USER
    ┌────────────────────────┐
    │  REKENING MERCHANT     │
    │  Rp 67.970             │
    └────────┬───────────────┘
             ↓ (Instant/1 hari kerja)
             ↓ (Potong payout fee ~Rp 2.500)
    ┌────────────────────────┐
    │  REKENING USER (Budi)  │
    │  Terima: Rp 64.000     │
    │  (66.500 - 2.500)      │
    └────────────────────────┘
             ↓
    [Midtrans Notifikasi Sistem]
    Status: completed

[TAHAP 7] NOTIFIKASI SELESAI
    ┌────────────────────────┐
    │  USER                  │
    │  Email: "Dana Rp 64.000│
    │  telah ditransfer"     │
    │  Dashboard: Completed  │
    └────────────────────────┘

[END]
```

#### Breakdown Biaya Kondisi 3

**Contoh: Admin membeli 700 kg dari User Budi**

```
TRANSAKSI:
700 kg × Rp 100 = Rp 70.000

ALUR UANG:
┌─────────────────────────────────────────────┐
│ Admin Bayar: Rp 70.000                      │
└──────────────────┬──────────────────────────┘
                   ↓
┌─────────────────────────────────────────────┐
│ Midtrans Payment (Escrow)                   │
│ Hold: Rp 70.000                             │
└──────────────────┬──────────────────────────┘
                   ↓ (T+1 hari kerja)
┌─────────────────────────────────────────────┐
│ Settlement ke Rekening Merchant             │
│ Rp 70.000 - 2.9% = Rp 67.970               │
│ (Fee Midtrans: Rp 2.030)                    │
└──────────────────┬──────────────────────────┘
                   ↓
┌─────────────────────────────────────────────┐
│ Sistem Buat Payout                          │
│ Amount: Rp 70.000                           │
│ Admin Fee 5%: Rp 3.500                      │
│ Net Amount: Rp 66.500                       │
└──────────────────┬──────────────────────────┘
                   ↓
┌─────────────────────────────────────────────┐
│ User Request Payout + OTP                   │
│ Midtrans Payout API                         │
└──────────────────┬──────────────────────────┘
                   ↓ (Instant/1 hari)
┌─────────────────────────────────────────────┐
│ Transfer ke Rekening User                   │
│ Rp 66.500 - Rp 2.500 = Rp 64.000           │
│ (Fee Payout: Rp 2.500)                      │
└─────────────────────────────────────────────┘

RINGKASAN:
- Admin Bayar:        Rp 70.000
- Midtrans Fee:       Rp  2.030 (2.9%)
- Admin Fee Sistem:   Rp  3.500 (5%)
- Payout Fee:         Rp  2.500 (flat)
─────────────────────────────────
- User Terima:        Rp 61.970

CATATAN: Dalam implementasi aktual, admin fee 5% 
sudah dipotong dari amount, sehingga:
Net Amount = Rp 70.000 - Rp 3.500 = Rp 66.500
User terima setelah payout fee: Rp 64.000
```

#### Timeline Kondisi 3

**Skenario Tercepat (E-Wallet + Instant Transfer):**
```
T+0 menit:    Admin bayar via GoPay (Rp 70.000)
T+1 menit:    Midtrans settlement → Status success
T+1 menit:    Sistem auto-create payout (Rp 66.500)
T+2 menit:    User request payout
T+3 menit:    User input OTP & approve
T+5 menit:    Uang masuk rekening user (Rp 64.000)
─────────────────────────────────────────────────
TOTAL: ~5 menit
```

**Skenario Normal (Bank Transfer + SKN):**
```
T+0 hari:     Admin bayar via Bank Transfer
T+1 hari:     Midtrans settlement → Status success
T+1 hari:     Sistem auto-create payout
T+2 hari:     User request payout + OTP
T+3 hari:     Uang masuk rekening user (SKN)
─────────────────────────────────────────────────
TOTAL: ~3 hari kerja
```

#### Contoh Kasus Kondisi 3

**Kasus 1: Transaksi Sukses Penuh**
```
Transaksi:
- Seller: User Budi (B 1234 XYZ, 700 kg)
- Buyer: Admin
- Amount: 700 kg × Rp 100 = Rp 70.000

Alur:
1. Admin klik "Beli" → Transaction created (pending)
2. Admin bayar via GoPay → Rp 70.000
3. Midtrans settlement (T+1) → Rp 67.970 ke merchant
4. Sistem create payout → Net: Rp 66.500
5. Budi request payout → Input bank BCA
6. Budi input OTP → Approve
7. Transfer ke rekening Budi → Rp 64.000
8. Status: completed

Result:
✅ Admin dapat 700 kg kuota
✅ Budi terima Rp 64.000
✅ Sistem dapat Rp 3.500 admin fee
```

**Kasus 2: Pembayaran Gagal**
```
Transaksi:
- Seller: User Ani (B 5678 ABC, 350 kg)
- Buyer: Admin
- Amount: 350 kg × Rp 100 = Rp 35.000

Alur:
1. Admin klik "Beli" → Transaction created (pending)
2. Quota reserved: Ani 350 kg → 0 kg
3. Admin bayar via Bank Transfer
4. Pembayaran expired/gagal
5. Midtrans notification: status = failed
6. Sistem restore quota: Ani 0 kg → 350 kg
7. Status kembali: available

Result:
❌ Transaction failed
✅ Quota restored ke marketplace
❌ Tidak ada payout dibuat
```

**Kasus 3: Payout Gagal (Rekening Invalid)**
```
Transaksi:
- Seller: User Citra
- Amount: Rp 50.000
- Net Payout: Rp 47.500

Alur:
1. Transaction success → Payout created
2. Citra request payout
3. Input rekening: 9999999999 (invalid)
4. Midtrans Payout API: Create failed
5. Status: failed
6. Error: "Invalid bank account"

Result:
✅ Transaction tetap success
❌ Payout failed
⚠️ Citra harus update rekening & retry
```

---

### 14.4 KONDISI 4: MONEY FLOW - ADMIN SEBAGAI PENJUAL & USER SEBAGAI PEMBELI

**Deskripsi**: Aliran uang dari User (pembeli) ke Admin (penjual) melalui payment gateway, tanpa payout karena admin tidak perlu pencairan.

#### Aktor yang Terlibat
- **User**: Pembeli kuota (pihak yang membayar)
- **Admin**: Penjual kuota (pihak yang menerima uang)
- **Midtrans Payment**: Memproses pembayaran dari user
- **Bank**: Institusi keuangan yang memfasilitasi transfer

#### Visualisasi Alur Uang Garis Besar

```
KONDISI 4: ADMIN SEBAGAI PENJUAL & USER SEBAGAI PEMBELI
═══════════════════════════════════════════════════════════

[USER PEMBELI] → [ESCROW MIDTRANS] → [MERCHANT SISTEM]
    Rp 60.000         Rp 60.000          Rp 58.260
                                       (settlement)
                                       ⚠️ TIDAK ADA PAYOUT
                                       Uang tetap di sistem
```

**Penjelasan Alur:**
1. **User bayar** Rp 60.000 → Masuk escrow Midtrans
2. **Settlement** → Midtrans transfer ke merchant (potong fee 2.9%)
3. **STOP** → Tidak ada payout karena admin tidak perlu pencairan
4. **Uang tetap** di rekening merchant sistem (Rp 58.260)

**Perbedaan Utama dengan Kondisi 3:**
- ❌ Tidak ada tahap payout ke rekening penjual
- ❌ Tidak ada admin fee 5%
- ❌ Tidak ada payout fee Rp 2.500
- ✅ Uang tetap di rekening merchant untuk operasional sistem

---

#### Alur Money Flow Lengkap

```
┌─────────────────────────────────────────────────────────────────┐
│   KONDISI 4: MONEY FLOW (ADMIN PENJUAL ← USER PEMBELI)         │
└─────────────────────────────────────────────────────────────────┘

[TAHAP 1] USER BAYAR KE MIDTRANS
    ┌──────────────────┐
    │  USER (Pembeli)  │
    │  Bayar: Rp 60.000│
    │  (600 kg × 100)  │
    └────────┬─────────┘
             ↓ (Pilih metode: GoPay/Bank Transfer/dll)
    ┌────────────────────────┐
    │  MIDTRANS PAYMENT      │
    │  Terima: Rp 60.000     │
    │  (Escrow/Penampungan)  │
    └────────┬───────────────┘
             ↓
    [Midtrans Notifikasi Sistem]
    Status: settlement

[TAHAP 2] SETTLEMENT KE REKENING MERCHANT
    ┌────────────────────────┐
    │  MIDTRANS PAYMENT      │
    │  Rp 60.000             │
    └────────┬───────────────┘
             ↓ (T+1 hari kerja)
             ↓ (Potong fee Midtrans ~2.9%)
    ┌────────────────────────┐
    │  REKENING MERCHANT     │
    │  (Rekening Sistem)     │
    │  Terima: Rp 58.260     │
    │  (60.000 - 1.740)      │
    └────────┬───────────────┘
             ↓
    [Sistem Update Transaction]
    Status: success
    paid_at: timestamp

[TAHAP 3] DISTRIBUSI KUOTA KE USER
    ┌────────────────────────┐
    │  SISTEM                │
    │  Proses TransactionDetails:│
    │  - Credit 1: 500 kg    │
    │  - Credit 2: 100 kg    │
    └────────┬───────────────┘
             ↓
    ┌────────────────────────┐
    │  USER VEHICLE          │
    │  (Mobil B 9999 ZZZ)    │
    │  Amount: +600 kg       │
    │  Total: 800 + 600      │
    │       = 1400 kg        │
    └────────────────────────┘

[TAHAP 4] TIDAK ADA PAYOUT
    ┌────────────────────────┐
    │  CATATAN PENTING:      │
    │  ✗ Tidak ada payout    │
    │    dibuat untuk admin  │
    │  ✓ Uang tetap di       │
    │    rekening merchant   │
    │  ✓ Admin dapat kuota   │
    │    untuk dijual lagi   │
    └────────────────────────┘

[END]
```

#### Perbedaan Kondisi 3 vs Kondisi 4

**Perbandingan Alur Uang:**

```
┌─────────────────────────────────────────────────────────────────┐
│                    PERBANDINGAN ALUR UANG                        │
└─────────────────────────────────────────────────────────────────┘

KONDISI 3 (User Penjual):
[ADMIN] → [ESCROW] → [MERCHANT] → [USER]
  70k       70k       67.97k      64k
                    (settlement) (payout)
                    
KONDISI 4 (Admin Penjual):
[USER] → [ESCROW] → [MERCHANT] ⛔ STOP
  60k      60k       58.26k
                   (settlement)
```

| Aspek | Kondisi 3 (User Penjual) | Kondisi 4 (Admin Penjual) |
|-------|--------------------------|---------------------------|
| **Pembeli** | Admin | User |
| **Penjual** | User | Admin |
| **Alur Lengkap** | Pembeli → Escrow → Merchant → Penjual | Pembeli → Escrow → Merchant (STOP) |
| **Payout Created?** | ✅ Ya | ❌ Tidak |
| **Admin Fee** | 5% dipotong | Tidak ada |
| **Payout Fee** | Rp 2.500 | Tidak ada |
| **Pencairan Dana** | User request + OTP | Tidak perlu |
| **Uang Akhir** | Ke rekening user | Tetap di merchant |
| **Vehicle Selection** | Tidak perlu | Wajib |
| **Distribution** | Single credit | Multiple credits |
| **Total Tahap** | 4 tahap (bayar → settlement → payout → terima) | 2 tahap (bayar → settlement) |

#### Breakdown Biaya Kondisi 4

**Contoh: User Doni membeli 600 kg dari Admin**

```
TRANSAKSI:
600 kg × Rp 100 = Rp 60.000

ALUR UANG:
┌─────────────────────────────────────────────┐
│ User Bayar: Rp 60.000                       │
└──────────────────┬──────────────────────────┘
                   ↓
┌─────────────────────────────────────────────┐
│ Midtrans Payment (Escrow)                   │
│ Hold: Rp 60.000                             │
└──────────────────┬──────────────────────────┘
                   ↓ (T+1 hari kerja)
┌─────────────────────────────────────────────┐
│ Settlement ke Rekening Merchant             │
│ Rp 60.000 - 2.9% = Rp 58.260               │
│ (Fee Midtrans: Rp 1.740)                    │
└──────────────────┬──────────────────────────┘
                   ↓
┌─────────────────────────────────────────────┐
│ TIDAK ADA PAYOUT                            │
│ Uang tetap di rekening merchant             │
│ Admin tidak perlu pencairan                 │
└──────────────────┬──────────────────────────┘
                   ↓
┌─────────────────────────────────────────────┐
│ Kuota Masuk ke Vehicle User                 │
│ Vehicle: Mobil B 9999 ZZZ                   │
│ Amount: +600 kg                             │
└─────────────────────────────────────────────┘

RINGKASAN:
- User Bayar:         Rp 60.000
- Midtrans Fee:       Rp  1.740 (2.9%)
─────────────────────────────────
- Masuk Merchant:     Rp 58.260
- Admin Fee:          Rp      0 (tidak ada)
- Payout Fee:         Rp      0 (tidak ada)
─────────────────────────────────
- Tetap di Sistem:    Rp 58.260

CATATAN: Uang tetap di rekening merchant/sistem
karena admin tidak perlu pencairan dana.
```

#### Timeline Kondisi 4

**Skenario Tercepat (E-Wallet):**
```
T+0 menit:    User bayar via GoPay (Rp 60.000)
T+1 menit:    Midtrans settlement → Status success
T+1 menit:    Kuota masuk ke vehicle user (600 kg)
T+1 menit:    Transaction completed
─────────────────────────────────────────────────
TOTAL: ~1 menit
```

**Skenario Normal (Bank Transfer):**
```
T+0 hari:     User bayar via Bank Transfer
T+1 hari:     Midtrans settlement → Status success
T+1 hari:     Kuota masuk ke vehicle user
T+1 hari:     Transaction completed
─────────────────────────────────────────────────
TOTAL: ~1 hari kerja
```

#### Contoh Kasus Kondisi 4

**Kasus 1: Pembelian Sukses dari Single Admin Credit**
```
Marketplace:
- Admin Credit 1: 800 kg available

User Eka input:
- Quantity: 500 kg
- Vehicle: Motor B 1111 AAA

Alur:
1. User klik "Beli" → Transaction created (pending)
2. Distribusi: 500 kg dari Credit 1
3. Reserve quota: Credit 1 (800 → 300 kg)
4. User bayar via GoPay → Rp 50.000
5. Midtrans settlement → Rp 48.550 ke merchant
6. Kuota masuk vehicle: Motor +500 kg
7. Status: success

Result:
✅ User dapat 500 kg di motornya
✅ Admin Credit 1 sisa 300 kg
✅ Sistem terima Rp 48.550
❌ Tidak ada payout dibuat
```

**Kasus 2: Pembelian dari Multiple Admin Credits**
```
Marketplace:
- Admin Credit 1: 500 kg
- Admin Credit 2: 300 kg
- Total: 800 kg

User Fani input:
- Quantity: 600 kg
- Vehicle: Mobil B 2222 BBB

Alur:
1. User klik "Beli" → Transaction created
2. Distribusi FIFO:
   - Credit 1: 500 kg (habis)
   - Credit 2: 100 kg (sisa 200 kg)
3. Buat 2 TransactionDetail:
   - Detail 1: 500 kg, vehicle = B 2222 BBB
   - Detail 2: 100 kg, vehicle = B 2222 BBB
4. Reserve quota:
   - Credit 1: 500 → 0 kg (sold)
   - Credit 2: 300 → 200 kg
5. User bayar → Rp 60.000
6. Settlement → Rp 58.260 ke merchant
7. Kuota masuk vehicle: Mobil +600 kg

Result:
✅ User dapat 600 kg di mobilnya
✅ Credit 1 habis (sold)
✅ Credit 2 sisa 200 kg
✅ Sistem terima Rp 58.260
```

**Kasus 3: User Bisa Jual Lagi Kuota yang Dibeli**
```
User Gani:
- Punya mobil: 800 kg (kuota awal)
- Beli dari admin: 600 kg
- Total sekarang: 1400 kg
- Emisi harian: 100 kg
- Effective quota: 1400 - 100 = 1300 kg

Gani bisa request sale:
- Quantity to sell: 1200 kg
- Maksimal: 1300 kg ✅ VALID

Alur:
1. Gani submit sale request: 1200 kg
2. Admin approve
3. Masuk marketplace admin
4. Admin bisa beli dari Gani
5. Cycle berulang (Kondisi 3)

Result:
✅ User bisa jual kuota yang dibeli
✅ Cycle jual-beli terus berjalan
```

---

### 14.5 PERBANDINGAN 4 KONDISI

#### Tabel Perbandingan Lengkap

| Aspek | Kondisi 1 | Kondisi 2A | Kondisi 2B | Kondisi 3 | Kondisi 4 |
|-------|-----------|------------|------------|-----------|-----------|
| **Nama** | Pengajuan Penjualan | Pembelian (Admin) | Pembelian (User) | Money Flow (User→Admin) | Money Flow (Admin→User) |
| **Aktor Utama** | User | Admin | User | Admin & User | User & Admin |
| **Seller** | User | User | Admin | User | Admin |
| **Buyer** | - | Admin | User | Admin | User |
| **Approval Needed** | ✅ Ya (Admin) | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak |
| **Quantity Input** | Manual | Auto (full) | Manual | - | - |
| **Vehicle Selection** | ❌ Tidak | ❌ Tidak | ✅ Ya (wajib) | - | ✅ Ya |
| **Distribution** | Single | Single | Multiple (FIFO) | - | Multiple |
| **Payment Gateway** | ❌ Tidak | ✅ Ya | ✅ Ya | ✅ Ya | ✅ Ya |
| **Payout Created** | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | ❌ Tidak |
| **Admin Fee** | - | - | - | 5% | 0% |
| **Payout Fee** | - | - | - | Rp 2.500 | - |
| **Midtrans Fee** | - | 2.9% | 2.9% | 2.9% | 2.9% |
| **OTP Required** | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya (payout) | ❌ Tidak |
| **Timeline** | 1-2 hari | 1-5 menit | 1-5 menit | 3-5 hari | 1 hari |

#### Flow Diagram Gabungan 4 Kondisi

```
┌─────────────────────────────────────────────────────────────────┐
│           FLOW DIAGRAM GABUNGAN 4 KONDISI UTAMA                 │
└─────────────────────────────────────────────────────────────────┘

                        [START]
                           ↓
                  ┌────────────────┐
                  │ User Register  │
                  │ Kendaraan      │
                  └────────┬───────┘
                           ↓
                  ┌────────────────┐
                  │ Admin Approve  │
                  │ Alokasi Kuota  │
                  └────────┬───────┘
                           ↓
                  ┌────────────────┐
                  │ Monitoring     │
                  │ Emisi (MQTT)   │
                  └────────┬───────┘
                           ↓
        ┌──────────────────┴──────────────────┐
        ↓                                      ↓
┌───────────────┐                    ┌────────────────┐
│ KONDISI 1:    │                    │ Admin punya    │
│ User Request  │                    │ kuota di       │
│ Sale          │                    │ marketplace    │
└───────┬───────┘                    └────────┬───────┘
        ↓                                     ↓
┌───────────────┐                    ┌────────────────┐
│ Admin Approve │                    │ KONDISI 2B:    │
│ Sale          │                    │ User Beli dari │
└───────┬───────┘                    │ Admin          │
        ↓                            └────────┬───────┘
┌───────────────┐                            ↓
│ Masuk         │                    ┌────────────────┐
│ Marketplace   │                    │ KONDISI 4:     │
└───────┬───────┘                    │ Money Flow     │
        ↓                            │ Admin→User     │
┌───────────────┐                    └────────┬───────┘
│ KONDISI 2A:   │                            ↓
│ Admin Beli    │                    ┌────────────────┐
│ dari User     │                    │ Kuota masuk    │
└───────┬───────┘                    │ vehicle user   │
        ↓                            └────────┬───────┘
┌───────────────┐                            ↓
│ KONDISI 3:    │                    ┌────────────────┐
│ Money Flow    │                    │ User bisa      │
│ User→Admin    │                    │ request sale   │
└───────┬───────┘                    │ lagi (Cycle)   │
        ↓                            └────────────────┘
┌───────────────┐
│ User Request  │
│ Payout + OTP  │
└───────┬───────┘
        ↓
┌───────────────┐
│ Dana masuk    │
│ rekening user │
└───────┬───────┘
        ↓
┌───────────────┐
│ Admin dapat   │
│ kuota untuk   │
│ dijual lagi   │
└───────────────┘
        ↓
    [CYCLE BERULANG]
```

---

### 14.6 KESIMPULAN 4 KONDISI

#### Kondisi 1: Pengajuan Penjualan Kuota Karbon
**Tujuan**: User mengajukan kuota yang ingin dijual ke marketplace
**Key Points**:
- ✅ Butuh approval admin
- ✅ Validasi effective quota (total - emisi harian)
- ✅ Recalculate saat approval
- ✅ Validasi berkelanjutan di marketplace
- ⏱️ Timeline: 1-2 hari (approval process)

#### Kondisi 2: Pembelian Kuota Karbon
**Tujuan**: Buyer (Admin/User) membeli kuota dari marketplace
**Key Points**:
- ✅ Admin: Auto full quantity, no vehicle
- ✅ User: Manual quantity, wajib vehicle
- ✅ User: Distribusi FIFO ke multiple credits
- ✅ Reserve quota saat pending
- ⏱️ Timeline: 1-5 menit (instant)

#### Kondisi 3: Money Flow (User Penjual ← Admin Pembeli)
**Tujuan**: Aliran uang dari admin ke user dengan payout
**Key Points**:
- ✅ Payment via Midtrans (2.9% fee)
- ✅ Admin fee 5% dipotong
- ✅ Payout butuh OTP approval
- ✅ Payout fee Rp 2.500
- ⏱️ Timeline: 3-5 hari kerja (full cycle)

#### Kondisi 4: Money Flow (Admin Penjual ← User Pembeli)
**Tujuan**: Aliran uang dari user ke admin tanpa payout
**Key Points**:
- ✅ Payment via Midtrans (2.9% fee)
- ❌ Tidak ada admin fee
- ❌ Tidak ada payout dibuat
- ✅ Uang tetap di merchant
- ⏱️ Timeline: 1 hari kerja (settlement only)

#### Cycle Bisnis Lengkap

```
1. User register kendaraan → Dapat kuota awal
2. User pakai kendaraan → Emisi tercatat
3. User punya sisa kuota → Request sale (Kondisi 1)
4. Admin approve → Masuk marketplace
5. Admin beli dari user → Payment (Kondisi 2A + 3)
6. User terima uang → Payout completed
7. Admin dapat kuota → Jual ke user lain
8. User lain beli → Payment (Kondisi 2B + 4)
9. Kuota masuk vehicle → User bisa jual lagi
10. Cycle berulang → Sustainable marketplace
```

---

## KESIMPULAN

Sistem Carbon Credit Marketplace ini mengimplementasikan proses bisnis lengkap untuk:

1. **Alokasi Kuota**: Berdasarkan tipe kendaraan dengan monitoring emisi real-time
2. **Penjualan**: User menjual kelebihan kuota ke admin dengan approval workflow
3. **Pembelian**: Admin membeli dari user, user membeli dari admin dengan distribusi otomatis
4. **Pembayaran**: Terintegrasi penuh dengan Midtrans untuk berbagai metode pembayaran
5. **Payout**: Pencairan dana otomatis dengan approval OTP

**4 Kondisi Proses Bisnis Utama** yang telah dijelaskan secara detail:
- **Kondisi 1**: Pengajuan penjualan kuota karbon (User → Marketplace)
- **Kondisi 2**: Pembelian kuota karbon (Admin/User → Seller)
- **Kondisi 3**: Money flow dengan payout (User penjual ← Admin pembeli)
- **Kondisi 4**: Money flow tanpa payout (Admin penjual ← User pembeli)

Sistem ini memastikan transparansi, keamanan, dan efisiensi dalam perdagangan kredit karbon dengan monitoring emisi real-time melalui IoT, serta aliran uang yang jelas dan teraudit melalui payment gateway Midtrans.
