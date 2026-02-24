#!/bin/bash
# ============================================================
# Complete System Verification & Deployment Checklist
# ============================================================
# Run this checklist to verify entire system is working
# ============================================================

echo "============================================================"
echo "  IoT Research System - Complete Verification Checklist"
echo "============================================================"
echo ""

# ============================================================
# 1. HARDWARE CHECK
# ============================================================
echo "📱 HARDWARE VERIFICATION"
echo "=================================================="
echo "[ ] ESP32 DevKit V1 board available"
echo "[ ] DHT22 sensor present"
echo "[ ] Micro USB cable available"
echo "[ ] Jumper wires connected properly"
echo "[ ] 4.7kΩ resistor (optional but recommended)"
echo ""

# ============================================================
# 2. SOFTWARE ENVIRONMENT
# ============================================================
echo "💻 SOFTWARE ENVIRONMENT"
echo "=================================================="
echo "[ ] VS Code installed"
echo "[ ] PlatformIO IDE extension installed"
  echo "[ ] Python 3.6+ installed (for PlatformIO)"
echo "[ ] Python added to PATH"
echo ""

# ============================================================
# 3. BACKEND SETUP
# ============================================================
echo "🎯 BACKEND SETUP (Laravel)"
echo "=================================================="
echo "[ ] XAMPP installed and running"
echo "[ ] MySQL service running"
echo "[ ] Laravel dependencies installed (composer install)"
echo "[ ] .env file configured"
echo "    - DB_HOST=127.0.0.1"
echo "    - DB_DATABASE=esptest"
echo "    - DB_USERNAME=root"
echo "[ ] Database created (esptest)"
echo "[ ] Migrations ran (php artisan migrate)"
echo "[ ] Seeder ran (php artisan db:seed)"
echo "[ ] Device record created in database"
echo "[ ] Laravel server running (php artisan serve)"
echo "[ ] Dashboard accessible (http://localhost:8000)"
echo ""

# ============================================================
# 4. FIRMWARE FILES
# ============================================================
echo "🔧 FIRMWARE FILES"
echo "=================================================="
echo "[ ] ESP32_Firmware folder exists"
echo "[ ] platformio.ini configured"
echo "    - Correct board: esp32doit-devkit-v1"
echo "    - Correct framework: arduino"
echo "[ ] src/main.cpp contains:"
echo "    - WiFi configuration (SSID, password)"
echo "    - Server configuration (IP addresses)"
echo "    - Device ID matching database"
echo "    - DHT22 pin configuration (GPIO 4)"
echo "[ ] README.md documentation present"
echo "[ ] CONFIGURATION.cpp reference file present"
echo ""

# ============================================================
# 5. NETWORK CONFIGURATION
# ============================================================
echo "🌐 NETWORK CONFIGURATION"
echo "=================================================="
echo "[ ] WiFi SSID correct in firmware"
echo "[ ] WiFi password correct in firmware"
echo "[ ] Server IP correct in firmware (HTTP_SERVER)"
echo "[ ] MQTT broker IP correct in firmware (MQTT_SERVER)"
echo "[ ] Ports accessible:"
echo "    - Port 8000 (Laravel)"
echo "    - Port 1883 (MQTT) optional"
echo "[ ] Firewall allows connections"
echo "[ ] All devices on same network"
echo ""

# ============================================================
# 6. DATABASE VERIFICATION
# ============================================================
echo "🗄️  DATABASE VERIFICATION"
echo "=================================================="
echo "Connecting to MySQL..."
echo ""
echo "Run in MySQL console:"
echo "  mysql> SELECT * FROM devices;"
echo ""
echo "[ ] devices table exists"
echo "[ ] At least one device with device_id=1"
echo "[ ] Column: id, name, description, device_id"
echo ""
echo "Run in MySQL console:"
echo "  mysql> SELECT COUNT(*) FROM eksperimens;"
echo ""
echo "[ ] eksperimens table exists"
echo "[ ] Ready for data insertion"
echo ""

# ============================================================
# 7. HTTP ENDPOINT TESTING
# ============================================================
echo "📨 HTTP ENDPOINT TESTING"
echo "=================================================="
echo "Run in terminal:"
echo ""
echo "curl -X POST http://localhost:8000/api/http-data \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"device_id\":1,\"suhu\":25.5,\"timestamp_esp\":$(date +%s),\"daya\":100}'"
echo ""
echo "[ ] Response code: 201 Created"
echo "[ ] Response contains: success: true"
echo "[ ] Data saved to database"
echo ""

# ============================================================
# 8. FIRMWARE BUILD & UPLOAD
# ============================================================
echo "⚙️  FIRMWARE BUILD & UPLOAD"
echo "=================================================="
echo "[ ] PlatformIO opened folder: ESP32_Firmware"
echo "[ ] Terminal: pio run (compilation successful)"
echo "[ ] Terminal: pio run -t upload (upload successful)"
echo "[ ] SerialMonitor showing boot messages"
echo ""
echo "Expected output:"
echo "  [WiFi] ✓ Connected!"
echo "  [MQTT] ✓ Connected!"
echo "  [TIME] ✓ Time synchronized"
echo "  [SENSOR] ✓ T: XX.XXC | H: XX.XX% | P: XX.XX mW"
echo ""

# ============================================================
# 9. INTEGRATION VERIFICATION
# ============================================================
echo "🔗 INTEGRATION VERIFICATION"
echo "=================================================="
echo "[ ] Serial monitor shows HTTP success messages"
echo "[ ] Database shows new records (SELECT * FROM eksperimens;)"
echo "[ ] Protocol field shows: 'HTTP'"
echo "[ ] Latency calculated (latency_ms > 0)"
echo "[ ] Dashboard updates with new data"
echo "[ ] Animated counters display new values"
echo ""

# ============================================================
# 10. MQTT SETUP (OPTIONAL)
# ============================================================
echo "📡 MQTT SETUP (OPTIONAL)"
echo "=================================================="
echo "[ ] Mosquitto installed/running"
echo "[ ] MQTT port 1883 accessible"
echo "[ ] Topic 'iot/esp32/suhu' configured"
echo ""
echo "Test commands:"
echo "  mosquitto_sub -h 127.0.0.1 -t \"iot/esp32/suhu\""
echo "  mosquitto_pub -h 127.0.0.1 -t \"iot/esp32/suhu\" -m '{...}'"
echo ""
echo "[ ] Messages received on topic"
echo "[ ] Database shows protocol: 'MQTT'"
echo "[ ] Laravel listener parsing messages"
echo ""

# ============================================================
# 11. DASHBOARD VERIFICATION
# ============================================================
echo "📊 DASHBOARD VERIFICATION"
echo "=================================================="
echo "Open: http://localhost:8000"
echo ""
echo "[ ] Page loads without errors"
echo "[ ] Header displays: \"IoT Research System\""
echo "[ ] Real-Time Metrics section visible"
echo "    - MQTT Total Data display"
echo "    - MQTT Avg Latency display"
echo "    - MQTT Avg Power display"
echo "    - MQTT Reliability% display"
echo "    - HTTP equivalent metrics"
echo ""
echo "[ ] Comparative Analysis section"
echo "    - Latency bar chart visible"
echo "    - Power Consumption bar chart visible"
echo ""
echo "[ ] T-Test Results section"
echo "    - Latency analysis cards"
echo "    - Power analysis cards"
echo "    - Significance badges"
echo ""
echo "[ ] All numbers animated from 0 to real values"
echo "[ ] Charts display bars with animation"
echo "[ ] Auto-refresh every 5 seconds (no page reload)"
echo ""

# ============================================================
# 12. PERFORMANCE METRICS
# ============================================================
echo "⚡ PERFORMANCE METRICS"
echo "=================================================="
echo "Serial Monitor shows (every 30 seconds):"
echo ""
echo "[ ] Sensor Reads: incrementing"
echo "[ ] HTTP Success: > 0"
echo "[ ] HTTP Failures: = 0 (or minimal)"
echo "[ ] MQTT Success: > 0 (if enabled)"
echo "[ ] Free Memory: fluctuating (no constant decrease)"
echo "[ ] Temperature readings: realistic values (15-40°C)"
echo "[ ] Power consumption: reasonable (80-150 mW)"
echo ""

# ============================================================
# 13. SYSTEM STABILITY
# ============================================================
echo "🛡️  SYSTEM STABILITY"
echo "=================================================="
echo "Leave system running for 5+ minutes:"
echo ""
echo "[ ] ESP32 does not crash or reboot"
echo "[ ] Serial output continuous (no gaps)"
echo "[ ] Database records increasing regularly"
echo "[ ] Dashboard updates without errors"
echo "[ ] No memory leaks (free memory stable)"
echo "[ ] WiFi connection maintained"
echo "[ ] MQTT connection maintained"
echo ""

# ============================================================
# 14. DATA QUALITY
# ============================================================
echo "📈 DATA QUALITY"
echo "=================================================="
echo "Check database:"
echo "  SELECT * FROM eksperimens WHERE protokol='HTTP' ORDER BY id DESC LIMIT 10;"
echo ""
echo "[ ] suhu values realistic (15-40°C)"
echo "[ ] latency_ms values positive"
echo "[ ] daya_mw values reasonable (85-150)"
echo "[ ] timestamp_esp is valid Unix timestamp"
echo "[ ] timestamp_server is current time"
echo "[ ] No NULL values in required fields"
echo ""

# ============================================================
# 15. DEPLOYMENT READY
# ============================================================
echo "✅ DEPLOYMENT READY"
echo "=================================================="
echo "[ ] All previous checks PASSED"
echo "[ ] Code commented and documented"
echo "[ ] No debug statements left in code"
echo "[ ] Security settings configured"
echo "[ ] Error handling implemented"
echo "[ ] Logging enabled for monitoring"
echo "[ ] Backup of configuration files"
echo "[ ] Documentation complete"
echo ""

# ============================================================
# FINAL STATUS
# ============================================================
echo "============================================================"
echo "  SYSTEM READY FOR DEPLOYMENT! 🚀"
echo "============================================================"
echo ""
echo "Next steps:"
echo "1. Deploy to production server"
echo "2. Configure HTTPS/SSL if needed"
echo "3. Setup monitoring & alerting"
echo "4. Schedule regular data backups"
echo "5. Document findings from research"
echo ""
