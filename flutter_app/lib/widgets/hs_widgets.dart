import 'package:flutter/material.dart';
import '../config/theme.dart';

// ── Stat card used on dashboard ───────────────────────────────────────────
class StatCard extends StatelessWidget {
  final String label;
  final String value;
  final String unit;
  final IconData icon;
  final Color color;
  final String? subtitle;

  const StatCard({
    super.key,
    required this.label,
    required this.value,
    required this.unit,
    required this.icon,
    required this.color,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          width: 40, height: 40,
          decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: color, size: 20),
        ),
        const SizedBox(height: 12),
        Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: color)),
          const SizedBox(width: 4),
          Padding(padding: const EdgeInsets.only(bottom: 2), child: Text(unit, style: const TextStyle(fontSize: 12, color: Color(0xFF9E9E9E)))),
        ]),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF666666))),
        if (subtitle != null) ...[
          const SizedBox(height: 2),
          Text(subtitle!, style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E))),
        ],
      ]),
    );
  }
}

// ── Section header ────────────────────────────────────────────────────────
class SectionHeader extends StatelessWidget {
  final String title;
  final String? action;
  final VoidCallback? onAction;

  const SectionHeader({super.key, required this.title, this.action, this.onAction});

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: Color(0xFF1A1A2E))),
      const Spacer(),
      if (action != null)
        TextButton(
          onPressed: onAction,
          child: Text(action!, style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.w600)),
        ),
    ]);
  }
}

// ── Loading overlay ───────────────────────────────────────────────────────
class LoadingOverlay extends StatelessWidget {
  const LoadingOverlay({super.key});
  @override
  Widget build(BuildContext context) => const Center(
    child: CircularProgressIndicator(color: AppTheme.primary),
  );
}

// ── Error state ───────────────────────────────────────────────────────────
class ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;
  const ErrorState({super.key, required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.error_outline, size: 48, color: AppTheme.danger),
      const SizedBox(height: 12),
      Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF666666))),
      if (onRetry != null) ...[
        const SizedBox(height: 16),
        ElevatedButton(onPressed: onRetry, child: const Text('Retry')),
      ],
    ]),
  );
}

// ── Insight card ──────────────────────────────────────────────────────────
class InsightCard extends StatelessWidget {
  final Map<String, dynamic> insight;
  const InsightCard({super.key, required this.insight});

  Color get _color {
    return switch (insight['type']) {
      'critical' => AppTheme.danger,
      'warning'  => AppTheme.warning,
      'positive' => AppTheme.success,
      _          => AppTheme.primary,
    };
  }

  IconData get _icon {
    return switch (insight['type']) {
      'critical' => Icons.warning_rounded,
      'warning'  => Icons.info_rounded,
      'positive' => Icons.check_circle_rounded,
      _          => Icons.lightbulb_rounded,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _color.withOpacity(0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _color.withOpacity(0.3), width: 1),
      ),
      child: Row(children: [
        Icon(_icon, color: _color, size: 22),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(insight['title'] ?? '', style: TextStyle(fontWeight: FontWeight.w600, color: _color, fontSize: 14)),
          const SizedBox(height: 2),
          Text(insight['message'] ?? '', style: const TextStyle(fontSize: 12, color: Color(0xFF555555))),
        ])),
      ]),
    );
  }
}

// ── NHS Badge ─────────────────────────────────────────────────────────────
class NhsBadge extends StatelessWidget {
  final String nhsId;
  const NhsBadge({super.key, required this.nhsId});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
    decoration: BoxDecoration(
      gradient: const LinearGradient(colors: [Color(0xFF003087), Color(0xFF005EB8)]),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.local_hospital, color: Colors.white, size: 14),
      const SizedBox(width: 6),
      Text('NHS $nhsId', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  );
}

// ── Appointment tile ──────────────────────────────────────────────────────
class AppointmentTile extends StatelessWidget {
  final Map<String, dynamic> appointment;
  final VoidCallback? onCancel;

  const AppointmentTile({super.key, required this.appointment, this.onCancel});

  Color _statusColor(String s) => switch (s) {
    'confirmed' => AppTheme.success,
    'pending'   => AppTheme.warning,
    'cancelled' => AppTheme.danger,
    _           => AppTheme.primary,
  };

  @override
  Widget build(BuildContext context) {
    final status = appointment['status'] ?? 'pending';
    final doctorFirst = appointment['doctor_first'] ?? '';
    final doctorLast  = appointment['doctor_last']  ?? '';
    final spec  = appointment['specialization'] ?? 'General';
    final date  = appointment['appointment_date'] ?? '';
    final time  = appointment['appointment_time'] ?? '';
    final reason = appointment['reason'] ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 8)],
      ),
      child: Row(children: [
        Container(
          width: 48, height: 48,
          decoration: BoxDecoration(
            color: AppTheme.primary.withOpacity(0.1),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(Icons.person_rounded, color: AppTheme.primary),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Dr. $doctorFirst $doctorLast', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
          Text(spec, style: const TextStyle(color: Color(0xFF777777), fontSize: 12)),
          const SizedBox(height: 4),
          Row(children: [
            const Icon(Icons.calendar_today, size: 12, color: Color(0xFF9E9E9E)),
            const SizedBox(width: 4),
            Text('$date  $time', style: const TextStyle(fontSize: 12, color: Color(0xFF555555))),
          ]),
          if (reason.isNotEmpty) Text(reason, style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E)), maxLines: 1, overflow: TextOverflow.ellipsis),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: _statusColor(status).withOpacity(0.12),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(status.toUpperCase(), style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: _statusColor(status))),
          ),
          if (status == 'pending' && onCancel != null) ...[
            const SizedBox(height: 6),
            GestureDetector(
              onTap: onCancel,
              child: const Text('Cancel', style: TextStyle(fontSize: 11, color: AppTheme.danger)),
            ),
          ],
        ]),
      ]),
    );
  }
}
