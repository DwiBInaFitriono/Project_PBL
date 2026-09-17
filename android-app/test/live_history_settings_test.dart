import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rebung_pintar/api_client.dart';
import 'package:rebung_pintar/history_page.dart';
import 'package:rebung_pintar/settings_pages.dart';

// Exercise the real read/validation path without credentials or a network.
class ReadOnlyTestClient extends ApiClient {
  ReadOnlyTestClient(FutureOr<http.Response> Function(http.Request) respond)
    : super(
        baseUrl: 'https://example.invalid/api/v1',
        client: MockClient((request) async => respond(request)),
      );

  @override
  bool get isAuthenticated => true;
}

http.Response jsonResponse(Object body, {int status = 200}) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Map<String, Object?> historyResponse({
  int page = 1,
  int lastPage = 2,
  int total = 26,
  num value = 28.5,
  String node = '2',
}) => {
  'data': [
    {
      'id': page,
      'node_id': node,
      'node_name': 'Node $node',
      'sensor_id': 'temperature',
      'sensor_name': 'Suhu udara',
      'unit': '°C',
      'value': value,
      'recorded_at': '2026-08-01T18:30:00Z',
    },
  ],
  'meta': {
    'current_page': page,
    'last_page': lastPage,
    'per_page': 25,
    'total': total,
  },
  'filters': {
    'node': node,
    'sensor': 'temperature',
    'from': '2026-08-02',
    'to': '2026-08-03',
    'timezone': 'Asia/Jakarta',
  },
};

HistoryDraft fixedDraft() => HistoryDraft(node: '2', sensor: 'temperature')
  ..from = DateTime.utc(2026, 8, 2)
  ..to = DateTime.utc(2026, 8, 3);

Widget host(Widget child) => MaterialApp(
  home: Scaffold(
    body: SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: child,
    ),
  ),
);

Future<void> tapKey(WidgetTester tester, String key) async {
  final finder = find.byKey(Key(key));
  await tester.ensureVisible(finder);
  await tester.tap(finder);
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

Future<void> chooseNode(WidgetTester tester, String label) async {
  await tapKey(tester, 'history-node');
  await tester.tap(find.text(label).last);
  await tester.pumpAndSettle();
}

Widget settingsPage(bool esp, ApiClient api, {VoidCallback? onExpired}) => esp
    ? EspSettingsPage(api: api, onSessionExpired: onExpired)
    : AccountSettingsPage(api: api, onSessionExpired: onExpired);

Object settingsPayload(bool esp, {String name = 'Dari server'}) => {
  'data': esp
      ? {
          'nodes': [
            {'id': '1', 'name': name},
          ],
          'sensors': [
            {
              'id': 'temperature',
              'name': 'Suhu udara',
              'unit': '°C',
              'decimals': 1,
            },
          ],
          'integration': {'mqtt_enabled': false, 'hardware_connected': false},
        }
      : {
          'id': 12,
          'name': name,
          'email': 'operator@example.invalid',
          'role': 'operator',
        },
};

void main() {
  testWidgets('read-only profile exposes account values to accessibility', (tester) async {
    final semantics = tester.ensureSemantics();
    try {
      final api = ReadOnlyTestClient((_) => jsonResponse(settingsPayload(false)));
      addTearDown(api.dispose);
      await tester.pumpWidget(host(AccountSettingsPage(api: api)));
      await tester.pumpAndSettle();
      expect(find.bySemanticsLabel('Alamat email: operator@example.invalid'), findsOneWidget);
    } finally {
      semantics.dispose();
    }
  });
  testWidgets('history dates validate locally and keep the applied query', (
    tester,
  ) async {
    final queries = <Map<String, String>>[];
    final api = ReadOnlyTestClient((request) {
      queries.add(request.url.queryParameters);
      return jsonResponse(historyResponse());
    });
    addTearDown(api.dispose);
    final draft = fixedDraft();
    await tester.pumpWidget(host(HistoryPage(api: api, draft: draft)));
    await tester.pumpAndSettle();
    Future<void> choose(String key, DateTime date) async {
      await tapKey(tester, key);
      tester
          .widget<CalendarDatePicker>(find.byType(CalendarDatePicker))
          .onDateChanged(date);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Pilih'));
      await tester.pumpAndSettle();
    }

    await choose('history-to', DateTime(2026, 8, 1));
    await tapKey(tester, 'apply-history');
    expect(
      find.text('Sampai tanggal tidak boleh sebelum Dari tanggal.'),
      findsOneWidget,
    );
    expect(queries, hasLength(1));
    await choose('history-to', DateTime(2028, 8, 2));
    await tapKey(tester, 'apply-history');
    expect(find.text('Rentang tanggal maksimal 366 hari.'), findsOneWidget);
    expect(queries, hasLength(1));
    await choose('history-to', DateTime(2026, 8, 4));
    await tapKey(tester, 'apply-history');
    await tester.pumpAndSettle();
    expect(queries.last['to'], '2026-08-04');
    expect(queries.last['timezone'], 'Asia/Jakarta');
    expect(draft.to.isUtc, isTrue);
  });

  for (final page in ['history', 'account', 'esp']) {
    for (final failure in ['invalid', 'network']) {
      testWidgets('$page reports $failure failure instead of success', (
        tester,
      ) async {
        final api = ReadOnlyTestClient((request) {
          if (failure == 'network') throw http.ClientException('test boundary');
          return jsonResponse({'data': []});
        });
        addTearDown(api.dispose);
        await tester.pumpWidget(
          host(
            page == 'history'
                ? HistoryPage(api: api, draft: fixedDraft())
                : settingsPage(page == 'esp', api),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          find.text(
            failure == 'network'
                ? 'Tidak dapat terhubung ke server.'
                : 'Respons server tidak valid.',
          ),
          findsOneWidget,
        );
        expect(find.text('0 pembacaan'), findsNothing);
        expect(find.text('Belum ada riwayat'), findsNothing);
        expect(find.text('Profil hanya baca'), findsNothing);
        expect(find.text('Konfigurasi hanya baca'), findsNothing);
        expect(tester.takeException(), isNull);
      });
    }
  }

  testWidgets('preview retains filters and disabled account actions', (
    tester,
  ) async {
    final draft = fixedDraft();
    await tester.pumpWidget(host(HistoryPage(draft: draft)));
    await tester.pumpAndSettle();
    expect(find.text('PRATINJAU · Filter lokal'), findsOneWidget);
    await chooseNode(tester, 'Node 1');
    await tapKey(tester, 'apply-history');
    await tester.pumpWidget(host(const AccountSettingsPage()));
    await tester.pumpAndSettle();
    expect(
      find.text('PRATINJAU · Belum masuk. Form nonaktif.'),
      findsOneWidget,
    );
    for (final field in tester.widgetList<TextField>(find.byType(TextField))) {
      expect(field.enabled, isFalse);
    }
    for (final button in tester.widgetList<FilledButton>(
      find.byType(FilledButton),
    )) {
      expect(button.onPressed, isNull);
    }
    await tester.pumpWidget(host(HistoryPage(draft: draft)));
    await tester.pumpAndSettle();
    expect(find.textContaining('Node 1 · Suhu udara\n'), findsOneWidget);
    await tester.pumpWidget(host(const EspSettingsPage()));
    await tester.pumpAndSettle();
    expect(find.text('PRATINJAU · MQTT belum aktif.'), findsOneWidget);
    expect(find.text('Belum terhubung'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets('live populated pages fit 320px at 2x text', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 568);
    tester.platformDispatcher.textScaleFactorTestValue = 2;
    addTearDown(tester.view.reset);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    final api = ReadOnlyTestClient(
      (request) => jsonResponse(
        request.url.path.endsWith('/history')
            ? historyResponse(value: 0)
            : settingsPayload(request.url.path.endsWith('/esp')),
      ),
    );
    addTearDown(api.dispose);
    await tester.pumpWidget(host(HistoryPage(api: api, draft: fixedDraft())));
    await tester.pumpAndSettle();
    expect(find.text('0 °C'), findsOneWidget);
    await tester.ensureVisible(find.text('0 °C'));
    expect(tester.takeException(), isNull);
    for (final esp in [false, true]) {
      await tester.pumpWidget(host(settingsPage(esp, api)));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Dari server'));
      expect(tester.takeException(), isNull);
    }
  });

  testWidgets(
    'history stays expired when its draft changes on the same client',
    (tester) async {
      var calls = 0;
      var expired = 0;
      final api = ReadOnlyTestClient((request) {
        calls++;
        return jsonResponse({}, status: 401);
      });
      addTearDown(api.dispose);
      await tester.pumpWidget(
        host(
          HistoryPage(
            api: api,
            draft: fixedDraft(),
            onSessionExpired: () => expired++,
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.pumpWidget(
        host(
          HistoryPage(
            api: api,
            draft: fixedDraft(),
            onSessionExpired: () => expired++,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(calls, 1);
      expect(expired, 1);
      expect(
        find.text('Sesi berakhir. Silakan masuk kembali.'),
        findsOneWidget,
      );
    },
  );

  for (final esp in [false, true]) {
    testWidgets('settings esp=$esp errors allow retry without fake data', (
      tester,
    ) async {
      var calls = 0;
      final api = ReadOnlyTestClient((request) {
        calls++;
        return calls == 1
            ? jsonResponse({}, status: 403)
            : jsonResponse(settingsPayload(esp));
      });
      addTearDown(api.dispose);
      await tester.pumpWidget(host(settingsPage(esp, api)));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Anda tidak memiliki akses.'), findsOneWidget);
      expect(find.text('Dari server'), findsNothing);
      await tapKey(tester, 'settings-retry');
      await tester.pumpAndSettle();
      expect(calls, 2);
      expect(find.text('Dari server'), findsOneWidget);
    });

    testWidgets('settings esp=$esp stops after one 401', (tester) async {
      var calls = 0;
      var expired = 0;
      final api = ReadOnlyTestClient((request) {
        calls++;
        return jsonResponse({}, status: 401);
      });
      addTearDown(api.dispose);
      await tester.pumpWidget(
        host(settingsPage(esp, api, onExpired: () => expired++)),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(expired, 1);
      expect(
        find.text('Sesi berakhir. Silakan masuk kembali.'),
        findsOneWidget,
      );
      expect(find.byKey(const Key('settings-retry')), findsNothing);
      await tester.pumpWidget(
        host(settingsPage(esp, api, onExpired: () => expired++)),
      );
      await tester.pump(const Duration(minutes: 1));
      expect(calls, 1);
      expect(expired, 1);
    });

    testWidgets('settings esp=$esp ignores old client and disposed responses', (
      tester,
    ) async {
      final oldResponse = Completer<http.Response>();
      final oldApi = ReadOnlyTestClient((request) => oldResponse.future);
      final newResponse = Completer<http.Response>();
      final newApi = ReadOnlyTestClient((request) => newResponse.future);
      addTearDown(oldApi.dispose);
      addTearDown(newApi.dispose);
      await tester.pumpWidget(host(settingsPage(esp, oldApi)));
      await tester.pump();
      await tester.pumpWidget(host(settingsPage(esp, newApi)));
      await tester.pump();
      newResponse.complete(jsonResponse(settingsPayload(esp, name: 'Baru')));
      await tester.pumpAndSettle();
      expect(find.text('Baru'), findsOneWidget);
      oldResponse.complete(jsonResponse(settingsPayload(esp, name: 'Lama')));
      await tester.pumpAndSettle();
      expect(find.text('Lama'), findsNothing);
      expect(find.text('Baru'), findsOneWidget);
      final lateResponse = Completer<http.Response>();
      final lateApi = ReadOnlyTestClient((request) => lateResponse.future);
      addTearDown(lateApi.dispose);
      await tester.pumpWidget(host(settingsPage(esp, lateApi)));
      await tester.pump();
      await tester.pumpWidget(host(const SizedBox()));
      lateResponse.complete(jsonResponse({}, status: 401));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('ESP uses server configuration, not MQTT as online proof', (
    tester,
  ) async {
    final requests = <http.Request>[];
    final api = ReadOnlyTestClient((request) {
      requests.add(request);
      return jsonResponse({
        'data': {
          'nodes': [
            {'id': '2', 'name': 'Kebun dari server'},
          ],
          'sensors': [
            {
              'id': 'temperature',
              'name': 'Suhu terkonfigurasi',
              'unit': '°C',
              'decimals': 1,
            },
          ],
          'integration': {'mqtt_enabled': true, 'hardware_connected': false},
        },
      });
    });
    addTearDown(api.dispose);
    await tester.pumpWidget(host(EspSettingsPage(api: api)));
    await tester.pumpAndSettle();
    expect(requests.single.method, 'GET');
    expect(requests.single.url.path, '/api/v1/settings/esp');
    expect(find.text('Kebun dari server'), findsOneWidget);
    expect(find.text('Suhu terkonfigurasi (°C)'), findsOneWidget);
    expect(find.text('MQTT aktif di server'), findsOneWidget);
    expect(find.text('Koneksi perangkat belum terverifikasi'), findsOneWidget);
    expect(find.textContaining('online'), findsNothing);
    expect(find.textContaining('PRATINJAU'), findsNothing);
    expect(find.text('Konfigurasi hanya baca'), findsOneWidget);
  });

  testWidgets('account displays the server profile read-only', (tester) async {
    final requests = <http.Request>[];
    final api = ReadOnlyTestClient((request) {
      requests.add(request);
      return jsonResponse({
        'data': {
          'id': 12,
          'name': 'Nama dari server',
          'email': 'viewer@example.invalid',
          'role': 'viewer',
        },
      });
    });
    addTearDown(api.dispose);
    await tester.pumpWidget(host(AccountSettingsPage(api: api)));
    await tester.pumpAndSettle();
    expect(requests.single.method, 'GET');
    expect(requests.single.url.path, '/api/v1/me');
    expect(find.text('Nama dari server'), findsOneWidget);
    expect(find.text('viewer@example.invalid'), findsOneWidget);
    expect(find.text('Pemantau (viewer)'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
    expect(find.text('Simpan perubahan'), findsNothing);
    expect(find.textContaining('PRATINJAU'), findsNothing);
    expect(find.text('Profil hanya baca'), findsOneWidget);
  });

  for (final status in [403, 422, 429, 500]) {
    testWidgets('history $status is an error, with explicit retry', (
      tester,
    ) async {
      var calls = 0;
      final api = ReadOnlyTestClient((request) {
        calls++;
        return calls == 1
            ? jsonResponse({}, status: status)
            : jsonResponse(historyResponse(total: 0)..['data'] = []);
      });
      addTearDown(api.dispose);
      await tester.pumpWidget(host(HistoryPage(api: api, draft: fixedDraft())));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Gagal memuat riwayat'), findsOneWidget);
      expect(find.text('0 pembacaan'), findsNothing);
      expect(find.text('Belum ada riwayat'), findsNothing);
      await tapKey(tester, 'history-retry');
      await tester.pumpAndSettle();
      expect(calls, 2);
      expect(find.text('0 pembacaan'), findsOneWidget);
      expect(find.text('Belum ada riwayat'), findsOneWidget);
    });
  }

  testWidgets('history ignores responses superseded by edits or navigation', (
    tester,
  ) async {
    final pending = <Completer<http.Response>>[];
    final api = ReadOnlyTestClient((request) {
      final result = Completer<http.Response>();
      pending.add(result);
      return result.future;
    });
    addTearDown(api.dispose);
    final draft = fixedDraft();
    await tester.pumpWidget(host(HistoryPage(api: api, draft: draft)));
    await tester.pump();
    await chooseNode(tester, 'Node 1');
    pending.first.complete(jsonResponse(historyResponse(value: 999)));
    await tester.pumpAndSettle();
    expect(find.text('999 °C'), findsNothing);
    await tapKey(tester, 'apply-history');
    await tester.pumpWidget(host(const SizedBox()));
    await tester.pumpWidget(host(HistoryPage(api: api, draft: draft)));
    await tester.pump();
    pending.last.complete(jsonResponse(historyResponse(value: 17)));
    await tester.pumpAndSettle();
    pending[1].complete(jsonResponse(historyResponse(page: 2, value: 888)));
    await tester.pumpAndSettle();
    expect(find.text('17 °C'), findsOneWidget);
    expect(find.text('888 °C'), findsNothing);
    expect(find.text('Halaman 1 dari 2'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('history 401 expires once even with overlapping requests', (
    tester,
  ) async {
    final pending = <Completer<http.Response>>[];
    final api = ReadOnlyTestClient((request) {
      final result = Completer<http.Response>();
      pending.add(result);
      return result.future;
    });
    addTearDown(api.dispose);
    var expired = 0;
    await tester.pumpWidget(
      host(
        HistoryPage(
          api: api,
          draft: fixedDraft(),
          onSessionExpired: () => expired++,
        ),
      ),
    );
    await tester.pump();
    await tapKey(tester, 'apply-history');
    pending.first.complete(jsonResponse({}, status: 401));
    await tester.pumpAndSettle();
    pending.last.complete(jsonResponse(historyResponse()));
    await tester.pumpAndSettle();
    expect(expired, 1);
    expect(find.text('Sesi berakhir. Silakan masuk kembali.'), findsOneWidget);
    expect(find.text('28.5 °C'), findsNothing);
    expect(find.text('0 pembacaan'), findsNothing);
    await tapKey(tester, 'apply-history');
    await tapKey(tester, 'reset-history');
    expect(pending, hasLength(2));
    expect(expired, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pagination uses applied filters and survives draft navigation', (
    tester,
  ) async {
    final queries = <Map<String, String>>[];
    final api = ReadOnlyTestClient((request) {
      final query = request.url.queryParameters;
      queries.add(query);
      return jsonResponse(historyResponse(page: int.parse(query['page']!)));
    });
    addTearDown(api.dispose);
    final draft = fixedDraft();

    await tester.pumpWidget(host(HistoryPage(api: api, draft: draft)));
    await tester.pumpAndSettle();
    await chooseNode(tester, 'Node 1');
    expect(queries, hasLength(1));
    await tapKey(tester, 'history-next');
    await tester.pumpAndSettle();
    expect(queries.last['page'], '2');
    expect(queries.last['node'], '2');
    expect(find.text('Halaman 2 dari 2'), findsOneWidget);

    await tester.pumpWidget(host(const SizedBox()));
    await tester.pumpWidget(host(HistoryPage(api: api, draft: draft)));
    await tester.pumpAndSettle();
    expect(draft.node, '1');
    expect(queries.last['node'], '2');
    expect(queries.last['page'], '2');
    expect(find.textContaining('Node 2 · Suhu udara\n'), findsOneWidget);

    await tapKey(tester, 'history-previous');
    await tester.pumpAndSettle();
    expect(queries.last['page'], '1');
    expect(queries.last['node'], '2');
    await tapKey(tester, 'apply-history');
    await tester.pumpAndSettle();
    expect(queries.last['node'], '1');
    expect(queries.last['page'], '1');
    await tapKey(tester, 'reset-history');
    await tester.pumpAndSettle();
    expect(queries.last['node'], isNull);
    expect(queries.last['sensor'], isNull);
    expect(queries.last['page'], '1');
    expect(queries.last['timezone'], 'Asia/Jakarta');
    expect(draft.node, '');
    expect(draft.sensor, '');
    expect(draft.to.difference(draft.from).inDays, 6);
  });

  testWidgets('history sends applied WIB filters and renders server rows', (
    tester,
  ) async {
    final requests = <http.Request>[];
    final api = ReadOnlyTestClient((request) {
      requests.add(request);
      return jsonResponse(historyResponse());
    });
    addTearDown(api.dispose);

    await tester.pumpWidget(host(HistoryPage(api: api, draft: fixedDraft())));
    await tester.pumpAndSettle();

    expect(requests.single.url.path, '/api/v1/history');
    expect(requests.single.url.queryParameters, {
      'node': '2',
      'sensor': 'temperature',
      'from': '2026-08-02',
      'to': '2026-08-03',
      'timezone': 'Asia/Jakarta',
      'page': '1',
    });
    expect(find.text('28.5 °C'), findsOneWidget);
    expect(find.text('02/08/2026 01:30:00 WIB'), findsOneWidget);
    expect(find.text('26 pembacaan'), findsOneWidget);
    expect(find.textContaining('PRATINJAU'), findsNothing);
    expect(find.text('Belum ada riwayat'), findsNothing);
  });
}
