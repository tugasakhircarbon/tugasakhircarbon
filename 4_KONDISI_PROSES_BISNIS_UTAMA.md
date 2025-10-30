# 4 KONDISI PROSES BISNIS UTAMA
## SISTEM CARBON CREDIT MARKETPLACE

Dokumen ini menjelaskan secara eksplisit 4 kondisi proses bisnis yang menjadi inti dari sistem Carbon Credit Marketplace, dengan fokus pada alur pengajuan, pembelian, dan money flow.

---

## KONDISI 1: PENGAJUAN PENJUALAN KUOTA KARBON

**Deskripsi**: Proses dimana pemilik kendaraan (User) mengajukan kuota karbon yang ingin dijual ke marketplace.

### Aktor yang Terlibat
- **User (Pemilik Kendaraan)**: Penjual kuota karbon
- **Admin**: Pihak yang mereview dan menyetujui pengajuan
- **Sistem**: Melakukan validasi otomatis

### Prasyarat (Prerequisites)
1. ✅ User sudah mendaftarkan kendaraan
2. ✅ Kendaraan sudah disetujui admin (status = 'available')
3. ✅ User sudah mendapat alokasi kuota awal (800 kg untuk mobil, 500 kg untuk motor)
4. ✅ Sistem monitoring emisi sudah aktif (MQTT integration)
5. ✅ User memiliki effective quota > 0

### Langkah-langkah Proses

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

### Validasi Berkelanjutan

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

### Hasil Akhir (Outcome)

✅ **Berhasil**:
- Status berubah menjadi 'available'
- Kuota muncul di marketplace admin
- Siap dibeli oleh admin

❌ **Gagal**:
- Quantity melebihi effective quota
- Status tidak valid
- Emisi bertambah setelah approval

### Diagram Alur Kondisi 1

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

### Contoh Kasus

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

## KONDISI 2: PEMBELIAN KUOTA KARBON

**Deskripsi**: Proses dimana pembeli (Admin atau User) membeli kuota karbon dari marketplace.

### Aktor yang Terlibat
- **Buyer (Admin/User)**: Pihak yang membeli kuota
- **Seller (User/Admin)**: Pihak yang menjual kuota
- **Payment Gateway (Midtrans)**: Memproses pembayaran
- **Sistem**: Mengelola transaksi dan distribusi kuota

### Prasyarat (Prerequisites)

**Untuk Admin sebagai Buyer:**
1. ✅ Ada kuota user di marketplace (status = 'available')
2. ✅ Admin memiliki akses ke admin marketplace
3. ✅ Kuota masih valid (sisa kuota ≥ quantity_to_sell)

**Untuk User sebagai Buyer:**
1. ✅ Ada kuota admin di marketplace (status = 'available')
2. ✅ User memiliki kendaraan terdaftar
3. ✅ User memiliki metode pembayaran

### Skenario A: Admin Membeli dari User

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

### Skenario B: User Membeli dari Admin

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

### Perbedaan Admin vs User sebagai Buyer

| Aspek | Admin Buyer | User Buyer |
|-------|-------------|------------|
| **Quantity Input** | Otomatis (full quantity_to_sell) | Manual input |
| **Vehicle Selection** | Tidak perlu (NULL) | Wajib pilih kendaraan |
| **Distribution** | Single credit | Multiple admin credits (FIFO) |
| **TransactionDetail.vehicle_id** | NULL | Required |
| **Seller** | User only | Admin only |
| **Marketplace** | Admin marketplace | User marketplace |

### Diagram Alur Kondisi 2

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

### Contoh Kasus

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

## KONDISI 3: MONEY FLOW - USER SEBAGAI PENJUAL & ADMIN SEBAGAI PEMBELI

**Deskripsi**: Aliran uang dari Admin (pembeli) ke User (penjual) melalui payment gateway dan payout system.

### Aktor yang Terlibat
- **Admin**: Pembeli kuota (pihak yang membayar)
- **User**: Penjual kuota (pihak yang menerima uang)
- **Midtrans Payment**: Memproses pembayaran dari admin
- **Midtrans Payout**: Memproses pencairan ke user
- **Bank**: Institusi keuangan yang memfasilitasi transfer

### Visualisasi Alur Uang Garis Besar

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

### Alur Money Flow Lengkap

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
