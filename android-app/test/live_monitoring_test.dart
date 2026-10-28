import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hidroponik_dft/app_palette.dart';
import 'package:hidroponik_dft/chart_panel.dart';
import 'package:hidroponik_dft/dashboard_widgets.dart';
import 'package:hidroponik_dft/monitoring_page.dart';

Map<String, dynamic> snapshotFixture() {
  List<Map<String, dynamic>> nodeSensors(int node) => [
    for (final sensor in sensorsForNode(node))
      {
        'id': sensor.id,
        'name': sensor.name,
        'unit': sensor.unit,
        'decimals': 1,
        'value': sensor.id == 'ph' ? 0 : sensor.id == 'air_temperature' ? 31.2 : null,
        'last_reading': sensor.id == 'ph'
            ? '2026-09-15T23:59:00Z'
            : sensor.id == 'air_temperature'
            ? '2026-09-15T22:00:00Z'
            : null,
        'readings': <Map<String, dynamic>>[],
      },
  ];
  return {
    'generatedAt': '2026-09-16T00:00:00Z',
    'staleAfterSeconds': 300,
    'pollIntervalSeconds': 15,
    'sensors': [
      for (final sensor in sensors)
        {'id': sensor.id, 'name': sensor.name, 'unit': sensor.unit, 'decimals': 1},
    ],
    'nodes': [
      for (final node in [1, 2])
        {
          'id': '$node',
          'name': 'Node $node',
          'status': node == 1 ? 'online' : 'offline',
          'freshness': node == 1 ? 'fresh' : 'stale',
          'sensors': nodeSensors(node),
        },
    ],
  };
}

Widget monitoringApp({
  Map<String, dynamic>? snapshot,
  int? node,
  String sensorId = 'ph',
  int hours = 1,
  bool loading = false,
  String? error,
}) => MaterialApp(
  theme: AppPalette.theme(Brightness.light),
  home: Scaffold(
    body: SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: MonitoringPage(
        snapshot: snapshot,
        connected: true,
        loading: loading,
        error: error,
        node: node,
        sensorId: sensorId,
        hours: hours,
        onSensorChanged: (_) {},
        onHoursChanged: (_) {},
        onHistory: () {},
        onRefresh: () {},
      ),
    ),
  ),
);

void main() {
  testWidgets('node cards follow DFT allocation and preserve zero values', (tester) async {
    final semantics = tester.ensureSemantics();
    await tester.pumpWidget(monitoringApp(snapshot: snapshotFixture()));
    expect(find.byKey(const Key('sensor-1-ph')), findsOneWidget);
    expect(find.byKey(const Key('sensor-1-water_temperature')), findsOneWidget);
    expect(find.byKey(const Key('sensor-2-air_temperature')), findsOneWidget);
    expect(find.byKey(const Key('sensor-2-water_flow')), findsOneWidget);
    expect(find.byKey(const Key('sensor-1-air_temperature')), findsNothing);
    expect(find.descendant(of: find.byKey(const Key('sensor-1-ph')), matching: find.text('0.0')), findsOneWidget);
    expect(find.bySemanticsLabel('Node 1, pH nutrisi: 0.0 pH'), findsOneWidget);
    expect(find.text('31.2'), findsOneWidget);
    semantics.dispose();
  });

  testWidgets('chart uses selected node sensor readings only', (tester) async {
    final snapshot = snapshotFixture();
    snapshot['nodes'][0]['sensors'][0]['readings'] = [
      {'recorded_at': '2026-09-15T23:30:00Z', 'value': 6},
      {'recorded_at': '2026-09-15T23:55:00Z', 'value': 0},
    ];
    await tester.pumpWidget(monitoringApp(snapshot: snapshot));
    expect(find.byKey(const Key('monitoring-chart')), findsOneWidget);
    expect(find.byKey(const Key('stat-1-min')), findsOneWidget);
    final painter = tester.widget<CustomPaint>(find.byKey(const Key('monitoring-chart'))).painter as MonitoringChartPainter;
    expect(painter.points.map((point) => point.value), [6, 0]);
    expect(find.text('Belum ada riwayat'), findsNothing);
  });

  testWidgets('empty loading and errors never masquerade as telemetry', (tester) async {
    await tester.pumpWidget(monitoringApp(loading: true));
    expect(find.text('Memuat data…'), findsWidgets);
    await tester.pumpWidget(monitoringApp(error: 'Tidak dapat terhubung ke server.'));
    expect(find.textContaining('Gagal memuat'), findsWidgets);
    await tester.pumpWidget(monitoringApp(snapshot: snapshotFixture()));
    expect(find.text('Belum ada data'), findsOneWidget);
    expect(find.text('Belum ada riwayat'), findsOneWidget);
  });
}
