import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';

class BookAppointmentScreen extends StatefulWidget {
  const BookAppointmentScreen({super.key});
  @override
  State<BookAppointmentScreen> createState() => _BookAppointmentScreenState();
}

class _BookAppointmentScreenState extends State<BookAppointmentScreen> {
  int _step = 0;
  List<dynamic> _doctors = [];
  Map<String, dynamic>? _selectedDoctor;
  DateTime _selectedDate = DateTime.now().add(const Duration(days: 1));
  String? _selectedTime;
  List<dynamic> _slots = [];
  final _reasonCtrl = TextEditingController();
  bool _loading = false;
  final _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadDoctors();
  }

  Future<void> _loadDoctors({String? q}) async {
    setState(() => _loading = true);
    try {
      final docs = await ApiService.getDoctors(q: q);
      if (mounted) setState(() { _doctors = docs; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadSlots() async {
    if (_selectedDoctor == null) return;
    setState(() { _loading = true; _selectedTime = null; });
    try {
      final date = DateFormat('yyyy-MM-dd').format(_selectedDate);
      final slots = await ApiService.getSlots(_selectedDoctor!['id'] as int, date);
      if (mounted) setState(() { _slots = slots; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _book() async {
    if (_selectedDoctor == null || _selectedTime == null) return;
    setState(() => _loading = true);
    try {
      await ApiService.bookAppointment({
        'doctor_id': _selectedDoctor!['id'],
        'date': DateFormat('yyyy-MM-dd').format(_selectedDate),
        'time': _selectedTime,
        'reason': _reasonCtrl.text.trim(),
      });
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Appointment booked!'), backgroundColor: AppTheme.success),
      );
    } on ApiException catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message), backgroundColor: AppTheme.danger));
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Book Appointment')),
      body: Column(children: [
        _stepper(),
        Expanded(child: [
          _stepDoctor(),
          _stepDateTime(),
          _stepConfirm(),
        ][_step]),
      ]),
    );
  }

  Widget _stepper() => Container(
    color: Colors.white,
    padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 24),
    child: Row(children: List.generate(3, (i) {
      final labels = ['Doctor', 'Date & Time', 'Confirm'];
      final active = i <= _step;
      return Expanded(child: Row(children: [
        Container(
          width: 28, height: 28,
          decoration: BoxDecoration(
            color: active ? AppTheme.primary : const Color(0xFFE0E0E0),
            shape: BoxShape.circle,
          ),
          child: Center(child: Text('${i + 1}', style: TextStyle(color: active ? Colors.white : const Color(0xFF9E9E9E), fontSize: 12, fontWeight: FontWeight.bold))),
        ),
        const SizedBox(width: 6),
        Text(labels[i], style: TextStyle(fontSize: 12, color: active ? AppTheme.primary : const Color(0xFF9E9E9E), fontWeight: active ? FontWeight.w600 : FontWeight.normal)),
        if (i < 2) Expanded(child: Container(height: 1, color: const Color(0xFFE0E0E0), margin: const EdgeInsets.symmetric(horizontal: 6))),
      ]));
    })),
  );

  Widget _stepDoctor() => Column(children: [
    Padding(
      padding: const EdgeInsets.all(16),
      child: TextField(
        controller: _searchCtrl,
        decoration: InputDecoration(
          hintText: 'Search doctors or specialization...',
          prefixIcon: const Icon(Icons.search),
          suffixIcon: _searchCtrl.text.isNotEmpty
              ? IconButton(icon: const Icon(Icons.clear), onPressed: () { _searchCtrl.clear(); _loadDoctors(); })
              : null,
        ),
        onSubmitted: (v) => _loadDoctors(q: v),
        onChanged: (v) { if (v.isEmpty) _loadDoctors(); },
      ),
    ),
    if (_loading) const Expanded(child: Center(child: CircularProgressIndicator()))
    else Expanded(child: ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: _doctors.length,
      itemBuilder: (_, i) {
        final doc = _doctors[i] as Map<String, dynamic>;
        final sel = _selectedDoctor?['id'] == doc['id'];
        return GestureDetector(
          onTap: () => setState(() { _selectedDoctor = doc; _step = 1; _loadSlots(); }),
          child: Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: sel ? AppTheme.primary.withOpacity(0.08) : Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: sel ? AppTheme.primary : Colors.transparent, width: 2),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 8)],
            ),
            child: Row(children: [
              CircleAvatar(radius: 24, backgroundColor: AppTheme.primary.withOpacity(0.12), child: Text('${doc['first_name']?[0] ?? '?'}${doc['last_name']?[0] ?? '?'}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary))),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Dr. ${doc['first_name']} ${doc['last_name']}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                Text(doc['specialization'] ?? 'General', style: const TextStyle(color: Color(0xFF777777), fontSize: 12)),
                Text(doc['hospital_name'] ?? '', style: const TextStyle(color: Color(0xFF9E9E9E), fontSize: 11)),
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                if (doc['is_verified'] == 1) const Icon(Icons.verified_rounded, color: AppTheme.primary, size: 16),
                Text('£${doc['consultation_fee'] ?? 0}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary, fontSize: 13)),
              ]),
            ]),
          ),
        );
      },
    )),
  ]);

  Widget _stepDateTime() => SingleChildScrollView(
    padding: const EdgeInsets.all(16),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (_selectedDoctor != null)
        Container(
          padding: const EdgeInsets.all(12),
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(color: AppTheme.primary.withOpacity(0.08), borderRadius: BorderRadius.circular(12)),
          child: Row(children: [
            const Icon(Icons.person_rounded, color: AppTheme.primary),
            const SizedBox(width: 8),
            Text('Dr. ${_selectedDoctor!['first_name']} ${_selectedDoctor!['last_name']}', style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.primary)),
          ]),
        ),

      const Text('Select Date', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
      const SizedBox(height: 8),
      SizedBox(
        height: 80,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: 14,
          itemBuilder: (_, i) {
            final d = DateTime.now().add(Duration(days: i + 1));
            final sel = DateFormat('yyyy-MM-dd').format(d) == DateFormat('yyyy-MM-dd').format(_selectedDate);
            return GestureDetector(
              onTap: () { setState(() => _selectedDate = d); _loadSlots(); },
              child: Container(
                width: 60, margin: const EdgeInsets.only(right: 8),
                decoration: BoxDecoration(
                  color: sel ? AppTheme.primary : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: sel ? AppTheme.primary : const Color(0xFFE0E0E0)),
                ),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(DateFormat('EEE').format(d), style: TextStyle(fontSize: 11, color: sel ? Colors.white70 : const Color(0xFF9E9E9E))),
                  Text('${d.day}', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: sel ? Colors.white : const Color(0xFF1A1A2E))),
                  Text(DateFormat('MMM').format(d), style: TextStyle(fontSize: 11, color: sel ? Colors.white70 : const Color(0xFF9E9E9E))),
                ]),
              ),
            );
          },
        ),
      ),

      const SizedBox(height: 20),
      const Text('Available Slots', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
      const SizedBox(height: 8),
      if (_loading) const Center(child: CircularProgressIndicator())
      else Wrap(
        spacing: 8, runSpacing: 8,
        children: _slots.map((s) {
          final avail = s['available'] as bool;
          final sel = _selectedTime == s['time'];
          return GestureDetector(
            onTap: avail ? () => setState(() => _selectedTime = s['time']) : null,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: !avail ? const Color(0xFFF5F5F5) : sel ? AppTheme.primary : Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: sel ? AppTheme.primary : const Color(0xFFE0E0E0)),
              ),
              child: Text(
                s['time'] as String,
                style: TextStyle(
                  color: !avail ? const Color(0xFFCCCCCC) : sel ? Colors.white : const Color(0xFF333333),
                  fontWeight: sel ? FontWeight.w600 : FontWeight.normal,
                  fontSize: 13,
                  decoration: !avail ? TextDecoration.lineThrough : null,
                ),
              ),
            ),
          );
        }).toList(),
      ),

      const SizedBox(height: 24),
      ElevatedButton(
        onPressed: _selectedTime == null ? null : () => setState(() => _step = 2),
        style: ElevatedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
        child: const Text('Continue'),
      ),
    ]),
  );

  Widget _stepConfirm() => SingleChildScrollView(
    padding: const EdgeInsets.all(16),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Appointment Summary', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const Divider(height: 24),
          _row(Icons.person_rounded, 'Doctor', 'Dr. ${_selectedDoctor?['first_name'] ?? ''} ${_selectedDoctor?['last_name'] ?? ''}'),
          _row(Icons.medical_services_rounded, 'Specialization', _selectedDoctor?['specialization'] ?? ''),
          _row(Icons.calendar_today_rounded, 'Date', DateFormat('EEEE, d MMMM yyyy').format(_selectedDate)),
          _row(Icons.access_time_rounded, 'Time', _selectedTime ?? ''),
          _row(Icons.local_hospital_rounded, 'Hospital', _selectedDoctor?['hospital_name'] ?? ''),
        ]),
      ),
      const SizedBox(height: 16),
      TextField(
        controller: _reasonCtrl,
        maxLines: 3,
        decoration: const InputDecoration(
          labelText: 'Reason for visit (optional)',
          alignLabelWithHint: true,
        ),
      ),
      const SizedBox(height: 24),
      ElevatedButton(
        onPressed: _loading ? null : _book,
        style: ElevatedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
        child: _loading
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : const Text('Confirm Booking'),
      ),
      const SizedBox(height: 12),
      OutlinedButton(
        onPressed: () => setState(() => _step = 1),
        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
        child: const Text('Back'),
      ),
    ]),
  );

  Widget _row(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(children: [
      Icon(icon, size: 18, color: AppTheme.primary),
      const SizedBox(width: 10),
      Text('$label: ', style: const TextStyle(color: Color(0xFF777777), fontSize: 13)),
      Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
    ]),
  );
}
