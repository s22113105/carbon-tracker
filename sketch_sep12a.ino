/*
 * ESP32 GPS追蹤裝置 - 簡化穩定版
 * 移除強制重啟機制，提高系統穩定性
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPIFFS.h>
#include <TinyGPS++.h>

// 硬體針腳定義
#define GPS_RX_PIN 4
#define GPS_TX_PIN 2
#define GPS_BAUD 9600
#define POWER_BUTTON_PIN 21
#define POWER_LED_PIN 25
#define STATUS_LED_PIN 26
#define BATTERY_PIN 36

// 網路設定
const char* ssid = "penguin";
const char* password = "0965404559";
const char* serverURL = "http://10.128.79.215:8000/api/gps";
const char* deviceID = "ESP32_CARBON_001";

// 物件初始化
HardwareSerial gpsSerial(1);
TinyGPSPlus gps;

// 簡化的系統狀態
struct SystemState {
  bool systemOn = false;
  bool wifiConnected = false;
  bool gpsValid = false;
  int batteryLevel = 100;
  unsigned long lastGPSRead = 0;
  int offlineDataCount = 0;
};

struct GPSData {
  float latitude;
  float longitude;
  float speed;
  String timestamp;
  bool isValid;
};

SystemState state;

// 按鈕控制變數
unsigned long buttonPressTime = 0;
bool buttonPressed = false;
bool longPressExecuted = false;

// 系統參數
const unsigned long GPS_INTERVAL = 15000;
const int LOW_BATTERY_THRESHOLD = 15;
const unsigned long LONG_PRESS_TIME = 3000;

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("ESP32 GPS追蹤裝置啟動");
  
  // 基本硬體初始化
  pinMode(POWER_BUTTON_PIN, INPUT_PULLUP);
  pinMode(POWER_LED_PIN, OUTPUT);
  pinMode(STATUS_LED_PIN, OUTPUT);
  pinMode(BATTERY_PIN, INPUT);
  
  digitalWrite(POWER_LED_PIN, LOW);
  digitalWrite(STATUS_LED_PIN, LOW);
  
  // 初始化SPIFFS
  if (!SPIFFS.begin(true)) {
    Serial.println("SPIFFS初始化失敗");
  }
  
  Serial.println("系統準備就緒 - 短按開機，長按3秒關機");
}

void loop() {
  handleButton();
  
  if (state.systemOn) {
    runSystem();
  } else {
    powerSaveMode();
  }
  
  delay(100);
}

// 按鈕處理
void handleButton() {
  bool currentButton = !digitalRead(POWER_BUTTON_PIN);
  
  if (currentButton && !buttonPressed) {
    buttonPressTime = millis();
    buttonPressed = true;
    longPressExecuted = false;
    Serial.println("按鈕按下");
  }
  
  if (currentButton && !longPressExecuted && 
      (millis() - buttonPressTime >= LONG_PRESS_TIME)) {
    longPressExecuted = true;
    handleLongPress();
  }
  
  if (!currentButton && buttonPressed) {
    buttonPressed = false;
    if (!longPressExecuted) {
      handleShortPress();
    }
  }
}

void handleShortPress() {
  Serial.println("短按檢測");
  if (!state.systemOn) {
    powerOn();
  } else {
    Serial.println("切換GPS模式");
  }
}

void handleLongPress() {
  Serial.println("長按檢測 - 關機");
  if (state.systemOn) {
    powerOff();
  }
}

// 電源管理
void powerOn() {
  Serial.println("=== 系統開機 ===");
  state.systemOn = true;
  
  // 開機動畫
  for (int i = 0; i < 3; i++) {
    digitalWrite(STATUS_LED_PIN, HIGH);
    delay(150);
    digitalWrite(STATUS_LED_PIN, LOW);
    delay(150);
  }
  
  initializeSystems();
  digitalWrite(POWER_LED_PIN, HIGH);
  Serial.println("系統開機完成");
}

void powerOff() {
  Serial.println("=== 系統關機 ===");
  
  // 清理工作
  if (state.wifiConnected) {
    uploadOfflineData();
    WiFi.disconnect();
    state.wifiConnected = false;
  }
  
  // 關機動畫
  for (int i = 0; i < 3; i++) {
    digitalWrite(POWER_LED_PIN, HIGH);
    delay(100);
    digitalWrite(POWER_LED_PIN, LOW);
    delay(100);
  }
  
  state.systemOn = false;
  digitalWrite(POWER_LED_PIN, LOW);
  digitalWrite(STATUS_LED_PIN, LOW);
  
  Serial.println("系統已關機");
}

void initializeSystems() {
  Serial.println("初始化系統組件...");
  
  // 初始化GPS
  gpsSerial.begin(GPS_BAUD, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
  delay(1000);
  gpsSerial.println("$PMTK220,1000*1F"); // 1Hz更新率
  
  // 初始化WiFi
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  
  Serial.print("連接WiFi");
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    state.wifiConnected = true;
    Serial.println("\nWiFi連接成功");
    Serial.println("IP: " + WiFi.localIP().toString());
    uploadOfflineData(); // 上傳離線資料
  } else {
    Serial.println("\nWiFi連接失敗");
    state.wifiConnected = false;
  }
}

// 主系統運行
void runSystem() {
  static unsigned long lastCheck = 0;
  
  if (millis() - lastCheck > 2000) { // 每2秒檢查一次
    checkWiFi();
    handleGPS();
    checkBattery();
    updateLED();
    lastCheck = millis();
  }
}

void powerSaveMode() {
  static unsigned long lastMsg = 0;
  
  if (millis() - lastMsg > 30000) { // 每30秒顯示一次
    Serial.println("省電模式 - 短按開機");
    lastMsg = millis();
  }
  
  digitalWrite(STATUS_LED_PIN, LOW);
}

// WiFi檢查
void checkWiFi() {
  if (WiFi.status() != WL_CONNECTED && state.wifiConnected) {
    Serial.println("WiFi斷線，嘗試重連");
    state.wifiConnected = false;
    WiFi.reconnect();
    delay(2000);
    if (WiFi.status() == WL_CONNECTED) {
      state.wifiConnected = true;
      Serial.println("WiFi重連成功");
    }
  }
}

// GPS處理
void handleGPS() {
  if (millis() - state.lastGPSRead < GPS_INTERVAL) return;
  
  GPSData gpsData = readGPS();
  
  if (gpsData.isValid) {
    Serial.printf("GPS: %.6f, %.6f, 速度: %.1f km/h\n", 
                  gpsData.latitude, gpsData.longitude, gpsData.speed);
    state.gpsValid = true;
    
    if (state.wifiConnected) {
      if (!sendToServer(gpsData)) {
        saveOfflineData(gpsData);
      }
    } else {
      saveOfflineData(gpsData);
    }
  } else {
    state.gpsValid = false;
    // 發送測試資料（如果WiFi連接且無GPS）
    if (state.wifiConnected) {
      GPSData testData;
      testData.latitude = 25.033 + (random(-50, 50) * 0.0001);
      testData.longitude = 121.565 + (random(-50, 50) * 0.0001);
      testData.speed = random(0, 20);
      testData.timestamp = String(millis());
      testData.isValid = true;
      sendToServer(testData);
    }
  }
  
  state.lastGPSRead = millis();
}

GPSData readGPS() {
  GPSData data;
  data.isValid = false;
  
  while (gpsSerial.available() > 0) {
    if (gps.encode(gpsSerial.read())) {
      if (gps.location.isValid()) {
        data.latitude = gps.location.lat();
        data.longitude = gps.location.lng();
        data.speed = gps.speed.isValid() ? gps.speed.kmph() : 0.0;
        data.timestamp = getTimestamp();
        data.isValid = true;
        break;
      }
    }
  }
  
  return data;
}

String getTimestamp() {
  if (gps.time.isValid() && gps.date.isValid()) {
    return String(gps.date.year()) + "-" + 
           String(gps.date.month()) + "-" + 
           String(gps.date.day()) + " " +
           String(gps.time.hour()) + ":" + 
           String(gps.time.minute()) + ":" + 
           String(gps.time.second());
  }
  return String(millis());
}

// 資料傳送
bool sendToServer(GPSData gpsData) {
  if (!state.wifiConnected) return false;
  
  HTTPClient http;
  http.setTimeout(8000); // 8秒超時
  http.begin(serverURL);
  http.addHeader("Content-Type", "application/json");
  
  String json = "{";
  json += "\"device_id\":\"" + String(deviceID) + "\",";
  json += "\"latitude\":" + String(gpsData.latitude, 6) + ",";
  json += "\"longitude\":" + String(gpsData.longitude, 6) + ",";
  json += "\"speed\":" + String(gpsData.speed, 1) + ",";
  json += "\"timestamp\":\"" + gpsData.timestamp + "\",";
  json += "\"battery_level\":" + String(state.batteryLevel);
  json += "}";
  
  int responseCode = http.POST(json);
  bool success = (responseCode >= 200 && responseCode < 300);
  
  if (success) {
    Serial.println("資料上傳成功");
  } else {
    Serial.printf("上傳失敗，回應碼: %d\n", responseCode);
  }
  
  http.end();
  return success;
}

// 離線資料管理
void saveOfflineData(GPSData gpsData) {
  String filename = "/offline_" + String(state.offlineDataCount) + ".json";
  
  File file = SPIFFS.open(filename, "w");
  if (file) {
    DynamicJsonDocument doc(512);
    doc["device_id"] = deviceID;
    doc["latitude"] = gpsData.latitude;
    doc["longitude"] = gpsData.longitude;
    doc["speed"] = gpsData.speed;
    doc["timestamp"] = gpsData.timestamp;
    doc["battery_level"] = state.batteryLevel;
    
    serializeJson(doc, file);
    file.close();
    state.offlineDataCount++;
    
    Serial.println("離線資料已保存");
    
    // 限制離線資料數量
    if (state.offlineDataCount > 50) {
      SPIFFS.remove("/offline_0.json");
      state.offlineDataCount = 49;
    }
  }
}

void uploadOfflineData() {
  Serial.println("上傳離線資料...");
  
  for (int i = 0; i < state.offlineDataCount; i++) {
    String filename = "/offline_" + String(i) + ".json";
    if (SPIFFS.exists(filename)) {
      File file = SPIFFS.open(filename, "r");
      if (file) {
        String jsonData = file.readString();
        file.close();
        
        HTTPClient http;
        http.setTimeout(5000);
        http.begin(serverURL);
        http.addHeader("Content-Type", "application/json");
        
        int responseCode = http.POST(jsonData);
        if (responseCode >= 200 && responseCode < 300) {
          SPIFFS.remove(filename);
          Serial.printf("離線資料 %d 上傳成功\n", i);
        }
        http.end();
        delay(100); // 避免過快請求
      }
    }
  }
  
  state.offlineDataCount = 0;
}

// 電池檢查
void checkBattery() {
  static unsigned long lastCheck = 0;
  
  if (millis() - lastCheck < 30000) return; // 每30秒檢查
  
  int reading = analogRead(BATTERY_PIN);
  state.batteryLevel = map(reading, 0, 4095, 0, 100);
  state.batteryLevel = constrain(state.batteryLevel, 0, 100);
  
  if (state.batteryLevel < LOW_BATTERY_THRESHOLD) {
    Serial.printf("低電量: %d%%\n", state.batteryLevel);
    // 低電量LED閃爍
    for (int i = 0; i < 3; i++) {
      digitalWrite(POWER_LED_PIN, LOW);
      delay(100);
      digitalWrite(POWER_LED_PIN, HIGH);
      delay(100);
    }
  }
  
  lastCheck = millis();
}

// LED狀態更新
void updateLED() {
  static unsigned long lastUpdate = 0;
  
  if (millis() - lastUpdate < 1000) return;
  
  if (state.gpsValid) {
    digitalWrite(STATUS_LED_PIN, !digitalRead(STATUS_LED_PIN)); // 快閃
  } else {
    static int slowBlink = 0;
    slowBlink++;
    if (slowBlink >= 3) {
      digitalWrite(STATUS_LED_PIN, !digitalRead(STATUS_LED_PIN)); // 慢閃
      slowBlink = 0;
    }
  }
  
  lastUpdate = millis();
}