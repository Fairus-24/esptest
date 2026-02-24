# 🚀 Quick Start Guide - IoT Research System

## 5 Menit Setup

### Step 1: Database Setup (1 menit)
```bash
# Buka PowerShell di C:\xampp\htdocs\esptest

# Jalankan migrations
php artisan migrate

# Seed data devices
php artisan db:seed
```

### Step 2: Install Dependencies (2 menit)
```bash
composer install
npm install
npm run build
```

### Step 3: Start Server (1 menit)
```bash
composer run dev
```

Buka browser: **http://localhost:8000**

### Step 4: (Optional) Start MQTT Listener (1 menit)
Di terminal baru:
```bash
php artisan mqtt:listener
```

---

## 🧪 Langsung Test

### Test HTTP API
```bash
# Kirim data via HTTP dengan curl
curl -X POST http://localhost:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{"device_id":1,"suhu":25.5,"timestamp_esp":1708884000,"daya":100}'
```

**Expected Response (201):**
```json
{
  "success": true,
  "message": "Data HTTP berhasil disimpan",
  "data": { ... }
}
```

### Test MQTT (Opsional)
Jika sudah setup Mosquitto:
```bash
# Di terminal dengan PHPUnit berjalan, publish data:
mosquitto_pub -h 127.0.0.1 -t "iot/esp32/suhu" -m '{"device_id":1,"suhu":28.3,"timestamp_esp":1708884200,"daya":120}'
```

---

## 📊 View Dashboard

1. Buka: `http://localhost:8000/`
2. Lihat statistik real-time dari MQTT dan HTTP
3. Analisis grafik perbandingan latensi & daya
4. Lihat hasil T-Test otomatis di bawah

---

## 📝 File Konfigurasi Penting

### `.env` - Database Configuration
```
DB_DATABASE=esptest
DB_USERNAME=root
DB_PASSWORD=
```

### Routes - `routes/api.php` dan `routes/web.php`
- POST `/api/http-data` → Menerima data HTTP
- GET `/` → Dashboard

### Controllers
- `ApiController` - Handle HTTP data
- `DashboardController` - Tampilkan dashboard dengan statistik

### Services
- `StatisticsService` - Hitung mean, variance, t-test otomatis

---

## 🔥 Menggunakan dengan ESP32

### Arduino Code (Minimal HTTP)
```cpp
#include <WiFi.h>
#include <HTTPClient.h>

void loop() {
  HTTPClient http;
  http.begin("http://192.168.1.X:8000/api/http-data");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"device_id\":1,\"suhu\":25.5,\"timestamp_esp\":1708884000,\"daya\":100}";
  http.POST(payload);
  http.end();
  
  delay(5000);
}
```

### Arduino Code (Minimal MQTT)
```cpp
#include <PubSubClient.h>

void loop() {
  if (!client.connected()) client.connect("ESP32");
  
  String payload = "{\"device_id\":1,\"suhu\":25.5,\"timestamp_esp\":1708884000,\"daya\":100}";
  client.publish("iot/esp32/suhu", payload.c_str());
  client.loop();
  
  delay(5000);
}
```

---

## 🛠️ Troubleshooting Cepat

| Problem | Solusi |
|---------|--------|
| Database tidak terkoneksi | Pastikan MySQL running, `php artisan migrate` |
| Port 8000 sudah terpakai | `php artisan serve --port=8001` |
| MQTT tidak connect | Pastikan Mosquitto service running |
| Dashboard kosong | Buka terminal baru, jalankan: `php artisan mqtt:listener` atau kirim data HTTP |
| Migration error | `php artisan migrate:refresh` |

---

## 📚 Next Steps - Development Lanjutan

1. **Tambah device baru** - Di database via `php artisan tinker`:
   ```php
   App\Models\Device::create(['nama_device' => 'ESP32 #4', 'lokasi' => 'Lokasi baru']);
   ```

2. **Custom statistik** - Edit `app/Services/StatisticsService.php`

3. **Custom dashboard** - Edit `resources/views/dashboard.blade.php`

4. **Add authentication** - `php artisan make:auth`

5. **Export data** - Tambah endpoint CSV export

---

**Selamat menggunakan! Sukses untuk skripsimu! 🎓**
