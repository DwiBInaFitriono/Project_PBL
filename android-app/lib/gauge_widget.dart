import 'dart:math';
import 'package:flutter/material.dart';

/// Widget Radial/Arc Gauge kustom untuk visualisasi parameter sensor
/// (Suhu Udara, Kelembapan Udara, dan Kelembapan Tanah).
class RadialGaugeWidget extends StatelessWidget {
  const RadialGaugeWidget({
    super.key,
    required this.title,
    required this.value,
    required this.unit,
    required this.minValue,
    required this.maxValue,
    this.optimalMin,
    this.optimalMax,
    this.warningColor = const Color(0xFFF7B700),
    this.optimalColor = const Color(0xFF2E7D32),
    this.dangerColor = const Color(0xFFC62828),
  });

  final String title;
  final double? value;
  final String unit;
  final double minValue;
  final double maxValue;
  final double? optimalMin;
  final double? optimalMax;
  final Color warningColor;
  final Color optimalColor;
  final Color dangerColor;

  Color get _statusColor {
    if (value == null) return Colors.grey;
    final v = value!;
    if (optimalMin != null && optimalMax != null) {
      if (v >= optimalMin! && v <= optimalMax!) return optimalColor;
      if (v < optimalMin! * 0.7 || v > optimalMax! * 1.3) return dangerColor;
      return warningColor;
    }
    return const Color(0xFF6A7FC0);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF111F40) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark ? const Color(0xFF1E3366) : const Color(0xFFE2E0D8),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),

        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            title,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: isDark ? const Color(0xFFF2F1EF) : const Color(0xFF011E60),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: 140,
            height: 100,
            child: CustomPaint(
              painter: _GaugePainter(
                value: value,
                minValue: minValue,
                maxValue: maxValue,
                activeColor: _statusColor,
                trackColor: isDark ? const Color(0xFF1E3366) : const Color(0xFFE2E0D8),
              ),
            ),
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                value != null ? value!.toStringAsFixed(1) : '—',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: _statusColor,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                unit,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: isDark ? Colors.white70 : Colors.black54,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GaugePainter extends CustomPainter {
  _GaugePainter({
    required this.value,
    required this.minValue,
    required this.maxValue,
    required this.activeColor,
    required this.trackColor,
  });

  final double? value;
  final double minValue;
  final double maxValue;
  final Color activeColor;
  final Color trackColor;

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height * 0.85);
    final radius = size.width * 0.42;

    const startAngle = pi * 0.85;
    const sweepAngle = pi * 1.3;

    final trackPaint = Paint()
      ..color = trackColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 10
      ..strokeCap = StrokeCap.round;

    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      startAngle,
      sweepAngle,
      false,
      trackPaint,
    );

    if (value != null) {
      final clamped = value!.clamp(minValue, maxValue);
      final ratio = (clamped - minValue) / (maxValue - minValue);
      final activeSweep = sweepAngle * ratio;

      final activePaint = Paint()
        ..color = activeColor
        ..style = PaintingStyle.stroke
        ..strokeWidth = 10
        ..strokeCap = StrokeCap.round;

      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius),
        startAngle,
        activeSweep,
        false,
        activePaint,
      );

      // Jarum penunjuk sederhana
      final needleAngle = startAngle + activeSweep;
      final needleLength = radius * 0.75;
      final needleEnd = Offset(
        center.dx + needleLength * cos(needleAngle),
        center.dy + needleLength * sin(needleAngle),
      );

      final needlePaint = Paint()
        ..color = activeColor
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round;

      canvas.drawLine(center, needleEnd, needlePaint);
      canvas.drawCircle(center, 5, Paint()..color = activeColor);
    }
  }

  @override
  bool shouldRepaint(covariant _GaugePainter oldDelegate) {
    return oldDelegate.value != value ||
        oldDelegate.activeColor != activeColor ||
        oldDelegate.trackColor != trackColor;
  }
}
