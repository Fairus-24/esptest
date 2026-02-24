# ESP32 IoT Research System - Firmware & Documentation

## 🎯 Sistem Lengkap

Ini adalah implementasi lengkap ESP32 untuk sistem penelitian komparatif MQTT vs HTTP dengan sinkronisasi penuh ke Laravel backend.

---

## 📋 Persyaratan Hardware

### Komponen Yang Dibutuhkan:
- **ESP32 DevKit V1** (atau kompatibel seperti Wroom-32, Wroom-32U)
- **DHT22 Sensor** (Suhu & Kelembaban)
- **Micro USB Cable** (untuk upload dan power)
- **Jumper Wires** (DuPont wires)
- **Resistor 4.7kΩ** (pull-up untuk DHT22 - opsional, DHT22 sudah ada built-in)

### Pin Configuration:
```
ESP32 PIN     |  DHT22 Pin
--------------+-----------
GPIO 4        |  Data (S)
3.3V          |  Vcc (+)
GND           |  GND (-)
```

---

## 🔧 Instalasi & Setup

### 1. Install PlatformIO
```bash
# Visual Studio Code Extension
- Buka VS Code
- Extensions marketplace
- Cari "PlatformIO IDE"
- Install

# Alternative: Command Line
sudo apt-get install platformio  # Linux/Mac
```

### 2. Clone / Copy Project
```bash
# Copy folder ESP32_Firmware ke project directory Anda
cp -r ESP32_Firmware ~/Documents/Projects/
cd ~/Documents/Projects/ESP32_Firmware
```

### 3. Konfigurasi WiFi & Server
Edit `src/main.cpp` baris 9-20:

```cpp
// WiFi Settings
const char* WIFI_SSID = "YOUR_SSID";              // ← Ganti dengan SSID WiFi Anda
const char* WIFI_PASSWORD = "YOUR_PASSWORD";      // ← Ganti dengan password WiFi

// Server Settings
const char* HTTP_SERVER = "http://192.168.1.100:8000";  // ← Ganti IP server
const char* MQTT_SERVER = "192.168.1.100";              // ← Ganti IP MQTT broker

// Device Settings
const int DEVICE_ID = 1;  // ← Sesuaikan dengan database devices tabel
```

### 4. Build & Upload
```bash
# Build project
pio run

# Upload ke ESP32
pio run -t upload

# Monitor serial output
pio device monitor

# Atau lakukan semuanya sekaligus:
pio run -t upload -t monitor
```

---

## 📊 Data Format & Schema

### HTTP Request Format
```json
{
  "device_id": 1,
  "suhu": 25.5,
  "timestamp_esp": 1708884000,
  "daya": 100
}
```

### Database Mapping
Field | Type | Description
------|------|-------------
device_id | integer | Device yang mengirim data (FK ke devices table)
suhu | float | Suhu dalam Celsius dari DHT22
timestamp_esp | integer | Unix timestamp saat ESP membaca sensor
daya | float | Daya konsumsi dalam milliwatts
protokol | enum | 'HTTP' atau 'MQTT' (otomatis di server)
latency_ms | float | Dihitung server otomatis
timestamp_server | timestamp | Dihitung server otomatis

### API Endpoint
```
POST /api/http-data
Host: http://192.168.1.100:8000
Content-Type: application/json

Response 201 Created:
{
  "success": true,
  "message": "Data HTTP berhasil disimpan",
  "data": {
    "id": 123,
    "device_id": 1,
    "protokol": "HTTP",
    "suhu": 25.5,
    "latency_ms": 45.23,
    "daya_mw": 100,
    ...
  }
}
```

---

## 🚀 Fitur Firmware

### ✅ Sudah Implementasi:
- [x] WiFi connection management
- [x] DHT22 sensor reading (setiap 5 detik)
- [x] HTTP POST to Laravel API (setiap 10 detik)
- [x] MQTT publish (setiap 10 detik)
- [x] MQTT subscribe & callback
- [x] JSON serialization (ArduinoJson)
- [x] NTP time synchronization
- [x] Statistics tracking (success/fail counters)
- [x] Serial logging & monitoring
- [x] Power consumption calculation
- [x] System status reporting (setiap 30 detik)
- [x] Automatic reconnection
- [x] Watchdog timeout prevention

### 📊 Sensor Reading Intervals:
| Event | Interval | Purpose |
|-------|----------|---------|
| Sensor Read | 5 detik | Baca DHT22 |
| HTTP Send | 10 detik | Kirim ke HTTP API |
| MQTT Send | 10 detik | Publish ke MQTT |
| Status Report | 30 detik | Print status ke serial |

---

## 🔌 Konfigurasi Advanced

### MQTT Broker Settings
Untuk menggunakan MQTT, pastikan Mosquitto sudah running:

```bash
# Docker Mosquitto
docker run -it -p 1883:1883 -p 9001:9001 \
  -v mosquitto.conf:/mosquitto/config/mosquitto.conf \
  eclipse-mosquitto

# Atau install lokal:
sudo apt-get install mosquitto mosquitto-clients
sudo systemctl start mosquitto
```

### WiFi Modes:
```cpp
WiFi.mode(WIFI_STA);        // Station mode (connect to router)
WiFi.mode(WIFI_AP);         // Access point mode
WiFi.mode(WIFI_AP_STA);     // Both
```

### Sensor Reading Frequency:
```cpp
// Ubah di main.cpp:
const unsigned long INTERVAL_SENSOR = 5000;  // milliseconds
const unsigned long INTERVAL_HTTP = 10000;
const unsigned long INTERVAL_MQTT = 10000;
```

### Power Calculation Method:
```cpp
float calculatePowerConsumption() {
    // Current: Simplified calculation
    // Recommendation: Integrate dengan actual power meter
    // Options:
    // 1. INA219 Current Sensor
    // 2. ACS712 Hall Effect Sensor
    // 3. Actual multimeter readings
}
```

---

## 📈 Serial Monitor Output

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
[MQTT] Attempting to connect to: 192.168.1.100:1883
[MQTT] ✓ Connected!
       Client ID: ESP32-1
[TIME] Syncing with NTP server...
[TIME] ✓ Time synchronized: Thu Feb 25 10:30:45 2026
[SETUP] Initialization complete!

[SENSOR] ✓ T: 25.50°C | H: 55.20% | P: 89.34 mW
[HTTP] Sending to: http://192.168.1.100:8000/api/http-data
       Payload: {"device_id":1,"suhu":25.5,"timestamp_esp":1708884264,"daya":89.34}
[HTTP] ✓ Success (201 Created)
       Response: {"success":true,"message":"Data HTTP berhasil disimpan"...}
[MQTT] Publishing to: iot/esp32/suhu
       Payload: {"device_id":1,"suhu":25.5,"timestamp_esp":1708884264,"daya":89.34}
[MQTT] ✓ Published successfully

...

==========================================
         SYSTEM STATUS REPORT
==========================================
WiFi: ✓ Connected (192.168.1.105)
MQTT: ✓ Connected
Last Temperature: 25.50°C
Last Humidity: 55.20%
Last Power: 89.34 mW

--- STATISTICS ---
Sensor Reads: 250
HTTP Success: 125 | Failures: 0
MQTT Success: 125 | Failures: 0
Uptime: 1250 seconds
Free Memory: 178456 bytes
==========================================
```

---

## 🧪 Testing

### 1. Manual Test via Serial Monitor
```bash
pio device monitor
# Lihat output untuk verifikasi:
# - DHT22 reading success ✓
# - WiFi connection ✓
# - HTTP POST success ✓
# - MQTT publish success ✓
```

### 2. Test HTTP Endpoint
```bash
curl -X POST http://192.168.1.100:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 1,
    "suhu": 25.5,
    "timestamp_esp": 1708884000,
    "daya": 100
  }'

# Expected response (201):
{
  "success": true,
  "message": "Data HTTP berhasil disimpan",
  "data": {...}
}
```

### 3. Test MQTT Endpoint
```bash
# Terminal 1: Monitor MQTT topic
mosquitto_sub -h 192.168.1.100 -t "iot/esp32/suhu"

# Terminal 2: Publish test message
mosquitto_pub -h 192.168.1.100 -t "iot/esp32/suhu" \
  -m '{"device_id":1,"suhu":28.3,"timestamp_esp":1708884200,"daya":120}'

# Terminal 1 should receive: {"device_id":1,"suhu":28.3,...}
```

### 4. Check Dashboard
```
Buka: http://localhost:8000
- Real-time metrics harus update
- Graphs harus menampilkan data baru
- T-Test results harus terkalkulasi
```

---

## 🐛 Troubleshooting

### ❌ DHT22 tidak terbaca
```
Problem: isnan(temperature) atau isnan(humidity)
Solution:
1. Cek pin GPIO (default: GPIO 4)
2. Cek pull-up resistor 4.7kΩ atau built-in
3. Cek kabel koneksi
4. Reset ESP32
5. Tunggu 2 detik di setup() untuk stabilisasi
```

### ❌ WiFi tidak connect
```
Problem: WiFi disconnected message
Solution:
1. Verifikasi SSID dan password benar
2. Cek RSSI signal strength (gunakan WiFi analyzer)
3. Cek ESP32 dalam range WiFi router
4. Cek router channel (1, 6, 11 untuk 2.4GHz recommended)
5. Reset WiFi di ESP32: WiFi.disconnect(true);
```

### ❌ HTTP POST gagal (code 422)
```
Problem: Validation error dari Laravel
Solution:
1. Cek device_id ada di database
2. Cek JSON format sesuai dengan spec
3. Verifikasi data types:
   - device_id: integer
   - suhu: float/numeric
   - timestamp_esp: integer/unix timestamp
   - daya: float/numeric
4. Cek server URL benar
5. Test dengan curl dulu sebelum firmware
```

### ❌ MQTT tidak connect
```
Problem: MQTT connection failed
Solution:
1. Cek MQTT broker running (mosquitto)
2. Cek IP dan port benar
3. Cek firewall allow port 1883
4. Check MQTT user/password (default: esp32/esp32)
5. gunakan mosquitto_sub untuk test:
   mosquitto_sub -h 192.168.1.100 -u esp32 -P esp32 -t "#"
```

### ❌ Latency terlalu tinggi
```
Problem: latency_ms > 100ms
Solution:
1. Cek response time server (gunakan curl dengan -w timing)
2. Cek WiFi signal strength (RSSI)
3. Reduce HTTP/MQTT send interval
4. Check server load
5. Monitor router latency
```

---

## 📚 Library References

| Library | Version | Used For |
|---------|---------|----------|
| Adafruit DHT | ^1.4.4 | DHT22 sensor |
| Arduino JSON | ^6.21.3 | JSON serialization |
| PubSubClient | ^2.8 | MQTT protocol |
| WiFiManager | latest | WiFi connection |
| TFT_eSPI | ^2.5.34 | Display (optional) |

---

## 🔐 Security Notes

⚠️ **PENTING**: Untuk production, implementasikan:
1. HTTPS instead of HTTP
2. MQTT SSL/TLS (port 8883)
3. Proper authentication (JWT tokens)
4. Rate limiting
5. Input validation (server-side)
6. Encryption data sensitive
7. Support OTA updates

---

## 📝 License

Free to use untuk research dan educational purposes.

---

Untuk questions atau kontribusi, silakan buat issue di repository!
