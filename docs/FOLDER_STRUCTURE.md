# 📁 Struktur Folder Lengkap - Final Directory Structure

```
c:\xampp\htdocs\esptest\
│
├── 📄 .env                                    # ✅ Konfigurasi database & app
├── 📄 composer.json                           # ✅ PHP dependencies (php-mqtt/client ditambah)
├── 📄 package.json                            # ✅ npm dependencies
├── 📄 artisan                                 # ✅ Laravel CLI
│
├── 📂 app/
│   ├── 📂 Console/
│   │   ├── 📂 Commands/
│   │   │   └── MqttListener.php              # ✅ MQTT Listener Command
│   │   │       - Signature: mqtt:listener
│   │   │       - Subscribe: iot/esp32/suhu
│   │   │       - Auto save MQTT data ke database
│   │   │
│   │   └── Kernel.php
│   │
│   ├── 📂 Http/
│   │   ├── 📂 Controllers/
│   │   │   ├── ApiController.php             # ✅ HTTP API Handler
│   │   │   │   - POST /api/http-data
│   │   │   │   - Terima JSON dari ESP32
│   │   │   │   - Hitung latency & save
│   │   │   │
│   │   │   └── DashboardController.php       # ✅ Dashboard Handler
│   │   │       - GET /
│   │   │       - Return view dengan statistik
│   │   │
│   │   └── Middleware/
│   │
│   ├── 📂 Models/
│   │   ├── Device.php                        # ✅ Model Device
│   │   │   - fillable: nama_device, lokasi
│   │   │   - relation: hasMany(Eksperimen)
│   │   │
│   │   ├── Eksperimen.php                    # ✅ Model Eksperimen
│   │   │   - fillable: device_id, protokol, suhu, timestamps, latency, daya
│   │   │   - relation: belongsTo(Device)
│   │   │   - casts: timestamp fields
│   │   │
│   │   └── User.php
│   │
│   ├── 📂 Services/
│   │   └── StatisticsService.php             # ✅ Service Statistik & T-Test
│   │       - calculateMean()
│   │       - calculateVariance()
│   │       - calculateStdDev()
│   │       - tTest() → Independent Sample T-Test
│   │       - getSummary()
│   │       - getReliability()
│   │
│   ├── 📂 Providers/
│   │   ├── AppServiceProvider.php
│   │   └── RouteServiceProvider.php
│   │
│   └── Exceptions/
│
├── 📂 bootstrap/
│   ├── app.php                               # ✅ Application bootstrap
│   └── cache/
│
├── 📂 config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php                          # ✅ MySQL config
│   ├── cache.php
│   ├── filesystems.php
│   ├── mail.php
│   ├── queue.php
│   ├── services.php
│   ├── session.php
│   └── logging.php
│
├── 📂 database/
│   ├── 📂 migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   │
│   │   ├── 2026_02_24_171229_create_devices_table.php    # ✅ Devices Table
│   │   │   Schema:
│   │   │   - id (PK)
│   │   │   - nama_device
│   │   │   - lokasi (nullable)
│   │   │   - timestamps (created_at, updated_at)
│   │   │
│   │   └── 2026_02_24_171229_create_eksperimens_table.php # ✅ Eksperimen Table
│   │       Schema:
│   │       - id (PK)
│   │       - device_id (FK)
│   │       - protokol (ENUM: MQTT, HTTP)
│   │       - suhu (float)
│   │       - timestamp_esp (timestamp, nullable)
│   │       - timestamp_server (timestamp, useCurrent)
│   │       - latency_ms (float)
│   │       - daya_mw (float)
│   │       - timestamps (created_at, updated_at)
│   │
│   ├── 📂 seeders/
│   │   └── DatabaseSeeder.php                # ✅ Seed 3 Test Devices
│   │
│   └── 📂 factories/
│       └── UserFactory.php
│
├── 📂 public/
│   ├── index.php                             # ✅ Entry point
│   ├── 📂 build/
│   │   ├── manifest.json
│   │   └── 📂 assets/
│   │
│   └── robots.txt
│
├── 📂 resources/
│   ├── 📂 css/
│   │   └── app.css
│   │
│   ├── 📂 js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   │
│   └── 📂 views/
│       ├── dashboard.blade.php               # ✅ Dashboard UI (Production-Ready)
│       │   Features:
│       │   - Statistics cards (MQTT & HTTP)
│       │   - Bar charts (Latency & Power comparison)
│       │   - T-Test results display
│       │   - Responsive design
│       │   - Chart.js integration
│       │
│       └── welcome.blade.php
│
├── 📂 routes/
│   ├── api.php                               # ✅ API Routes
│   │   - POST /api/http-data (ApiController@storeHttp)
│   │
│   ├── web.php                               # ✅ Web Routes
│   │   - GET / (DashboardController@index)
│   │
│   └── console.php
│
├── 📂 storage/
│   ├── 📂 app/
│   ├── 📂 framework/
│   │   ├── 📂 cache/
│   │   ├── 📂 sessions/
│   │   ├── 📂 testing/
│   │   └── 📂 views/
│   │
│   └── 📂 logs/
│
├── 📂 tests/
│   ├── TestCase.php
│   ├── 📂 Feature/
│   │   └── ExampleTest.php
│   │
│   └── 📂 Unit/
│       └── ExampleTest.php
│
├── 📂 vendor/
│   ├── laravel/                              # ✅ Framework
│   ├── php-mqtt/client/                      # ✅ MQTT Client Library
│   └── ... (other packages)
│
├── 📄 vite.config.js                         # ✅ Vite configuration
├── 📄 phpunit.xml                            # ✅ PHPUnit config
│
├── 📄 README.md                              # Original README
├── 📄 QUICK_START.md                         # ✅ Quick Start Guide (NEW)
└── 📄 SYSTEM_DOCUMENTATION.md                # ✅ Full Documentation (NEW)
```

---

## 📊 Database Schema

### Table: devices
```sql
CREATE TABLE devices (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama_device varchar(255) NOT NULL,
  lokasi varchar(255),
  created_at timestamp NULL,
  updated_at timestamp NULL
);
```

### Table: eksperimens
```sql
CREATE TABLE eksperimens (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  device_id bigint unsigned NOT NULL,
  protokol enum('MQTT', 'HTTP') NOT NULL,
  suhu float(53) NOT NULL,
  timestamp_esp timestamp NULL,
  timestamp_server timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  latency_ms float(53) NOT NULL,
  daya_mw float(53) NOT NULL,
  created_at timestamp NULL,
  updated_at timestamp NULL,
  CONSTRAINT eksperimens_device_id_foreign 
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);
```

---

## 🔗 Relations & Keys

### One-to-Many: Device → Eksperimen
```
Device (1) ──────── (Many) Eksperimen
  ↓
  id              device_id
```

- Satu Device bisa punya banyak Eksperimen record
- Ketika Device dihapus, semua Eksperimen-nya ikut terhapus (CASCADE)

---

## 📋 API Contracts

### HTTP Endpoint
```
POST /api/http-data

Headers:
  Content-Type: application/json

Request Body:
{
  "device_id": integer (required, exists in devices),
  "suhu": float (required),
  "timestamp_esp": integer (required, unix timestamp),
  "daya": float (required)
}

Response (201):
{
  "success": true,
  "message": "Data HTTP berhasil disimpan",
  "data": {
    "id": integer,
    "device_id": integer,
    "protokol": "HTTP",
    "suhu": float,
    "timestamp_esp": datetime,
    "timestamp_server": datetime,
    "latency_ms": float,
    "daya_mw": float,
    "created_at": datetime,
    "updated_at": datetime
  }
}

Response (422 - Validation Error):
{
  "success": false,
  "message": "Validasi gagal",
  "errors": {
    "field_name": ["error message"]
  }
}
```

### MQTT Topic
```
Topic: iot/esp32/suhu

Payload (JSON):
{
  "device_id": integer,
  "suhu": float,
  "timestamp_esp": integer (unix timestamp),
  "daya": float
}
```

---

## 🔄 Data Flow

### HTTP Flow
```
ESP32 (DHT22)
    ↓
POST /api/http-data
    ↓
ApiController::storeHttp()
    ↓
Calculate timestamp_server & latency
    ↓
Eksperimen::create()
    ↓
MySQL Database (eksperimens table)
    ↓
DashboardController (query data)
    ↓
StatisticsService (calculate statistics & t-test)
    ↓
Dashboard View (display charts & results)
```

### MQTT Flow
```
ESP32 (DHT22)
    ↓
MQTT Publish: iot/esp32/suhu
    ↓
Mosquitto Broker
    ↓
Laravel MQTT Listener
    ↓
MqttListener::processMqttMessage()
    ↓
Calculate timestamp_server & latency
    ↓
Eksperimen::create()
    ↓
MySQL Database (eksperimens table)
    ↓
DashboardController (query data)
    ↓
StatisticsService (calculate statistics & t-test)
    ↓
Dashboard View (display charts & results)
```

---

## ⚙️ Configuration Files

### .env (Database)
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=esptest
DB_USERNAME=root
DB_PASSWORD=
```

### composer.json (Added)
```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "php-mqtt/client": "^2.3"  // ✅ Added
  }
}
```

### routes/api.php
```php
Post::post('/http-data', [ApiController::class, 'storeHttp']);
```

### routes/web.php
```php
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
```

---

## 🎯 Command Reference

```bash
# Setup
composer create-project laravel/laravel esptest
cd esptest
composer install
npm install && npm run build

# Database
php artisan migrate
php artisan db:seed

# Development
composer run dev                    # Run all (server, queue, vite)
php artisan serve                  # Just server
npm run dev                         # Just Vite
php artisan queue:listen           # Just queue

# MQTT
php artisan mqtt:listener          # Listen MQTT

# Maintenance
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan migrate:refresh        # Rollback & re-run
```

---

**✅ Sistem lengkap siap production!**
