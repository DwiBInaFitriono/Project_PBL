import 'package:flutter/material.dart';

import 'app_palette.dart';
import 'dashboard_widgets.dart';
import 'chart_panel.dart';
import 'gauge_widget.dart';
import 'model_3d_view.dart';


class MonitoringPage extends StatelessWidget {
  const MonitoringPage({
    super.key,
    this.node,
    required this.sensorId,
    required this.hours,
    required this.onSensorChanged,
    required this.onHoursChanged,
    required this.onHistory,
    required this.onRefresh,
    this.snapshot,
    this.connected = false,
    this.loading = false,
    this.error,
  });
  final Map<String, dynamic>? snapshot;
  final bool connected;
  final bool loading;
  final String? error;
  final int? node;
  final String sensorId;
  final int hours;
  final ValueChanged<String> onSensorChanged;
  final ValueChanged<int> onHoursChanged;
  final VoidCallback onHistory;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final nodes = snapshot == null
        ? (node == null ? [1, 2] : [node!])
        : [
            for (final data in snapshot!['nodes'] as List)
              if (node == null || data['id'] == '$node')
                int.parse(data['id'] as String),
          ];
    final sensorCount = snapshot == null
        ? nodes.length * sensors.length
        : nodes.fold<int>(
            0,
            (count, id) =>
                count +
                (monitoringNode(snapshot, id)!['sensors'] as List).length,
          );
    final readings = monitoringPoints(
      snapshot,
      nodes: nodes,
      sensorId: sensorId,
      hours: hours,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Text(
                node == null ? 'Ringkasan monitoring' : 'Monitoring Node $node',
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w700,
                  letterSpacing: -.8,
                ),
              ),
            ),
            const SizedBox(width: 10),
            IconButton.filled(
              tooltip: 'Muat ulang',
              style: IconButton.styleFrom(
                backgroundColor: AppPalette.yellow,
                foregroundColor: AppPalette.navy,
              ),
              onPressed: loading ? null : onRefresh,
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: Text(_status, style: const TextStyle(fontSize: 12)),
            ),
            IconButton(
              tooltip: 'Tentang status data',
              onPressed: () => showDialog<void>(
                context: context,
                builder: (context) => AlertDialog(
                  title: const Text('Status data'),
                  content: Text(
                    _preview
                        ? 'Belum terhubung ke API Laravel dan perangkat. Data kosong bukan nol. Pembaruan otomatis belum aktif; tidak ada pembacaan simulasi.'
                        : 'Data dari API Laravel. Data kosong bukan nol. Kesegaran pembacaan bukan status online perangkat. Statistik hanya menghitung titik yang dimuat; waktu WIB.',
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Mengerti'),
                    ),
                  ],
                ),
              ),
              icon: const Icon(Icons.info_outline_rounded, size: 19),
            ),
          ],
        ),
        if (error != null) ...[
          const SizedBox(height: 8),
          Semantics(
            liveRegion: true,
            child: InfoNotice(
              '$error${snapshot == null ? '' : ' Data terakhir dipertahankan.'}',
            ),
          ),
        ],
        const SizedBox(height: 14),
        LayoutBuilder(
          builder: (context, box) => Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _metric(
                context,
                'Node',
                '${nodes.length}',
                Icons.memory_rounded,
                AppPalette.yellow,
                (box.maxWidth - 12) / 2,
              ),
              _metric(
                context,
                'Sensor usulan',
                '$sensorCount',
                Icons.sensors_rounded,
                AppPalette.blue.withValues(alpha: .18),
                (box.maxWidth - 12) / 2,
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        TwoColumns(
          children: [
            for (final id in nodes)
              NodeCard(id, key: Key('node-card-$id'), snapshot: snapshot),
          ],
        ),
        const SizedBox(height: 24),
        Builder(
          builder: (context) {
            final activeNodeId = node ?? 1;
            final nodeData = monitoringNode(snapshot, activeNodeId);
            final sTemp = monitoringSensor(nodeData, 'temperature');
            final sAir = monitoringSensor(nodeData, 'air_humidity');
            final sSoil = monitoringSensor(nodeData, 'soil_moisture');
            final tempVal = sTemp != null && sTemp['value'] != null ? (sTemp['value'] as num).toDouble() : null;
            final airVal = sAir != null && sAir['value'] != null ? (sAir['value'] as num).toDouble() : null;
            final soilVal = sSoil != null && sSoil['value'] != null ? (sSoil['value'] as num).toDouble() : null;

            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionTitle(
                  'Gauge Indikator (Node $activeNodeId)',
                  'Visualisasi dial parameter sensor',
                ),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  alignment: WrapAlignment.spaceEvenly,
                  children: [
                    RadialGaugeWidget(
                      title: 'Suhu Udara',
                      value: tempVal,
                      unit: '°C',
                      minValue: 0,
                      maxValue: 50,
                      optimalMin: 22,
                      optimalMax: 30,
                    ),
                    RadialGaugeWidget(
                      title: 'Kelembapan Udara',
                      value: airVal,
                      unit: '% RH',
                      minValue: 0,
                      maxValue: 100,
                      optimalMin: 60,
                      optimalMax: 85,
                    ),
                    RadialGaugeWidget(
                      title: 'Kelembapan Tanah',
                      value: soilVal,
                      unit: '%',
                      minValue: 0,
                      maxValue: 100,
                      optimalMin: 45,
                      optimalMax: 75,
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                Bamboo3DViewer(
                  soilMoisturePercent: soilVal ?? 60.0,
                  temperatureC: tempVal ?? 27.0,
                  nodeName: 'Node $activeNodeId',
                ),
              ],
            );
          },
        ),
        const SizedBox(height: 24),
        SectionTitle(node == null ? 'Tren sensor' : 'Tren Node $node', ''),

        ChartPanel(
          snapshot: snapshot,
          connected: connected,
          loading: loading,
          error: error,
          nodes: nodes,
          sensorId: sensorId,
          hours: hours,
          onSensorChanged: onSensorChanged,
          onHoursChanged: onHoursChanged,
        ),
        const SizedBox(height: 24),
        SectionTitle(
          'Riwayat',
          '',
          action: TextButton(
            onPressed: onHistory,
            child: const Text('Lihat semua'),
          ),
        ),
        if (readings.isEmpty)
          snapshot == null && !_preview
              ? InfoNotice(
                  loading
                      ? 'Memuat riwayat…'
                      : error != null
                      ? 'Riwayat gagal dimuat'
                      : 'Menunggu data server',
                )
              : const EmptyHistory()
        else
          SurfaceCard(
            key: const Key('recent-readings'),
            padding: 16,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final reading in readings.reversed.take(10))
                  _recentReading(context, reading),
              ],
            ),
          ),
      ],
    );
  }

  bool get _preview =>
      !connected && snapshot == null && !loading && error == null;

  String get _status {
    if (loading) return snapshot == null ? 'Memuat data…' : 'Memperbarui data…';
    if (error != null) return 'Gagal memuat data';
    if (snapshot != null) {
      return 'REST · ${monitoringTime(snapshot!['generatedAt'])}';
    }
    return _preview ? 'PRATINJAU · Belum terhubung' : 'Menunggu data server';
  }

  Widget _recentReading(BuildContext context, MonitoringPoint reading) {
    final definition = monitoringSensor(
      monitoringNode(snapshot, reading.node),
      sensorId,
    )!;
    final value =
        '${monitoringValue(reading.value, decimals: definition['decimals'] as int)} ${definition['unit']}';
    final time = monitoringTime(reading.recordedAt.toIso8601String());
    return Semantics(
      label: 'Node ${reading.node}, ${definition['name']}, $value, $time',
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Wrap(
              alignment: WrapAlignment.spaceBetween,
              spacing: 16,
              runSpacing: 4,
              children: [
                Text(
                  'Node ${reading.node} · ${definition['name']}',
                  style: const TextStyle(fontSize: 13),
                ),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              time,
              style: TextStyle(
                fontSize: 12,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _metric(
    BuildContext context,
    String label,
    String value,
    IconData icon,
    Color tint,
    double width,
  ) => SizedBox(
    width: width,
    child: SurfaceCard(
      padding: 14,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: tint,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              icon,
              size: 20,
              color: label == 'Node'
                  ? AppPalette.navy
                  : Theme.of(context).colorScheme.onSurface,
            ),
          ),
          const SizedBox(height: 14),
          Text(
            value,
            style: const TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.w700,
              height: 1,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    ),
  );
}
