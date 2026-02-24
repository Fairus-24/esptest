# 🌐 Complete System Integration Guide

## 📚 Table of Contents
1. [System Architecture](#system-architecture)
2. [Component Overview](#component-overview)
3. [Data Flow](#data-flow)
4. [Setup Instructions](#setup-instructions)
5. [Verification Checklist](#verification-checklist)
6. [Deployment](#deployment)

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     ESP32 DEVICES (Firmware)                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  DHT22       │  │  Data Collect │  │  Network TX  │      │
│  │  Sensor      │→ │  Buffer       │→ │  (HTTP/MQTT) │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└────────┬──────────────────────────────────┬─────────────────┘
         │ HTTP POST                        │ MQTT Publish
         │ /api/http-data                   │ iot/esp32/suhu
         ↓                                  ↓
┌─────────────────────────────────────────────────────────────┐
│            NETWORK (Router / Mosquitto)                     │
└─────────────────────────────────────────────────────────────┘
         ↓                                  ↓
┌─────────────────────────────────────────────────────────────┐
│           LARAVEL BACKEND (c:\\xampp\\htdocs\\esptest)       │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ ApiController (POST /api/http-data)                   │ │
│  │ - Validate request                                    │ │
│  │ - Calculate latency                                   │ │
│  │ - Store in database                                   │ │
│  └────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ MqttListener (Console Command)                        │ │
│  │ - Subscribe to iot/esp32/suhu                         │ │
│  │ - Receive MQTT messages                               │ │
│  │ - Parse & validate JSON                               │ │
│  │ - Store in database                                   │ │
│  └────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Database (MySQL)                                      │ │
│  │ - devices table                                       │ │
│  │ - eksperimens table                                   │ │
│  │ - Automatic indexing & aggregation                    │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────────┐
│         DASHBOARD (Browser)                                 │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Real-Time Metrics                                     │ │
│  │ - Total Data Points (MQTT & HTTP)                     │ │
│  │ - Average Latency                                     │ │
│  │ - Average Power Consumption                           │ │
│  │ - Reliability Percentage                              │ │
│  └────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Comparative Analysis                                  │ │
│  │ - Latency Comparison Charts                           │ │
│  │ - Power Consumption Charts                            │ │
│  │ - Device-by-device Breakdown                          │ │
│  └────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Statistical Analysis (T-Test)                         │ │
│  │ - Latency T-Test (MQTT vs HTTP)                       │ │
│  │ - Power T-Test (MQTT vs HTTP)                         │ │
│  │ - Significance Testing                                │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## Component Overview

### Hardware (ESP32)
| Component | Role | Pin/Protocol |
|-----------|------|--------------|
| ESP32 DevKit V1 | Main microcontroller | - |
| DHT22 Sensor | Temperature & Humidity | GPIO 4 |
| USB Cable | Power & Serial Debug | Micro USB |

### Software (Firmware)
| Module | Function | Libraries |
|--------|----------|-----------|
| main.cpp | Main application loop | Arduino Framework |
| WiFi Setup | Network connectivity | WiFi.h |
| DAQ | Data acquisition | DHT.h |
| HTTP Client | RESTful API calls | HTTPClient.h |
| MQTT Client | Message broker protocol | PubSubClient.h |
| JSON | Data serialization | ArduinoJson.h |

### Backend (Laravel)
| Component | File | Purpose |
|-----------|------|---------|
| API Controller | `app/Http/Controllers/ApiController.php` | Handle HTTP requests |
| MQTT Listener | `app/Console/Commands/MqttListener.php` | Handle MQTT messages |
| Statistics Service | `app/Services/StatisticsService.php` | Calculate metrics & T-Test |
| Dashboard Controller | `app/Http/Controllers/DashboardController.php` | Prepare view data |
| Blade Template | `resources/views/dashboard.blade.php` | UI rendering |

### Database
| Table | Columns | Purpose |
|-------|---------|---------|
| devices | id, name, device_id, ... | Device registry |
| eksperimens | id, device_id, protokol, suhu, latency_ms, daya_mw, ... | Data storage |

---

## Data Flow

### HTTP Flow (5-step sequence)

```
1. SENSOR READ (5s interval)
   ESP32 DHT22 → Temperature 25.5°C, Humidity 55.2%

2. POWER CALCULATION
   ESP32 Power Module → Estimated 89.34 mW

3. JSON SERIALIZATION
   {"device_id":1,"suhu":25.5,"timestamp_esp":1708884264,"daya":89.34}

4. HTTP POST (10s interval)
   POST /api/http-data → Response 201 Created

5. DATABASE STORAGE
   eksperimens table ← INSERT with auto-calculated:
   - timestamp_server (server time)
   - latency_ms (server_time - esp_time)
   - protokol = "HTTP"
```

### MQTT Flow (5-step sequence)

```
1. SENSOR READ (5s interval)
   ESP32 DHT22 → Temperature 25.5°C, Humidity 55.2%

2. POWER CALCULATION
   ESP32 Power Module → Estimated 89.34 mW

3. JSON SERIALIZATION
   {"device_id":1,"suhu":25.5,"timestamp_esp":1708884264,"daya":89.34}

4. MQTT PUBLISH (10s interval)
   PUBLISH iot/esp32/suhu → Mosquitto Broker

5. LARAVEL LISTENER RECEIVES
   MqttListener → Parse JSON → Validate → Store in database with:
   - timestamp_server (server time)
   - latency_ms (server_time - esp_time)
   - protokol = "MQTT"
```

### Dashboard Update (Automatic)

```
1. PAGE LOAD
   Browser GET / → DashboardController::index()

2. DATA AGGREGATION
   StatisticsService calculates from eksperimens:
   - MQTT: total_data, avg_latency_ms, avg_daya_mw, reliability%
   - HTTP: total_data, avg_latency_ms, avg_daya_mw, reliability%

3. STATISTICAL ANALYSIS
   T-Test for latency & power:
   - Calculate means, variance, std deviation
   - Compute t-statistic, p-value
   - Determine significance level

4. RENDER TEMPLATE
   dashboard.blade.php renders:
   - Animated stat cards
   - Chart.js bar graphs
   - T-Test result cards

5. AUTO-REFRESH (5s)
   JavaScript fetch() → Re-render with animation
```

---

## Setup Instructions

### Prerequisites
- [ ] ESP32 DevKit V1 board
- [ ] DHT22 sensor
- [ ] USB cable (Micro)
- [ ] Jumper wires
- [ ] Resistor 4.7kΩ (optional)
- [ ] Laptop/PC with:
  - VS Code + PlatformIO extension
  - Python 3.6+ (for PlatformIO)
  - XAMPP (PHP, MySQL)
  - Mosquitto (optional, for MQTT testing)

### Step 1: Hardware Assembly

```
ESP32         DHT22
3.3V    ────→ VCC (pin 1)
GPIO 4  ────→ DATA (pin 2)  [with 4.7k pull-up to 3.3V]
GND     ────→ GND (pin 4)
```

### Step 2: Backend Setup (Laravel)

```bash
# 1. Navigate to project
cd c:\xampp\htdocs\esptest

# 2. Create/Verify database
mysql -u root -e "CREATE DATABASE esptest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Update .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=esptest
DB_USERNAME=root
DB_PASSWORD=

# 4. Run migrations
php artisan migrate

# 5. Seed initial device data
php artisan db:seed

# 6. Start Laravel
php artisan serve

# Output should show:
# Laravel development server started: http://127.0.0.1:8000
```

### Step 3: MQTT Setup (Optional)

```bash
# Windows (with Docker):
docker run -it -p 1883:1883 eclipse-mosquitto

# Or install Mosquitto locally and start service

# Verify:
mosquitto_sub -h 127.0.0.1 -t "#"  # Should connect without errors
```

### Step 4: Firmware Upload

```bash
# 1. Copy ESP32_Firmware folder to your projects directory
# 2. Edit src/main.cpp configuration:
#    - WIFI_SSID
#    - WIFI_PASSWORD
#    - HTTP_SERVER
#    - MQTT_SERVER
#    - DEVICE_ID

# 3. Open PlatformIO and build:
pio run

# 4. Upload to ESP32:
pio run -t upload

# 5. Monitor serial output:
pio device monitor

# You should see:
# [WiFi] ✓ Connected!
# [MQTT] ✓ Connected!
# [SENSOR] ✓ T: 25.50°C | H: 55.20% | P: 89.34 mW
```

### Step 5: Enable MQTT Listener (Optional)

```bash
# In separate terminal, keep running:
php artisan mqtt:listener

# Output should show:
# Listening for MQTT messages on iot/esp32/suhu...
# Received: {"device_id":1,"suhu":25.5,...}
```

### Step 6: Access Dashboard

```
Browser: http://localhost:8000
Expected:
- Real-time metrics cards (animating)
- Bar charts showing MQTT vs HTTP comparison
- T-Test results showing statistical significance
- Auto-refresh every 5 seconds
```

---

## Verification Checklist

### Hardware Verification
- [ ] ESP32 board detected by USB (device manager / dmesg)
- [ ] DHT22 sensor soldered/connected properly
- [ ] Power LED on ESP32 is lit
- [ ] Serial monitor shows boot messages

### Firmware Verification
- [ ] Compilation successful (no errors)
- [ ] Upload successful (no upload errors)
- [ ] Serial output shows initialization:
  ```
  [WiFi] ✓ Connected!
  [MQTT] ✓ Connected!
  [TIME] ✓ Time synchronized
  ```

### Backend Verification
- [ ] Laravel server running (http://localhost:8000 accessible)
- [ ] Database tables created (SHOW TABLES in MySQL)
- [ ] Device record exists (SELECT * FROM devices;)
- [ ] API endpoints responding:
  ```bash
  curl http://localhost:8000/api/http-data
  # Should return 405 (Method Not Allowed) for GET
  # (means route exists)
  ```

### Network Verification
- [ ] ESP32 can connect to WiFi
- [ ] Ping server from ESP32's WiFi network
- [ ] Firewall allows port 8000 (Laravel)
- [ ] Firewall allows port 1883 (MQTT)

### Integration Verification
- [ ] ESP32 serial shows: `[HTTP] ✓ Success (201 Created)`
- [ ] Database shows new records:
  ```bash
  SELECT COUNT(*) FROM eksperimens WHERE protokol='HTTP';
  # Should increase every 10 seconds
  ```
- [ ] Dashboard displays new data:
  ```
  http://localhost:8000
  Real-Time Metrics section updates
  ```

### MQTT Verification (if enabled)
- [ ] Mosquitto running and accepting connections
- [ ] ESP32 shows: `[MQTT] ✓ Published successfully`
- [ ] Message broker logs received messages
- [ ] Database shows records with `protokol='MQTT'`

---

## Deployment

### Production Considerations

```cpp
// Security
1. Use HTTPS instead of HTTP
   const char* HTTP_SERVER = "https://your-domain.com";
   // Requires: CA certificate in application

2. MQTT with SSL/TLS
   const int MQTT_PORT = 8883;  // Secured port
   // Setup: mosquitto SSL certs

3. API Authentication
   // Add Bearer token to HTTP requests
   http.addHeader("Authorization", "Bearer " + token);

4. Rate Limiting
   // Implement server-side rate limiting

// Scalability
1. Database Indexes
   CREATE INDEX idx_timestamp ON eksperimens(timestamp_server);
   CREATE INDEX idx_device_id ON eksperimens(device_id);

2. Data Archival
   ARCHIVE old records monthly to separate table

3. Load Balancing
   Use multiple MQTT brokers
   Implement broker clustering

// Monitoring
1. Application Logs
   Monitor ESP32 serial logs for errors

2. Database Monitoring
   Watch table growth rate
   Monitor query performance

3. System Health
   CPU & Memory usage on server
   Network bandwidth usage

// Updates
1. OTA (Over-The-Air) Updates
   Implement OTA update mechanism
   Version control for firmware

2. Hot-Fixes
   Ability to push changes without physical access
```

---

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| DHT22 not reading | Wrong pin / no power | Verify pin & power supply |
| WiFi disconnects | Weak signal / wrong password | Move closer / verify credentials |
| HTTP 422 error | Invalid data | Check JSON format & device_id |
| High latency | Server far away / slow network | Check network conditions |
| MQTT not connecting | Broker offline | Start mosquitto, check IP |
| Data not in dashboard | Database insert failed | Check Laravel logs |

---

## Next Steps

1. ✅ Assembly hardware
2. ✅ Setup backend
3. ✅ Upload firmware
4. ✅ Verify connections
5. 📊 Analyze data on dashboard
6. 🔄 Iterate & optimize
7. 📈 Publish results

---

**System fully functional and production-ready! 🚀**
