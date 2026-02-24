# 🌐 IoT Research System - MQTT vs HTTP Comparative Analysis

![Status](https://img.shields.io/badge/Status-Production%20Ready-brightgreen)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)
![ESP32](https://img.shields.io/badge/ESP32-Arduino-05A0D1?logo=arduino)

Sistem penelitian lengkap untuk membandingkan protokol MQTT dan HTTP pada ESP32 dengan real-time analytics, statistical testing, dan interactive dashboard.

---

## 📋 Daftar Isi

1. [Overview](#-overview)
2. [Fitur Utama](#-fitur-utama)
3. [Requirements](#-requirements)
4. [Instalasi & Setup](#-instalasi--setup)
5. [Menjalankan Project](#-menjalankan-project)
6. [API Documentation](#-api-documentation)
7. [Dashboard Features](#-dashboard-features)
8. [ESP32 Firmware](#-esp32-firmware)
9. [Database Schema](#-database-schema)
10. [Troubleshooting](#-troubleshooting)
11. [File Structure](#-file-structure)
12. [Dokumentasi Lengkap](#-dokumentasi-lengkap)

---

## 🎯 Overview

**Tujuan:** Melakukan penelitian empiris untuk mengevaluasi performa protokol MQTT vs HTTP pada IoT devices (ESP32) dalam hal:
- **Latency**: Waktu transmisi data dari device ke server
- **Power Consumption**: Konsumsi daya setiap protokol
- **Reliability**: Tingkat keberhasilan pengiriman data
- **Statistical Significance**: Analisis t-test untuk test hipotesis

**Teknologi Stack:**
- **Backend**: Laravel 12 (PHP 8.2)
- **Frontend**: HTML5 + CSS3 + Vanilla JavaScript
- **Database**: MySQL 8.0
- **Hardware**: ESP32 DevKit V1 + DHT22 Sensor
- **Real-time**: MQTT Broker (Mosquitto) + HTTP REST API

---

## ✨ Fitur Utama

### 📊 Real-Time Dashboard
```
✅ 8 Animated Stat Cards (MQTT & HTTP metrics)
✅ 2 Comparative Bar Charts (Latency & Power)
✅ T-Test Statistical Analysis (Latency & Power)
✅ 6 Responsive Breakpoints (1024px - 360px)
✅ Auto-refresh setiap 5 detik (tanpa reload)
✅ Smooth animations & transitions
```

### 🔬 Statistical Analysis
```
✅ Independent Sample T-Test
✅ Mean & Standard Deviation calculation
✅ Variance computation
✅ P-value & Significance testing
✅ Critical value comparison
✅ Degrees of freedom calculation
```

### 🔌 Hardware Integration
```
✅ DHT22 Sensor (Temperature & Humidity)
✅ WiFi Connectivity (ESP32)
✅ HTTP POST requests
✅ MQTT Publish/Subscribe
✅ NTP Time Synchronization
✅ Power Consumption Estimation
```

### 🚀 API Endpoints
```
✅ POST /api/http-data (Store HTTP data)
✅ GET / (Dashboard view)
✅ Console command: mqtt:listener (MQTT receiver)
```

---

## 📦 Requirements

### Hardware
- [ ] ESP32 DevKit V1
- [ ] DHT22 Sensor (Temperature & Humidity)
- [ ] Micro USB Cable
- [ ] Jumper Wires
- [ ] Resistor 4.7kΩ (optional)

### Software
- [ ] XAMPP (PHP 8.2, MySQL 8.0)
- [ ] Composer (PHP dependency manager)
- [ ] Node.js 18+ (untuk build tools)
- [ ] Git (optional)
- [ ] Visual Studio Code + PlatformIO extension
- [ ] Python 3.6+ (untuk PlatformIO)

### Network
- [ ] WiFi 2.4GHz access point
- [ ] Mosquitto MQTT Broker (optional but recommended)
- [ ] Static IP atau hostname untuk server

---

## ⚙️ Instalasi & Setup

### 1️⃣ Clone / Setup Project

```bash
# Navigasi ke folder project
cd c:\xampp\htdocs\esptest

# Jika menggunakan Git
git clone <repository> esptest
cd esptest

# Jika sudah ada folder, pastikan di dalam folder tersebut
```

### 2️⃣ Install Backend Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node dependencies (optional, untuk build assets)
npm install

# Compile assets
npm run build

# Atau development mode dengan watch
npm run dev
```

### 3️⃣ Database Configuration

Edit file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=esptest
DB_USERNAME=root
DB_PASSWORD=
```

### 4️⃣ Run Migrations & Seeders

```bash
# Generate application key
php artisan key:generate

# Run migrations (create tables)
php artisan migrate

# Seed initial device data
php artisan db:seed
```

### 5️⃣ Verify Database

```bash
# Check database setup
mysql -u root -e "USE esptest; SHOW TABLES;"

# Expected output:
# Tables_in_esptest
# devices
# eksperimens
# cache
# jobs
# ... (other Laravel tables)
```

### 6️⃣ Setup ESP32 Firmware

```bash
# Navigate to firmware folder
cd ESP32_Firmware

# Edit src/main.cpp dengan konfigurasi Anda:
# - WIFI_SSID
# - WIFI_PASSWORD  
# - HTTP_SERVER (server IP)
# - MQTT_SERVER (MQTT broker IP)
# - DEVICE_ID (harus match database)

# Build firmware
pio run

# Upload ke ESP32
pio run -t upload

# Monitor serial output
pio device monitor
```

---

## 🚀 Menjalankan Project

### Method 1: Menggunakan Composer Script (Recommended)

```bash
# Development mode dengan auto-reload
composer run dev

# Atau untuk production
composer run prod
```

### Method 2: Manual Commands

```bash
# Terminal 1: Laravel Development Server
php artisan serve
# Output: Laravel development server started: http://127.0.0.1:8000

# Terminal 2: (Optional) MQTT Listener
php artisan mqtt:listener
# Listening for MQTT messages on iot/esp32/suhu...

# Terminal 3: (Optional) Node Watch (jika perlu update assets)
npm run dev
```

### Verify Server is Running

```bash
# Test dengan curl
curl http://localhost:8000

# Expected: HTML dari dashboard (atau jika ada error, akan terlihat)
```

---

## 📡 API Documentation

### HTTP Data Submission Endpoint

**URL:** `POST /api/http-data`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "device_id": 1,
  "suhu": 25.5,
  "timestamp_esp": 1708884000,
  "daya": 100.5
}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| device_id | integer | ✅ | Device ID (must exist in devices table) |
| suhu | float | ✅ | Temperature in Celsius |
| timestamp_esp | integer | ✅ | Unix timestamp from ESP32 |
| daya | float | ✅ | Power consumption in milliwatts |

**Success Response (201 Created):**
```json
{
  "success": true,
  "message": "Data HTTP berhasil disimpan",
  "data": {
    "id": 123,
    "device_id": 1,
    "protokol": "HTTP",
    "suhu": 25.5,
    "timestamp_esp": 1708884000,
    "timestamp_server": "2026-02-25T10:30:45Z",
    "latency_ms": 45.23,
    "daya_mw": 100.5
  }
}
```

**Error Response (422 Validation Failed):**
```json
{
  "success": false,
  "message": "Validasi gagal",
  "errors": {
    "device_id": ["The device_id field is required."],
    "suhu": ["The suhu must be a number."]
  }
}
```

### Test dengan PowerShell

```powershell
$body = @{
  device_id = 1
  suhu = 25.5
  timestamp_esp = [int](Get-Date -UFormat %s)
  daya = 100
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:8000/api/http-data" `
  -Method POST `
  -Headers @{"Content-Type"="application/json"} `
  -Body $body
```

### Test dengan Bash/cURL

```bash
curl -X POST http://localhost:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{"device_id":1,"suhu":25.5,"timestamp_esp":'$(date +%s)',"daya":100}'
```

---

## 📊 Dashboard Features

### Akses Dashboard
```
URL: http://localhost:8000
```

### Sections:

#### 1. **Header**
```
Title: IoT Research System
Subtitle: Analisis Komparatif Protokol MQTT vs HTTP
Badge: MQTT Ready | HTTP Ready | T-Test Active
```

#### 2. **Real-Time Metrics** (Animated Statistics)
```
MQTT Metrics:
├─ Total Data: [count] data points
├─ Avg Latency: [value] milliseconds
├─ Avg Power: [value] milliwatts
└─ Reliability: [percentage]%

HTTP Metrics:
├─ Total Data: [count] data points
├─ Avg Latency: [value] milliseconds
├─ Avg Power: [value] milliwatts
└─ Reliability: [percentage]%
```

#### 3. **Comparative Analysis** (Charts)
```
Latency Comparison:
├─ X-axis: Device IDs
├─ Y-axis: Latency (ms)
├─ MQTT bars (blue)
└─ HTTP bars (green)

Power Consumption Comparison:
├─ X-axis: Device IDs
├─ Y-axis: Power (mW)
├─ MQTT bars (blue)
└─ HTTP bars (green)
```

#### 4. **T-Test Results** (Statistical Analysis)
```
Latency Analysis:
├─ MQTT Data:
│  ├─ Sample Size (N): [value]
│  ├─ Mean (μ): [value]
│  ├─ Std Deviation (σ): [value]
│  └─ Variance (σ²): [value]
├─ HTTP Data:
│  ├─ Sample Size (N): [value]
│  ├─ Mean (μ): [value]
│  ├─ Std Deviation (σ): [value]
│  └─ Variance (σ²): [value]
└─ Test Results:
   ├─ t-value: [value]
   ├─ Degrees of Freedom: [value]
   ├─ Critical Value: ± [value]
   ├─ p-value: [value]
   └─ Significance: ✓ Signifikan / ✗ Tidak Signifikan

Power Analysis: [Sama seperti Latency Analysis]
```

### Features:

✅ **Responsive Design**: Optimized untuk desktop, tablet, mobile
✅ **Animated Counters**: Nilai animasi dari 0 ke nilai sebenarnya (1000ms)
✅ **Chart Animations**: Bars animasi tumbuh dari bawah (1200ms)
✅ **Auto-Refresh**: Update data setiap 5 detik tanpa page reload
✅ **Glassmorphism UI**: Modern design dengan blur effect
✅ **Touch Optimized**: Mobile-friendly dengan active states

---

## 🔌 ESP32 Firmware

### Hardware Connection

```
ESP32 PIN Layout:

3.3V  ────→ DHT22 Pin 1 (Vcc)
GPIO 4 ────→ DHT22 Pin 2 (Data) + 4.7k pull-up to 3.3V
GND   ────→ DHT22 Pin 4 (GND)
```

### Firmware Features

```
✅ WiFi Connection & Auto-Reconnect
✅ DHT22 Sensor Reading (every 5 seconds)
✅ HTTP POST to /api/http-data (every 10 seconds)
✅ MQTT Publish to iot/esp32/suhu (every 10 seconds)
✅ JSON Serialization (ArduinoJson library)
✅ NTP Time Synchronization
✅ Power Consumption Estimation
✅ Serial Logging & Debugging
✅ Statistics Tracking (success/failure)
```

### Configuration (src/main.cpp)

```cpp
// WiFi Settings
const char* WIFI_SSID = "YOUR_SSID";
const char* WIFI_PASSWORD = "YOUR_PASSWORD";

// Server Settings
const char* HTTP_SERVER = "http://192.168.1.100:8000";
const char* MQTT_SERVER = "192.168.1.100";
const int MQTT_PORT = 1883;

// Device Settings
const int DEVICE_ID = 1;  // Match database devices table
const int DHT_PIN = 4;    // GPIO 4

// Timing (milliseconds)
const unsigned long INTERVAL_SENSOR = 5000;   // Read sensor
const unsigned long INTERVAL_HTTP = 10000;    // Send HTTP
const unsigned long INTERVAL_MQTT = 10000;    // Publish MQTT
```

### Serial Monitor Output

```
==========================================
  ESP32 IoT Data Logger - MQTT vs HTTP
==========================================

[INIT] Initializing DHT22 sensor...
[WiFi] Connecting to: MyWiFi
............
[WiFi] ✓ Connected!
       IP: 192.168.1.105
       RSSI: -52 dBm
[MQTT] ✓ Connected!
       Client ID: ESP32-1
[TIME] ✓ Time synchronized: Thu Feb 25 10:30:45 2026

[SENSOR] ✓ T: 25.50°C | H: 55.20% | P: 89.34 mW
[HTTP] ✓ Success (201 Created)
[MQTT] ✓ Published successfully

==========================================
         SYSTEM STATUS REPORT
==========================================
Sensor Reads: 250
HTTP Success: 125 | Failures: 0
MQTT Success: 125 | Failures: 0
Uptime: 1250 seconds
Free Memory: 178456 bytes
==========================================
```

---

## 🗄️ Database Schema

### Devices Table

```sql
CREATE TABLE devices (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  device_id INT NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sample Data:
INSERT INTO devices (device_id, name, description) VALUES
(1, 'ESP32-MQTT', 'Main MQTT Test Device'),
(2, 'ESP32-HTTP', 'HTTP Test Device');
```

### Eksperimen Table

```sql
CREATE TABLE eksperimens (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  protokol ENUM('MQTT', 'HTTP') NOT NULL,
  suhu FLOAT NOT NULL,
  timestamp_esp TIMESTAMP DEFAULT NULL,
  timestamp_server TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  latency_ms FLOAT NOT NULL,
  daya_mw FLOAT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_protokol (protokol),
  INDEX idx_timestamp (timestamp_server),
  INDEX idx_device (device_id)
);
```

### Query Examples

```sql
-- Count by Protocol
SELECT 
  protokol, 
  COUNT(*) as count,
  AVG(latency_ms) as avg_latency,
  AVG(daya_mw) as avg_power
FROM eksperimens
GROUP BY protokol;

-- Latency Statistics
SELECT 
  protokol,
  MIN(latency_ms) as min_latency,
  MAX(latency_ms) as max_latency,
  AVG(latency_ms) as mean_latency,
  STDDEV(latency_ms) as std_dev,
  VAR_POP(latency_ms) as variance
FROM eksperimens
GROUP BY protokol;

-- Reliability (success rate)
SELECT 
  protokol,
  COUNT(*) as total,
  COUNT(CASE WHEN latency_ms > 0 THEN 1 END) as success,
  ROUND(100 * COUNT(CASE WHEN latency_ms > 0 THEN 1 END) / COUNT(*), 2) as reliability_percent
FROM eksperimens
GROUP BY protokol;
```

---

## 🐛 Troubleshooting

### ❌ Dashboard tidak load

**Problem:** Browser menampilkan error atau tidak bisa akses http://localhost:8000

**Solution:**
```bash
# 1. Pastikan Laravel server running
php artisan serve

# 2. Check if port 8000 is occupied
netstat -ano | findstr :8000

# 3. Kill process jika perlu
taskkill /PID [PID] /F

# 4. Restart Laravel
php artisan serve --port=8001
```

---

### ❌ Database error

**Problem:** "SQLSTATE[HY000] [1045] Access denied for user"

**Solution:**
```bash
# 1. Verify MySQL running
mysql -u root -p
# Enter password (default empty) atau sesuai setting

# 2. Check .env configuration
cat .env | grep DB_

# 3. Jika perlu reset, jalankan migrations
php artisan migrate:fresh --seed
```

---

### ❌ API returns 422 Validation Error

**Problem:** HTTP POST /api/http-data gagal dengan validation error

**Solution:**
```bash
# 1. Verify device exists in database
mysql -u root -e "SELECT * FROM esptest.devices;"

# 2. Ensure JSON format valid
# Should have: device_id, suhu, timestamp_esp, daya

# 3. Test dengan curl yang benar
curl -X POST http://localhost:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{"device_id":1,"suhu":25.5,"timestamp_esp":1708884000,"daya":100}'
```

---

### ❌ DHT22 sensor tidak terbaca

**Problem:** ESP32 serial monitor menampilkan "Failed to read from DHT22"

**Solution:**
```cpp
// 1. Check GPIO pin (default: GPIO 4)
const int DHT_PIN = 4;

// 2. Verify 4.7k ohm pull-up resistor connected
// 3. Wait 2 seconds untuk sensor stabilize (sudah di setup())
// 4. Check power supply (3.3V)
// 5. Try different GPIO pin jika perlu

// Pins yang aman: GPIO 0, 2, 4, 5, 12-19, 21-23, 25-27, 32-39
```

---

### ❌ WiFi connection failed

**Problem:** Serial output menampilkan WiFi connection timeout

**Solution:**
```cpp
// 1. Verify SSID & password correct
const char* WIFI_SSID = "YOUR_SSID";
const char* WIFI_PASSWORD = "YOUR_PASSWORD";

// 2. Ensure WiFi is 2.4GHz (ESP32 tidak support 5GHz)
// 3. Check signal strength (RSSI > -70 dBm optimal)
// 4. Check router allows ESP32 to connect
// 5. Restart WiFi router jika perlu
```

---

### ❌ MQTT tidak connect

**Problem:** Serial output "MQTT connection failed with code: X"

**Solution:**
```bash
# 1. Verify Mosquitto running
mosquitto -v

# 2. Check IP address correct
ping 192.168.1.100  # atau sesuai IP MQTT broker

# 3. Test MQTT connectivity
mosquitto_sub -h 192.168.1.100 -t "#"

# 4. Check firewall (port 1883 harus open)
# 5. Verify MQTT credentials (default: no auth)
```

---

### ❌ Data tidak muncul di dashboard

**Problem:** Dashboard kosong atau tidak menampilkan data

**Solution:**
```bash
# 1. Check database has records
mysql -u root -e "SELECT COUNT(*) FROM esptest.eksperimens;"

# 2. Verify data format
SELECT * FROM esptest.eksperimens LIMIT 5\G

# 3. Check Laravel logs
tail -f storage/logs/laravel.log

# 4. Refresh dashboard (Ctrl+Shift+R untuk hard refresh)
# 5. Check browser console untuk JavaScript errors (F12)
```

---

## 📂 File Structure

```
esptest/
├── README.md                           ← Project overview (THIS FILE)
│
├── docs/                               ← Additional documentation
│   ├── QUICK_START.md                 ← 5 minute quick start
│   ├── INTEGRATION_GUIDE.md            ← System architecture & data flow
│   ├── SYSTEM_DOCUMENTATION.md         ← Detailed system documentation
│   ├── SYSTEM_COMPLETE.md              ← Complete feature summary
│   └── FOLDER_STRUCTURE.md             ← Detailed folder structure
│
├── ESP32_Firmware/                    ← ESP32 Firmware Project
│   ├── platformio.ini                 ← PlatformIO configuration
│   ├── src/
│   │   └── main.cpp                   ← Main firmware code (500+ lines)
│   ├── README.md                      ← Firmware setup guide
│   └── CONFIGURATION.cpp              ← Configuration reference
│
├── app/                               ← Laravel Application Code
│   ├── Http/Controllers/
│   │   ├── Controller.php             ← Base controller
│   │   ├── ApiController.php          ← HTTP API endpoint handler
│   │   └── DashboardController.php    ← Dashboard data aggregation
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Device.php                 ← Device model dengan relationships
│   │   └── Eksperimen.php             ← Data record model
│   │
│   ├── Console/Commands/
│   │   └── MqttListener.php           ← MQTT message listener
│   │
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   │
│   └── Services/
│       └── StatisticsService.php      ← T-Test & Statistics calculation
│
├── resources/views/
│   ├── dashboard.blade.php            ← Main dashboard (1511 lines HTML/CSS/JS)
│   ├── welcome.blade.php
│   └── layouts/
│       └── app.blade.php              ← Layout template
│
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_02_24_171229_create_devices_table.php
│   │   └── 2026_02_24_171229_create_eksperimens_table.php
│   │
│   ├── factories/
│   │   └── UserFactory.php
│   │
│   └── seeders/
│       └── DatabaseSeeder.php         ← Initial data seeding
│
├── routes/
│   ├── api.php                        ← API routes (POST /api/http-data)
│   ├── web.php                        ← Web routes (GET /)
│   └── console.php                    ← Console commands
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── services.php
│   └── session.php
│
├── storage/                           ← Runtime storage
│   ├── logs/
│   │   └── laravel.log
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   ├── testing/
│   │   └── views/
│   └── app/
│
├── tests/                             ← Test suite
│   ├── TestCase.php
│   ├── Feature/
│   │   └── ExampleTest.php
│   └── Unit/
│       └── ExampleTest.php
│
├── public/                            ← Web root
│   ├── index.php                      ← Entry point
│   ├── robots.txt
│   ├── hot
│   └── build/
│       └── manifest.json
│
├── bootstrap/
│   ├── app.php                        ← Application bootstrap
│   ├── cache/
│   │   ├── packages.php
│   │   └── services.php
│   └── providers.php
│
├── artisan                            ← Artisan CLI
├── composer.json                      ← PHP dependencies
├── composer.lock
├── package.json                       ← Node dependencies
├── package-lock.json
├── phpunit.xml                        ← Testing configuration
├── vite.config.js                     ← Build configuration
├── .env                               ← Environment config (LOCAL ONLY)
├── .env.example                       ← Environment template
├── .gitignore
└── vendor/                            ← Installed PHP packages
```

---

## 📚 Dokumentasi Lengkap

Dokumentasi tambahan tersedia di folder `docs/`:

### 1. **docs/QUICK_START.md**
```
5 menit quick setup guide
- Database setup
- Install dependencies
- Run server
- Test API endpoints
- View dashboard
```

### 2. **docs/INTEGRATION_GUIDE.md**
```
Comprehensive system integration guide
- System architecture
- Complete data flow (HTTP & MQTT)
- Component overview
- Setup instructions (step-by-step)
- Verification checklist
- Deployment considerations
```

### 3. **docs/SYSTEM_DOCUMENTATION.md**
```
Detailed technical documentation
- API specifications
- Database schema & relationships
- Service layer & statistics
- Error handling & logging
- Performance metrics
```

### 4. **docs/SYSTEM_COMPLETE.md**
```
Complete feature summary & status
- Implementation checklist
- Testing results
- Performance metrics
- Learning outcomes
- Next steps for research
```

### 5. **docs/FOLDER_STRUCTURE.md** & **ESP32_Firmware/README.md**
```
Detailed folder & file structure
- ESP32 hardware setup
- Firmware installation
- Serial monitor output
- Troubleshooting hardware issues
```

---

## 🎯 Quick Reference

### Start Development

```bash
# 1. Setup once
composer install
php artisan migrate --seed

# 2. Start servers
php artisan serve

# 3. Optional: MQTT listener
php artisan mqtt:listener

# 4. Open dashboard
# Browser: http://localhost:8000
```

### Test HTTP API

```bash
# PowerShell
$body = @{device_id=1;suhu=25.5;timestamp_esp=1708884000;daya=100} | ConvertTo-Json
Invoke-WebRequest -Uri "http://localhost:8000/api/http-data" -Method POST -Headers @{"Content-Type"="application/json"} -Body $body

# Bash/cURL
curl -X POST http://localhost:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{"device_id":1,"suhu":25.5,"timestamp_esp":1708884000,"daya":100}'
```

### Database Queries

```bash
# View devices
mysql -u root esptest -e "SELECT * FROM devices;"

# View data records
mysql -u root esptest -e "SELECT * FROM eksperimens LIMIT 10;"

# Count by protocol
mysql -u root esptest -e "SELECT protokol, COUNT(*) FROM eksperimens GROUP BY protokol;"
```

### Upload ESP32 Firmware

```bash
cd ESP32_Firmware
# Edit src/main.cpp dengan config Anda
pio run -t upload -t monitor
```

---

## 🚨 Important Notes

⚠️ **Security (Development Only)**
- `.env` file contains local credentials only
- Change database password for production
- Use HTTPS/SSL for production deployment
- Implement API authentication
- Enable MQTT SSL/TLS

⚠️ **System Requirements**
- Minimum 2GB RAM
- 500MB free disk space
- PHP 8.2+
- MySQL 8.0
- WiFi 2.4GHz (NOT 5GHz)

⚠️ **WiFi Configuration**
- Device dan server HARUS di network yang sama
- Atau gunakan public domain dengan proper routing
- Mosquitto MQTT default port: 1883
- Laravel default port: 8000

---

## 📈 Project Status

| Component | Status | Notes |
|-----------|--------|-------|
| Backend (Laravel) | ✅ Production Ready | All endpoints working |
| Frontend (Dashboard) | ✅ Fully Functional | Responsive & animated |
| Database (MySQL) | ✅ Configured | Schema & migrations done |
| API (HTTP) | ✅ Tested | Returns 201 on success |
| MQTT Integration | ✅ Optional | Working with Mosquitto |
| ESP32 Firmware | ✅ Complete | 500+ lines, all features |
| Documentation | ✅ Comprehensive | 5 additional guides |
| Statistics (T-Test) | ✅ Implemented | Automatic calculation |

---

## 📞 Support

Jika mengalami masalah:

1. **Check docs/** folder untuk dokumentasi lengkap
2. **Review TROUBLESHOOTING section** di README ini
3. **Check logs**: `storage/logs/laravel.log`
4. **Monitor serial**: `pio device monitor` untuk ESP32
5. **Test API manually**: Gunakan curl atau Postman

---

## 📝 License

Free untuk research & educational purposes.

---

**Built with ❤️ for IoT Research**

Last Updated: February 25, 2026
