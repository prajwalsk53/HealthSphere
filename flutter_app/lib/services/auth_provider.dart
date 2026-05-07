import 'package:flutter/material.dart';
import 'api_service.dart';

class AuthProvider extends ChangeNotifier {
  Map<String, dynamic>? _user;
  bool _loading = true;

  Map<String, dynamic>? get user => _user;
  bool get isLoggedIn => _user != null;
  bool get loading => _loading;

  String get fullName => '${_user?['first_name'] ?? ''} ${_user?['last_name'] ?? ''}'.trim();
  String get role => _user?['role'] ?? 'patient';

  Future<void> init() async {
    _user = await ApiService.getUser();
    _loading = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final data = await ApiService.login(email, password);
    _user = data['user'] as Map<String, dynamic>;
    notifyListeners();
  }

  Future<void> logout() async {
    await ApiService.logout();
    _user = null;
    notifyListeners();
  }

  void updateUser(Map<String, dynamic> updated) {
    _user = {...?_user, ...updated};
    ApiService.saveUser(_user!);
    notifyListeners();
  }
}
