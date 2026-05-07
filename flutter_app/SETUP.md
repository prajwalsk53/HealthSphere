# HealthSphere Android App — Setup Guide

## Prerequisites

1. **Flutter SDK** (3.x+)  
   https://docs.flutter.dev/get-started/install/windows

2. **Android Studio** with:
   - Android SDK (API 21+)
   - Android Emulator or physical device with USB debugging

3. **XAMPP running** with HealthSphere web app working at `http://localhost/HealthSphere/`

---

## Step 1 — Install Flutter

1. Download Flutter SDK from flutter.dev → unzip to `C:\flutter`
2. Add `C:\flutter\bin` to your Windows PATH
3. Open a new terminal and run: `flutter doctor`
4. Fix any issues flagged (usually: Android licenses → run `flutter doctor --android-licenses`)

---

## Step 2 — Open in Android Studio

1. Open Android Studio
2. **File → Open** → navigate to `C:\xampp\htdocs\HealthSphere\flutter_app`
3. Wait for Gradle sync to complete
4. Install Flutter + Dart plugins if prompted (File → Settings → Plugins)

---

## Step 3 — Configure the API URL

Open `lib/config/api_config.dart`:

```dart
// For Android Emulator (10.0.2.2 maps to your PC's localhost):
static const String baseUrl = 'http://10.0.2.2/HealthSphere/api/v1';

// For Physical Device (replace with your PC's local IP):
static const String baseUrl = 'http://192.168.1.X/HealthSphere/api/v1';
```

To find your PC's IP: run `ipconfig` in Command Prompt → look for IPv4 Address under your WiFi adapter.

**IMPORTANT:** Your phone and PC must be on the same WiFi network.

---

## Step 4 — Run the App

### On Emulator:
1. Open AVD Manager → Create Virtual Device → Pixel 6 → API 33
2. Press the Run button (▶) or run: `flutter run`

### On Physical Device:
1. Enable Developer Options on your Android phone (tap Build Number 7 times)
2. Enable USB Debugging
3. Connect via USB
4. Run: `flutter run`

---

## Step 5 — Test Login

Use any existing account from your XAMPP database.  
Demo credentials (from setup): `emma.patient@nhs.uk` / `password`

---

## Build Release APK

```bash
flutter build apk --release
```

Output: `build/app/outputs/flutter-apk/app-release.apk`

To install on a phone: `flutter install`

---

## Project Structure

```
flutter_app/
├── lib/
│   ├── main.dart                    # App entry + navigation shell
│   ├── config/
│   │   ├── api_config.dart          # ← Change baseUrl here
│   │   └── theme.dart               # Colors, fonts, button styles
│   ├── services/
│   │   ├── api_service.dart         # All HTTP calls to PHP API
│   │   └── auth_provider.dart       # Auth state (ChangeNotifier)
│   ├── widgets/
│   │   └── hs_widgets.dart          # Reusable UI components
│   └── screens/
│       ├── auth/                    # Login + Register
│       ├── dashboard/               # Home dashboard with vitals
│       ├── appointments/            # List + Book appointment
│       ├── health/                  # Charts + metrics + insights
│       ├── diet/                    # Diet log tracker
│       ├── messages/                # Chat with doctors
│       └── profile/                 # Profile + prescriptions
└── android/                         # Android-specific config
```

## REST API Endpoints (PHP)

| File | Actions |
|------|---------|
| `api/v1/auth.php` | login, register, me, refresh |
| `api/v1/dashboard.php` | (GET) vitals, appointments, meds |
| `api/v1/health.php` | list, latest, add, score, insights |
| `api/v1/appointments.php` | list, book, cancel, doctors, slots |
| `api/v1/messages.php` | conversations, thread, send, contacts |
| `api/v1/diet.php` | today, list, add, delete, summary |
| `api/v1/notifications.php` | list, unread, mark_read |
| `api/v1/profile.php` | get, update, change_password, allergies, prescriptions |

All endpoints require `Authorization: Bearer <token>` header (except auth login/register).
