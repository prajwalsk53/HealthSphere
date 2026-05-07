import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../widgets/hs_widgets.dart';
import 'add_metric_screen.dart';

class HealthScreen extends StatefulWidget {
  const HealthScreen({super.key});
  @override
  State<HealthScreen> createState() => _HealthScreenState();
}

class _HealthScreenState extends State<HealthScreen> {
  List<dynamic> _metrics = [];
  Map<String, dynamic>? _score;
  List<dynamic> _insights = [];
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
        ApiService.getHealthMetrics(limit: 14),
        ApiService.getHealthScore(),
        ApiService.getHealthInsights(),
      ]);
      if (mounted) {
        setState(() {
        _metrics  = results[0] as List<dynamic>;
        _score    = results[1] as Map<String, dynamic>;
        _insights = results[2] as List<dynamic>;
        _loading  = false;
      });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Color get _scoreColor {
    final s = _score?['score'] as int? ?? 0;
    if (s >= 80) return AppTheme.success;
    if (s >= 60) return AppTheme.warning;
    if (s >= 40) return const Color(0xFFE67E22);
    return AppTheme.danger;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Health Insights'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_circle_outline),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AddMetricScreen()))
                .then((_) => _load()),
          ),
        ],
      ),
      body: _loading
          ? const LoadingOverlay()
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Score card
                  if (_score != null) _scoreCard(),
                  const SizedBox(height: 20),

                  // HR Chart
                  if (_metrics.length >= 2) ...[
                    const SectionHeader(title: 'Heart Rate Trend'),
                    const SizedBox(height: 10),
                    _chartCard(_metrics, 'heart_rate', AppTheme.danger, 'bpm'),
                    const SizedBox(height: 20),

                    const SectionHeader(title: 'Blood Pressure'),
                    const SizedBox(height: 10),
                    _bpChartCard(_metrics),
                    const SizedBox(height: 20),

                    const SectionHeader(title: 'Steps & Activity'),
                    const SizedBox(height: 10),
                    _stepsChart(_metrics),
                    const SizedBox(height: 20),
                  ],

                  // Insights
                  const SectionHeader(title: 'AI Health Insights'),
                  const SizedBox(height: 10),
                  if (_insights.isEmpty)
                    const Center(child: Padding(
                      padding: EdgeInsets.all(20),
                      child: Text('No insights yet. Log your health metrics to get started.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF9E9E9E))),
                    ))
                  else
                    ..._insights.map((i) => InsightCard(insight: i as Map<String, dynamic>)),

                  const SizedBox(height: 20),

                  // Recent metrics table
                  const SectionHeader(title: 'Recent Logs'),
                  const SizedBox(height: 10),
                  ..._metrics.take(7).map((m) => _metricRow(m as Map<String, dynamic>)),
                ],
              ),
            ),
    );
  }

  Widget _scoreCard() {
    final s = _score!['score'] as int? ?? 0;
    final level = (_score!['level'] as String? ?? 'unknown').toUpperCase();
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [_scoreColor, _scoreColor.withOpacity(0.7)]),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: _scoreColor.withOpacity(0.3), blurRadius: 15, offset: const Offset(0, 6))],
      ),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Health Score', style: TextStyle(color: Colors.white70, fontSize: 14)),
          const SizedBox(height: 4),
          Text('$s / 100', style: const TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(color: Colors.white.withOpacity(0.25), borderRadius: BorderRadius.circular(20)),
            child: Text(level, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
          ),
        ])),
        SizedBox(
          width: 80, height: 80,
          child: Stack(alignment: Alignment.center, children: [
            CircularProgressIndicator(value: s / 100, strokeWidth: 8, backgroundColor: Colors.white.withOpacity(0.3), valueColor: const AlwaysStoppedAnimation<Color>(Colors.white)),
            Icon(_scoreIcon(level.toLowerCase()), color: Colors.white, size: 28),
          ]),
        ),
      ]),
    );
  }

  IconData _scoreIcon(String level) => switch (level) {
    'good'     => Icons.sentiment_very_satisfied_rounded,
    'fair'     => Icons.sentiment_satisfied_rounded,
    'poor'     => Icons.sentiment_dissatisfied_rounded,
    'critical' => Icons.sentiment_very_dissatisfied_rounded,
    _          => Icons.help_outline,
  };

  Widget _chartCard(List<dynamic> data, String field, Color color, String unit) {
    final spots = List.generate(data.length, (i) {
      final v = (data[data.length - 1 - i] as Map)[field] as num? ?? 0;
      return FlSpot(i.toDouble(), v.toDouble());
    });
    return Container(
      height: 160,
      padding: const EdgeInsets.fromLTRB(12, 16, 16, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)],
      ),
      child: LineChart(LineChartData(
        gridData: FlGridData(show: true, drawVerticalLine: false,
            getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey.withOpacity(0.1), strokeWidth: 1)),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 36, getTitlesWidget: (v, _) => Text('${v.toInt()}', style: const TextStyle(fontSize: 10, color: Color(0xFF9E9E9E))))),
          bottomTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        lineBarsData: [
          LineChartBarData(
            spots: spots, isCurved: true, color: color, barWidth: 2.5,
            belowBarData: BarAreaData(show: true, color: color.withOpacity(0.1)),
            dotData: const FlDotData(show: false),
          ),
        ],
      )),
    );
  }

  Widget _bpChartCard(List<dynamic> data) {
    final sysSpots = List.generate(data.length, (i) {
      final v = (data[data.length - 1 - i] as Map)['blood_pressure_systolic'] as num? ?? 0;
      return FlSpot(i.toDouble(), v.toDouble());
    });
    final diaSpots = List.generate(data.length, (i) {
      final v = (data[data.length - 1 - i] as Map)['blood_pressure_diastolic'] as num? ?? 0;
      return FlSpot(i.toDouble(), v.toDouble());
    });
    return Container(
      height: 160,
      padding: const EdgeInsets.fromLTRB(12, 16, 16, 8),
      decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)],
      ),
      child: LineChart(LineChartData(
        gridData: FlGridData(show: true, drawVerticalLine: false,
            getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey.withOpacity(0.1), strokeWidth: 1)),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 36, getTitlesWidget: (v, _) => Text('${v.toInt()}', style: const TextStyle(fontSize: 10, color: Color(0xFF9E9E9E))))),
          bottomTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        lineBarsData: [
          LineChartBarData(spots: sysSpots, isCurved: true, color: const Color(0xFF8E44AD), barWidth: 2.5, dotData: const FlDotData(show: false)),
          LineChartBarData(spots: diaSpots, isCurved: true, color: const Color(0xFF2980B9), barWidth: 2, dotData: const FlDotData(show: false), dashArray: [5, 3]),
        ],
      )),
    );
  }

  Widget _stepsChart(List<dynamic> data) {
    final bars = List.generate(data.length, (i) {
      final v = ((data[data.length - 1 - i] as Map)['steps_count'] as num? ?? 0).toDouble();
      return BarChartGroupData(x: i, barRods: [BarChartRodData(toY: v, color: AppTheme.success, width: 10, borderRadius: BorderRadius.circular(4))]);
    });
    return Container(
      height: 140,
      padding: const EdgeInsets.fromLTRB(12, 16, 16, 8),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)]),
      child: BarChart(BarChartData(
        gridData: const FlGridData(show: false),
        titlesData: const FlTitlesData(
          leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
          bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        barGroups: bars,
      )),
    );
  }

  Widget _metricRow(Map<String, dynamic> m) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)]),
    child: Row(children: [
      Expanded(child: Text(m['metric_date'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
      _mini('HR', '${m['heart_rate'] ?? '-'}', AppTheme.danger),
      _mini('BP', '${m['blood_pressure_systolic'] ?? '-'}/${m['blood_pressure_diastolic'] ?? '-'}', const Color(0xFF8E44AD)),
      _mini('SpO2', '${m['spo2'] ?? '-'}%', const Color(0xFF2980B9)),
    ]),
  );

  Widget _mini(String label, String val, Color c) => Padding(
    padding: const EdgeInsets.only(left: 10),
    child: Column(children: [
      Text(val, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: c)),
      Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF9E9E9E))),
    ]),
  );
}
