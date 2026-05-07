import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../widgets/hs_widgets.dart';
import 'book_appointment_screen.dart';

class AppointmentsScreen extends StatefulWidget {
  const AppointmentsScreen({super.key});
  @override
  State<AppointmentsScreen> createState() => _AppointmentsScreenState();
}

class _AppointmentsScreenState extends State<AppointmentsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabs;
  List<dynamic> _upcoming = [];
  List<dynamic> _past = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final all = await ApiService.getAppointments();
      final now = DateTime.now();
      if (mounted) {
        setState(() {
        _upcoming = all.where((a) {
          final d = DateTime.tryParse(a['appointment_date'] ?? '') ?? DateTime(2000);
          return d.isAfter(now.subtract(const Duration(days: 1))) && a['status'] != 'cancelled';
        }).toList();
        _past = all.where((a) {
          final d = DateTime.tryParse(a['appointment_date'] ?? '') ?? DateTime(2000);
          return d.isBefore(now) || a['status'] == 'cancelled';
        }).toList();
        _loading = false;
      });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _cancel(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Cancel Appointment'),
        content: const Text('Are you sure you want to cancel this appointment?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('No')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Yes, Cancel'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await ApiService.cancelAppointment(id);
      _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Appointments'),
        bottom: TabBar(
          controller: _tabs,
          labelColor: AppTheme.primary,
          unselectedLabelColor: const Color(0xFF9E9E9E),
          indicatorColor: AppTheme.primary,
          tabs: const [Tab(text: 'Upcoming'), Tab(text: 'Past')],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const BookAppointmentScreen()))
            .then((_) => _load()),
        icon: const Icon(Icons.add),
        label: const Text('Book'),
        backgroundColor: AppTheme.primary,
      ),
      body: _loading
          ? const LoadingOverlay()
          : TabBarView(
              controller: _tabs,
              children: [
                _list(_upcoming, isUpcoming: true),
                _list(_past, isUpcoming: false),
              ],
            ),
    );
  }

  Widget _list(List<dynamic> items, {required bool isUpcoming}) {
    if (items.isEmpty) {
      return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.calendar_today_rounded, size: 56, color: Color(0xFFCCCCCC)),
        const SizedBox(height: 12),
        Text(isUpcoming ? 'No upcoming appointments' : 'No past appointments',
            style: const TextStyle(color: Color(0xFF9E9E9E))),
        if (isUpcoming) ...[
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const BookAppointmentScreen())).then((_) => _load()),
            child: const Text('Book Now'),
          ),
        ],
      ]));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: items.length,
        itemBuilder: (_, i) => AppointmentTile(
          appointment: items[i] as Map<String, dynamic>,
          onCancel: isUpcoming ? () => _cancel(items[i]['id'] as int) : null,
        ),
      ),
    );
  }
}
