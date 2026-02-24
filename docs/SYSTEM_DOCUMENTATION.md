# 📊 Sistem Analisis Komparatif MQTT vs HTTP - IoT Research

Penelitian skripsi: **"Analisis Komparatif Latensi, Konsumsi Daya, dan Keandalan Protokol MQTT dan HTTP pada Sistem Monitoring Suhu Berbasis ESP32"**

## 🎯 Fitur Utama

✅ **Database** - Migrations untuk devices & eksperimens dengan relasi One-to-Many
✅ **API HTTP** - Endpoint untuk menerima data dari ESP32 via HTTP
✅ **MQTT Listener** - Command untuk subscribe data real-time dari MQTT broker
✅ **Dashboard Modern** - Interactive dashboard dengan statistik & grafik menggunakan Chart.js
✅ **T-Test Otomatis** - Implementation Independent Sample T-Test untuk analisis komparatif
✅ **Responsive Design** - Dashboard yang mobile-friendly dengan Tailwind CSS

## 📋 Persyaratan Sistem

- Windows 10/11
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & npm
- XAMPP (recommended)
- Mosquitto MQTT Broker

## ⚙️ Setup Instalasi

### 1. Clone/Setup Project
```bash
cd C:\xampp\htdocs\esptest
composer install
npm install
npm run build
```

### 2. Setup Database
```bash
# Buat database
mysql -u root -e "CREATE DATABASE esptest;"

# Jalankan migrations
php artisan migrate

# Seed data devices
php artisan db:seed
```

### 3. Generate App Key
```bash
php artisan key:generate
```

### 4. Setup Environment (.env)
File `.env` sudah dikonfigurasi dengan benar:
```
DB_DATABASE=esptest
DB_USERNAME=root
DB_PASSWORD=
DB_HOST=127.0.0.1
```

### 5. Start Development Server
```bash
# Option 1: Jalankan semua secara bersamaan
composer run dev

# Option 2: Jalankan secara terpisah di terminal berbeda
php artisan serve                           # Terminal 1 - Server (http://localhost:8000)
php artisan queue:listen --tries=1          # Terminal 2 - Queue
npm run dev                                 # Terminal 3 - Vite

# Di terminal lain:
php artisan mqtt:listener                   # MQTT Listener
```

## 📡 API Endpoints

### HTTP Data Endpoint
**POST** `/api/http-data`

Request body JSON:
```json
{
  "device_id": 1,
  "suhu": 25.5,
  "timestamp_esp": 1708884000,
  "daya": 100
}
```

Response (201 Created):
```json
{
  "success": true,
  "message": "Data HTTP berhasil disimpan",
  "data": {
    "id": 1,
    "device_id": 1,
    "protokol": "HTTP",
    "suhu": 25.5,
    "timestamp_esp": "2026-02-25T...",
    "timestamp_server": "2026-02-25T...",
    "latency_ms": 123.45,
    "daya_mw": 100,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### Dashboard
**GET** `/` - Akses dashboard di `http://localhost/esptest/public`

## 🔌 MQTT Configuration

### Setup Mosquitto
1. Install Mosquitto dari [mosquitto.org](https://mosquitto.org/)
2. Start service Mosquitto
3. (Optional) Configure mosquitto.conf untuk username/password

### Jalankan MQTT Listener
```bash
# Default broker: 127.0.0.1:1883
php artisan mqtt:listener

# Custom broker:
php artisan mqtt:listener --broker=192.168.1.100 --port=1883
```

### MQTT Topic & Payload
**Topic:** `iot/esp32/suhu`

**Payload JSON:**
```json
{
  "device_id": 1,
  "suhu": 25.5,
  "timestamp_esp": 1708884000,
  "daya": 100
}
```

### Contoh Publish dengan MQTT CLI
```bash
mosquitto_pub -h 127.0.0.1 -p 1883 -t "iot/esp32/suhu" -m '{"device_id":1,"suhu":25.5,"timestamp_esp":1708884000,"daya":100}'
```

## 📊 Dashboard Features

### Statistik Cards
- **MQTT Stats**: Total data, Avg latency, Avg power, Reliability
- **HTTP Stats**: Total data, Avg latency, Avg power, Reliability

### Grafik Perbandingan
- Bar chart untuk perbandingan latency (MQTT vs HTTP)
- Bar chart untuk perbandingan konsumsi daya (MQTT vs HTTP)

### Independent Sample T-Test
Menampilkan:
- Statistik dasar (N, Mean, Std Dev, Variance) untuk MQTT dan HTTP
- t-value, degrees of freedom (df), critical value, p-value
- Interpretasi signifikansi (α=0.05, two-tailed)

**Null Hypothesis (H0):** μ₁ = μ₂ (Tidak ada perbedaan rata-rata)
**Alternative Hypothesis (H1):** μ₁ ≠ μ₂ (Ada perbedaan rata-rata)

**Decision Rule:**
- Jika |t| > 1.96 atau p < 0.05 → Tolak H0 (Signifikan)
- Jika |t| ≤ 1.96 atau p ≥ 0.05 → Gagal tolak H0 (Tidak signifikan)

## 📁 Struktur File Penting

```
esptest/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── MqttListener.php          ✓ MQTT listener command
│   ├── Http/
│   │   └── Controllers/
│   │       ├── ApiController.php         ✓ HTTP API untuk menerima data
│   │       └── DashboardController.php   ✓ Dashboard controller
│   ├── Models/
│   │   ├── Device.php                    ✓ Model dengan relasi
│   │   └── Eksperimen.php                ✓ Model dengan relasi
│   └── Services/
│       └── StatisticsService.php         ✓ Service untuk statistik & t-test
├── database/
│   ├── migrations/
│   │   ├── ..._create_devices_table.php
│   │   └── ..._create_eksperimens_table.php
│   └── seeders/
│       └── DatabaseSeeder.php            ✓ Seeder untuk test devices
├── resources/
│   └── views/
│       └── dashboard.blade.php           ✓ Dashboard UI dengan Chart.js
├── routes/
│   ├── api.php                           ✓ API routes
│   └── web.php                           ✓ Web routes
├── .env                                   ✓ Configuration file
└── composer.json                          ✓ Updated dengan php-mqtt/client
```

## 🧪 Testing

### 1. Test HTTP Endpoint
```bash
curl -X POST http://localhost:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 1,
    "suhu": 25.5,
    "timestamp_esp": 1708884000,
    "daya": 100
  }'
```

### 2. Test MQTT
```bash
# Terminal 1: Jalankan listener
php artisan mqtt:listener

# Terminal 2: Publish data
mosquitto_pub -h 127.0.0.1 -t "iot/esp32/suhu" -m '{"device_id":1,"suhu":28.3,"timestamp_esp":1708884200,"daya":120}'
```

### 3. Check Dashboard
Buka browser: `http://localhost/esptest/public`

## 📈 ESP32 Code Example

### HTTP Client (Arduino IDE)
```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";
const char* serverUrl = "http://192.168.1.X/esptest/public/api/http-data";

#define DHT_PIN 5
#define DHT_TYPE DHT22
DHT dht(DHT_PIN, DHT_TYPE);

void setup() {
  Serial.begin(115200);
  dht.begin();
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) delay(500);
}

void loop() {
  float temperatura = dht.readTemperature();
  uint32_t timestamp = millis() / 1000;
  
  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"device_id\":1,\"suhu\":" + String(temperatura) + 
                   ",\"timestamp_esp\":" + String(timestamp) + ",\"daya\":100}";
  
  int httpCode = http.POST(payload);
  Serial.println(httpCode);
  http.end();
  
  delay(5000);
}
```

### MQTT Client (Arduino IDE)
```cpp
#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>

const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";
const char* mqtt_server = "192.168.1.X";

#define DHT_PIN 5
#define DHT_TYPE DHT22
DHT dht(DHT_PIN, DHT_TYPE);

WiFiClient espClient;
PubSubClient client(espClient);

void setup() {
  Serial.begin(115200);
  dht.begin();
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) delay(500);
  
  client.setServer(mqtt_server, 1883);
}

void loop() {
  if (!client.connected()) {
    client.connect("ESP32-Device-1");
  }
  
  float temperatura = dht.readTemperature();
  uint32_t timestamp = millis() / 1000;
  
  String payload = "{\"device_id\":1,\"suhu\":" + String(temperatura) + 
                   ",\"timestamp_esp\":" + String(timestamp) + ",\"daya\":100}";
  
  client.publish("iot/esp32/suhu", payload.c_str());
  client.loop();
  
  delay(5000);
}
```

## 🔍 Development Commands

```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Database
php artisan migrate:refresh          # Rollback dan re-run migrations
php artisan db:seed                  # Jalankan seeder
php artisan tinker                   # Interactive shell

# Testing
php artisan test                     # Run PHPUnit tests

# Queue (jika diperlukan)
php artisan queue:work

# Optimize
php artisan optimize
php artisan optimize:clear
```

## 🐛 Troubleshooting

### Database Connection Error
```
SQLSTATE[HY000]: General error: 2054 The server requested authentication 
method unknown to the client
```
**Solution:** Update MySQL user password atau gunakan latest MySQL driver.

### MQTT Connection Timeout
- Pastikan Mosquitto service sudah running
- Check firewall settings port 1883
- Verify broker IP address dan port

### Laravel Routes Not Found
```bash
php artisan route:clear
php artisan route:cache
```

### View Not Found Error
```bash
php artisan view:clear
```

## 📚 Referensi Statistik

### Independent Sample T-Test
Formula:
$$t = \frac{\bar{x_1} - \bar{x_2}}{\sqrt{\frac{s_1^2}{n_1} + \frac{s_2^2}{n_2}}}$$

Dimana:
- $\bar{x_1}, \bar{x_2}$ = Mean sampel 1 dan 2
- $s_1^2, s_2^2$ = Variance sampel 1 dan 2
- $n_1, n_2$ = Ukuran sampel 1 dan 2

### Degrees of Freedom
$$df = n_1 + n_2 - 2$$

### Critical Value (α=0.05, two-tailed)
$$t_{crit} = \pm 1.96$$

## 📝 License

MIT License - Untuk keperluan akademik

## 👨‍💼 Author

Peneliti: [Your Name]
Universitas: [Your University]
Tahun: 2026

---

**Last Updated:** 25 Feb 2026
**Status:** Production Ready ✓
