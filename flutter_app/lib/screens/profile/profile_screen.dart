import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/auth_provider.dart';
import '../../widgets/hs_widgets.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? _profile;
  List<dynamic> _prescriptions = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final results = await Future.wait([
        ApiService.getProfile(),
        ApiService.getPrescriptions(),
      ]);
      if (mounted) {
        setState(() {
        _profile       = results[0] as Map<String, dynamic>;
        _prescriptions = results[1] as List<dynamic>;
        _loading       = false;
      });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Sign Out'),
        content: const Text('Are you sure you want to sign out?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Sign Out'),
          ),
        ],
      ),
    );
    if (confirm == true && mounted) {
      await context.read<AuthProvider>().logout();
    }
  }

  void _changePassword() {
    final oldPass = TextEditingController();
    final newPass = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 20, right: 20, top: 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Text('Change Password', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 16),
          TextField(controller: oldPass, obscureText: true, decoration: const InputDecoration(labelText: 'Current Password')),
          const SizedBox(height: 12),
          TextField(controller: newPass, obscureText: true, decoration: const InputDecoration(labelText: 'New Password (min 8 chars)')),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () async {
              if (oldPass.text.isEmpty || newPass.text.length < 8) {
                ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Please fill both fields (min 8 chars for new password)')));
                return;
              }
              Navigator.pop(ctx);
              try {
                await ApiService.changePassword(oldPass.text, newPass.text);
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Password changed!'), backgroundColor: AppTheme.success));
              } on ApiException catch (e) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message), backgroundColor: AppTheme.danger));
              }
            },
            child: const Text('Update Password'),
          ),
          const SizedBox(height: 16),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          IconButton(icon: const Icon(Icons.logout_rounded), onPressed: _logout, tooltip: 'Sign Out'),
        ],
      ),
      body: _loading
          ? const LoadingOverlay()
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Profile header
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(colors: [Color(0xFF4285E8), Color(0xFF5A4FCE)]),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Column(children: [
                      CircleAvatar(
                        radius: 36,
                        backgroundColor: Colors.white.withOpacity(0.2),
                        child: Text(
                          '${(_profile?['first_name'] as String? ?? '?')[0]}${(_profile?['last_name'] as String? ?? '')[0]}',
                          style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text('${_profile?['first_name']} ${_profile?['last_name']}', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                      Text(_profile?['email'] ?? '', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                      const SizedBox(height: 12),
                      NhsBadge(nhsId: _profile?['nhs_id'] ?? ''),
                    ]),
                  ),
                  const SizedBox(height: 20),

                  // Personal info
                  _card('Personal Information', [
                    _infoRow(Icons.person_rounded, 'Full Name', '${_profile?['first_name']} ${_profile?['last_name']}'),
                    _infoRow(Icons.email_rounded, 'Email', _profile?['email'] ?? ''),
                    _infoRow(Icons.phone_rounded, 'Phone', _profile?['phone'] ?? 'Not set'),
                    _infoRow(Icons.cake_rounded, 'Date of Birth', _profile?['date_of_birth'] ?? 'Not set'),
                    _infoRow(Icons.wc_rounded, 'Gender', _profile?['gender'] ?? 'Not set'),
                    _infoRow(Icons.bloodtype_rounded, 'Blood Type', _profile?['blood_type'] ?? 'Not set'),
                  ]),
                  const SizedBox(height: 16),

                  // Active prescriptions
                  if (_prescriptions.isNotEmpty) ...[
                    const SectionHeader(title: 'Active Prescriptions'),
                    const SizedBox(height: 10),
                    ..._prescriptions.map((p) => _prescriptionTile(p as Map<String, dynamic>)),
                    const SizedBox(height: 16),
                  ],

                  // Actions
                  _card('Account', [
                    _actionRow(Icons.lock_outline_rounded, 'Change Password', _changePassword),
                    _actionRow(Icons.logout_rounded, 'Sign Out', _logout, color: AppTheme.danger),
                  ]),

                  const SizedBox(height: 20),
                  const Center(child: Text('HealthSphere v1.0 · NHS Digital Health', style: TextStyle(color: Color(0xFF9E9E9E), fontSize: 12))),
                  const SizedBox(height: 20),
                ],
              ),
            ),
    );
  }

  Widget _card(String title, List<Widget> children) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
      const SizedBox(height: 8),
      Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
        child: Column(children: children),
      ),
    ],
  );

  Widget _infoRow(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    child: Row(children: [
      Icon(icon, size: 18, color: AppTheme.primary),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E))),
        Text(value.isNotEmpty ? value : 'Not set', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
      ])),
    ]),
  );

  Widget _actionRow(IconData icon, String label, VoidCallback onTap, {Color? color}) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(16),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(children: [
        Icon(icon, size: 20, color: color ?? const Color(0xFF555555)),
        const SizedBox(width: 12),
        Expanded(child: Text(label, style: TextStyle(fontSize: 14, color: color ?? const Color(0xFF333333)))),
        Icon(Icons.chevron_right_rounded, size: 18, color: color ?? const Color(0xFFCCCCCC)),
      ]),
    ),
  );

  Widget _prescriptionTile(Map<String, dynamic> p) => Container(
    padding: const EdgeInsets.all(12),
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)]),
    child: Row(children: [
      Container(
        width: 36, height: 36,
        decoration: BoxDecoration(color: AppTheme.success.withOpacity(0.12), borderRadius: BorderRadius.circular(8)),
        child: const Icon(Icons.medication_rounded, color: AppTheme.success, size: 18),
      ),
      const SizedBox(width: 10),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(p['medication_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        Text('${p['dosage'] ?? ''} · ${p['frequency'] ?? ''}', style: const TextStyle(color: Color(0xFF777777), fontSize: 12)),
        Text('Dr. ${p['doctor_first'] ?? ''} ${p['doctor_last'] ?? ''}', style: const TextStyle(color: Color(0xFF9E9E9E), fontSize: 11)),
      ])),
    ]),
  );
}
