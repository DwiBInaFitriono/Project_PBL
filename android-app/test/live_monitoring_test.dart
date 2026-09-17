import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/app_palette.dart';
import 'package:rebung_pintar/chart_panel.dart';
import 'package:rebung_pintar/dashboard_widgets.dart';
import 'package:rebung_pintar/monitoring_page.dart';

Map<String, dynamic> snapshotFixture() {
  final definitions = [
    for (final sensor in sensors)
      {
        'id': sensor.id,
        'name': sensor.name,
        'unit': sensor.unit,
        'decimals': 1,
      },
  ];
  return {
    'generatedAt': '2026-09-16T00:00:00+00:00',
    'staleAfterSeconds': 300,
    'pollIntervalSeconds': 15,
    'sensors': definitions,
    'nodes': [
      for (final id in ['1', '2'])
        {
          'id': id,
          'name': 'Node $id',
          'status': id == '1' ? 'Data terbaru' : 'Data terlambat',
          'freshness': id == '1' ? 'fresh' : 'stale',
          'age_seconds': id == '1' ? 60 : 7200,
          'last_reading': id == '1'
              ? '2026-09-15T23:59:00+00:00'
              : '2026-09-15T22:00:00+00:00',
          'sensors': [
            for (final definition in definitions)
              {
                ...definition,
                'value': definition['id'] == 'temperature'
                    ? (id == '1' ? 0 : 31.2)
                    : null,
                'last_reading': definition['id'] == 'temperature'
                    ? (id == '1'
                          ? '2026-09-15T23:59:00+00:00'
                          : '2026-09-15T22:00:00+00:00')
                    : null,
                'readings': <Map<String, dynamic>>[],
              },
          ],
        },
    ],
  };
}

Widget monitoringApp({
  Map<String, dynamic>? snapshot,
  bool connected = true,
  int? node,
  String sensorId = 'temperature',
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
        connected: connected,
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
  testWidgets(
    'old sensor remains labelled stale when another sensor is fresh',
    (tester) async {
      final snapshot = snapshotFixture();
      snapshot['nodes'][0]['sensors'][1]['value'] = 65;
      snapshot['nodes'][0]['sensors'][1]['last_reading'] =
          '2026-09-14T00:00:00Z';
      await tester.pumpWidget(monitoringApp(snapshot: snapshot));
      expect(
        find.descendant(
          of: find.byKey(const Key('sensor-1-air_humidity')),
          matching: find.textContaining('Data terlambat'),
        ),
        findsOneWidget,
      );
      expect(
        find.descendant(
          of: find.byKey(const Key('sensor-1-temperature')),
          matching: find.textContaining('Data terbaru'),
        ),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('populated monitoring fits narrow screen and 2x text', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 568);
    tester.platformDispatcher.textScaleFactorTestValue = 2;
    addTearDown(tester.view.reset);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    final snapshot = snapshotFixture();
    snapshot['nodes'][1]['sensors'][0]['readings'] = [
      {'recorded_at': '2026-09-15T23:55:00Z', 'value': 0},
      {'recorded_at': '2026-09-15T23:55:00Z', 'value': 0},
    ];
    await tester.pumpWidget(monitoringApp(snapshot: snapshot));
    await tester.ensureVisible(find.byKey(const Key('monitoring-chart')));
    await tester.pump();
    expect(tester.takeException(), isNull);
    await tester.ensureVisible(find.byKey(const Key('recent-readings')));
    await tester.pump();
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(
      monitoringApp(
        snapshot: snapshot,
        error: 'Tidak dapat terhubung ke server.',
      ),
    );
    expect(find.byKey(const Key('monitoring-chart')), findsOneWidget);
    expect(find.byKey(const Key('recent-readings')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'snapshot metadata controls node count, sensor label and chart unit',
    (tester) async {
      final snapshot = snapshotFixture();
      snapshot['nodes'] = [snapshot['nodes'][1]];
      snapshot['sensors'][0]['name'] = 'Suhu sensor';
      snapshot['sensors'][0]['unit'] = '°C';
      snapshot['nodes'][0]['sensors'][0]['name'] = 'Suhu sensor';
      await tester.pumpWidget(monitoringApp(snapshot: snapshot));
      expect(find.byKey(const Key('node-card-1')), findsNothing);
      expect(find.byKey(const Key('node-card-2')), findsOneWidget);
      expect(find.text('Suhu sensor · °C'), findsOneWidget);
      expect(find.text('1'), findsOneWidget);
      expect(find.text('3'), findsOneWidget);
      await tester.tap(find.byTooltip('Tentang status data'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Data dari API Laravel'), findsOneWidget);
      expect(
        find.textContaining('Belum terhubung ke API Laravel'),
        findsNothing,
      );
    },
  );

  testWidgets('loading and failures do not masquerade as empty readings', (
    tester,
  ) async {
    await tester.pumpWidget(monitoringApp(loading: true));
    expect(find.text('Memuat data…'), findsWidgets);
    expect(find.text('Belum ada data'), findsNothing);
    expect(find.text('Belum ada riwayat'), findsNothing);
    expect(
      tester
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.refresh_rounded),
          )
          .onPressed,
      isNull,
    );
    await tester.pumpWidget(
      monitoringApp(error: 'Tidak dapat terhubung ke server.'),
    );
    expect(find.textContaining('Gagal memuat'), findsWidgets);
    expect(
      find.textContaining('Tidak dapat terhubung ke server.'),
      findsWidgets,
    );
    expect(find.text('Belum ada data'), findsNothing);
    expect(find.text('Belum ada riwayat'), findsNothing);
    await tester.pumpWidget(monitoringApp(snapshot: snapshotFixture()));
    expect(find.text('Belum ada data'), findsOneWidget);
    expect(find.text('Belum ada riwayat'), findsOneWidget);
    await tester.pumpWidget(
      monitoringApp(
        snapshot: snapshotFixture(),
        error: 'Permintaan kehabisan waktu. Coba lagi.',
      ),
    );
    expect(find.textContaining('Data terakhir dipertahankan'), findsOneWidget);
    expect(find.text('31.2'), findsOneWidget);
    await tester.pumpWidget(
      monitoringApp(snapshot: snapshotFixture(), loading: true),
    );
    expect(find.text('Memperbarui data…'), findsOneWidget);
    expect(find.text('31.2'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('recent readings render selected measurements newest first', (
    tester,
  ) async {
    final snapshot = snapshotFixture();
    snapshot['nodes'][0]['sensors'][0]['readings'] = [
      {'recorded_at': '2026-09-15T23:10:00Z', 'value': 4},
      {'recorded_at': '2026-09-15T23:55:00Z', 'value': 0},
    ];
    snapshot['nodes'][1]['sensors'][0]['readings'] = [
      {'recorded_at': '2026-09-15T23:30:00Z', 'value': 12},
    ];
    await tester.pumpWidget(monitoringApp(snapshot: snapshot));
    expect(find.text('Belum ada riwayat'), findsNothing);
    final rows = find.byKey(const Key('recent-readings'));
    expect(rows, findsOneWidget);
    final times = tester
        .widgetList<Text>(
          find.descendant(of: rows, matching: find.byType(Text)),
        )
        .map((t) => t.data)
        .where((t) => t != null && t.endsWith('WIB'))
        .toList();
    expect(times, [
      '16/09/2026 06:55 WIB',
      '16/09/2026 06:30 WIB',
      '16/09/2026 06:10 WIB',
    ]);
    expect(
      find.descendant(of: rows, matching: find.text('0.0 °C')),
      findsOneWidget,
    );
    await tester.pumpWidget(monitoringApp(snapshot: snapshot, node: 2));
    expect(
      find.descendant(of: rows, matching: find.text('0.0 °C')),
      findsNothing,
    );
    expect(
      find.descendant(of: rows, matching: find.text('12.0 °C')),
      findsOneWidget,
    );
    await tester.pumpWidget(
      monitoringApp(snapshot: snapshot, sensorId: 'soil_moisture'),
    );
    expect(find.byKey(const Key('recent-readings')), findsNothing);
    expect(find.text('Belum ada riwayat'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'chart filters server readings and statistics by time, node and sensor',
    (tester) async {
      final snapshot = snapshotFixture();
      snapshot['nodes'][0]['sensors'][0]['readings'] = [
        {'recorded_at': '2026-09-14T23:00:00Z', 'value': 999},
        {'recorded_at': '2026-09-15T00:00:00Z', 'value': 8},
        {'recorded_at': '2026-09-15T18:00:00Z', 'value': 10},
        {'recorded_at': '2026-09-15T23:00:00Z', 'value': 20},
        {'recorded_at': '2026-09-15T23:59:00Z', 'value': 0},
        {'recorded_at': '2026-09-16T00:00:00Z', 'value': 30},
        {'recorded_at': '2026-09-16T00:01:00Z', 'value': 777},
      ];
      snapshot['nodes'][0]['sensors'][1]['readings'] = [
        {'recorded_at': '2026-09-15T23:45:00Z', 'value': 90},
      ];
      snapshot['nodes'][1]['sensors'][0]['readings'] = [
        {'recorded_at': '2026-09-15T23:30:00Z', 'value': 12},
      ];
      await tester.pumpWidget(monitoringApp(snapshot: snapshot));
      expect(find.byKey(const Key('monitoring-chart')), findsOneWidget);
      expect(find.text('Belum ada data'), findsNothing);
      String? stat(String id) =>
          tester.widget<Text>(find.byKey(Key('stat-$id'))).data;
      expect(stat('1-min'), '0.0');
      expect(stat('1-average'), '16.7');
      expect(stat('1-max'), '30.0');
      expect(stat('2-average'), '12.0');
      final painter =
          tester
                  .widget<CustomPaint>(
                    find.byKey(const Key('monitoring-chart')),
                  )
                  .painter
              as MonitoringChartPainter;
      expect(painter.points.map((p) => p.value), [20, 12, 0, 30]);
      expect(painter.end, DateTime.utc(2026, 9, 16));
      expect(painter.start, DateTime.utc(2026, 9, 15, 23));

      await tester.pumpWidget(
        monitoringApp(snapshot: snapshot, node: 1, hours: 6),
      );
      expect(stat('1-average'), '15.0');
      expect(find.byKey(const Key('stat-2-min')), findsNothing);
      await tester.pumpWidget(
        monitoringApp(snapshot: snapshot, node: 1, hours: 24),
      );
      expect(stat('1-average'), '13.6');
      final dayPainter =
          tester
                  .widget<CustomPaint>(
                    find.byKey(const Key('monitoring-chart')),
                  )
                  .painter
              as MonitoringChartPainter;
      expect(dayPainter.points.map((p) => p.value), [8, 10, 20, 0, 30]);
      await tester.pumpWidget(
        monitoringApp(snapshot: snapshot, node: 1, sensorId: 'air_humidity'),
      );
      expect(stat('1-average'), '90.0');
      await tester.pumpWidget(
        monitoringApp(snapshot: snapshot, node: 1, sensorId: 'soil_moisture'),
      );
      expect(find.byKey(const Key('monitoring-chart')), findsNothing);
      expect(find.text('Belum ada data'), findsOneWidget);
      expect(stat('1-average'), '—');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'snapshot cards preserve zero, missing values and WIB freshness',
    (tester) async {
      final semantics = tester.ensureSemantics();
      await tester.pumpWidget(monitoringApp(snapshot: snapshotFixture()));
      expect(find.text('PRATINJAU · Belum terhubung'), findsNothing);
      final temperature = find.byKey(const Key('sensor-1-temperature'));
      expect(
        find.descendant(of: temperature, matching: find.text('0.0')),
        findsOneWidget,
      );
      expect(
        find.descendant(
          of: find.byKey(const Key('sensor-1-air_humidity')),
          matching: find.text('—'),
        ),
        findsOneWidget,
      );
      expect(find.text('31.2'), findsOneWidget);
      expect(find.text('Data terbaru'), findsWidgets);
      expect(find.text('Data terlambat'), findsWidgets);
      expect(find.textContaining('16/09/2026 06:59 WIB'), findsWidgets);
      expect(find.textContaining('16/09/2026 05:00 WIB'), findsWidgets);
      expect(
        find.bySemanticsLabel('Node 1, Suhu udara: 0.0 °C'),
        findsOneWidget,
      );
      expect(
        find.bySemanticsLabel('Node 1, Kelembapan udara: belum tersedia'),
        findsOneWidget,
      );
      expect(find.textContaining('Online'), findsNothing);
      expect(tester.takeException(), isNull);
      semantics.dispose();
    },
  );
}
