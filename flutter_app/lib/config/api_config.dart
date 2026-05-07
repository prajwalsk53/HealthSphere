class ApiConfig {
  // ── Change this to your machine's local IP when testing on a physical device ──
  // Emulator:        http://10.0.2.2/HealthSphere/api/v1
  // Physical device: http://192.168.x.x/HealthSphere/api/v1
static const String baseUrl = 'http://172.20.10.2/HealthSphere/api/v1';
  static String auth(String action)          => '$baseUrl/auth.php?action=$action';
  static String dashboard()                  => '$baseUrl/dashboard.php';
  static String health(String action)        => '$baseUrl/health.php?action=$action';
  static String appointments(String action)  => '$baseUrl/appointments.php?action=$action';
  static String messages(String action)      => '$baseUrl/messages.php?action=$action';
  static String diet(String action)          => '$baseUrl/diet.php?action=$action';
  static String notifications(String action) => '$baseUrl/notifications.php?action=$action';
  static String profile(String action)       => '$baseUrl/profile.php?action=$action';
}
