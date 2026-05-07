import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';

class ApiService {
  static const String _tokenKey = 'jwt_token';
  static const String _userKey  = 'user_data';

  // ── Token management ─────────────────────────────────────────────────
  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  static Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  static Future<void> saveUser(Map<String, dynamic> user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_userKey, jsonEncode(user));
  }

  static Future<Map<String, dynamic>?> getUser() async {
    final prefs = await SharedPreferences.getInstance();
    final s = prefs.getString(_userKey);
    if (s == null) return null;
    return jsonDecode(s) as Map<String, dynamic>;
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
  }

  // ── HTTP helpers ──────────────────────────────────────────────────────
  static Future<Map<String, String>> _headers({bool auth = true}) async {
    final headers = {'Content-Type': 'application/json'};
    if (auth) {
      final token = await getToken();
      if (token != null) headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  static Map<String, dynamic> _parse(http.Response res) {
    final body = jsonDecode(res.body) as Map<String, dynamic>;
    if (body['success'] == true) return body['data'] as Map<String, dynamic>? ?? body;
    throw ApiException(body['error'] ?? 'Unknown error', res.statusCode);
  }

  static List<dynamic> _parseList(http.Response res) {
    final body = jsonDecode(res.body) as Map<String, dynamic>;
    if (body['success'] == true) return body['data'] as List<dynamic>? ?? [];
    throw ApiException(body['error'] ?? 'Unknown error', res.statusCode);
  }

  // ── Auth ──────────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> login(String email, String password) async {
    final res = await http.post(
      Uri.parse(ApiConfig.auth('login')),
      headers: await _headers(auth: false),
      body: jsonEncode({'email': email, 'password': password}),
    );
    final data = _parse(res);
    await saveToken(data['token'] as String);
    await saveUser(data['user'] as Map<String, dynamic>);
    return data;
  }

  static Future<Map<String, dynamic>> register(Map<String, dynamic> body) async {
    final res = await http.post(
      Uri.parse(ApiConfig.auth('register')),
      headers: await _headers(auth: false),
      body: jsonEncode(body),
    );
    return _parse(res);
  }

  static Future<Map<String, dynamic>> getMe() async {
    final res = await http.get(
      Uri.parse(ApiConfig.auth('me')),
      headers: await _headers(),
    );
    return _parse(res);
  }

  // ── Dashboard ─────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> getDashboard() async {
    final res = await http.get(
      Uri.parse(ApiConfig.dashboard()),
      headers: await _headers(),
    );
    return _parse(res);
  }

  // ── Health ────────────────────────────────────────────────────────────
  static Future<List<dynamic>> getHealthMetrics({int limit = 30}) async {
    final res = await http.get(
      Uri.parse('${ApiConfig.health('list')}&limit=$limit'),
      headers: await _headers(),
    );
    return _parseList(res);
  }

  static Future<Map<String, dynamic>> getHealthScore() async {
    final res = await http.get(
      Uri.parse(ApiConfig.health('score')),
      headers: await _headers(),
    );
    return _parse(res);
  }

  static Future<List<dynamic>> getHealthInsights() async {
    final res = await http.get(
      Uri.parse(ApiConfig.health('insights')),
      headers: await _headers(),
    );
    return _parseList(res);
  }

  static Future<Map<String, dynamic>> addHealthMetric(Map<String, dynamic> data) async {
    final res = await http.post(
      Uri.parse(ApiConfig.health('add')),
      headers: await _headers(),
      body: jsonEncode(data),
    );
    return _parse(res);
  }

  // ── Appointments ──────────────────────────────────────────────────────
  static Future<List<dynamic>> getAppointments({String? status, bool upcoming = false}) async {
    String url = ApiConfig.appointments('list');
    if (status != null) url += '&status=$status';
    if (upcoming) url += '&upcoming=1';
    final res = await http.get(Uri.parse(url), headers: await _headers());
    return _parseList(res);
  }

  static Future<List<dynamic>> getDoctors({String? q}) async {
    String url = ApiConfig.appointments('doctors');
    if (q != null) url += '&q=${Uri.encodeComponent(q)}';
    final res = await http.get(Uri.parse(url), headers: await _headers());
    return _parseList(res);
  }

  static Future<List<dynamic>> getSlots(int doctorId, String date) async {
    final res = await http.get(
      Uri.parse('${ApiConfig.appointments('slots')}&doctor_id=$doctorId&date=$date'),
      headers: await _headers(),
    );
    return _parseList(res);
  }

  static Future<Map<String, dynamic>> bookAppointment(Map<String, dynamic> data) async {
    final res = await http.post(
      Uri.parse(ApiConfig.appointments('book')),
      headers: await _headers(),
      body: jsonEncode(data),
    );
    return _parse(res);
  }

  static Future<void> cancelAppointment(int id) async {
    final res = await http.post(
      Uri.parse('${ApiConfig.appointments('cancel')}&id=$id'),
      headers: await _headers(),
    );
    _parse(res);
  }

  // ── Messages ──────────────────────────────────────────────────────────
  static Future<List<dynamic>> getConversations() async {
    final res = await http.get(Uri.parse(ApiConfig.messages('conversations')), headers: await _headers());
    return _parseList(res);
  }

  static Future<List<dynamic>> getThread(int userId) async {
    final res = await http.get(
      Uri.parse('${ApiConfig.messages('thread')}&user_id=$userId'),
      headers: await _headers(),
    );
    return _parseList(res);
  }

  static Future<void> sendMessage(int receiverId, String text) async {
    final res = await http.post(
      Uri.parse(ApiConfig.messages('send')),
      headers: await _headers(),
      body: jsonEncode({'receiver_id': receiverId, 'message': text}),
    );
    _parse(res);
  }

  static Future<List<dynamic>> getContacts() async {
    final res = await http.get(Uri.parse(ApiConfig.messages('contacts')), headers: await _headers());
    return _parseList(res);
  }

  // ── Diet ──────────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> getTodayDiet() async {
    final res = await http.get(Uri.parse(ApiConfig.diet('today')), headers: await _headers());
    return _parse(res);
  }

  static Future<List<dynamic>> getDietSummary() async {
    final res = await http.get(Uri.parse(ApiConfig.diet('summary')), headers: await _headers());
    return _parseList(res);
  }

  static Future<void> addDietLog(Map<String, dynamic> data) async {
    final res = await http.post(
      Uri.parse(ApiConfig.diet('add')),
      headers: await _headers(),
      body: jsonEncode(data),
    );
    _parse(res);
  }

  static Future<void> deleteDietLog(int id) async {
    final res = await http.post(
      Uri.parse('${ApiConfig.diet('delete')}&id=$id'),
      headers: await _headers(),
    );
    _parse(res);
  }

  // ── Notifications ─────────────────────────────────────────────────────
  static Future<List<dynamic>> getNotifications() async {
    final res = await http.get(Uri.parse(ApiConfig.notifications('list')), headers: await _headers());
    return _parseList(res);
  }

  static Future<void> markNotificationsRead({int? id}) async {
    String url = ApiConfig.notifications('mark_read');
    if (id != null) url += '&id=$id';
    await http.post(Uri.parse(url), headers: await _headers());
  }

  // ── Profile ───────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> getProfile() async {
    final res = await http.get(Uri.parse(ApiConfig.profile('get')), headers: await _headers());
    return _parse(res);
  }

  static Future<void> updateProfile(Map<String, dynamic> data) async {
    final res = await http.post(
      Uri.parse(ApiConfig.profile('update')),
      headers: await _headers(),
      body: jsonEncode(data),
    );
    _parse(res);
  }

  static Future<void> changePassword(String oldPass, String newPass) async {
    final res = await http.post(
      Uri.parse(ApiConfig.profile('change_password')),
      headers: await _headers(),
      body: jsonEncode({'old_password': oldPass, 'new_password': newPass}),
    );
    _parse(res);
  }

  static Future<List<dynamic>> getPrescriptions() async {
    final res = await http.get(
      Uri.parse('${ApiConfig.profile('prescriptions')}&active=1'),
      headers: await _headers(),
    );
    return _parseList(res);
  }
}

class ApiException implements Exception {
  final String message;
  final int statusCode;
  ApiException(this.message, this.statusCode);
  @override
  String toString() => message;
}
