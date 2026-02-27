
#include <WiFi.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <DHTesp.h>

#ifndef ESP_HTTP_INGEST_KEY
#define ESP_HTTP_INGEST_KEY ""
#endif

#ifndef ESP_SENSOR_INTERVAL_MS
#define ESP_SENSOR_INTERVAL_MS 8000UL
#endif

#ifndef ESP_SENSOR_SNAPSHOT_MAX_AGE_MS
#define ESP_SENSOR_SNAPSHOT_MAX_AGE_MS 20000UL
#endif

#ifndef ESP_SENSOR_FALLBACK_MAX_AGE_MS
#define ESP_SENSOR_FALLBACK_MAX_AGE_MS 180000UL
#endif

#ifndef ESP_SENSOR_EMERGENCY_MAX_AGE_MS
#define ESP_SENSOR_EMERGENCY_MAX_AGE_MS 900000UL
#endif

#ifndef ESP_SENSOR_RECOVERY_INTERVAL_MS
#define ESP_SENSOR_RECOVERY_INTERVAL_MS 3200UL
#endif

#ifndef ESP_HTTP_POST_RETRY_MAX
#define ESP_HTTP_POST_RETRY_MAX 2
#endif

#ifndef ESP_HTTP_POST_RETRY_BACKOFF_MS
#define ESP_HTTP_POST_RETRY_BACKOFF_MS 250
#endif

#ifndef ESP_HTTP_READ_TIMEOUT_MS
#define ESP_HTTP_READ_TIMEOUT_MS 3500
#endif


// ==================== CONFIGURATION ====================
// WiFi Settings
const char* WIFI_SSID = "Free";
const char* WIFI_PASSWORD = "gratiskok";

// Server Settings
#define SERVER_HOST "192.168.0.104"  // Windows host LAN IP (update if DHCP IP changes)
const char* HTTP_SERVER = "http://" SERVER_HOST;  // Apache front, proxied to PHP 8.4 server
const char* HTTP_ENDPOINT = "/esptest/public/api/http-data";
const char* MQTT_SERVER = SERVER_HOST;  // Same subnet as ESP32
const int MQTT_PORT = 1883;
const char* MQTT_TOPIC = "iot/esp32/suhu";
const char* MQTT_USER = "esp32";
const char* MQTT_PASSWORD = "esp32";
const char* HTTP_INGEST_KEY = ESP_HTTP_INGEST_KEY;


// Device Settings
#define DHTPIN 4
DHTesp dht;
const int DEVICE_ID = 1;
const DHTesp::DHT_MODEL_t DHT_MODEL_PREFERRED = DHTesp::DHT11;

// Timing Settings
const unsigned long INTERVAL_SENSOR = ESP_SENSOR_INTERVAL_MS;    // Background sensor read cadence.
const unsigned long INTERVAL_HTTP = 10000;     // Send HTTP every 10 seconds
const unsigned long INTERVAL_MQTT = 10000;     // Send MQTT every 10 seconds
const unsigned long WIFI_TIMEOUT = 10000;      // WiFi connection timeout
const unsigned long INTERVAL_NTP_RETRY = 30000; // Retry NTP every 30 seconds when unsynced
const bool ENABLE_BACKGROUND_SENSOR_READ = false; // Disable extra polling to reduce DHT timing collisions.
const bool ALLOW_SENSOR_STATUS_FALLBACK = true; // Compatibility mode: accept plausible values even if DHT status is noisy.
const bool ALLOW_SENSOR_EMERGENCY_FALLBACK = true; // Keep telemetry running when DHT is temporarily unstable.
const unsigned long MAX_SENSOR_SNAPSHOT_AGE_MS = ESP_SENSOR_SNAPSHOT_MAX_AGE_MS; // Fresh snapshot budget for protocol send.
const unsigned long MAX_SENSOR_FALLBACK_AGE_MS = ESP_SENSOR_FALLBACK_MAX_AGE_MS; // Standard fallback budget when direct read fails.
const unsigned long MAX_SENSOR_EMERGENCY_AGE_MS = ESP_SENSOR_EMERGENCY_MAX_AGE_MS; // Emergency fallback budget for severe DHT instability.
const unsigned long INTERVAL_SENSOR_RECOVERY = ESP_SENSOR_RECOVERY_INTERVAL_MS; // Faster controlled poll when DHT is unstable.
const unsigned long SENSOR_OUTLIER_GUARD_WINDOW_MS = 180000UL;
const float SENSOR_MAX_TEMP_JUMP_C = 4.5f;
const float SENSOR_MAX_HUMIDITY_JUMP = 18.0f;
unsigned long dhtMinReadIntervalMs = 2200;
constexpr size_t PAYLOAD_JSON_DOC_CAPACITY = 1024;
constexpr size_t PAYLOAD_VERIFY_DOC_CAPACITY = 1536;
const uint8_t DHT_READ_RETRY_MAX = 4;
const uint8_t DHT_FAILS_BEFORE_REINIT = 5;
const uint8_t DHT_BOOTSTRAP_TRIES = 3;
const uint8_t HTTP_POST_RETRY_MAX = ESP_HTTP_POST_RETRY_MAX;
const uint16_t HTTP_POST_RETRY_BACKOFF_MS = ESP_HTTP_POST_RETRY_BACKOFF_MS;
const uint16_t HTTP_READ_TIMEOUT_MS = ESP_HTTP_READ_TIMEOUT_MS;

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
unsigned long lastSensorPollMs = 0;
long lastHTTPSend = 0;
long lastMQTTSend = 0;
unsigned long lastDhtCaptureMs = 0;
unsigned long lastDhtAttemptMs = 0;
bool mqttConnected = false;
bool httpConnected = false;
bool timeSynced = false;
uint8_t currentSensorFallbackLevel = 0;
uint32_t lastDeliveredSensorReadSeq = 0;

// Statistics
unsigned long sensorReadCount = 0;
unsigned long httpSendSuccess = 0;
unsigned long httpSendFail = 0;
unsigned long mqttSendSuccess = 0;
unsigned long mqttSendFail = 0;
unsigned long lastMQTTAttempt = 0;  // Prevent MQTT connection spam
unsigned long lastNtpAttempt = 0;
unsigned long sensorFallbackStandardCount = 0;
unsigned long sensorFallbackEmergencyCount = 0;
unsigned long sensorFallbackRejectedCount = 0;
uint8_t dhtConsecutiveFail = 0;
uint8_t dhtReinitCounter = 0;
DHTesp::DHT_MODEL_t activeDhtModel = DHTesp::DHT11;
bool dhtModelFallbackAttempted = false;

// ==================== FUNCTION DECLARATIONS ====================
void setupWiFi();
void readSensor();
void sendHTTP();
void sendMQTT();
void connectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int length);
float calculatePowerConsumption();
float estimateProtocolPower(const char* protocol, float txDurationMs, size_t payloadBytes, int rssiDbm, bool success);
void fillProtocolPayload(
    StaticJsonDocument<PAYLOAD_JSON_DOC_CAPACITY>& jsonDoc,
    const char* protocol,
    uint32_t packetSeq,
    float dayaMw,
    float txDurationMs,
    uint32_t payloadBytes,
    int rssiDbm,
    long timestampEsp,
    uint32_t sensorAgeMs,
    uint32_t sensorReadSeq
);
String buildProtocolPayload(
    const char* protocol,
    uint32_t packetSeq,
    float dayaMw,
    float txDurationMs,
    int rssiDbm,
    long timestampEsp,
    uint32_t sensorAgeMs,
    uint32_t sensorReadSeq,
    uint32_t* payloadBytesOut = nullptr
);
bool payloadHasRequiredFields(const String& payload);
bool captureSensorSnapshot(const char* sourceTag, bool printSuccessLog);
bool hasRecentSensorSnapshot(uint32_t* sensorAgeMsOut = nullptr, uint32_t maxAgeMs = MAX_SENSOR_SNAPSHOT_AGE_MS);
bool hasAnySensorSnapshot(uint32_t* sensorAgeMsOut = nullptr);
bool captureDedicatedSensorSnapshotForSend(const char* sourceTag, uint32_t* sensorAgeMsOut, uint32_t* sensorReadSeqOut);
void printStatus();
bool updateTime(bool verbose = true);
bool isServerHostSelfTarget();
void printServerTargetConfig();
void refreshDhtSamplingInterval();
const char* dhtModelToString(DHTesp::DHT_MODEL_t model);
void reinitializeDhtSensor(const char* reason);
void seedPacketSequenceFromTime();
void cooperativeDelay(unsigned long durationMs);

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
    pinMode(DHTPIN, INPUT_PULLUP);
    delay(30);
    activeDhtModel = DHT_MODEL_PREFERRED;
    dht.setup(DHTPIN, activeDhtModel);
    refreshDhtSamplingInterval();
    Serial.print("[INIT] DHT model: ");
    Serial.println(dhtModelToString(activeDhtModel));
    cooperativeDelay(dhtMinReadIntervalMs);  // Let sensor stabilize after boot

    bool bootstrapOk = false;
    for (uint8_t bootstrapTry = 1; bootstrapTry <= DHT_BOOTSTRAP_TRIES; bootstrapTry++) {
        if (captureSensorSnapshot("BOOT", false)) {
            bootstrapOk = true;
            break;
        }
        Serial.print("[INIT] DHT bootstrap attempt ");
        Serial.print(bootstrapTry);
        Serial.println(" failed.");
        cooperativeDelay(dhtMinReadIntervalMs);
    }
    if (bootstrapOk) {
        Serial.print("[INIT] DHT bootstrap OK: T=");
        Serial.print(lastTemperature, 2);
        Serial.print(" C, H=");
        Serial.print(lastHumidity, 2);
        Serial.println(" %");
    } else {
        Serial.println("[INIT] DHT bootstrap failed, runtime retry/reinit will continue.");
    }
    
    // Connect to WiFi
    setupWiFi();
    printServerTargetConfig();
    
    // Setup MQTT
    mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
    mqttClient.setCallback(mqttCallback);
    mqttClient.setKeepAlive(45);
    mqttClient.setSocketTimeout(4);
    
    // Synchronize time with NTP
    updateTime();
    seedPacketSequenceFromTime();

    // Stagger protocol send schedule to avoid DHT read collisions.
    // HTTP will fire first (after ~10s), MQTT follows ~5s later and then alternates.
    lastSensorPollMs = millis();
    lastHTTPSend = (long) millis();
    lastMQTTSend = (long) (millis() - (INTERVAL_MQTT / 2UL));
    
    Serial.println("[SETUP] Initialization complete!");
    Serial.println("==========================================\n");
}

// ==================== MAIN LOOP ====================
void loop() {
    httpConnected = (WiFi.status() == WL_CONNECTED);

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

    if (!timeSynced && (millis() - lastNtpAttempt >= INTERVAL_NTP_RETRY)) {
        updateTime(true);
    }
    
    // Background sensor poll keeps latest valid snapshot ready for protocol sends.
    // Use dedicated poll timestamp so failed reads don't trigger read hammering.
    unsigned long sensorPollInterval = dhtConsecutiveFail > 0
        ? max(INTERVAL_SENSOR_RECOVERY, dhtMinReadIntervalMs + 600UL)
        : INTERVAL_SENSOR;
    if (ENABLE_BACKGROUND_SENSOR_READ && (millis() - lastSensorPollMs >= sensorPollInterval)) {
        lastSensorPollMs = millis();
        readSensor();
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
    
    cooperativeDelay(100);  // Small delay to keep WiFi/MQTT task responsive.
}

// ==================== WiFi SETUP ====================
void setupWiFi() {
    Serial.println("\n[WiFi] Connecting to: " + String(WIFI_SSID));
    
    WiFi.mode(WIFI_STA);
    WiFi.setSleep(false);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    unsigned long startAttemptTime = millis();
    int attempts = 0;
    
    while (WiFi.status() != WL_CONNECTED && millis() - startAttemptTime < WIFI_TIMEOUT) {
        cooperativeDelay(500);
        Serial.print(".");
        attempts++;
    }
    
    Serial.println("");
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("[WiFi] âœ“ Connected!");
        Serial.println("       IP: " + WiFi.localIP().toString());
        Serial.println("       RSSI: " + String(WiFi.RSSI()) + " dBm");
        httpConnected = true;
    } else {
        Serial.println("[WiFi] âœ— Failed to connect after " + String(attempts) + " attempts");
        Serial.println("[WiFi] Check SSID and password!");
        httpConnected = false;
    }
}

bool isServerHostSelfTarget() {
    if (WiFi.status() != WL_CONNECTED) {
        return false;
    }

    IPAddress serverIp;
    if (!serverIp.fromString(SERVER_HOST)) {
        return false;
    }

    return serverIp == WiFi.localIP();
}

void printServerTargetConfig() {
    Serial.println("[CONFIG] HTTP target: " + String(HTTP_SERVER) + String(HTTP_ENDPOINT));
    Serial.println("[CONFIG] MQTT target: " + String(MQTT_SERVER) + ":" + String(MQTT_PORT));

    if (isServerHostSelfTarget()) {
        Serial.println("[CONFIG] ERROR: SERVER_HOST points to ESP32 IP itself.");
        Serial.println("[CONFIG] Set SERVER_HOST to the PC/Laravel host IP (example: 192.168.0.104).");
    }
}

void cooperativeDelay(unsigned long durationMs) {
    unsigned long startedAt = millis();
    while (millis() - startedAt < durationMs) {
        if (mqttClient.connected()) {
            mqttClient.loop();
        }

        unsigned long elapsed = millis() - startedAt;
        unsigned long remaining = durationMs > elapsed ? (durationMs - elapsed) : 0UL;
        unsigned long slice = min(remaining, 25UL);
        if (slice == 0UL) {
            break;
        }

        delay(slice);
        yield();
    }
}

// ==================== SENSOR READING ====================
bool captureSensorSnapshot(const char* sourceTag, bool printSuccessLog) {
    unsigned long nowMs = millis();
    if (lastDhtAttemptMs > 0 && nowMs > lastDhtAttemptMs) {
        unsigned long elapsed = nowMs - lastDhtAttemptMs;
        if (elapsed < dhtMinReadIntervalMs) {
            cooperativeDelay(dhtMinReadIntervalMs - elapsed);
        }
    }

    TempAndHumidity sample;
    DHTesp::DHT_ERROR_t dhtStatus = DHTesp::ERROR_TIMEOUT;
    bool sampleValid = false;
    bool acceptedWithStatusFallback = false;
    bool rejectedAsOutlier = false;

    for (uint8_t attempt = 1; attempt <= DHT_READ_RETRY_MAX; attempt++) {
        lastDhtAttemptMs = millis();
        delay(1);  // Keep scheduler responsive around timing-sensitive sensor read.
        sample = dht.getTempAndHumidity();
        dhtStatus = dht.getStatus();
        rejectedAsOutlier = false;

        float humidityAttempt = sample.humidity;
        float temperatureAttempt = sample.temperature;

        bool plausibleRange = !isnan(humidityAttempt)
            && !isnan(temperatureAttempt)
            && humidityAttempt >= 0.0f
            && humidityAttempt <= 100.0f
            && temperatureAttempt > -40.0f
            && temperatureAttempt < 85.0f;
        bool outlierJump = false;
        if (plausibleRange && sensorReadCount > 0 && lastDhtCaptureMs > 0) {
            unsigned long captureAgeMs = millis() >= lastDhtCaptureMs
                ? (millis() - lastDhtCaptureMs)
                : 0UL;
            if (captureAgeMs <= SENSOR_OUTLIER_GUARD_WINDOW_MS) {
                outlierJump = fabsf(temperatureAttempt - lastTemperature) > SENSOR_MAX_TEMP_JUMP_C
                    || fabsf(humidityAttempt - lastHumidity) > SENSOR_MAX_HUMIDITY_JUMP;
            }
        }

        if (!plausibleRange) {
            sampleValid = false;
        } else if (outlierJump) {
            sampleValid = false;
            rejectedAsOutlier = true;
        } else if (dhtStatus == DHTesp::ERROR_NONE) {
            sampleValid = true;
        } else if (ALLOW_SENSOR_STATUS_FALLBACK) {
            sampleValid = true;
            acceptedWithStatusFallback = true;
        } else {
            sampleValid = false;
        }

        if (sampleValid) {
            break;
        }

        if (attempt < DHT_READ_RETRY_MAX) {
            unsigned long retryBackoff = dhtMinReadIntervalMs + ((unsigned long) dhtConsecutiveFail * 120UL);
            cooperativeDelay(min(retryBackoff, dhtMinReadIntervalMs + 900UL));
        }
    }

    if (!sampleValid) {
        dhtConsecutiveFail++;
        Serial.print("[");
        Serial.print(sourceTag != nullptr ? sourceTag : "SENSOR");
        Serial.print("] Sensor read failed (");
        Serial.print(dht.getStatusString());
        if (rejectedAsOutlier) {
            Serial.print(", OUTLIER");
        }
        Serial.print("), streak=");
        Serial.println(dhtConsecutiveFail);
        if (dhtConsecutiveFail >= DHT_FAILS_BEFORE_REINIT) {
            reinitializeDhtSensor("consecutive read/checksum failures");
            dhtConsecutiveFail = 0;
        }
        return false;
    }

    dhtConsecutiveFail = 0;
    float humidity = sample.humidity;
    float temperature = sample.temperature;

    lastTemperature = temperature;
    lastHumidity = humidity;
    sensorReadCount++;
    lastSensorRead = (long) millis();
    lastDhtCaptureMs = (unsigned long) lastSensorRead;

    if (printSuccessLog) {
        lastPower = calculatePowerConsumption();
        Serial.print("[SENSOR] ✓ T: ");
        Serial.print(temperature, 2);
        Serial.print("°C | H: ");
        Serial.print(humidity, 2);
        Serial.print("% | P: ");
        Serial.print(lastPower, 2);
        Serial.println(" mW");
    }

    if (acceptedWithStatusFallback) {
        Serial.print("[");
        Serial.print(sourceTag != nullptr ? sourceTag : "SENSOR");
        Serial.print("] Sensor status=");
        Serial.print(dht.getStatusString());
        Serial.println(", value accepted by compatibility fallback.");
    }

    return true;
}

bool hasRecentSensorSnapshot(uint32_t* sensorAgeMsOut, uint32_t maxAgeMs) {
    if (sensorReadCount == 0 || lastSensorRead <= 0) {
        return false;
    }

    unsigned long nowMs = millis();
    unsigned long lastReadMs = (unsigned long) lastSensorRead;
    if (nowMs < lastReadMs) {
        return false;
    }

    uint32_t ageMs = (uint32_t) (nowMs - lastReadMs);
    if (ageMs > maxAgeMs) {
        return false;
    }

    if (sensorAgeMsOut != nullptr) {
        *sensorAgeMsOut = ageMs;
    }

    return true;
}

bool hasAnySensorSnapshot(uint32_t* sensorAgeMsOut) {
    if (sensorReadCount == 0 || lastSensorRead <= 0) {
        return false;
    }

    unsigned long nowMs = millis();
    unsigned long lastReadMs = (unsigned long) lastSensorRead;
    if (nowMs < lastReadMs) {
        return false;
    }

    if (sensorAgeMsOut != nullptr) {
        *sensorAgeMsOut = (uint32_t) (nowMs - lastReadMs);
    }

    return true;
}

bool captureDedicatedSensorSnapshotForSend(const char* sourceTag, uint32_t* sensorAgeMsOut, uint32_t* sensorReadSeqOut) {
    uint32_t sensorAgeMs = 0U;
    uint32_t standardFallbackBudgetMs = MAX_SENSOR_FALLBACK_AGE_MS;
    uint32_t emergencyFallbackBudgetMs = MAX_SENSOR_EMERGENCY_AGE_MS;
    if (dhtConsecutiveFail >= 3) {
        standardFallbackBudgetMs = min(standardFallbackBudgetMs, static_cast<uint32_t>(60000U));
    }
    if (dhtConsecutiveFail >= (DHT_FAILS_BEFORE_REINIT - 1)) {
        emergencyFallbackBudgetMs = min(emergencyFallbackBudgetMs, static_cast<uint32_t>(180000U));
    }

    currentSensorFallbackLevel = 0;
    // Force a dedicated sensor read for each protocol send so HTTP and MQTT
    // use independent captures whenever sensor read succeeds.
    if (captureSensorSnapshot(sourceTag, false) && hasRecentSensorSnapshot(&sensorAgeMs, MAX_SENSOR_SNAPSHOT_AGE_MS)) {
        if (sensorAgeMsOut != nullptr) {
            *sensorAgeMsOut = sensorAgeMs;
        }
        if (sensorReadSeqOut != nullptr) {
            *sensorReadSeqOut = (uint32_t) sensorReadCount;
        }
        return true;
    }

    if (hasRecentSensorSnapshot(&sensorAgeMs, standardFallbackBudgetMs)) {
        currentSensorFallbackLevel = 1;
        sensorFallbackStandardCount++;
        Serial.print("[");
        Serial.print(sourceTag != nullptr ? sourceTag : "SENSOR");
        Serial.print("] Fresh read unavailable, using fallback snapshot (age=");
        Serial.print(sensorAgeMs);
        Serial.print(" ms, budget=");
        Serial.print(standardFallbackBudgetMs);
        Serial.println(" ms)");
        if (sensorAgeMsOut != nullptr) {
            *sensorAgeMsOut = sensorAgeMs;
        }
        if (sensorReadSeqOut != nullptr) {
            *sensorReadSeqOut = (uint32_t) sensorReadCount;
        }
        return true;
    }

    if (hasAnySensorSnapshot(&sensorAgeMs)) {
        if (ALLOW_SENSOR_EMERGENCY_FALLBACK && sensorAgeMs <= emergencyFallbackBudgetMs) {
            currentSensorFallbackLevel = 2;
            sensorFallbackEmergencyCount++;
            Serial.print("[");
            Serial.print(sourceTag != nullptr ? sourceTag : "SENSOR");
            Serial.print("] DHT unstable, using emergency snapshot (age=");
            Serial.print(sensorAgeMs);
            Serial.print(" ms, budget=");
            Serial.print(emergencyFallbackBudgetMs);
            Serial.println(" ms)");
            if (sensorAgeMsOut != nullptr) {
                *sensorAgeMsOut = sensorAgeMs;
            }
            if (sensorReadSeqOut != nullptr) {
                *sensorReadSeqOut = (uint32_t) sensorReadCount;
            }
            return true;
        }

        Serial.print("[");
        Serial.print(sourceTag != nullptr ? sourceTag : "SENSOR");
        Serial.print("] Snapshot age ");
        Serial.print(sensorAgeMs);
        Serial.println(" ms exceeds fallback budget, skipping send.");
        sensorFallbackRejectedCount++;
    }

    return false;
}

void readSensor() {
    if (!captureSensorSnapshot("SENSOR", true)) {
        Serial.println("[SENSOR] ✗ Failed to read from DHT11.");
    }
}

// ==================== HTTP SENDER ====================
void sendHTTP() {
    httpConnected = (WiFi.status() == WL_CONNECTED);
    if (!httpConnected) {
        Serial.println("[HTTP] WiFi not connected, skipping HTTP send");
        httpSendFail++;
        return;
    }

    if (!timeSynced) {
        Serial.println("[HTTP] Waiting NTP sync, skipping send");
        httpSendFail++;
        return;
    }

    uint32_t sensorAgeMs = 0U;
    uint32_t sensorReadSeq = (uint32_t) sensorReadCount;
    if (!captureDedicatedSensorSnapshotForSend("HTTP", &sensorAgeMs, &sensorReadSeq)) {
        Serial.println("[HTTP] Sensor snapshot invalid, skipping send");
        httpSendFail++;
        return;
    }
    if (currentSensorFallbackLevel > 0 && sensorReadSeq <= lastDeliveredSensorReadSeq) {
        Serial.print("[HTTP] Duplicate fallback sensor_read_seq=");
        Serial.print(sensorReadSeq);
        Serial.println(" already delivered, skipping send");
        httpSendFail++;
        return;
    }

    long timestampEsp = (long) time(nullptr);
    if (timestampEsp < 1640995200L) {
        Serial.println("[HTTP] NTP time not synced, skipping send");
        httpSendFail++;
        return;
    }

    if (isServerHostSelfTarget()) {
        Serial.println("[HTTP] ERROR: SERVER_HOST equals ESP32 IP, request aborted.");
        httpSendFail++;
        return;
    }

    HTTPClient http;
    String url = String(HTTP_SERVER) + String(HTTP_ENDPOINT);

    if (httpPacketSeq == 0 && timeSynced) {
        seedPacketSequenceFromTime();
    }
    uint32_t packetSeq = ++httpPacketSeq;
    int rssiDbm = WiFi.RSSI();
    float expectedTxMs = max(20.0f, (lastHttpTxDurationMs * 0.65f) + (fabsf((float) rssiDbm) * 0.35f));
    uint32_t payloadBytes = 0;
    String payload = buildProtocolPayload("HTTP", packetSeq, lastHttpPower, expectedTxMs, rssiDbm, timestampEsp, sensorAgeMs, sensorReadSeq, &payloadBytes);
    float predictedPower = estimateProtocolPower("HTTP", expectedTxMs, payloadBytes, rssiDbm, true);
    payload = buildProtocolPayload("HTTP", packetSeq, predictedPower, expectedTxMs, rssiDbm, timestampEsp, sensorAgeMs, sensorReadSeq, &payloadBytes);
    if (!payloadHasRequiredFields(payload)) {
        Serial.println("[HTTP] Payload invalid (missing required fields), skipping send");
        httpSendFail++;
        return;
    }

    Serial.print("[HTTP] Sending to: ");
    Serial.println(url);
    Serial.print("       Payload: ");
    Serial.println(payload);

    auto beginHttpRequest = [&http, &url]() {
        http.begin(url);
        http.addHeader("Content-Type", "application/json");
        if (HTTP_INGEST_KEY != nullptr && strlen(HTTP_INGEST_KEY) > 0) {
            http.addHeader("X-Ingest-Key", HTTP_INGEST_KEY);
        }
        http.setConnectTimeout(5000);
        http.setTimeout(HTTP_READ_TIMEOUT_MS);
    };

    int httpCode = -1;
    bool success = false;
    uint8_t attemptsUsed = 0;
    unsigned long txStart = millis();

    beginHttpRequest();
    for (uint8_t attempt = 1; attempt <= HTTP_POST_RETRY_MAX; attempt++) {
        attemptsUsed = attempt;
        httpCode = http.POST(payload);
        success = (httpCode >= 200 && httpCode < 300);
        if (success) {
            break;
        }

        if (attempt < HTTP_POST_RETRY_MAX) {
            Serial.print("[HTTP] Attempt ");
            Serial.print(attempt);
            Serial.print(" failed (code ");
            Serial.print(httpCode);
            Serial.println("), retrying...");
            http.end();
            cooperativeDelay(HTTP_POST_RETRY_BACKOFF_MS * attempt);
            beginHttpRequest();
        }
    }

    float txDurationMs = (float) (millis() - txStart);

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
        Serial.print(" ms | Try: ");
        Serial.print(attemptsUsed);
        Serial.print(" | Daya: ");
        Serial.print(finalPower, 2);
        Serial.println(" mW");
        httpSendSuccess++;
        lastDeliveredSensorReadSeq = max(lastDeliveredSensorReadSeq, sensorReadSeq);
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
        Serial.print(" ms | Try: ");
        Serial.print(attemptsUsed);
        Serial.print(" | Daya(estimasi): ");
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

    if (!timeSynced) {
        Serial.println("[MQTT] Waiting NTP sync, skipping send");
        mqttSendFail++;
        return;
    }

    uint32_t sensorAgeMs = 0U;
    uint32_t sensorReadSeq = (uint32_t) sensorReadCount;
    if (!captureDedicatedSensorSnapshotForSend("MQTT", &sensorAgeMs, &sensorReadSeq)) {
        Serial.println("[MQTT] Sensor snapshot invalid, skipping send");
        mqttSendFail++;
        return;
    }
    if (currentSensorFallbackLevel > 0 && sensorReadSeq <= lastDeliveredSensorReadSeq) {
        Serial.print("[MQTT] Duplicate fallback sensor_read_seq=");
        Serial.print(sensorReadSeq);
        Serial.println(" already delivered, skipping send");
        mqttSendFail++;
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

    if (mqttPacketSeq == 0 && timeSynced) {
        seedPacketSequenceFromTime();
    }
    uint32_t packetSeq = ++mqttPacketSeq;
    int rssiDbm = WiFi.RSSI();
    float expectedTxMs = max(5.0f, (lastMqttTxDurationMs * 0.70f) + (fabsf((float) rssiDbm) * 0.18f));
    uint32_t payloadBytes = 0;
    String payload = buildProtocolPayload("MQTT", packetSeq, lastMqttPower, expectedTxMs, rssiDbm, (long) now, sensorAgeMs, sensorReadSeq, &payloadBytes);
    float predictedPower = estimateProtocolPower("MQTT", expectedTxMs, payloadBytes, rssiDbm, true);
    payload = buildProtocolPayload("MQTT", packetSeq, predictedPower, expectedTxMs, rssiDbm, (long) now, sensorAgeMs, sensorReadSeq, &payloadBytes);
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
        lastDeliveredSensorReadSeq = max(lastDeliveredSensorReadSeq, sensorReadSeq);
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

    if (isServerHostSelfTarget()) {
        Serial.println("[MQTT] ERROR: SERVER_HOST equals ESP32 IP, connection aborted.");
        return;
    }
    
    Serial.print("[MQTT] Attempting to connect to: ");
    Serial.print(MQTT_SERVER);
    Serial.print(":");
    Serial.println(MQTT_PORT);
    
    String clientId = "ESP32-" + String(DEVICE_ID);
    
    if (mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASSWORD)) {
        Serial.println("[MQTT] âœ“ Connected!");
        Serial.print("       Client ID: ");
        Serial.println(clientId);
        mqttConnected = true;
        
        // Subscribe to topics if needed
        // mqttClient.subscribe("iot/commands");
    } else {
        Serial.print("[MQTT] âœ— Connection failed with code: ");
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
        + retryPenalty;
    float powerMw = voltage * totalCurrentMa;
    return max(0.0f, powerMw);
}

void fillProtocolPayload(
    StaticJsonDocument<PAYLOAD_JSON_DOC_CAPACITY>& jsonDoc,
    const char* protocol,
    uint32_t packetSeq,
    float dayaMw,
    float txDurationMs,
    uint32_t payloadBytes,
    int rssiDbm,
    long timestampEsp,
    uint32_t sensorAgeMs,
    uint32_t sensorReadSeq
) {
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
    jsonDoc["sensor_age_ms"] = sensorAgeMs;
    jsonDoc["sensor_read_seq"] = sensorReadSeq;
    jsonDoc["sensor_fallback_level"] = currentSensorFallbackLevel;
    jsonDoc["send_tick_ms"] = (uint32_t) millis();
    jsonDoc["sensor_reads"] = sensorReadCount;
    jsonDoc["dht_fail_streak"] = dhtConsecutiveFail;
    jsonDoc["sensor_fallback_std_count"] = sensorFallbackStandardCount;
    jsonDoc["sensor_fallback_emergency_count"] = sensorFallbackEmergencyCount;
    jsonDoc["sensor_fallback_rejected_count"] = sensorFallbackRejectedCount;
    jsonDoc["http_success_count"] = httpSendSuccess;
    jsonDoc["http_fail_count"] = httpSendFail;
    jsonDoc["mqtt_success_count"] = mqttSendSuccess;
    jsonDoc["mqtt_fail_count"] = mqttSendFail;
}

String buildProtocolPayload(
    const char* protocol,
    uint32_t packetSeq,
    float dayaMw,
    float txDurationMs,
    int rssiDbm,
    long timestampEsp,
    uint32_t sensorAgeMs,
    uint32_t sensorReadSeq,
    uint32_t* payloadBytesOut
) {
    StaticJsonDocument<PAYLOAD_JSON_DOC_CAPACITY> jsonDoc;
    fillProtocolPayload(jsonDoc, protocol, packetSeq, dayaMw, txDurationMs, 0, rssiDbm, timestampEsp, sensorAgeMs, sensorReadSeq);

    String payload;
    serializeJson(jsonDoc, payload);
    uint32_t payloadBytes = (uint32_t) payload.length();

    fillProtocolPayload(jsonDoc, protocol, packetSeq, dayaMw, txDurationMs, payloadBytes, rssiDbm, timestampEsp, sensorAgeMs, sensorReadSeq);
    payload = "";
    serializeJson(jsonDoc, payload);
    payloadBytes = (uint32_t) payload.length();
    if (payloadBytesOut != nullptr) {
        *payloadBytesOut = payloadBytes;
    }

    return payload;
}

bool payloadHasRequiredFields(const String& payload) {
    StaticJsonDocument<PAYLOAD_VERIFY_DOC_CAPACITY> verifyDoc;
    DeserializationError error = deserializeJson(verifyDoc, payload);
    if (error) {
        Serial.print("[PAYLOAD] Invalid JSON: ");
        Serial.println(error.c_str());
        if (error == DeserializationError::NoMemory) {
            Serial.print("[PAYLOAD] JSON bytes: ");
            Serial.print(payload.length());
            Serial.print(" | verify capacity: ");
            Serial.println((unsigned long) PAYLOAD_VERIFY_DOC_CAPACITY);
        }
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

    Serial.print("Time Sync: ");
    Serial.println(timeSynced ? "Synced" : "Not synced");

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
    uint32_t snapshotAgeMs = 0U;
    if (hasAnySensorSnapshot(&snapshotAgeMs)) {
        Serial.print("Latest Snapshot Age: ");
        Serial.print(snapshotAgeMs);
        Serial.print(" ms | Fallback Level: ");
        Serial.println(currentSensorFallbackLevel);
    } else {
        Serial.println("Latest Snapshot Age: none");
    }
    Serial.print("Fallback Std/Emergency/Rejected: ");
    Serial.print(sensorFallbackStandardCount);
    Serial.print(" / ");
    Serial.print(sensorFallbackEmergencyCount);
    Serial.print(" / ");
    Serial.println(sensorFallbackRejectedCount);

    Serial.print("Uptime: ");
    Serial.print(millis() / 1000);
    Serial.println(" seconds");

    Serial.print("Free Memory: ");
    Serial.print(ESP.getFreeHeap());
    Serial.println(" bytes");

    Serial.println("==========================================\n");
}
// ==================== TIME SYNC ====================
void refreshDhtSamplingInterval() {
    unsigned long recommended = (unsigned long) dht.getMinimumSamplingPeriod();
    unsigned long withGuard = recommended + 400UL;
    if (activeDhtModel == DHTesp::DHT11) {
        dhtMinReadIntervalMs = max(2500UL, withGuard);
        return;
    }

    dhtMinReadIntervalMs = max(2500UL, withGuard);
}

const char* dhtModelToString(DHTesp::DHT_MODEL_t model) {
    switch (model) {
        case DHTesp::DHT11:
            return "DHT11";
        case DHTesp::DHT22:
            return "DHT22";
        case DHTesp::AM2302:
            return "AM2302";
        case DHTesp::RHT03:
            return "RHT03";
        default:
            return "AUTO/UNKNOWN";
    }
}

void reinitializeDhtSensor(const char* reason) {
    dhtReinitCounter++;
    Serial.print("[SENSOR] Reinitializing DHT (");
    Serial.print(reason != nullptr ? reason : "unknown");
    Serial.println(")...");

    // If preferred model keeps failing from cold boot (no successful read yet),
    // probe once with AUTO_DETECT to recover from mislabeled/alternate DHT modules.
    bool tryAutoDetect = (sensorReadCount == 0)
        && !dhtModelFallbackAttempted
        && (dhtReinitCounter >= 3);
    if (tryAutoDetect) {
        activeDhtModel = DHTesp::AUTO_DETECT;
        dhtModelFallbackAttempted = true;
        Serial.println("[SENSOR] No successful reads yet, trying AUTO_DETECT fail-safe.");
    } else {
        activeDhtModel = DHT_MODEL_PREFERRED;
    }

    dht.setup(DHTPIN, activeDhtModel);
    refreshDhtSamplingInterval();
    Serial.print("[SENSOR] DHT model after reinit: ");
    Serial.println(dhtModelToString(activeDhtModel));
    cooperativeDelay(dhtMinReadIntervalMs);
}

void seedPacketSequenceFromTime() {
    if (!timeSynced) {
        return;
    }

    const time_t now = time(nullptr);
    if (now < 1640995200L) {
        return;
    }

    // Use epoch delta seed so packet_seq doesn't restart from 1 after reboot.
    const uint32_t baseSeed = (uint32_t) max((time_t) 1, now - 1640995200L);

    if (httpPacketSeq < baseSeed) {
        httpPacketSeq = baseSeed;
    }

    if (mqttPacketSeq < baseSeed) {
        mqttPacketSeq = baseSeed;
    }

    Serial.print("[SEQ] Packet sequence seed: ");
    Serial.print(baseSeed);
    Serial.print(" (HTTP=");
    Serial.print(httpPacketSeq);
    Serial.print(", MQTT=");
    Serial.print(mqttPacketSeq);
    Serial.println(")");
}

bool updateTime(bool verbose) {
    lastNtpAttempt = millis();

    if (verbose) {
        Serial.print("[TIME] Syncing with NTP server...");
    }

    // Configure time with fallback NTP servers.
    configTime(0, 0, "pool.ntp.org", "time.nist.gov", "time.google.com");

    const int maxAttempts = 40;
    time_t now = time(nullptr);

    for (int attempts = 0; attempts < maxAttempts; attempts++) {
        now = time(nullptr);
        if (now >= 1640995200L) {
            timeSynced = true;
            if (verbose) {
                Serial.println("");
                Serial.print("[TIME] âœ“ Time synchronized: ");
                Serial.println(ctime(&now));
            }
            return true;
        }

        if (verbose) {
            Serial.print(".");
        }

        delay(500);
        yield();
    }

    timeSynced = false;

    if (verbose) {
        Serial.println("");
        Serial.print("[TIME] âœ— Sync failed, epoch=");
        Serial.println((long) now);
        Serial.println("[TIME] Will retry automatically every 30 seconds.");
    }

    return false;
}
