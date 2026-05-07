import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../widgets/hs_widgets.dart';

class DietScreen extends StatefulWidget {
  const DietScreen({super.key});
  @override
  State<DietScreen> createState() => _DietScreenState();
}

class _DietScreenState extends State<DietScreen> with SingleTickerProviderStateMixin {
  late TabController _tabs;
  Map<String, dynamic>? _today;
  List<dynamic> _summary = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        ApiService.getTodayDiet(),
        ApiService.getDietSummary(),
      ]);
      if (mounted) setState(() {
        _today   = results[0] as Map<String, dynamic>;
        _summary = results[1] as List<dynamic>;
        _loading = false;
      });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _delete(int id) async {
    try {
      await ApiService.deleteDietLog(id);
      _load();
    } catch (_) {}
  }

  void _addMeal() {
    final nameCtrl    = TextEditingController();
    final calCtrl     = TextEditingController();
    final proteinCtrl = TextEditingController();
    final carbsCtrl   = TextEditingController();
    final fatCtrl     = TextEditingController();
    String mealType   = 'breakfast';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => StatefulBuilder(builder: (ctx, setModal) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 20, right: 20, top: 8),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
          const SizedBox(height: 16),
          const Text('Log a Meal', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: 16),

          // Meal type chips
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(children: [
              for (final t in ['breakfast', 'lunch', 'dinner', 'snack'])
                GestureDetector(
                  onTap: () => setModal(() => mealType = t),
                  child: Container(
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: mealType == t ? AppTheme.primary : const Color(0xFFF0F4FF),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(children: [
                      Icon(_mealIcon(t), size: 14, color: mealType == t ? Colors.white : AppTheme.primary),
                      const SizedBox(width: 4),
                      Text(t[0].toUpperCase() + t.substring(1),
                          style: TextStyle(color: mealType == t ? Colors.white : AppTheme.primary, fontWeight: FontWeight.w600, fontSize: 13)),
                    ]),
                  ),
                ),
            ]),
          ),
          const SizedBox(height: 14),
          TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Food Name *', prefixIcon: Icon(Icons.restaurant_rounded))),
          const SizedBox(height: 10),
          Row(children: [
            Expanded(child: TextField(controller: calCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Calories *', suffixText: 'kcal'))),
            const SizedBox(width: 10),
            Expanded(child: TextField(controller: proteinCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Protein', suffixText: 'g'))),
          ]),
          const SizedBox(height: 10),
          Row(children: [
            Expanded(child: TextField(controller: carbsCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Carbs', suffixText: 'g'))),
            const SizedBox(width: 10),
            Expanded(child: TextField(controller: fatCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Fat', suffixText: 'g'))),
          ]),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () async {
              if (nameCtrl.text.isEmpty || calCtrl.text.isEmpty) {
                ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Food name and calories are required')));
                return;
              }
              Navigator.pop(ctx);
              await ApiService.addDietLog({
                'meal_type': mealType,
                'food_name': nameCtrl.text.trim(),
                'calories':  double.tryParse(calCtrl.text) ?? 0,
                'protein':   double.tryParse(proteinCtrl.text),
                'carbs':     double.tryParse(carbsCtrl.text),
                'fats':      double.tryParse(fatCtrl.text),
              });
              _load();
            },
            child: const Text('Save Meal'),
          ),
          const SizedBox(height: 20),
        ]),
      )),
    );
  }

  IconData _mealIcon(String type) => switch (type) {
    'breakfast' => Icons.free_breakfast_rounded,
    'lunch'     => Icons.lunch_dining_rounded,
    'dinner'    => Icons.dinner_dining_rounded,
    _           => Icons.cookie_rounded,
  };

  Color _mealColor(String type) => switch (type) {
    'breakfast' => const Color(0xFFE67E22),
    'lunch'     => const Color(0xFF27AE60),
    'dinner'    => const Color(0xFF8E44AD),
    _           => const Color(0xFF2980B9),
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Diet Tracker'),
        bottom: TabBar(
          controller: _tabs,
          labelColor: AppTheme.primary,
          unselectedLabelColor: const Color(0xFF9E9E9E),
          indicatorColor: AppTheme.primary,
          tabs: const [Tab(text: "Today"), Tab(text: "7-Day Trend")],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addMeal,
        icon: const Icon(Icons.add),
        label: const Text('Log Meal'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
      ),
      body: _loading
          ? const LoadingOverlay()
          : _error != null
              ? ErrorState(message: _error!, onRetry: _load)
              : TabBarView(controller: _tabs, children: [
                  _buildToday(),
                  _buildTrend(),
                ]),
    );
  }

  // ── TODAY TAB ──────────────────────────────────────────────────────────
  Widget _buildToday() {
    final logs  = _today?['logs']  as List<dynamic>? ?? [];
    final total = (_today?['total_calories'] as num?)?.toInt() ?? 0;
    final goal  = (_today?['goal'] as num?)?.toInt() ?? 2500;
    final pct   = (total / goal).clamp(0.0, 1.0);

    // Compute macros
    double protein = 0, carbs = 0, fats = 0;
    for (final l in logs) {
      protein += (l['protein'] as num?)?.toDouble() ?? 0;
      carbs   += (l['carbs']   as num?)?.toDouble() ?? 0;
      fats    += (l['fats']    as num?)?.toDouble() ?? 0;
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 100), children: [
        // Calorie progress card
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFFE67E22), Color(0xFFF39C12)]),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Column(children: [
            Row(children: [
              const Icon(Icons.local_fire_department_rounded, color: Colors.white, size: 28),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text("Today's Calories", style: TextStyle(color: Colors.white70, fontSize: 13)),
                Text('$total kcal', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text('Goal: $goal', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                Text('${(pct * 100).toInt()}%', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              ]),
            ]),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(value: pct, minHeight: 8,
                backgroundColor: Colors.white.withOpacity(0.3),
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.white)),
            ),
            const SizedBox(height: 6),
            Text('${(goal - total).clamp(0, goal)} kcal remaining', style: const TextStyle(color: Colors.white70, fontSize: 12)),
          ]),
        ),
        const SizedBox(height: 16),

        // Macros row
        if (protein + carbs + fats > 0)
          Row(children: [
            _macroChip('Protein', protein, const Color(0xFF3498DB)),
            const SizedBox(width: 8),
            _macroChip('Carbs', carbs, const Color(0xFF27AE60)),
            const SizedBox(width: 8),
            _macroChip('Fat', fats, const Color(0xFFE74C3C)),
          ]),
        if (protein + carbs + fats > 0) const SizedBox(height: 16),

        // Meals by type
        for (final type in ['breakfast', 'lunch', 'dinner', 'snack']) ...[
          ..._buildMealSection(type, logs),
        ],

        if (logs.isEmpty)
          Center(child: Padding(
            padding: const EdgeInsets.only(top: 40),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.restaurant_menu_rounded, size: 56, color: Color(0xFFCCCCCC)),
              const SizedBox(height: 12),
              const Text('Nothing logged today', style: TextStyle(color: Color(0xFF9E9E9E), fontSize: 16)),
              const SizedBox(height: 6),
              const Text('Tap "Log Meal" to start tracking', style: TextStyle(color: Color(0xFFBBBBBB), fontSize: 13)),
              const SizedBox(height: 20),
              ElevatedButton.icon(onPressed: _addMeal, icon: const Icon(Icons.add), label: const Text('Log Your First Meal')),
            ]),
          )),
      ]),
    );
  }

  List<Widget> _buildMealSection(String type, List<dynamic> logs) {
    final typeLogs = logs.where((l) => l['meal_type'] == type).toList();
    if (typeLogs.isEmpty) return [];
    final typeTotal = typeLogs.fold<double>(0, (s, l) => s + ((l['calories'] as num?)?.toDouble() ?? 0));
    final color = _mealColor(type);

    return [
      Row(children: [
        Container(width: 4, height: 20, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        const SizedBox(width: 8),
        Icon(_mealIcon(type), color: color, size: 16),
        const SizedBox(width: 6),
        Text(type[0].toUpperCase() + type.substring(1), style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: color)),
        const Spacer(),
        Text('${typeTotal.toInt()} kcal', style: const TextStyle(color: Color(0xFF9E9E9E), fontSize: 13)),
      ]),
      const SizedBox(height: 8),
      ...typeLogs.map((l) => _logTile(l as Map<String, dynamic>, color)),
      const SizedBox(height: 16),
    ];
  }

  Widget _logTile(Map<String, dynamic> l, Color color) {
    final protein = (l['protein'] as num?)?.toDouble() ?? 0;
    final carbs   = (l['carbs']   as num?)?.toDouble() ?? 0;
    final fats    = (l['fats']    as num?)?.toDouble() ?? 0;

    return Dismissible(
      key: Key('diet_${l['id']}'),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 16),
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
        child: const Icon(Icons.delete_rounded, color: AppTheme.danger),
      ),
      confirmDismiss: (_) async {
        await _delete((l['id'] as num).toInt());
        return false;
      },
      child: Container(
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
            decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(10)),
            child: Icon(_mealIcon(l['meal_type'] ?? 'snack'), color: color, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l['food_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
            if (protein + carbs + fats > 0)
              Text('P:${protein.toInt()}g  C:${carbs.toInt()}g  F:${fats.toInt()}g',
                  style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E))),
          ])),
          Text('${(l['calories'] as num?)?.toInt() ?? 0} kcal',
              style: TextStyle(fontWeight: FontWeight.bold, color: color, fontSize: 14)),
        ]),
      ),
    );
  }

  Widget _macroChip(String label, double value, Color color) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
      child: Column(children: [
        Text('${value.toInt()}g', style: TextStyle(fontWeight: FontWeight.bold, color: color, fontSize: 16)),
        Text(label, style: TextStyle(fontSize: 11, color: color.withOpacity(0.8))),
      ]),
    ),
  );

  // ── TREND TAB ──────────────────────────────────────────────────────────
  Widget _buildTrend() {
    if (_summary.isEmpty) {
      return const Center(child: Text('No data for the past 7 days', style: TextStyle(color: Color(0xFF9E9E9E))));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: const EdgeInsets.all(16), children: [
        const Text('7-Day Calorie Trend', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        const SizedBox(height: 16),
        Container(
          height: 220,
          padding: const EdgeInsets.fromLTRB(8, 16, 16, 8),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)]),
          child: BarChart(BarChartData(
            alignment: BarChartAlignment.spaceAround,
            maxY: _summary.fold<double>(0, (m, r) => (r['total_cal'] as num? ?? 0) > m ? (r['total_cal'] as num).toDouble() : m) * 1.3,
            barTouchData: BarTouchData(
              touchTooltipData: BarTouchTooltipData(
                getTooltipItem: (_, __, rod, ___) => BarTooltipItem('${rod.toY.toInt()} kcal',
                    const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
              ),
            ),
            titlesData: FlTitlesData(
              leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
              rightTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
              topTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
              bottomTitles: AxisTitles(sideTitles: SideTitles(
                showTitles: true, reservedSize: 28,
                getTitlesWidget: (val, _) {
                  final i = val.toInt();
                  if (i < 0 || i >= _summary.length) return const SizedBox();
                  final date = DateTime.tryParse(_summary[i]['log_date'] as String? ?? '');
                  return Padding(padding: const EdgeInsets.only(top: 4),
                    child: Text(date != null ? DateFormat('E').format(date) : '',
                        style: const TextStyle(fontSize: 10, color: Color(0xFF9E9E9E))));
                },
              )),
            ),
            borderData: FlBorderData(show: false),
            gridData: FlGridData(show: true, drawVerticalLine: false,
                getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey.withOpacity(0.1), strokeWidth: 1)),
            barGroups: List.generate(_summary.length, (i) {
              final cal = (_summary[i]['total_cal'] as num?)?.toDouble() ?? 0;
              final isToday = (_summary[i]['log_date'] as String? ?? '') == DateFormat('yyyy-MM-dd').format(DateTime.now());
              return BarChartGroupData(x: i, barRods: [
                BarChartRodData(
                  toY: cal,
                  color: isToday ? AppTheme.primary : AppTheme.primary.withOpacity(0.4),
                  width: 22,
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(6)),
                ),
              ]);
            }),
          )),
        ),
        const SizedBox(height: 24),

        // Macro trend table
        const Text('Nutrition Summary', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        const SizedBox(height: 12),
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
          child: Column(children: [
            _trendHeader(),
            ..._summary.map((r) => _trendRow(r as Map<String, dynamic>)),
          ]),
        ),
        const SizedBox(height: 80),
      ]),
    );
  }

  Widget _trendHeader() => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    decoration: BoxDecoration(color: AppTheme.surface, borderRadius: const BorderRadius.vertical(top: Radius.circular(14))),
    child: const Row(children: [
      Expanded(flex: 2, child: Text('Date', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Color(0xFF666666)))),
      Expanded(child: Text('Kcal', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Color(0xFF666666)))),
      Expanded(child: Text('Protein', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Color(0xFF666666)))),
      Expanded(child: Text('Carbs', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Color(0xFF666666)))),
      Expanded(child: Text('Fat', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Color(0xFF666666)))),
    ]),
  );

  Widget _trendRow(Map<String, dynamic> r) {
    final date = DateTime.tryParse(r['log_date'] as String? ?? '');
    final isToday = r['log_date'] == DateFormat('yyyy-MM-dd').format(DateTime.now());
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: isToday ? AppTheme.primary.withOpacity(0.05) : Colors.white,
        border: Border(top: BorderSide(color: Colors.grey.withOpacity(0.08))),
      ),
      child: Row(children: [
        Expanded(flex: 2, child: Text(
          date != null ? DateFormat('EEE, d MMM').format(date) : '',
          style: TextStyle(fontSize: 12, fontWeight: isToday ? FontWeight.bold : FontWeight.normal, color: isToday ? AppTheme.primary : const Color(0xFF333333)),
        )),
        Expanded(child: Text('${(r['total_cal'] as num?)?.toInt() ?? 0}', textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFFE67E22)))),
        Expanded(child: Text('${(r['total_protein'] as num?)?.toInt() ?? 0}g', textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, color: Color(0xFF3498DB)))),
        Expanded(child: Text('${(r['total_carbs'] as num?)?.toInt() ?? 0}g', textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, color: Color(0xFF27AE60)))),
        Expanded(child: Text('${(r['total_fat'] as num?)?.toInt() ?? 0}g', textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, color: Color(0xFFE74C3C)))),
      ]),
    );
  }
}
