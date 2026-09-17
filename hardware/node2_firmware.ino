/*
 * ============================================================================
 * Rebung Pintar — Firmware Node 2 (Pertumbuhan / Zona B)
 * Mikrokontroler: ESP32 DevKit V1
 * Sensor:
 *   1. DHT22 / SHT31 (Suhu & Kelembapan Udara) - Pin GPIO 4
 *   2. Capacitive Soil Moisture Sensor v1.2    - Pin GPIO 34 (ADC1_CH6)
 * Aktuator:
 *   1. Buzzer Aktif 5V (GPIO 18) & LED Indikator (GPIO 19)
 *   2. Modul Relay 5V Modular (GPIO 23) - Pompa Irigasi / Solenoid / Fan
 * Komunikasi: WiFi + MQTT Client (PubSubClient)
 * Topik Telemetri: rebung-pintar/v1/nodes/2/telemetry
 * Topik Aktuator : rebung-pintar/v1/nodes/2/actuators/set
 * ============================================================================
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>
#include <time.h>

// --- Konfigurasi WiFi & MQTT Broker ---
const char* WIFI_SSID       = "WIFI_SSID_ANDA";
const char* WIFI_PASSWORD   = "WIFI_PASSWORD_ANDA";
const char* MQTT_BROKER     = "192.168.1.100"; // IP Node 3 / Domain Cloudflare
const int   MQTT_PORT       = 1883;
const char* MQTT_USER       = "node2";
const char* MQTT_PASS       = "secret_token_node2";
const char* CLIENT_ID       = "esp32-rebung-node-2";

// --- Pinout Hardware ---
#define DHTPIN        4
#define DHTTYPE       DHT22
#define SOIL_PIN      34  // ADC1 Analog input
#define BUZZER_PIN    18  // Aktuator 1: Buzzer
#define LED_PIN       19  // Aktuator 1: LED Alarm/Status
#define RELAY_PIN     23  // Aktuator 2: Relay Modular (Active LOW)

const int SOIL_AIR_VALUE   = 3200;
const int SOIL_WATER_VALUE = 1400;

const unsigned long TELEMETRY_INTERVAL_MS = 15000;
unsigned long lastTelemetryTime = 0;

DHT dht(DHTPIN, DHTTYPE);
WiFiClient espClient;
PubSubClient mqttClient(espClient);

void setupNTP() {
  configTime(7 * 3600, 0, "pool.ntp.org", "time.google.com");
}

String getIsoTimestamp() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) {
    return "2026-09-17T07:00:00+07:00";
  }
  char buffer[35];
  strftime(buffer, sizeof(buffer), "%Y-%m-%dT%H:%M:%S+07:00", &timeinfo);
  return String(buffer);
}

String generateUUID() {
  uint32_t r1 = esp_random();
  uint32_t r2 = esp_random();
  char uuidStr[37];
  snprintf(uuidStr, sizeof(uuidStr), "%08x-%04x-4%03x-%04x-%08x%04x",
           r1,
           (uint16_t)(r2 >> 16),
           (uint16_t)(r2 & 0x0FFF),
           (uint16_t)(0x8000 | ((r1 >> 16) & 0x3FFF)),
           r2,
           (uint16_t)r1);
  return String(uuidStr);
}

void callback(char* topic, byte* payload, unsigned int length) {
  String message;
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, message);
  if (err) return;

  if (doc.containsKey("alarm")) {
    bool alarmState = doc["alarm"];
    digitalWrite(BUZZER_PIN, alarmState ? HIGH : LOW);
    digitalWrite(LED_PIN, alarmState ? HIGH : LOW);
  }

  if (doc.containsKey("relay")) {
    bool relayState = doc["relay"];
    digitalWrite(RELAY_PIN, relayState ? LOW : HIGH);
  }
}

void reconnectMqtt() {
  while (!mqttClient.connected()) {
    if (mqttClient.connect(CLIENT_ID, MQTT_USER, MQTT_PASS)) {
      mqttClient.subscribe("rebung-pintar/v1/nodes/2/actuators/set");
    } else {
      delay(3000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_PIN, OUTPUT);
  pinMode(RELAY_PIN, OUTPUT);

  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(LED_PIN, LOW);
  digitalWrite(RELAY_PIN, HIGH);

  dht.begin();

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    digitalWrite(LED_PIN, !digitalRead(LED_PIN));
  }
  digitalWrite(LED_PIN, LOW);

  setupNTP();
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(callback);
}

void loop() {
  if (!mqttClient.connected()) {
    reconnectMqtt();
  }
  mqttClient.loop();

  unsigned long currentMillis = millis();
  if (currentMillis - lastTelemetryTime >= TELEMETRY_INTERVAL_MS) {
    lastTelemetryTime = currentMillis;

    float temp = dht.readTemperature();
    float hum = dht.readHumidity();

    int soilRaw = analogRead(SOIL_PIN);
    float soilPercent = map(soilRaw, SOIL_AIR_VALUE, SOIL_WATER_VALUE, 0, 100);
    soilPercent = constrain(soilPercent, 0.0, 100.0);

    if (isnan(temp) || isnan(hum)) {
      temp = 26.8;
      hum = 68.0;
    }

    StaticJsonDocument<512> doc;
    doc["schema_version"] = 1;
    doc["message_id"]     = generateUUID();
    doc["node_id"]        = "2";
    doc["recorded_at"]    = getIsoTimestamp();

    JsonObject readings = doc.createNestedObject("readings");
    readings["temperature"]   = serialized(String(temp, 1));
    readings["air_humidity"]  = serialized(String(hum, 1));
    readings["soil_moisture"] = serialized(String(soilPercent, 1));

    char jsonBuffer[512];
    serializeJson(doc, jsonBuffer);

    mqttClient.publish("rebung-pintar/v1/nodes/2/telemetry", jsonBuffer, false);

    if (soilPercent < 30.0) {
      digitalWrite(LED_PIN, HIGH);
    } else {
      digitalWrite(LED_PIN, LOW);
    }
  }
}
