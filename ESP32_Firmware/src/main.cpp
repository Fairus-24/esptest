
#include <WiFi.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <DHT.h>


// ==================== CONFIGURATION ====================
// WiFi Settings
const char* WIFI_SSID = "Free";
const char* WIFI_PASSWORD = "gratiskok";

// Server Settings
const char* HTTP_SERVER = "http://192.168.0.100";  // Apache front, proxied to PHP 8.4 server
const char* HTTP_ENDPOINT = "/esptest/public/api/http-data";
const char* MQTT_SERVER = "192.168.0.100";  // Same subnet as ESP32
const int MQTT_PORT = 1883;
const char* MQTT_TOPIC = "iot/esp32/suhu";
const char* MQTT_USER = "esp32";
const char* MQTT_PASSWORD = "esp32";


// Device Settings
#define DHTPIN 4
#define DHTTYPE DHT11
DHT dht(DHTPIN, DHTTYPE);
const int DEVICE_ID = 1;

// Timing Settings
const unsigned long INTERVAL_SENSOR = 5000;    // Read sensor every 5 seconds
const unsigned long INTERVAL_HTTP = 10000;     // Send HTTP every 10 seconds
const unsigned long INTERVAL_MQTT = 10000;     // Send MQTT every 10 seconds
const unsigned long WIFI_TIMEOUT = 10000;      // WiFi connection timeout

// ==================== GLOBAL VARIABLES ====================
WiFiClient wifiClient;
PubSubClient mqttClient(wifiClient);

// Data storage
float lastTemperature = 0.0;
float lastHumidity = 0.0;
float lastPower = 0.0;
float lastHttpPower = 0.0;
float lastMqttPower = 0.0;
float lastHttpTxDurationMs = 120.0;
float lastMqttTxDurationMs = 50.0;
uint32_t httpPacketSeq = 0;
uint32_t mqttPacketSeq = 0;
long lastSensorRead = 0;
long lastHTTPSend = 0;
long lastMQTTSend = 0;
bool mqttConnected = false;
bool httpConnected = false;

// Statistics
unsigned long sensorReadCount = 0;
unsigned long httpSendSuccess = 0;
unsigned long httpSendFail = 0;
unsigned long mqttSendSuccess = 0;
unsigned long mqttSendFail = 0;
unsigned long lastMQTTAttempt = 0;  // Prevent MQTT connection spam

// ==================== FUNCTION DECLARATIONS ====================
void setupWiFi();
void readSensor();
void sendHTTP();
void sendMQTT();
void connectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int length);
float calculatePowerConsumption();
float estimateProtocolPower(const char* protocol, float txDurationMs, size_t payloadBytes, int rssiDbm, bool success);
void fillProtocolPayload(StaticJsonDocument<640>& jsonDoc, const char* protocol, uint32_t packetSeq, float dayaMw, float txDurationMs, uint32_t payloadBytes, int rssiDbm, long timestampEsp);
String buildProtocolPayload(const char* protocol, uint32_t packetSeq, float dayaMw, float txDurationMs, int rssiDbm, long timestampEsp, uint32_t* payloadBytesOut = nullptr);
bool payloadHasRequiredFields(const String& payload);
void printStatus();
void updateTime();

// ==================== SETUP ====================
void setup() {
    Serial.begin(115200);
    delay(1000);
    
    Serial.println("\n\n");
    Serial.println("==========================================");
    Serial.println("  ESP32 IoT Data Logger - MQTT vs HTTP");
    Serial.println("==========================================");
    
    // Initialize DHT11
    Serial.println("[INIT] Initializing DHT11 sensor...");
    dht.begin();
    delay(1000);  // DHT11 needs time to stabilize
    
    // Connect to WiFi
    setupWiFi();
    
    // Setup MQTT
    mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
    mqttClient.setCallback(mqttCallback);
    
    // Synchronize time with NTP
    updateTime();
    
    Serial.println("[SETUP] Initialization complete!");
    Serial.println("==========================================\n");
}

// ==================== MAIN LOOP ====================
void loop() {
    // Re-connect WiFi if disconnected
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[WARN] WiFi disconnected, reconnecting...");
        setupWiFi();
    }
    
    // MQTT connection handling
    if (!mqttClient.connected()) {
        // Only attempt connection every 10 seconds (prevent spam)
        if (millis() - lastMQTTAttempt > 10000) {
            connectMQTT();
            lastMQTTAttempt = millis();
        }
    } else {
        mqttClient.loop();
    }
    
    // Read sensor data periodically
    if (millis() - lastSensorRead >= INTERVAL_SENSOR) {
        readSensor();
        lastSensorRead = millis();
    }
    
    // Send HTTP data periodically
    if (millis() - lastHTTPSend >= INTERVAL_HTTP) {
        sendHTTP();
        lastHTTPSend = millis();
    }
    
    // Send MQTT data periodically
    if (millis() - lastMQTTSend >= INTERVAL_MQTT) {
        if (mqttClient.connected()) {
            sendMQTT();
        }
        lastMQTTSend = millis();
    }
    
    // Print status periodically (every 30 seconds)
    static unsigned long lastStatus = 0;
    if (millis() - lastStatus >= 30000) {
        printStatus();
        lastStatus = millis();
    }
    
    delay(100);  // Small delay to prevent watchdog timeout
}

// ==================== WiFi SETUP ====================
void setupWiFi() {
    Serial.println("\n[WiFi] Connecting to: " + String(WIFI_SSID));
    
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    unsigned long startAttemptTime = millis();
    int attempts = 0;
    
    while (WiFi.status() != WL_CONNECTED && millis() - startAttemptTime < WIFI_TIMEOUT) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    Serial.println("");
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("[WiFi] ✓ Connected!");
        Serial.println("       IP: " + WiFi.localIP().toString());
        Serial.println("       RSSI: " + String(WiFi.RSSI()) + " dBm");
        httpConnected = true;
    } else {
        Serial.println("[WiFi] ✗ Failed to connect after " + String(attempts) + " attempts");
        Serial.println("[WiFi] Check SSID and password!");
        httpConnected = false;
    }
}

// ==================== SENSOR READING ====================
void readSensor() {
    float humidity = dht.readHumidity();
    float temperature = dht.readTemperature();
    if (isnan(humidity) || isnan(temperature)) {
        Serial.println("[SENSOR] ✗ Failed to read from DHT11!");
        Serial.println("         GPIO 4: Check connection & pull-up resistor (4.7kΩ)");
        Serial.print("         Raw values - T: ");
        Serial.print(temperature);
        Serial.print(", H: ");
        Serial.println(humidity);
        return;
    }
    lastTemperature = temperature;
    lastHumidity = humidity;
    lastPower = calculatePowerConsumption();
    sensorReadCount++;
    Serial.print("[SENSOR] ✓ T: ");
    Serial.print(temperature, 2);
    Serial.print("°C | H: ");
    Serial.print(humidity, 2);
    Serial.print("% | P: ");
    Serial.print(lastPower, 2);
    Serial.println(" mW");
}

// ==================== HTTP SENDER ====================
void sendHTTP() {
    if (!httpConnected || WiFi.status() != WL_CONNECTED) {
        Serial.println("[HTTP] WiFi not connected, skipping HTTP send");
        httpSendFail++;
        return;
    }

    if (lastTemperature == 0.0 && lastHumidity == 0.0) {
        Serial.println("[HTTP] No sensor data available");
        return;
    }

    long timestampEsp = (long) time(nullptr);
    if (timestampEsp < 1640995200L) {
        Serial.println("[HTTP] NTP time not synced, skipping send");
        httpSendFail++;
        return;
    }

    HTTPClient http;
    String url = String(HTTP_SERVER) + String(HTTP_ENDPOINT);

    uint32_t packetSeq = ++httpPacketSeq;
    int rssiDbm = WiFi.RSSI();
    float expectedTxMs = max(20.0f, (lastHttpTxDurationMs * 0.65f) + (fabsf((float) rssiDbm) * 0.35f));
    uint32_t payloadBytes = 0;
    String payload = buildProtocolPayload("HTTP", packetSeq, lastHttpPower, expectedTxMs, rssiDbm, timestampEsp, &payloadBytes);
    float predictedPower = estimateProtocolPower("HTTP", expectedTxMs, payloadBytes, rssiDbm, true);
    payload = buildProtocolPayload("HTTP", packetSeq, predictedPower, expectedTxMs, rssiDbm, timestampEsp, &payloadBytes);
    if (!payloadHasRequiredFields(payload)) {
        Serial.println("[HTTP] Payload invalid (missing required fields), skipping send");
        httpSendFail++;
        return;
    }

    Serial.print("[HTTP] Sending to: ");
    Serial.println(url);
    Serial.print("       Payload: ");
    Serial.println(payload);

    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.setConnectTimeout(5000);

    unsigned long txStart = millis();
    int httpCode = http.POST(payload);
    float txDurationMs = (float) (millis() - txStart);
    bool success = (httpCode >= 200 && httpCode < 300);

    float finalPower = estimateProtocolPower("HTTP", txDurationMs, payloadBytes, rssiDbm, success);
    lastHttpTxDurationMs = txDurationMs;
    lastHttpPower = finalPower;
    lastPower = finalPower;

    if (success) {
        Serial.print("[HTTP] Success (");
        Serial.print(httpCode);
        Serial.println(")");
        Serial.println("       Response: " + http.getString());
        Serial.print("       Seq: ");
        Serial.print(packetSeq);
        Serial.print(" | RSSI: ");
        Serial.print(rssiDbm);
        Serial.print(" dBm | TX: ");
        Serial.print(txDurationMs, 2);
        Serial.print(" ms | Daya: ");
        Serial.print(finalPower, 2);
        Serial.println(" mW");
        httpSendSuccess++;
    } else {
        Serial.print("[HTTP] Failed with code: ");
        Serial.println(httpCode);
        Serial.println("       Response: " + http.getString());
        Serial.print("       Seq: ");
        Serial.print(packetSeq);
        Serial.print(" | RSSI: ");
        Serial.print(rssiDbm);
        Serial.print(" dBm | TX: ");
        Serial.print(txDurationMs, 2);
        Serial.print(" ms | Daya(estimasi): ");
        Serial.print(finalPower, 2);
        Serial.println(" mW");
        httpSendFail++;
    }

    http.end();
}
// ==================== MQTT SENDER ====================
void sendMQTT() {
    if (!mqttClient.connected()) {
        Serial.println("[MQTT] Not connected, skipping send");
        mqttSendFail++;
        return;
    }

    if (lastTemperature == 0.0 && lastHumidity == 0.0) {
        Serial.println("[MQTT] No sensor data available");
        return;
    }

    time_t now = time(nullptr);
    if (now < 1640995200L) {
        Serial.println("[MQTT] NTP time not synced, skipping send");
        mqttSendFail++;
        return;
    }

    if (DEVICE_ID <= 0) {
        Serial.println("[MQTT] Invalid DEVICE_ID, skipping send");
        mqttSendFail++;
        return;
    }

    if (isnan(lastTemperature) || isnan(lastHumidity)) {
        Serial.println("[MQTT] Invalid sensor data, skipping send");
        mqttSendFail++;
        return;
    }

    uint32_t packetSeq = ++mqttPacketSeq;
    int rssiDbm = WiFi.RSSI();
    float expectedTxMs = max(5.0f, (lastMqttTxDurationMs * 0.70f) + (fabsf((float) rssiDbm) * 0.18f));
    uint32_t payloadBytes = 0;
    String payload = buildProtocolPayload("MQTT", packetSeq, lastMqttPower, expectedTxMs, rssiDbm, (long) now, &payloadBytes);
    float predictedPower = estimateProtocolPower("MQTT", expectedTxMs, payloadBytes, rssiDbm, true);
    payload = buildProtocolPayload("MQTT", packetSeq, predictedPower, expectedTxMs, rssiDbm, (long) now, &payloadBytes);
    if (!payloadHasRequiredFields(payload)) {
        Serial.println("[MQTT] Payload invalid (missing required fields), skipping publish");
        mqttSendFail++;
        return;
    }

    Serial.print("[MQTT] Publishing to: ");
    Serial.println(MQTT_TOPIC);
    Serial.print("       Payload: ");
    Serial.println(payload);

    unsigned long txStart = millis();
    bool publishSuccess = mqttClient.publish(MQTT_TOPIC, payload.c_str());
    float txDurationMs = (float) (millis() - txStart);
    float finalPower = estimateProtocolPower("MQTT", txDurationMs, payloadBytes, rssiDbm, publishSuccess);

    lastMqttTxDurationMs = txDurationMs;
    lastMqttPower = finalPower;
    lastPower = finalPower;

    if (publishSuccess) {
        Serial.println("[MQTT] Published successfully");
        Serial.print("       Seq: ");
        Serial.print(packetSeq);
        Serial.print(" | RSSI: ");
        Serial.print(rssiDbm);
        Serial.print(" dBm | TX: ");
        Serial.print(txDurationMs, 2);
        Serial.print(" ms | Daya: ");
        Serial.print(finalPower, 2);
        Serial.println(" mW");
        mqttSendSuccess++;
    } else {
        Serial.println("[MQTT] Publish failed");
        Serial.print("       Seq: ");
        Serial.print(packetSeq);
        Serial.print(" | RSSI: ");
        Serial.print(rssiDbm);
        Serial.print(" dBm | TX: ");
        Serial.print(txDurationMs, 2);
        Serial.print(" ms | Daya(estimasi): ");
        Serial.print(finalPower, 2);
        Serial.println(" mW");
        mqttSendFail++;
    }
}
// ==================== MQTT CONNECTION ====================
void connectMQTT() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[MQTT] WiFi not connected, skipping connection");
        return;
    }
    
    Serial.print("[MQTT] Attempting to connect to: ");
    Serial.print(MQTT_SERVER);
    Serial.print(":");
    Serial.println(MQTT_PORT);
    
    String clientId = "ESP32-" + String(DEVICE_ID);
    
    if (mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASSWORD)) {
        Serial.println("[MQTT] ✓ Connected!");
        Serial.print("       Client ID: ");
        Serial.println(clientId);
        mqttConnected = true;
        
        // Subscribe to topics if needed
        // mqttClient.subscribe("iot/commands");
    } else {
        Serial.print("[MQTT] ✗ Connection failed with code: ");
        Serial.println(mqttClient.state());
        mqttConnected = false;
    }
}

// ==================== MQTT CALLBACK ====================
void mqttCallback(char* topic, byte* payload, unsigned int length) {
    Serial.print("[MQTT] Received message from topic: ");
    Serial.println(topic);
    
    // Parse JSON if needed
    StaticJsonDocument<256> jsonDoc;
    deserializeJson(jsonDoc, payload, length);
    
    // Handle commands here
    Serial.println("[MQTT] Message processed");
}

// ==================== POWER CALCULATION ====================
float calculatePowerConsumption() {
    int rssi = WiFi.status() == WL_CONNECTED ? WiFi.RSSI() : -120;
    return estimateProtocolPower("IDLE", 25.0f, 64, rssi, true);
}

float estimateProtocolPower(const char* protocol, float txDurationMs, size_t payloadBytes, int rssiDbm, bool success) {
    const float voltage = 3.30f;
    float clampedTxMs = max(1.0f, min(txDurationMs, 60000.0f));
    int clampedRssi = max(-120, min(rssiDbm, 0));

    float cpuCurrentMa = 0.11f * (float) getCpuFrequencyMhz();
    float wifiBaseCurrentMa = WiFi.status() == WL_CONNECTED ? 72.0f : 50.0f;
    float sensorCurrentMa = 2.0f;

    float signalPenalty = (float) abs(clampedRssi + 45) * 0.65f;
    float payloadCurrent = ((float) payloadBytes) * 0.20f;
    float txDurationCurrent = clampedTxMs * 0.18f;
    float protocolOverhead = 0.0f;
    float protocolReliabilityPenalty = 0.0f;

    if (strcmp(protocol, "HTTP") == 0) {
        protocolOverhead = 20.0f;
        float attemptCount = (float) (httpSendSuccess + httpSendFail);
        if (attemptCount > 0.0f) {
            protocolReliabilityPenalty = ((float) httpSendFail / attemptCount) * 30.0f;
        }
    } else if (strcmp(protocol, "MQTT") == 0) {
        protocolOverhead = 10.0f;
        float attemptCount = (float) (mqttSendSuccess + mqttSendFail);
        if (attemptCount > 0.0f) {
            protocolReliabilityPenalty = ((float) mqttSendFail / attemptCount) * 30.0f;
        }
    }

    float thermalCurrent = fabsf(lastTemperature - 25.0f) * 0.9f;
    float humidityCurrent = fabsf(lastHumidity - 55.0f) * 0.10f;
    float baselineNoiseCurrent = (((float) (esp_random() % 2001)) / 1000.0f) - 1.0f; // -1..1 mA
    float retryPenalty = success ? 0.0f : 24.0f;

    float totalCurrentMa = wifiBaseCurrentMa
        + sensorCurrentMa
        + cpuCurrentMa
        + signalPenalty
        + payloadCurrent
        + txDurationCurrent
        + protocolOverhead
        + protocolReliabilityPenalty
        + thermalCurrent
        + humidityCurrent
        + baselineNoiseCurrent
        + retryPenalty;
    float powerMw = voltage * totalCurrentMa;
    return max(0.0f, powerMw);
}

void fillProtocolPayload(StaticJsonDocument<640>& jsonDoc, const char* protocol, uint32_t packetSeq, float dayaMw, float txDurationMs, uint32_t payloadBytes, int rssiDbm, long timestampEsp) {
    jsonDoc["device_id"] = DEVICE_ID;
    jsonDoc["protokol"] = protocol;
    jsonDoc["packet_seq"] = packetSeq;
    jsonDoc["suhu"] = lastTemperature;
    jsonDoc["kelembapan"] = lastHumidity;
    jsonDoc["timestamp_esp"] = timestampEsp;
    jsonDoc["daya"] = roundf(dayaMw * 100.0f) / 100.0f;
    jsonDoc["rssi_dbm"] = rssiDbm;
    jsonDoc["tx_duration_ms"] = roundf(txDurationMs * 100.0f) / 100.0f;
    jsonDoc["payload_bytes"] = payloadBytes;
    jsonDoc["uptime_s"] = (uint32_t) (millis() / 1000UL);
    jsonDoc["free_heap_bytes"] = (uint32_t) ESP.getFreeHeap();
    jsonDoc["sensor_reads"] = sensorReadCount;
    jsonDoc["http_success_count"] = httpSendSuccess;
    jsonDoc["http_fail_count"] = httpSendFail;
    jsonDoc["mqtt_success_count"] = mqttSendSuccess;
    jsonDoc["mqtt_fail_count"] = mqttSendFail;
}

String buildProtocolPayload(const char* protocol, uint32_t packetSeq, float dayaMw, float txDurationMs, int rssiDbm, long timestampEsp, uint32_t* payloadBytesOut) {
    StaticJsonDocument<640> jsonDoc;
    fillProtocolPayload(jsonDoc, protocol, packetSeq, dayaMw, txDurationMs, 0, rssiDbm, timestampEsp);

    String payload;
    serializeJson(jsonDoc, payload);
    uint32_t payloadBytes = (uint32_t) payload.length();

    fillProtocolPayload(jsonDoc, protocol, packetSeq, dayaMw, txDurationMs, payloadBytes, rssiDbm, timestampEsp);
    payload = "";
    serializeJson(jsonDoc, payload);
    payloadBytes = (uint32_t) payload.length();
    if (payloadBytesOut != nullptr) {
        *payloadBytesOut = payloadBytes;
    }

    return payload;
}

bool payloadHasRequiredFields(const String& payload) {
    StaticJsonDocument<512> verifyDoc;
    DeserializationError error = deserializeJson(verifyDoc, payload);
    if (error) {
        Serial.print("[PAYLOAD] Invalid JSON: ");
        Serial.println(error.c_str());
        return false;
    }

    const char* requiredFields[] = {
        "device_id",
        "suhu",
        "kelembapan",
        "timestamp_esp",
        "daya",
        "packet_seq",
        "rssi_dbm",
        "tx_duration_ms",
        "payload_bytes",
        "uptime_s",
        "free_heap_bytes"
    };

    for (size_t i = 0; i < (sizeof(requiredFields) / sizeof(requiredFields[0])); i++) {
        if (!verifyDoc.containsKey(requiredFields[i])) {
            Serial.print("[PAYLOAD] Missing field: ");
            Serial.println(requiredFields[i]);
            return false;
        }
    }

    return true;
}
// ==================== STATUS PRINTING ====================
void printStatus() {
    Serial.println("\n==========================================");
    Serial.println("         SYSTEM STATUS REPORT");
    Serial.println("==========================================");

    Serial.print("WiFi: ");
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("Connected (" + WiFi.localIP().toString() + ")");
        Serial.print("RSSI: ");
        Serial.print(WiFi.RSSI());
        Serial.println(" dBm");
    } else {
        Serial.println("Disconnected");
    }

    Serial.print("MQTT: ");
    Serial.println(mqttClient.connected() ? "Connected" : "Disconnected");

    Serial.print("Last Temperature: ");
    Serial.print(lastTemperature);
    Serial.println(" C");

    Serial.print("Last Humidity: ");
    Serial.print(lastHumidity);
    Serial.println(" %");

    Serial.print("Last Power (Overall): ");
    Serial.print(lastPower, 2);
    Serial.println(" mW");

    Serial.print("Last HTTP Power: ");
    Serial.print(lastHttpPower, 2);
    Serial.print(" mW | Last MQTT Power: ");
    Serial.print(lastMqttPower, 2);
    Serial.println(" mW");

    Serial.print("Last HTTP TX: ");
    Serial.print(lastHttpTxDurationMs, 2);
    Serial.print(" ms | Last MQTT TX: ");
    Serial.print(lastMqttTxDurationMs, 2);
    Serial.println(" ms");

    float httpTotalAttempt = (float) (httpSendSuccess + httpSendFail);
    float mqttTotalAttempt = (float) (mqttSendSuccess + mqttSendFail);
    float httpReliability = httpTotalAttempt > 0 ? (httpSendSuccess / httpTotalAttempt) * 100.0f : 0.0f;
    float mqttReliability = mqttTotalAttempt > 0 ? (mqttSendSuccess / mqttTotalAttempt) * 100.0f : 0.0f;

    Serial.println("\n--- PROTOCOL STATISTICS ---");
    Serial.print("HTTP Seq: ");
    Serial.print(httpPacketSeq);
    Serial.print(" | Success: ");
    Serial.print(httpSendSuccess);
    Serial.print(" | Fail: ");
    Serial.print(httpSendFail);
    Serial.print(" | Reliability: ");
    Serial.print(httpReliability, 2);
    Serial.println(" %");

    Serial.print("MQTT Seq: ");
    Serial.print(mqttPacketSeq);
    Serial.print(" | Success: ");
    Serial.print(mqttSendSuccess);
    Serial.print(" | Fail: ");
    Serial.print(mqttSendFail);
    Serial.print(" | Reliability: ");
    Serial.print(mqttReliability, 2);
    Serial.println(" %");

    Serial.print("Sensor Reads: ");
    Serial.println(sensorReadCount);

    Serial.print("Uptime: ");
    Serial.print(millis() / 1000);
    Serial.println(" seconds");

    Serial.print("Free Memory: ");
    Serial.print(ESP.getFreeHeap());
    Serial.println(" bytes");

    Serial.println("==========================================\n");
}
// ==================== TIME SYNC ====================
void updateTime() {
    Serial.print("[TIME] Syncing with NTP server...");
    
    // Configure time with NTP Server
    configTime(0, 0, "pool.ntp.org", "time.nist.gov");
    
    time_t now = time(nullptr);
    int attempts = 0;
    while (now < 24 * 3600 * 2 && attempts < 20) {
        delay(500);
        Serial.print(".");
        now = time(nullptr);
        attempts++;
    }
    
    Serial.println("");
    Serial.print("[TIME] ✓ Time synchronized: ");
    Serial.println(ctime(&now));
}




