import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  String _role = 'patient';
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  final _first = TextEditingController();
  final _last  = TextEditingController();
  final _email = TextEditingController();
  final _pass  = TextEditingController();
  final _phone = TextEditingController();

  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });
    try {
      final result = await ApiService.register({
        'role': _role,
        'first_name': _first.text.trim(),
        'last_name':  _last.text.trim(),
        'email':      _email.text.trim(),
        'password':   _pass.text,
        'phone':      _phone.text.trim(),
      });
      if (!mounted) return;
      showDialog(context: context, builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Success!'),
        content: Text(result['message'] ?? 'Account created.'),
        actions: [
          ElevatedButton(
            onPressed: () { Navigator.pop(context); Navigator.pop(context); },
            child: const Text('Sign In'),
          ),
        ],
      ));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Connection error. Try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Account'),
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
      ),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF5EA8F0), Color(0xFF4285E8), Color(0xFF3270D6)],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Form(key: _formKey, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Text('Join HealthSphere', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                const SizedBox(height: 20),

                // Role selector
                Row(children: [
                  _roleBtn('patient', Icons.person_rounded, 'Patient'),
                  const SizedBox(width: 8),
                  _roleBtn('doctor', Icons.medical_services_rounded, 'Doctor'),
                ]),
                const SizedBox(height: 16),

                if (_role != 'patient')
                  Container(
                    padding: const EdgeInsets.all(10),
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: AppTheme.warning.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppTheme.warning.withOpacity(0.4)),
                    ),
                    child: const Row(children: [
                      Icon(Icons.info_outline, color: AppTheme.warning, size: 16),
                      SizedBox(width: 8),
                      Expanded(child: Text('Doctor accounts require admin approval (1-2 business days).', style: TextStyle(fontSize: 12, color: AppTheme.warning))),
                    ]),
                  ),

                if (_error != null)
                  Container(
                    padding: const EdgeInsets.all(10),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
                    child: Text(_error!, style: const TextStyle(color: AppTheme.danger, fontSize: 13)),
                  ),

                Row(children: [
                  Expanded(child: TextFormField(controller: _first, decoration: const InputDecoration(labelText: 'First Name'), validator: (v) => v!.isEmpty ? 'Required' : null)),
                  const SizedBox(width: 12),
                  Expanded(child: TextFormField(controller: _last, decoration: const InputDecoration(labelText: 'Last Name'), validator: (v) => v!.isEmpty ? 'Required' : null)),
                ]),
                const SizedBox(height: 14),
                TextFormField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)), validator: (v) => v!.isEmpty ? 'Required' : null),
                const SizedBox(height: 14),
                TextFormField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone (optional)', prefixIcon: Icon(Icons.phone_outlined))),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _pass, obscureText: _obscure,
                  decoration: InputDecoration(
                    labelText: 'Password', prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => setState(() => _obscure = !_obscure)),
                  ),
                  validator: (v) => (v?.length ?? 0) < 8 ? 'Min 8 characters' : null,
                ),
                const SizedBox(height: 24),

                ElevatedButton(
                  onPressed: _loading ? null : _register,
                  child: _loading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Create Account'),
                ),
              ])),
            ),
          ),
        ),
      ),
    );
  }

  Widget _roleBtn(String value, IconData icon, String label) => Expanded(
    child: GestureDetector(
      onTap: () => setState(() => _role = value),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: _role == value ? AppTheme.primary : const Color(0xFFF0F4FF),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(children: [
          Icon(icon, color: _role == value ? Colors.white : AppTheme.primary, size: 22),
          const SizedBox(height: 4),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: _role == value ? Colors.white : AppTheme.primary)),
        ]),
      ),
    ),
  );
}
