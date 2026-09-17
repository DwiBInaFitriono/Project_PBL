import 'package:flutter/material.dart';

import 'app_palette.dart';

const sensors = <({String id, String name, String unit, IconData icon})>[
  (
    id: 'temperature',
    name: 'Suhu udara',
    unit: '°C',
    icon: Icons.thermostat_outlined,
  ),
  (
    id: 'air_humidity',
    name: 'Kelembapan udara',
    unit: '% RH',
    icon: Icons.water_drop_outlined,
  ),
  (
    id: 'soil_moisture',
    name: 'Kelembapan tanah',
    unit: '%',
    icon: Icons.grass_outlined,
  ),
];

class SurfaceCard extends StatelessWidget {
  const SurfaceCard({super.key, required this.child, this.padding = 20});
  final Widget child;
  final double padding;
  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.all(padding),
    decoration: BoxDecoration(
      color: Theme.of(context).brightness == Brightness.dark
          ? AppPalette.darkCard
          : AppPalette.card,
      border: Border.all(
        color: Theme.of(context).colorScheme.outline.withValues(alpha: .65),
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: child,
  );
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, this.subtitle, {super.key, this.action});
  final String title;
  final String subtitle;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Wrap(
      alignment: WrapAlignment.spaceBetween,
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 16,
      runSpacing: 8,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
            ),
            if (subtitle.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 12,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
        ?action,
      ],
    ),
  );
}

class InfoNotice extends StatelessWidget {
  const InfoNotice(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(
      color: AppPalette.yellow.withValues(alpha: .10),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline_rounded, size: 18),
        const SizedBox(width: 10),
        Expanded(
          child: Text(text, style: const TextStyle(fontSize: 12, height: 1.5)),
        ),
      ],
    ),
  );
}

class TwoColumns extends StatelessWidget {
  const TwoColumns({super.key, required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) {
      final paired =
          box.maxWidth > 700 && MediaQuery.textScalerOf(context).scale(14) < 22;
      return Wrap(
        spacing: 16,
        runSpacing: 16,
        children: [
          for (final child in children)
            SizedBox(
              width: paired ? (box.maxWidth - 16) / 2 : box.maxWidth,
              child: child,
            ),
        ],
      );
    },
  );
}

/// Snapshot helpers consume ApiClient.monitoring's unwrapped, validated data.
Map<String, dynamic>? monitoringNode(Map<String, dynamic>? snapshot, int id) {
  for (final node in (snapshot?['nodes'] as List? ?? const [])) {
    if (node['id'] == '$id') return node as Map<String, dynamic>;
  }
  return null;
}

Map<String, dynamic>? monitoringSensor(Map<String, dynamic>? node, String id) {
  for (final sensor in (node?['sensors'] as List? ?? const [])) {
    if (sensor['id'] == id) return sensor as Map<String, dynamic>;
  }
  return null;
}

String monitoringValue(num? value, {int decimals = 1}) =>
    value == null ? '—' : value.toStringAsFixed(decimals.clamp(0, 20));

String monitoringTime(Object? timestamp) {
  final utc = timestamp is String ? DateTime.tryParse(timestamp) : null;
  if (utc == null) return '—';
  final time = utc.toUtc().add(const Duration(hours: 7));
  String two(int n) => n.toString().padLeft(2, '0');
  return '${two(time.day)}/${two(time.month)}/${time.year} '
      '${two(time.hour)}:${two(time.minute)} WIB';
}

String _freshnessLabel(Object? freshness) => switch (freshness) {
  'fresh' => 'Data terbaru',
  'stale' => 'Data terlambat',
  _ => 'Data belum tersedia',
};

class NodeCard extends StatelessWidget {
  const NodeCard(this.node, {super.key, this.snapshot});
  final int node;
  final Map<String, dynamic>? snapshot;
  @override
  Widget build(BuildContext context) {
    final data = monitoringNode(snapshot, node);
    return SurfaceCard(
      padding: 16,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 12,
            runSpacing: 10,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: node == 1
                          ? AppPalette.yellow
                          : AppPalette.blue.withValues(alpha: .20),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      '0$node',
                      style: TextStyle(
                        color: node == 1
                            ? AppPalette.navy
                            : Theme.of(context).colorScheme.onSurface,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Flexible(
                    child: Text(
                      'Node $node',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              StatusBadge(
                snapshot == null
                    ? 'Menunggu'
                    : _freshnessLabel(data?['freshness']),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final sensor in sensors)
            _sensorRow(context, sensor, monitoringSensor(data, sensor.id)),
        ],
      ),
    );
  }

  Widget _sensorRow(
    BuildContext context,
    ({String id, String name, String unit, IconData icon}) sensor,
    Map<String, dynamic>? data,
  ) {
    final value = data?['value'] as num?;
    final display = monitoringValue(
      value,
      decimals: data?['decimals'] as int? ?? 1,
    );
    final name = data?['name'] as String? ?? sensor.name;
    final unit = data?['unit'] as String? ?? sensor.unit;
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    final last = data?['last_reading'] as String?;
    final age = last == null || snapshot == null
        ? null
        : DateTime.parse(snapshot!['generatedAt'] as String)
              .difference(DateTime.parse(last))
              .inSeconds;
    final freshness = age == null
        ? 'unavailable'
        : age <= (snapshot!['staleAfterSeconds'] as int)
        ? 'fresh'
        : 'stale';
    final label = Row(
      children: [
        Icon(sensor.icon, size: 20, color: muted),
        const SizedBox(width: 10),
        Expanded(child: Text(name, style: const TextStyle(fontSize: 13))),
      ],
    );
    final measurement = Semantics(
      label:
          'Node $node, $name: ${value == null ? 'belum tersedia' : '$display $unit'}',
      excludeSemantics: true,
      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 8,
        children: [
          Text(
            display,
            style: const TextStyle(fontSize: 23, fontWeight: FontWeight.w700),
          ),
          Text(unit, style: TextStyle(fontSize: 12, color: muted)),
        ],
      ),
    );
    return Padding(
      key: Key('sensor-$node-${sensor.id}'),
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LayoutBuilder(
            builder: (context, box) {
              if (box.maxWidth < 360 &&
                  MediaQuery.textScalerOf(context).scale(14) > 20) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [label, const SizedBox(height: 6), measurement],
                );
              }
              return Row(
                children: [
                  Expanded(child: label),
                  const SizedBox(width: 8),
                  Flexible(child: measurement),
                ],
              );
            },
          ),
          if (last != null) ...[
            const SizedBox(height: 4),
            Text(
              '${_freshnessLabel(freshness)} · ${monitoringTime(last)}',
              style: TextStyle(fontSize: 11, color: muted),
            ),
          ],
        ],
      ),
    );
  }
}

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.label, {super.key});
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.onSurface.withValues(alpha: .06),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(
        fontSize: 11,
        color: Theme.of(context).colorScheme.onSurfaceVariant,
      ),
    ),
  );
}

class EmptyHistory extends StatelessWidget {
  const EmptyHistory({super.key});
  @override
  Widget build(BuildContext context) => SurfaceCard(
    child: Row(
      children: [
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppPalette.yellow.withValues(alpha: .15),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(Icons.history_rounded, size: 24),
        ),
        const SizedBox(width: 14),
        const Expanded(
          child: Text(
            'Belum ada riwayat',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
          ),
        ),
      ],
    ),
  );
}
