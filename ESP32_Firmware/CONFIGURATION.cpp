// ============================================================
// ESP32 Configuration File - FOR REFERENCE
// ============================================================
// 
// Gunakan file ini sebagai referensi untuk konfigurasi
// Edit langsung di src/main.cpp untuk actual implementation
// 

// ============================================================
// 1. WIFI CONFIGURATION
// ============================================================

const char* WIFI_SSID = "MyWiFiNetwork";           // Nama WiFi Anda
const char* WIFI_PASSWORD = "MyPassword123";       // Password WiFi

// Tips:
// - Gunakan WiFi 2.4GHz (tidak support 5GHz)
// - Pastikan signal minimal -70 dBm
// - Gunakan static IP untuk deterministic behavior:
//   WiFi.config(IPAddress(192,168,1,105), 
//               IPAddress(192,168,1,1),     // gateway
//               IPAddress(255,255,255,0));  // subnet

// ============================================================
// 2. SERVER CONFIGURATION
// ============================================================

const char* HTTP_SERVER = "http://192.168.1.100:8000";
// Opsi:
// - "http://192.168.1.100:8000" (local network)
// - "http://your-domain.com" (with public DNS)
// - "http://192.168.0.1:3000" (different router)

const char* HTTP_ENDPOINT = "/api/http-data";
// Laravel API endpoint untuk HTTP data submission

const char* MQTT_SERVER = "192.168.1.100";
const int MQTT_PORT = 1883;
// MQTT Broker settings - ganti dengan IP/hostname Anda
// Default port: 1883 (plain), 8883 (SSL/TLS)

const char* MQTT_TOPIC = "iot/esp32/suhu";
// Topic untuk publish sensor data
// Alternatif struktur:
// - "devices/{device_id}/temperature"
// - "mqtt/esp32/{device_id}/data"
// - "home/bedroom/temperature"

const char* MQTT_USER = "esp32";
const char* MQTT_PASSWORD = "esp32";
// Default Mosquitto tidak memerlukan authentication
// Ubah sesuai konfigurasi broker Anda

// ============================================================
// 3. DEVICE CONFIGURATION
// ============================================================

const int DEVICE_ID = 1;
// Device ID harus matching dengan database
// Check di: SELECT * FROM devices;

const int DHT_PIN = 4;
// GPIO pin untuk DHT22 data pin
// Opsi pin: GPIO 0, 2, 4, 5, 12-19, 21-23, 25-27, 32-39
// Rekomendasi: GPIO 4, 5 (tidak conflict dengan UART/Flash)

const int DHT_TYPE = DHT22;
// DHT22 = Suhu & Humidity (±2%)
// DHT11 = lebih murah tapi less accurate (±5%)
// BME280 = temperature, humidity, pressure (library berbeda)

// ============================================================
// 4. TIMING CONFIGURATION (semua dalam milliseconds)
// ============================================================

const unsigned long INTERVAL_SENSOR = 5000;
// Baca sensor setiap 5 detik
// Opsi: 2000 (2s), 5000 (5s), 10000 (10s)

const unsigned long INTERVAL_HTTP = 10000;
// Kirim HTTP setiap 10 detik
// Opsi: 5000, 10000, 30000, 60000

const unsigned long INTERVAL_MQTT = 10000;
// Publish MQTT setiap 10 detik
// Opsi: 5000, 10000, 30000, 60000

const unsigned long WIFI_TIMEOUT = 10000;
// Timeout untuk WiFi connection attempt
// Opsi: 5000, 10000, 15000

// ============================================================
// 5. TELEMETRY & MONITORING
// ============================================================

// Status Report Interval (setiap 30 detik)
// Menampilkan: statistics, memory, uptime, connection status
// Disable: hapus code di loop() atau ubah interval ke UINT32_MAX

// Serial Logging
// Baud rate: 115200
// Format: [TIMESTAMP] [COMPONENT] Message
// Levels: INFO (✓), WARN (⚠), ERROR (✗)

// ============================================================
// 6. POWER CALCULATION
// ============================================================

float calculatePowerConsumption() {
    // Current implementation: Estimated based on RSSI
    // 
    // Production alternatives:
    // 1. INA219 Power Monitor
    //    - Measure actual voltage & current
    //    - Very accurate
    //    - I2C connection
    // 
    // 2. ACS712 Hall Effect Sensor
    //    - Current sensing only
    //    - Cheaper than INA219
    //    - Analog input
    // 
    // 3. Calibrated Estimation
    //    - Collect actual readings
    //    - Build regression model
    //    - More accurate than baseline
    
    float basePower = 80.0;        // Base (WiFi + MCU)
    float rssiPower = 0.0;         // Variable based on signal
    float sensorPower = 2.0;       // DHT22 reading
    float transmitPower = 15.0;    // HTTP/MQTT TX
    
    return basePower + rssiPower + sensorPower + transmitPower;
}

// ============================================================
// 7. PIN MAPPING & CONNECTIONS
// ============================================================

/*
ESP32 DEVKIT V1 PIN LAYOUT:

Front View (USB at bottom):

                    USB
                    |
          GND  D35  VP   D34  D36  D39
          EN   D23  D22  TX   RX   D3
          SVP  TMS  TCO  TDI  TDO  CLK CMD
          SVN  D25  D26  D27  D14  D12  D13  D9   D10  CMD CLK
          5V   D33  TX2  RX2  D32  D35  D34  VUSB GND  5V  GND

Standard I2C: SDA=21, SCL=22
Standard SPI: MOSI=23, MISO=19, CLK=18, CS=5
UART: TX=1, RX=3, TX2=17, RX2=16

DHT22 Connection:
Pin 1 (Vcc)   → 3.3V
Pin 2 (Data)  → GPIO 4 (with 4.7k pull-up to 3.3V)
Pin 3 (NC)    → Not connected
Pin 4 (GND)   → GND

INA219 I2C Connection (optional):
SDA → GPIO 21
SCL → GPIO 22
VCC → 5V (or 3.3V with regulated)
GND → GND
A0-A1 → GND (for address 0x40)
*/

// ============================================================
// 8. DATABASE DEVICE SETUP
// ============================================================

/*
Laravel Database - devices table:

INSERT INTO devices (name, description, device_id, created_at) VALUES
('ESP32-MQTT', 'Main MQTT Test Device', 1, NOW()),
('ESP32-HTTP', 'HTTP Test Device', 2, NOW()),
('Sensor-01', 'Living Room', 3, NOW());

Verify:
mysql> SELECT * FROM devices;
+----+----------+----------------------+-----------+---------------------+
| id | name     | description          | device_id | created_at          |
+----+----------+----------------------+-----------+---------------------+
|  1 | ESP32-01 | Test Device 1        |         1 | 2026-02-25 10:00:00 |
+----+----------+----------------------+-----------+---------------------+
*/

// ============================================================
// 9. API ENDPOINT TESTING
// ============================================================

/*
Test before uploading to ESP32:

// Test 1: HTTP POST
curl -X POST http://192.168.1.100:8000/api/http-data \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 1,
    "suhu": 25.5,
    "timestamp_esp": '$(date +%s)',
    "daya": 89.5
  }' \
  -w "\nHTTP Status: %{http_code}\n"

// Test 2: MQTT Publish
mosquitto_pub -h 192.168.1.100 -t "iot/esp32/suhu" \
  -m '{"device_id":1,"suhu":25.5,"timestamp_esp":'$(date +%s)',"daya":89.5}'

// Test 3: Dashboard
Open: http://192.168.1.100:8000 (or localhost:8000)
Check: New data appears in Real-Time Metrics
*/

// ============================================================
// 10. OPTIMIZATION TIPS
// ============================================================

/*
Performance:
- Use static IP: WiFi.config(...) untuk avoid DHCP delay
- Reduce sensor read interval jika WiFi reliable
- Batch requests: combine multiple reads sebelum send
- Use MQTT untuk frequent updates, HTTP untuk reliability

Power Saving:
- Increase interval times jika battery powered
- Use deep sleep: esp_deep_sleep_start()
- Reduce serial logging di production
- Disable MQTT jika hanya HTTP needed

Memory:
- Stack size: configurable via IDF
- Heap: monitor dengan ESP.getFreeHeap()
- Use PROGMEM untuk string constants
- Clear unnecessary libraries

Reliability:
- Implement retry logic untuk failed sends
- Add error checking di semua operations
- Use watchdog timer (WDT enabled by default)
- Log errors untuk debugging
*/

// ============================================================
// 11. TROUBLESHOOTING CHECKLIST
// ============================================================

/*
❌ DHT22 Sensor Issues:
[ ] GPIO pin correct?
[ ] Wiring proper (Vcc, GND, Data)?
[ ] Pull-up resistor (4.7k) present?
[ ] Sensor powered (3.3V)?
[ ] Wait 2 seconds untuk stabilize?
[ ] Try different GPIO pin?
[ ] Replace sensor test?

❌ WiFi Connection Issues:
[ ] SSID spelled correctly?
[ ] Password correct?
[ ] WiFi 2.4GHz (not 5GHz)?
[ ] Signal strength > -70 dBm?
[ ] Router not blocking MAC?
[ ] Try static IP?
[ ] Check firewall?
[ ] Restart router?

❌ HTTP API Issues:
[ ] Server running (curl to server)?
[ ] Correct IP address?
[ ] Port 8000 accessible?
[ ] JSON format valid?
[ ] device_id exists in database?
[ ] Laravel routes defined?
[ ] Check server logs?

❌ MQTT Broker Issues:
[ ] Mosquitto running (ps aux | grep mosquitto)?
[ ] Correct IP/port?
[ ] User/password correct?
[ ] Topic published?
[ ] Subscriber listening?
[ ] Firewall allow 1883?

❌ Data Not in Dashboard:
[ ] Check database: SELECT * FROM eksperimens;
[ ] Check recent timestamp?
[ ] Verify protocols (HTTP/MQTT)?
[ ] Dashboard refresh automatic?
[ ] Check browser console errors?
*/
