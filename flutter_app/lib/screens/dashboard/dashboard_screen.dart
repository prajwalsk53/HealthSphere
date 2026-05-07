import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/auth_provider.dart';
import '../../widgets/hs_widgets.dart';
import '../appointments/appointments_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});
  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final d = await ApiService.getDashboard();
      if (mounted) setState(() { _data = d; _loading = false; });
    } on ApiException catch (e) {
      if (mounted) setState(() { _error = e.message; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _error = 'Failed to load dashboard'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final vitals = _data?['vitals'] as Map<String, dynamic>? ?? {};
    final trend = _data?['health_trend'] as List<dynamic>? ?? [];
    final upcoming = _data?['upcoming_appointments'] as List<dynamic>? ?? [];
    final meds = _data?['active_medications'] as List<dynamic>? ?? [];

    return Scaffold(
      backgroundColor: AppTheme.surface,
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              expandedHeight: 160,
              floating: false,
              pinned: true,
              backgroundColor: AppTheme.primary,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFF4285E8), Color(0xFF5A4FCE)],
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                    ),
                  ),
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const SizedBox(height: 40),
                        Text('Good ${_greeting()},', style: const TextStyle(color: Colors.white70, fontSize: 14)),
                        Text(user?['first_name'] ?? 'User', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 8),
                        NhsBadge(nhsId: user?['nhs_id'] ?? ''),
                      ]),
                    ),
                  ),
                ),
              ),
              actions: [
                Stack(children: [
                  IconButton(
                    icon: const Icon(Icons.notifications_outlined, color: Colors.white),
                    onPressed: () {},
                  ),
                  if ((_data?['unread_notifications'] ?? 0) > 0)
                    Positioned(
                      right: 8, top: 8,
                      child: Container(
                        width: 16, height: 16,
                        decoration: const BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle),
                        child: Center(child: Text('${_data!['unread_notifications']}', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold))),
                      ),
                    ),
                ]),
              ],
            ),

            if (_loading)
              const SliverFillRemaining(child: LoadingOverlay())
            else if (_error != null)
              SliverFillRemaining(child: ErrorState(message: _error!, onRetry: _load))
            else
              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverList(delegate: SliverChildListDelegate([
                  // Vitals grid
                  const SectionHeader(title: 'Current Vitals'),
                  const SizedBox(height: 12),
                  LayoutBuilder(builder: (ctx, constraints) {
                    final cols = constraints.maxWidth > 700 ? 3 : 2;
                    final ratio = constraints.maxWidth > 700 ? 2.2 : 1.6;
                    return GridView.count(
                      crossAxisCount: cols, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
                      crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: ratio,
                      children: [
                        StatCard(label: 'Heart Rate', value: '${vitals['heart_rate'] ?? 0}', unit: 'bpm', icon: Icons.favorite_rounded, color: const Color(0xFFE74C3C)),
                        StatCard(label: 'Blood Pressure', value: vitals['bp'] ?? '0/0', unit: 'mmHg', icon: Icons.monitor_heart_rounded, color: const Color(0xFF8E44AD)),
                        StatCard(label: 'SpO2', value: '${vitals['spo2'] ?? 0}', unit: '%', icon: Icons.air_rounded, color: const Color(0xFF2980B9)),
                        StatCard(label: 'Steps Today', value: '${vitals['steps'] ?? 0}', unit: 'steps', icon: Icons.directions_walk_rounded, color: const Color(0xFF27AE60)),
                        StatCard(label: 'Sleep', value: '${vitals['sleep'] ?? 0}', unit: 'hrs', icon: Icons.bedtime_rounded, color: const Color(0xFF2C3E50)),
                        StatCard(
                          label: 'Calories',
                          value: '${_data?['calories_today'] ?? 0}',
                          unit: 'kcal',
                          icon: Icons.local_fire_department_rounded,
                          color: const Color(0xFFE67E22),
                          subtitle: 'Goal: ${_data?['calorie_goal'] ?? 2500}',
                        ),
                      ],
                    );
                  }),
                  const SizedBox(height: 24),

                  // Trend chart
                  if (trend.length >= 2) ...[
                    const SectionHeader(title: '7-Day Trend'),
                    const SizedBox(height: 12),
                    Container(
                      height: 180,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)],
                      ),
                      child: LineChart(_buildChart(trend)),
                    ),
                    const SizedBox(height: 24),
                  ],

                  // Upcoming appointments
                  SectionHeader(
                    title: 'Upcoming Appointments',
                    action: 'View All',
                    onAction: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AppointmentsScreen())),
                  ),
                  const SizedBox(height: 12),
                  if (upcoming.isEmpty)
                    _emptyCard('No upcoming appointments', Icons.calendar_today_rounded)
                  else
                    ...upcoming.map((a) => AppointmentTile(appointment: a as Map<String, dynamic>)),
                  const SizedBox(height: 24),

                  // Active medications
                  const SectionHeader(title: 'Active Medications'),
                  const SizedBox(height: 12),
                  if (meds.isEmpty)
                    _emptyCard('No active medications', Icons.medication_rounded)
                  else
                    ...meds.map((m) => _medTile(m as Map<String, dynamic>)),
                  const SizedBox(height: 20),
                ])),
              ),
          ],
        ),
      ),
    );
  }

  Widget _emptyCard(String text, IconData icon) => Container(
    padding: const EdgeInsets.all(20),
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
    child: Row(children: [
      Icon(icon, color: const Color(0xFFCCCCCC), size: 28),
      const SizedBox(width: 12),
      Text(text, style: const TextStyle(color: Color(0xFF9E9E9E))),
    ]),
  );

  Widget _medTile(Map<String, dynamic> m) => Container(
    padding: const EdgeInsets.all(12),
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)],
    ),
    child: Row(children: [
      Container(
        width: 40, height: 40,
        decoration: BoxDecoration(color: AppTheme.success.withOpacity(0.12), borderRadius: BorderRadius.circular(10)),
        child: const Icon(Icons.medication_rounded, color: AppTheme.success, size: 20),
      ),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(m['medication_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
        Text('${m['dosage'] ?? ''} · ${m['frequency'] ?? ''}', style: const TextStyle(color: Color(0xFF777777), fontSize: 12)),
      ])),
    ]),
  );

  LineChartData _buildChart(List<dynamic> trend) {
    final hrs = trend.map((t) => (t as Map<String, dynamic>)['heart_rate'] as num? ?? 0).toList();
    final spots = List.generate(hrs.length, (i) => FlSpot(i.toDouble(), hrs[i].toDouble()));

    return LineChartData(
      gridData: FlGridData(
        show: true,
        drawVerticalLine: false,
        getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey.withOpacity(0.1), strokeWidth: 1),
      ),
      titlesData: FlTitlesData(
        leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 32, getTitlesWidget: (v, m) => Text('${v.toInt()}', style: const TextStyle(fontSize: 10, color: Color(0xFF9E9E9E))))),
        bottomTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
      ),
      borderData: FlBorderData(show: false),
      lineBarsData: [
        LineChartBarData(
          spots: spots,
          isCurved: true,
          color: AppTheme.danger,
          barWidth: 2.5,
          belowBarData: BarAreaData(show: true, color: AppTheme.danger.withOpacity(0.1)),
          dotData: const FlDotData(show: false),
        ),
      ],
    );
  }

  String _greeting() {
    final h = DateTime.now().hour;
    if (h < 12) return 'morning';
    if (h < 17) return 'afternoon';
    return 'evening';
  }
}
