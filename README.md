# 🌊 Water Quality Monitoring System (IoT)
> **An end-to-end IoT solution for real-time water health tracking using ESP32, PHP, and MySQL.**

[![Platform](https://img.shields.io/badge/Platform-ESP32-blue?style=for-the-badge&logo=espressif)](https://www.espressif.com/)
[![Language](https://img.shields.io/badge/Language-C++%20|%20PHP%20|%20JS-4F5D95?style=for-the-badge)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](https://opensource.org/licenses/MIT)

---

## 📌 Project Overview
A low-cost, real-time water quality monitoring system designed for municipalities and citizens. The system captures vital water metrics and provides actionable insights via a web-based dashboard.



### ⚡ Key Features
* 🚀 **Real-time Data:** Live sensor readings (pH, TDS, Turbidity, Temp).
* 🔔 **Smart Alerts:** Automated email notifications for poor water quality.
* 👥 **Multi-Role Access:** Dedicated panels for Admins, Municipalities, and Citizens.
* 📊 **Visual Analytics:** Historical data trends visualized via **Chart.js**.
* 📋 **Maintenance Tracking:** Citizen reporting and tank cleaning schedules.

---

## 🛠️ Technology Stack

| Layer | Technologies |
| :--- | :--- |
| **Frontend** | ![HTML5](https://img.shields.io/badge/-HTML5-E34F26?logo=html5&logoColor=white) ![CSS3](https://img.shields.io/badge/-CSS3-1572B6?logo=css3) ![JS](https://img.shields.io/badge/-JavaScript-F7DF1E?logo=javascript&logoColor=black) ![Bootstrap](https://img.shields.io/badge/-Bootstrap-7952B3?logo=bootstrap&logoColor=white) |
| **Backend** | ![PHP](https://img.shields.io/badge/-PHP-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/-MySQL-4479A1?logo=mysql&logoColor=white) |
| **Hardware** | ![ESP32](https://img.shields.io/badge/-ESP32-E7352C?logo=espressif) ![C++](https://img.shields.io/badge/-C++-00599C?logo=c%2B%2B&logoColor=white) |

---

## 🔌 Hardware Configuration

### Sensor Connection Table
| Component | ESP32 Pin | Connection Note |
| :--- | :---: | :--- |
| **🌡️ DS18B20 (Temp)** | `GPIO 4` | Requires 4.7kΩ Pull-up |
| **🧪 pH Sensor** | `GPIO 34` | Analog Output (VCC 5V) |
| **☁️ Turbidity** | `GPIO 32` | Analog Output (VCC 5V) |
| **💎 TDS Sensor** | `GPIO 35` | Analog Output (VCC 5V) |



---

## 📂 Project Structure
```text
water-quality-iot/
├── 📁 api/              # ESP32 POST endpoint
├── 📁 assets/           # CSS, JS (Chart.js), and Icons
├── 📄 dashboard_admin.php # Admin User Management
├── 📄 dashboard_muni.php  # Municipality Control Center
├── 📄 index.php          # Citizen Portal
├── 📄 esp32_full_code.ino # Embedded Firmware
└── 📄 database.sql       # Database Schema


🚀 Installation & Setup
Hardware Setup: Wire the sensors to the ESP32 as per the table above.

Database: Import database.sql into your local MySQL server (XAMPP/WAMP).

Backend: Place the project folder in htdocs and update db.php with your credentials.

Firmware: * Open esp32_full_code.ino in Arduino IDE.

Update WiFi SSID, Password, and your Server URL.

Upload to ESP32.
```

📈 Future Roadmap
[ ] WebSockets: Implementation for zero-latency chart updates.

[ ] Mobile App: Flutter-based app for push notifications.

[ ] AI Analysis: Predictive maintenance for water filter replacements.

[ ] Cloud: Migration to AWS IoT Core or Railway.app.

🤝 Contributing
Contributions are what make the open source community such an amazing place to learn, inspire, and create.

Fork the Project
Create your Feature Branch (git checkout -b feature/AmazingFeature)
Commit your Changes (git commit -m 'Add some AmazingFeature')
Push to the Branch (git push origin feature/AmazingFeature)
Open a Pull Request
```

```
## 👨‍💻 Developed By
  
   **Narayan Ashok Gawade** 
   *"Safety First — Ride Smart"*

   ⭐ **Star this repo if you like it!** **Feel free to fork and improve!** **Live Demo Coming Soon...**
