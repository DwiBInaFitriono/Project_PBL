import 'package:flutter/material.dart';

import 'dashboard_widgets.dart';
import 'app_palette.dart';

typedef MonitoringPoint = ({int node, DateTime recordedAt, num value});

List<MonitoringPoint> monitoringPoints(
  Map<String, dynamic>? snapshot, {
  required List<int> nodes,
  required String sensorId,
  required int hours,
}) {
  if (snapshot == null) return const [];
  final end = DateTime.parse(snapshot['generatedAt'] as String).toUtc();
  final start = end.subtract(Duration(hours: hours));
  final points = <MonitoringPoint>[];
  for (final node in nodes) {
    final sensor = monitoringSensor(monitoringNode(snapshot, node), sensorId);
    for (final reading in (sensor?['readings'] as List? ?? const [])) {
      final time = DateTime.parse(reading['recorded_at'] as String).toUtc();
      if (!time.isBefore(start) && !time.isAfter(end)) {
        points.add((
          node: node,
          recordedAt: time,
          value: reading['value'] as num,
        ));
      }
    }
  }
  points.sort((a, b) {
    final time = a.recordedAt.compareTo(b.recordedAt);
    return time == 0 ? a.node.compareTo(b.node) : time;
  });
  return List.unmodifiable(points);
}

class ChartPanel extends StatelessWidget {
  const ChartPanel({
    super.key,
    required this.nodes,
    required this.sensorId,
    required this.hours,
    required this.onSensorChanged,
    required this.onHoursChanged,
    this.snapshot,
    this.connected = false,
    this.loading = false,
    this.error,
  });
  final Map<String, dynamic>? snapshot;
  final bool connected;
  final bool loading;
  final String? error;
  final List<int> nodes;
  final String sensorId;
  final int hours;
  final ValueChanged<String> onSensorChanged;
  final ValueChanged<int> onHoursChanged;

  String _statistic(List<MonitoringPoint> points, int node, String kind) {
    final values =
        points.where((p) => p.node == node).map((p) => p.value).toList()
          ..sort();
    if (values.isEmpty) return '—';
    final value = switch (kind) {
      'min' => values.first,
      'max' => values.last,
      _ => values.reduce((a, b) => a + b) / values.length,
    };
    final definition = monitoringSensor(
      monitoringNode(snapshot, node),
      sensorId,
    );
    return monitoringValue(
      value,
      decimals: definition?['decimals'] as int? ?? 1,
    );
  }

  @override
  Widget build(BuildContext context) {
    final sensor = sensors.firstWhere((s) => s.id == sensorId);
    final definition = monitoringSensor(snapshot, sensorId);
    final sensorName = definition?['name'] as String? ?? sensor.name;
    final sensorUnit = definition?['unit'] as String? ?? sensor.unit;
    final points = monitoringPoints(
      snapshot,
      nodes: nodes,
      sensorId: sensorId,
      hours: hours,
    );
    final end = snapshot == null
        ? null
        : DateTime.parse(snapshot!['generatedAt'] as String).toUtc();
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return SurfaceCard(
      padding: 16,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final item in sensors)
                Tooltip(
                  message: item.name,
                  child: ChoiceChip(
                    key: Key('chart-sensor-${item.id}'),
                    avatar: Icon(
                      item.icon,
                      size: 17,
                      color: sensorId == item.id
                          ? AppPalette.navy
                          : Theme.of(context).colorScheme.onSurface,
                    ),
                    label: Text(switch (item.id) {
                      'temperature' => 'Suhu',
                      'air_humidity' => 'Udara',
                      _ => 'Tanah',
                    }),
                    selected: sensorId == item.id,
                    showCheckmark: false,
                    onSelected: (_) => onSensorChanged(item.id),
                    selectedColor: AppPalette.yellow,
                    labelStyle: TextStyle(
                      fontSize: 12,
                      color: sensorId == item.id
                          ? AppPalette.navy
                          : Theme.of(context).colorScheme.onSurface,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final hour in [1, 6, 24])
                ChoiceChip(
                  key: Key('chart-hours-$hour'),
                  label: Text('$hour jam'),
                  selected: hour == hours,
                  showCheckmark: false,
                  onSelected: (_) => onHoursChanged(hour),
                  selectedColor: Theme.of(context).colorScheme.onSurface
                      .withValues(alpha: .10),
                ),
            ],
          ),
          const Divider(height: 28),
          Text(
            '$sensorName · $sensorUnit',
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 20,
            runSpacing: 8,
            children: [
              for (final node in nodes)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox(
                      width: 26,
                      height: 10,
                      child: CustomPaint(
                        painter: _LegendPainter(
                          node == 2,
                          node == 2 ? AppPalette.yellow : AppPalette.blue,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text('Node $node', style: const TextStyle(fontSize: 12)),
                  ],
                ),
            ],
          ),
          if (points.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Semantics(
                    label:
                        'Grafik $sensorName, ${points.length} titik pembacaan. Rincian pada riwayat.',
                    child: SizedBox(
                      height: 180,
                      child: CustomPaint(
                        key: const Key('monitoring-chart'),
                        painter: MonitoringChartPainter(
                          points: points,
                          start: end!.subtract(Duration(hours: hours)),
                          end: end,
                          gridColor: Theme.of(context).colorScheme.outline,
                          labelColor: muted,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    spacing: 12,
                    children: [
                      Text(
                        monitoringTime(
                          end
                              .subtract(Duration(hours: hours))
                              .toIso8601String(),
                        ),
                        style: TextStyle(fontSize: 11, color: muted),
                      ),
                      Text(
                        monitoringTime(end.toIso8601String()),
                        style: TextStyle(fontSize: 11, color: muted),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${points.length} titik dimuat',
                    style: TextStyle(fontSize: 11, color: muted),
                  ),
                ],
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 32),
              child: Column(
                children: [
                  Icon(Icons.show_chart_rounded, size: 42, color: muted),
                  const SizedBox(height: 12),
                  Text(
                    snapshot != null
                        ? 'Belum ada data'
                        : loading
                        ? 'Memuat data…'
                        : error != null
                        ? 'Grafik gagal dimuat'
                        : connected
                        ? 'Menunggu data server'
                        : 'Belum ada data',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          Text(
            '$hours jam terakhir · WIB',
            style: TextStyle(fontSize: 11, color: muted),
          ),
          const Divider(height: 28),
          TwoColumns(
            children: [
              for (final node in nodes)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Node $node',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 24,
                      runSpacing: 8,
                      children: [
                        for (final stat in [
                          ('min', 'Min'),
                          ('average', 'Rata-rata'),
                          ('max', 'Maks'),
                        ])
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                stat.$2,
                                style: TextStyle(fontSize: 11, color: muted),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _statistic(points, node, stat.$1),
                                key: Key('stat-$node-${stat.$1}'),
                                style: const TextStyle(fontSize: 20),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ],
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Draws only measured points; node 2 keeps its dashed identity in detail view.
class MonitoringChartPainter extends CustomPainter {
  const MonitoringChartPainter({
    required this.points,
    required this.start,
    required this.end,
    required this.gridColor,
    required this.labelColor,
  });
  final List<MonitoringPoint> points;
  final DateTime start;
  final DateTime end;
  final Color gridColor;
  final Color labelColor;

  @override
  void paint(Canvas canvas, Size size) {
    if (points.isEmpty || size.width <= 0 || size.height <= 0) return;
    final values = points.map((p) => p.value.toDouble()).toList()..sort();
    final min = values.first;
    final max = values.last;
    final span = max - min;
    final plot = Rect.fromLTRB(46, 12, size.width - 8, size.height - 12);
    final duration = end.difference(start).inMilliseconds;
    Offset position(MonitoringPoint point) => Offset(
      plot.left +
          (duration == 0
                  ? .5
                  : point.recordedAt.difference(start).inMilliseconds /
                        duration) *
              plot.width,
      span == 0
          ? plot.center.dy
          : plot.bottom - (point.value - min) / span * plot.height,
    );
    final grid = Paint()
      ..color = gridColor
      ..strokeWidth = 1;
    for (final fraction in [0.0, .5, 1.0]) {
      final y = plot.bottom - fraction * plot.height;
      canvas.drawLine(Offset(plot.left, y), Offset(plot.right, y), grid);
      if (span == 0 && fraction != .5) continue;
      final label = TextPainter(
        text: TextSpan(
          text: monitoringValue(min + span * fraction),
          style: TextStyle(fontSize: 10, color: labelColor),
        ),
        textDirection: TextDirection.ltr,
      )..layout(maxWidth: 42);
      label.paint(canvas, Offset(0, y - label.height / 2));
    }
    for (final node in points.map((p) => p.node).toSet()) {
      final series = points.where((p) => p.node == node).toList();
      final paint = Paint()
        ..color = node == 2 ? AppPalette.yellow : AppPalette.blue
        ..strokeWidth = 2;
      for (var i = 1; i < series.length; i++) {
        final from = position(series[i - 1]);
        final to = position(series[i]);
        if (node == 2) {
          final distance = (to - from).distance;
          for (double offset = 0; offset < distance; offset += 10) {
            canvas.drawLine(
              Offset.lerp(from, to, offset / distance)!,
              Offset.lerp(from, to, ((offset + 6) / distance).clamp(0, 1))!,
              paint,
            );
          }
        } else {
          canvas.drawLine(from, to, paint);
        }
      }
      for (final point in series) {
        final at = position(point);
        if (node == 2) {
          canvas.drawRect(
            Rect.fromCenter(center: at, width: 6, height: 6),
            paint,
          );
        } else {
          canvas.drawCircle(at, 3, paint);
        }
      }
    }
  }

  @override
  bool shouldRepaint(MonitoringChartPainter oldDelegate) =>
      points != oldDelegate.points ||
      start != oldDelegate.start ||
      end != oldDelegate.end ||
      gridColor != oldDelegate.gridColor ||
      labelColor != oldDelegate.labelColor;
}

class _LegendPainter extends CustomPainter {
  const _LegendPainter(this.dashed, this.color);
  final bool dashed;
  final Color color;
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 3;
    if (dashed) {
      for (double x = 0; x < size.width; x += 9) {
        canvas.drawLine(
          Offset(x, 5),
          Offset((x + 5).clamp(0, size.width), 5),
          paint,
        );
      }
    } else {
      canvas.drawLine(const Offset(0, 5), Offset(size.width, 5), paint);
    }
  }

  @override
  bool shouldRepaint(_LegendPainter oldDelegate) =>
      color != oldDelegate.color || dashed != oldDelegate.dashed;
}
