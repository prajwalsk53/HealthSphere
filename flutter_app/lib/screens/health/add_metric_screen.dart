import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';

class AddMetricScreen extends StatefulWidget {
  const AddMetricScreen({super.key});
  @override
  State<AddMetricScreen> createState() => _AddMetricScreenState();
}

class _AddMetricScreenState extends State<AddMetricScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;

  final _hr     = TextEditingController();
  final _sys    = TextEditingController();
  final _dia    = TextEditingController();
  final _spo2   = TextEditingController();
  final _steps  = TextEditingController();
  final _sleep  = TextEditingController();
  final _weight = TextEditingController();
  final _temp   = TextEditingController();

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      await ApiService.addHealthMetric({
        'heart_rate':                 _hr.text.isNotEmpty    ? int.parse(_hr.text)       : null,
        'blood_pressure_systolic':    _sys.text.isNotEmpty   ? int.parse(_sys.text)      : null,
        'blood_pressure_diastolic':   _dia.text.isNotEmpty   ? int.parse(_dia.text)      : null,
        'spo2':                       _spo2.text.isNotEmpty  ? double.parse(_spo2.text)  : null,
        'steps_count':                _steps.text.isNotEmpty ? int.parse(_steps.text)    : null,
        'sleep_hours':                _sleep.text.isNotEmpty ? double.parse(_sleep.text) : null,
        'weight':                     _weight.text.isNotEmpty? double.parse(_weight.text): null,
        'temperature':                _temp.text.isNotEmpty  ? double.parse(_temp.text)  : null,
      });
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Metric logged!'), backgroundColor: AppTheme.success),
      );
    } on ApiException catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Log Health Metric')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _section('Heart & Blood', [
              _field(_hr,   'Heart Rate',           'bpm',  Icons.favorite_rounded,         color: AppTheme.danger),
              _field(_sys,  'Systolic BP',           'mmHg', Icons.monitor_heart_rounded,    color: const Color(0xFF8E44AD)),
              _field(_dia,  'Diastolic BP',          'mmHg', Icons.monitor_heart_outlined,   color: const Color(0xFF8E44AD)),
              _field(_spo2, 'Blood Oxygen (SpO2)',   '%',    Icons.air_rounded,              color: const Color(0xFF2980B9), decimal: true),
            ]),
            const SizedBox(height: 16),
            _section('Activity & Body', [
              _field(_steps,  'Steps Count',    'steps', Icons.directions_walk_rounded, color: AppTheme.success),
              _field(_sleep,  'Sleep Hours',    'hrs',   Icons.bedtime_rounded,         color: const Color(0xFF2C3E50), decimal: true),
              _field(_weight, 'Weight',         'kg',    Icons.monitor_weight_rounded,  color: const Color(0xFFE67E22), decimal: true),
              _field(_temp,   'Temperature',    '°C',    Icons.thermostat_rounded,      color: AppTheme.warning, decimal: true),
            ]),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _loading ? null : _save,
              style: ElevatedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
              child: _loading
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Save Metrics'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _section(String title, List<Widget> fields) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
      const SizedBox(height: 10),
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
        child: Column(children: fields),
      ),
    ],
  );

  Widget _field(TextEditingController ctrl, String label, String unit, IconData icon, {required Color color, bool decimal = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: ctrl,
        keyboardType: decimal ? const TextInputType.numberWithOptions(decimal: true) : TextInputType.number,
        decoration: InputDecoration(
          labelText: label,
          suffixText: unit,
          prefixIcon: Icon(icon, color: color, size: 20),
        ),
        validator: (v) {
          if (v != null && v.isNotEmpty) {
            final n = decimal ? double.tryParse(v) : int.tryParse(v);
            if (n == null) return 'Invalid number';
          }
          return null;
        },
      ),
    );
  }
}
