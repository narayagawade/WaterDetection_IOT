#include <WiFi.h>
#include <HTTPClient.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ------------------ CONFIGURATION ------------------
const char* ssid = "YOUR_WIFI_NAME";        // Change to your WiFi name
const char* password = "YOUR_WIFI_PASSWORD"; // Change to your WiFi password

const String serverUrl = "http://yourdomain.com/api/readings.php";  // Change to your actual URL

const String device_id = "VENGUR-001";      // Change to your device ID
const String auth_token = "abcd1234";       // Change to your auth token

// Pins
#define TEMP_PIN 4     // DS18B20
#define TURB_PIN 32    // Turbidity
#define TDS_PIN  35    // TDS

// ------------------ SENSOR SETUP ------------------
OneWire oneWire(TEMP_PIN);
DallasTemperature sensors(&oneWire);

// TDS variables
#define VREF 3.3
#define SCOUNT 30
int analogBuffer[SCOUNT];
int analogBufferIndex = 0;

int getMedianNum(int bArray[], int iFilterLen) {
  int bTab[iFilterLen];
  for (int i = 0; i<iFilterLen; i++) bTab[i] = bArray[i];
  for (int j = 0; j < iFilterLen - 1; j++) {
    for (int i = 0; i < iFilterLen - j - 1; i++) {
      if (bTab[i] > bTab[i + 1]) {
        int bTemp = bTab[i];
        bTab[i] = bTab[i + 1];
        bTab[i + 1] = bTemp;
      }
    }
  }
  if ((iFilterLen & 1) > 0) return bTab[(iFilterLen - 1) / 2];
  return (bTab[iFilterLen / 2] + bTab[iFilterLen / 2 - 1]) / 2;
}

void setup() {
  Serial.begin(115200);
  analogSetAttenuation(ADC_11db);
  sensors.begin();

  // Connect to WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected!");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
}

void loop() {
  static unsigned long timepoint = millis();
  if (millis() - timepoint > 30000UL) {  // Every 30 seconds
    timepoint = millis();

    // Temperature
    sensors.requestTemperatures();
    float temperature = sensors.getTempCByIndex(0);
    if (temperature == DEVICE_DISCONNECTED_C) temperature = -99;

    // Turbidity
    float turbVoltage = analogRead(TURB_PIN) / 4095.0 * 3.3;
    float turbidityNTU = (turbVoltage < 2.5) ? 3000 : -1120.4 * turbVoltage*turbVoltage + 5742.3 * turbVoltage - 4352.9;

    // TDS
    analogBuffer[analogBufferIndex] = analogRead(TDS_PIN);
    analogBufferIndex = (analogBufferIndex + 1) % SCOUNT;
    int tempBuffer[SCOUNT];
    memcpy(tempBuffer, analogBuffer, sizeof(tempBuffer));
    float avgVoltage = getMedianNum(tempBuffer, SCOUNT) * VREF / 4095.0;
    float compCoeff = 1.0 + 0.02 * (temperature - 25.0);
    float compVoltage = avgVoltage / compCoeff;
    float tdsValue = (133.42 * compVoltage*compVoltage*compVoltage - 255.86 * compVoltage*compVoltage + 857.39 * compVoltage) * 0.5;

    // Print readings
    Serial.println("=== Current Readings ===");
    Serial.print("Temperature: "); Serial.print(temperature); Serial.println(" °C");
    Serial.print("Turbidity: "); Serial.print(turbidityNTU, 1); Serial.println(" NTU");
    Serial.print("TDS: "); Serial.print(tdsValue, 0); Serial.println(" ppm");
    Serial.println("Sending to server...");

    // Send to server
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(serverUrl);
      http.addHeader("Content-Type", "application/json");

      // Create JSON
      String jsonData = "{";
      jsonData += "\"device_id\":\"" + device_id + "\",";
      jsonData += "\"auth_token\":\"" + auth_token + "\",";
      jsonData += "\"timestamp\":\"" + String(millis()/1000) + "\",";
      jsonData += "\"pH\":7.0,";  // Fixed for demo (since pH not working)
      jsonData += "\"tds\":" + String((int)tdsValue) + ",";
      jsonData += "\"turbidity\":" + String(turbidityNTU, 1) + ",";
      jsonData += "\"temperature\":" + String(temperature, 1);
      jsonData += "}";

      int httpCode = http.POST(jsonData);

      if (httpCode > 0) {
        String response = http.getString();
        Serial.print("HTTP Code: "); Serial.println(httpCode);
        Serial.print("Response: "); Serial.println(response);
      } else {
        Serial.println("Error on HTTP request");
      }
      http.end();
    } else {
      Serial.println("WiFi disconnected");
    }

    Serial.println("------------------------\n");
  }
}
