import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../services/auth_provider.dart';
import '../../services/api_service.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey  = GlobalKey<FormState>();
  final _emailCtrl= TextEditingController();
  final _passCtrl = TextEditingController();
  bool _obscure   = true;
  bool _loading   = false;
  String? _error;

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });
    try {
      await context.read<AuthProvider>().login(_emailCtrl.text.trim(), _passCtrl.text);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Error: $e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF5EA8F0), Color(0xFF4285E8), Color(0xFF3270D6), Color(0xFF5A4FCE)],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(children: [
                // Logo
                Container(
                  width: 80, height: 80,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: const Icon(Icons.favorite_rounded, color: Colors.white, size: 40),
                ),
                const SizedBox(height: 16),
                const Text('HealthSphere', style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: Colors.white)),
                const Text('NHS Digital Health Platform', style: TextStyle(color: Colors.white70, fontSize: 14)),
                const SizedBox(height: 40),

                // Card
                Container(
                  padding: const EdgeInsets.all(28),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.15), blurRadius: 30, offset: const Offset(0, 10))],
                  ),
                  child: Form(key: _formKey, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    const Text('Sign In', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF1A1A2E))),
                    const SizedBox(height: 4),
                    const Text('Access your health dashboard', style: TextStyle(color: Color(0xFF777777), fontSize: 13)),
                    const SizedBox(height: 24),

                    if (_error != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 16),
                        decoration: BoxDecoration(
                          color: AppTheme.danger.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: AppTheme.danger.withOpacity(0.3)),
                        ),
                        child: Row(children: [
                          const Icon(Icons.error_outline, color: AppTheme.danger, size: 18),
                          const SizedBox(width: 8),
                          Expanded(child: Text(_error!, style: const TextStyle(color: AppTheme.danger, fontSize: 13))),
                        ]),
                      ),

                    TextFormField(
                      controller: _emailCtrl,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        labelText: 'Email Address',
                        prefixIcon: Icon(Icons.email_outlined),
                      ),
                      validator: (v) => (v?.isEmpty ?? true) ? 'Email required' : null,
                    ),
                    const SizedBox(height: 16),

                    TextFormField(
                      controller: _passCtrl,
                      obscureText: _obscure,
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          onPressed: () => setState(() => _obscure = !_obscure),
                        ),
                      ),
                      validator: (v) => (v?.isEmpty ?? true) ? 'Password required' : null,
                      onFieldSubmitted: (_) => _login(),
                    ),
                    const SizedBox(height: 28),

                    ElevatedButton(
                      onPressed: _loading ? null : _login,
                      child: _loading
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Text('Sign In'),
                    ),
                    const SizedBox(height: 20),

                    Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Text("Don't have an account? ", style: TextStyle(color: Color(0xFF777777))),
                      GestureDetector(
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())),
                        child: const Text('Register', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.w600)),
                      ),
                    ]),
                  ])),
                ),

                const SizedBox(height: 24),
                const Text('Secure NHS Login  •  v1.0', style: TextStyle(color: Colors.white54, fontSize: 12)),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
