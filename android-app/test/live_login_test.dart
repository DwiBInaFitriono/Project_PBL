import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rebung_pintar/api_client.dart';
import 'package:rebung_pintar/main.dart';

final _user = {
  'id': 1,
  'name': 'Operator uji',
  'email': 'operator@example.test',
  'role': 'operator',
};
http.Response _json(Object value, [int status = 200]) =>
    http.Response(jsonEncode(value), status);
http.Response _login() => _json({
  'token': 'fixture-token',
  'token_type': 'Bearer',
  'expires_at': '2030-01-01T00:00:00Z',
  'user': _user,
});
http.Response _snapshot() => _json({
  'data': {
    'nodes': [],
    'sensors': [],
    'generatedAt': '2026-09-16T00:00:00Z',
    'staleAfterSeconds': 300,
    'pollIntervalSeconds': 15,
  },
});
Future<void> _submit(WidgetTester tester) async {
  await tester.enterText(
    find.byKey(const Key('email')),
    'operator@example.test',
  );
  await tester.enterText(find.byKey(const Key('password')), 'fixture-password');
  await tester.ensureVisible(find.byKey(const Key('submit')));
  await tester.tap(find.byKey(const Key('submit')));
}

void main() {
  testWidgets(
    'late login after screen disposal revokes token without touching controllers',
    (tester) async {
      final login = Completer<http.Response>();
      var logouts = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) return login.future;
          if (request.url.path.endsWith('/auth/logout')) logouts++;
          return http.Response('', 204);
        }),
      );
      addTearDown(api.dispose);
      await tester.pumpWidget(MainApp(api: api));
      await _submit(tester);
      await tester.pump();
      await tester.pumpWidget(const SizedBox());
      login.complete(_login());
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(logouts, 1);
      expect(api.isAuthenticated, isFalse);
    },
  );
  testWidgets(
    'Pemantau tetap punya riwayat/akun tanpa menu ESP atau label pratinjau',
    (tester) async {
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) {
            final data = jsonDecode(_login().body) as Map<String, dynamic>;
            data['user']['role'] = 'viewer';
            return _json(data);
          }
          return _snapshot();
        }),
      );
      addTearDown(api.dispose);
      await tester.pumpWidget(MainApp(api: api));
      await _submit(tester);
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Buka navigasi'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('nav-account')), findsOneWidget);
      expect(find.byKey(const Key('nav-history')), findsOneWidget);
      expect(find.byKey(const Key('nav-esp')), findsNothing);
      expect(find.textContaining('PRATINJAU'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets('Back ganda menunggu logout tanpa membocorkan sesi', (
    tester,
  ) async {
    final logout = Completer<http.Response>();
    var logouts = 0;
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        if (request.url.path.endsWith('/auth/login')) return _login();
        if (request.url.path.endsWith('/auth/logout')) {
          logouts++;
          return logout.future;
        }
        return _snapshot();
      }),
    );
    addTearDown(api.dispose);
    await tester.pumpWidget(MainApp(api: api));
    await _submit(tester);
    await tester.pumpAndSettle();
    await tester.binding.handlePopRoute();
    await tester.pump();
    await tester.binding.handlePopRoute();
    await tester.pump();
    expect(find.byTooltip('Keluar'), findsNothing);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(api.isAuthenticated, isFalse);
    expect(logouts, 1);
    logout.complete(http.Response('', 204));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('submit')), findsOneWidget);
    expect(api.isAuthenticated, isFalse);
  });
  testWidgets('login v1 benar memuat monitoring lalu logout mencabut token', (
    tester,
  ) async {
    final paths = <String>[];
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        paths.add('${request.method} ${request.url.path}');
        if (request.url.path.endsWith('/auth/login')) {
          expect(jsonDecode(request.body)['email'], 'operator@example.test');
          return _login();
        }
        expect(request.headers['Authorization'], 'Bearer fixture-token');
        if (request.url.path.endsWith('/auth/logout')) {
          return http.Response('', 204);
        }
        return _snapshot();
      }),
    );
    addTearDown(api.dispose);
    await tester.pumpWidget(MainApp(api: api));
    await _submit(tester);
    await tester.pumpAndSettle();
    expect(find.byTooltip('Keluar'), findsOneWidget);
    expect(paths, contains('GET /api/v1/monitoring'));
    expect(api.isAuthenticated, isTrue);
    await tester.tap(find.byTooltip('Keluar'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('submit')), findsOneWidget);
    expect(paths, contains('POST /api/v1/auth/logout'));
    expect(api.isAuthenticated, isFalse);
    expect(
      tester
          .widget<TextField>(
            find.descendant(
              of: find.byKey(const Key('password')),
              matching: find.byType(TextField),
            ),
          )
          .controller!
          .text,
      isEmpty,
    );
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('sandi salah tetap login dan tidak mengakses monitoring', (
    tester,
  ) async {
    var calls = 0;
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        calls++;
        return _json({}, 401);
      }),
    );
    addTearDown(api.dispose);
    await tester.pumpWidget(MainApp(api: api));
    await _submit(tester);
    await tester.pumpAndSettle();
    expect(find.text('Email atau kata sandi salah.'), findsOneWidget);
    expect(find.byTooltip('Keluar'), findsNothing);
    expect(calls, 1);
  });
  testWidgets('submit ganda diblokir selama login', (tester) async {
    final completer = Completer<http.Response>();
    var calls = 0;
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((_) {
        calls++;
        return completer.future;
      }),
    );
    addTearDown(api.dispose);
    await tester.pumpWidget(MainApp(api: api));
    await _submit(tester);
    await tester.pump();
    expect(
      tester.widget<FilledButton>(find.byKey(const Key('submit'))).onPressed,
      isNull,
    );
    expect(calls, 1);
    completer.complete(_json({}, 401));
    await tester.pumpAndSettle();
  });
  testWidgets('401 monitoring kembali login dan menghentikan polling', (
    tester,
  ) async {
    var reads = 0;
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        if (request.url.path.endsWith('/auth/login')) return _login();
        reads++;
        return _json({}, 401);
      }),
    );
    addTearDown(api.dispose);
    await tester.pumpWidget(MainApp(api: api));
    await _submit(tester);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('submit')), findsOneWidget);
    await tester.pump(const Duration(seconds: 60));
    expect(reads, 1);
  });
}
