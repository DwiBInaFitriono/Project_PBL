/*
 * Hidroponik DFT IoT: Node Iklim (ID 2).
 * Template telemetry only. Select sensor drivers, pins, pulse conversion,
 * placement, TLS CA, and credentials before deployment.
 */
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#define NODE_ID "2"
#define MQTT_CLIENT_ID "hidroponik-node-iklim-2"
#define TELEMETRY_TOPIC "hidroponik/v1/nodes/2/telemetry"
const char* CA_CERTIFICATE_PLACEHOLDER = "-----BEGIN CERTIFICATE-----\nREPLACE_WITH_BROKER_CA\n-----END CERTIFICATE-----\n";

// Implement after selecting calibrated hardware. Return false on any unavailable
// required reading; never substitute fabricated numeric data.
bool readSensors(JsonObject readings) {
  // TODO: read air-temperature sensor, then readings["air_temperature"] = value;
  // TODO: read humidity sensor, then readings["air_humidity"] = value;
  // TODO: read light sensor, then readings["light_intensity"] = value;
  // TODO: convert verified flow-sensor pulses, then readings["water_flow"] = value;
  return false;
}

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <time.h>

// Supply these at build/deployment time. Never commit real credentials.
const char* WIFI_SSID = "WIFI_SSID_PLACEHOLDER";
const char* WIFI_PASSWORD = "WIFI_PASSWORD_PLACEHOLDER";
const char* MQTT_HOST = "MQTT_HOST_PLACEHOLDER";
const uint16_t MQTT_PORT = 8883;
const char* MQTT_USERNAME = "MQTT_USERNAME_PLACEHOLDER";
const char* MQTT_PASSWORD = "MQTT_PASSWORD_PLACEHOLDER";

const unsigned long TELEMETRY_INTERVAL_MS = 60000;
unsigned long lastTelemetryAt = 0;
WiFiClientSecure transport;
PubSubClient mqttClient(transport);

bool readSensors(JsonObject readings);

String isoTimestamp() {
  struct tm timeInfo;
  if (!getLocalTime(&timeInfo)) {
    return String();
  }
  char value[26];
  strftime(value, sizeof(value), "%Y-%m-%dT%H:%M:%SZ", &timeInfo);
  return String(value);
}

String messageId() {
  char value[37];
  snprintf(value, sizeof(value), "%08lx-%04x-4%03x-%04x-%08lx%04x",
    (unsigned long)esp_random(), (uint16_t)esp_random(),
    (uint16_t)(esp_random() & 0x0fff),
    (uint16_t)(0x8000 | (esp_random() & 0x3fff)),
    (unsigned long)esp_random(), (uint16_t)esp_random());
  return String(value);
}

void connectWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
  }
}

void connectMqtt() {
  while (!mqttClient.connected()) {
    mqttClient.connect(MQTT_CLIENT_ID, MQTT_USERNAME, MQTT_PASSWORD);
    if (!mqttClient.connected()) {
      delay(3000);
    }
  }
}

void publishTelemetry() {
  String timestamp = isoTimestamp();
  if (timestamp.isEmpty()) {
    return; // Do not invent a timestamp when NTP is unavailable.
  }

  StaticJsonDocument<512> payload;
  payload["schema_version"] = 1;
  payload["message_id"] = messageId();
  payload["node_id"] = NODE_ID;
  payload["recorded_at"] = timestamp;
  JsonObject readings = payload.createNestedObject("readings");
  if (!readSensors(readings) || readings.size() == 0) {
    return; // Do not publish fallback readings.
  }

  char encoded[512];
  size_t size = serializeJson(payload, encoded, sizeof(encoded));
  if (size == 0 || size >= sizeof(encoded)) {
    return;
  }
  mqttClient.publish(TELEMETRY_TOPIC, encoded, false);
}

void setup() {
  Serial.begin(115200);
  connectWifi();
  configTime(0, 0, "pool.ntp.org", "time.google.com");
  transport.setCACert(CA_CERTIFICATE_PLACEHOLDER); // Replace with broker CA.
  mqttClient.setServer(MQTT_HOST, MQTT_PORT);
  // Initialize actual sensor drivers and pins after hardware selection.
}

void loop() {
  if (!mqttClient.connected()) {
    connectMqtt();
  }
  mqttClient.loop();
  if (millis() - lastTelemetryAt >= TELEMETRY_INTERVAL_MS) {
    lastTelemetryAt = millis();
    publishTelemetry();
  }
}
