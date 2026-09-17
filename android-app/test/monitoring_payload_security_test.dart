import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rebung_pintar/api_client.dart';
import 'package:rebung_pintar/main.dart';

import 'api_client_test.dart' show loginResponse;
import 'live_monitoring_test.dart' show snapshotFixture;

http.Response _snapshotResponse(Map<String, dynamic> snapshot) => http.Response(
  jsonEncode({'data': snapshot}),
  200,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

void main() {
  for (final id in <Object?>[
    'node-one',
    '01',
    '+1',
    '1 ',
    '1.0',
    '0',
    '3',
    1,
    null,
  ]) {
    test('monitoring rejects noncanonical node ID ${jsonEncode(id)}', () async {
      final snapshot =
          jsonDecode(jsonEncode(snapshotFixture())) as Map<String, dynamic>;
      snapshot['nodes'][0]['id'] = id;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient(
          (request) async => request.url.path.endsWith('/login')
              ? loginResponse()
              : _snapshotResponse(snapshot),
        ),
      );
      addTearDown(api.dispose);
      await api.login(
        email: 'viewer@example.test',
        password: 'fixture-password',
        deviceName: 'test',
      );
      await expectLater(
        api.monitoring(),
        throwsA(
          isA<ApiException>().having(
            (e) => e.message,
            'message',
            'Respons server tidak valid.',
          ),
        ),
      );
      expect(api.isAuthenticated, isTrue);
    });
  }

  test('monitoring rejects duplicate node identities', () async {
    final snapshot = snapshotFixture();
    snapshot['nodes'][1]['id'] = '1';
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient(
        (request) async => request.url.path.endsWith('/login')
            ? loginResponse()
            : _snapshotResponse(snapshot),
      ),
    );
    addTearDown(api.dispose);
    await api.login(
      email: 'viewer@example.test',
      password: 'fixture-password',
      deviceName: 'test',
    );
    await expectLater(api.monitoring(), throwsA(isA<ApiException>()));
  });

  for (final ids in <List<String>>[
    [],
    ['1'],
    ['2'],
    ['2', '1'],
  ]) {
    test('monitoring preserves canonical node subset/order $ids', () async {
      final snapshot = snapshotFixture();
      final nodes = snapshot['nodes'] as List;
      snapshot['nodes'] = [
        for (final id in ids) nodes.firstWhere((node) => node['id'] == id),
      ];
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient(
          (request) async => request.url.path.endsWith('/login')
              ? loginResponse()
              : _snapshotResponse(snapshot),
        ),
      );
      addTearDown(api.dispose);
      await api.login(
        email: 'viewer@example.test',
        password: 'fixture-password',
        deviceName: 'test',
      );
      expect(await api.monitoring(), snapshot);
    });
  }

  testWidgets(
    'malformed node ID is a recoverable error instead of a build crash',
    (tester) async {
      final snapshot = snapshotFixture();
      snapshot['nodes'][0]['id'] = 'node-one';
      var valid = false;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient(
          (request) async => request.url.path.endsWith('/login')
              ? loginResponse()
              : _snapshotResponse(valid ? snapshotFixture() : snapshot),
        ),
      );
      addTearDown(api.dispose);
      await tester.pumpWidget(MainApp(api: api));
      await tester.enterText(
        find.byKey(const Key('email')),
        'viewer@example.test',
      );
      await tester.enterText(
        find.byKey(const Key('password')),
        'fixture-password',
      );
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Respons server tidak valid.'), findsOneWidget);
      expect(find.text('Gagal memuat data'), findsOneWidget);
      expect(find.text('31.2'), findsNothing);
      expect(api.isAuthenticated, isTrue);
      valid = true;
      await tester.tap(find.byTooltip('Muat ulang'));
      await tester.pumpAndSettle();
      expect(find.text('31.2'), findsOneWidget);
      expect(find.text('Respons server tidak valid.'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
}
