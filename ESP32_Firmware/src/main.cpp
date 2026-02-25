
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
        Serial.println("[HTTP] ✗ WiFi not connected, skipping HTTP send");
        httpSendFail++;
        return;
    }
    
    if (lastTemperature == 0.0 && lastHumidity == 0.0) {
        Serial.println("[HTTP] ✗ No sensor data available");
        return;
    }
    
    HTTPClient http;
    String url = String(HTTP_SERVER) + String(HTTP_ENDPOINT);
    
    // Create JSON payload
    StaticJsonDocument<256> jsonDoc;
    jsonDoc["device_id"] = DEVICE_ID;
    jsonDoc["suhu"] = lastTemperature;
    jsonDoc["timestamp_esp"] = (long)time(nullptr);
    jsonDoc["daya"] = lastPower;
    
    String payload;
    serializeJson(jsonDoc, payload);
    
    Serial.print("[HTTP] Sending to: ");
    Serial.println(url);
    Serial.print("       Payload: ");
    Serial.println(payload);
    
    // Setup HTTP client
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.setConnectTimeout(5000);
    
    // Send POST request
    int httpCode = http.POST(payload);
    
    if (httpCode >= 200 && httpCode < 300) {
        Serial.print("[HTTP] ✓ Success (");
        Serial.print(httpCode);
        Serial.println(")");
        Serial.println("       Response: " + http.getString());
        httpSendSuccess++;
    } else {
        Serial.print("[HTTP] ✗ Failed with code: ");
        Serial.println(httpCode);
        Serial.println("       Response: " + http.getString());
        httpSendFail++;
    }
    
    http.end();
}

// ==================== MQTT SENDER ====================
void sendMQTT() {
    if (!mqttClient.connected()) {
        Serial.println("[MQTT] ✗ Not connected, skipping send");
        mqttSendFail++;
        return;
    }
    if (lastTemperature == 0.0 && lastHumidity == 0.0) {
        Serial.println("[MQTT] ✗ No sensor data available");
        return;
    }
    // Ensure NTP time is synced
    time_t now = time(nullptr);
    if (now < 1640995200L) { // 2022-01-01 as sanity check
        Serial.println("[MQTT] ✗ NTP time not synced, skipping send");
        mqttSendFail++;
        return;
    }
    // Validate all fields before sending
    if (DEVICE_ID <= 0) {
        Serial.println("[MQTT] ✗ Invalid DEVICE_ID, skipping send");
        mqttSendFail++;
        return;
    }
    if (isnan(lastTemperature) || isnan(lastPower)) {
        Serial.println("[MQTT] ✗ Invalid sensor/power data, skipping send");
        mqttSendFail++;
        return;
    }
    // Create JSON payload
    StaticJsonDocument<256> jsonDoc;
    jsonDoc["device_id"] = DEVICE_ID;
    jsonDoc["suhu"] = lastTemperature;
    jsonDoc["timestamp_esp"] = (long)now;
    jsonDoc["daya"] = lastPower;
    String payload;
    serializeJson(jsonDoc, payload);
    // Debug: print all fields
    Serial.print("[MQTT] Publishing to: ");
    Serial.println(MQTT_TOPIC);
    Serial.print("       Payload: ");
    Serial.println(payload);
    if (mqttClient.publish(MQTT_TOPIC, payload.c_str())) {
        Serial.println("[MQTT] ✓ Published successfully");
        mqttSendSuccess++;
    } else {
        Serial.println("[MQTT] ✗ Publish failed");
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
    // Simplified power calculation based on WiFi RSSI and operations
    // Real implementation would use actual power meter
    
    float basePower = 80.0;  // Base power consumption in mW (WiFi + MCU)
    
    // Add power based on WiFi signal strength
    if (WiFi.status() == WL_CONNECTED) {
        int rssi = WiFi.RSSI();
        // RSSI ranges from -120 (worst) to -30 (best)
        // Convert to additional power (higher RSSI = lower additional power)
        float rssiPower = max(0.0f, (-120 - rssi) * 0.5f);
        basePower += rssiPower;
    } else {
        basePower += 20.0;  // Extra power when searching for network
    }
    
    // Add DHT11 reading power
    basePower += 1.0;
    
    return basePower;
}

// ==================== STATUS PRINTING ====================
void printStatus() {
    Serial.println("\n==========================================");
    Serial.println("         SYSTEM STATUS REPORT");
    Serial.println("==========================================");
    Serial.print("WiFi: ");
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("✓ Connected (" + WiFi.localIP().toString() + ")");
    } else {
        Serial.println("✗ Disconnected");
    }
    
    Serial.print("MQTT: ");
    Serial.println(mqttClient.connected() ? "✓ Connected" : "✗ Disconnected");
    
    Serial.print("Last Temperature: ");
    Serial.print(lastTemperature);
    Serial.println("°C");
    
    Serial.print("Last Humidity: ");
    Serial.print(lastHumidity);
    Serial.println("%");
    
    Serial.print("Last Power: ");
    Serial.print(lastPower);
    Serial.println(" mW");
    
    Serial.println("\n--- STATISTICS ---");
    Serial.print("Sensor Reads: ");
    Serial.println(sensorReadCount);
    
    Serial.print("HTTP Success: ");
    Serial.print(httpSendSuccess);
    Serial.print(" | Failures: ");
    Serial.println(httpSendFail);
    
    Serial.print("MQTT Success: ");
    Serial.print(mqttSendSuccess);
    Serial.print(" | Failures: ");
    Serial.println(mqttSendFail);
    
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
