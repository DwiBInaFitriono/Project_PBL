import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rebung_pintar/api_client.dart';
import 'package:rebung_pintar/dashboard_page.dart';
import 'package:rebung_pintar/main.dart';

import 'api_client_test.dart' show loginResponse, testUser;
import 'live_monitoring_test.dart' show snapshotFixture;
import 'live_history_settings_test.dart' show historyResponse;

http.Response _json(Object data, [int status = 200]) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Future<void> _enter(WidgetTester tester, ApiClient api) async {
  await tester.pumpWidget(MainApp(api: api));
  await tester.enterText(
    find.byKey(const Key('email')),
    testUser['email']! as String,
  );
  await tester.enterText(find.byKey(const Key('password')), 'fixture-password');
  await tester.ensureVisible(find.byKey(const Key('submit')));
  await tester.tap(find.byKey(const Key('submit')));
  await tester.pumpAndSettle();
}

Future<void> _navigate(WidgetTester tester, String destination) async {
  await tester.tap(find.byTooltip('Buka navigasi'));
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.byKey(Key('nav-$destination')));
  await tester.tap(find.byKey(Key('nav-$destination')));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'logout timeout leaves no private content and returns to login with a warning',
    (tester) async {
      final logout = Completer<http.Response>();
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) return loginResponse();
          if (request.url.path.endsWith('/logout')) return logout.future;
          return _json({'data': snapshotFixture()});
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      await tester.tap(find.byTooltip('Keluar'));
      await tester.pump();
      expect(find.text('31.2', skipOffstage: false), findsNothing);
      expect(find.byTooltip('Tentang status data'), findsNothing);
      await tester.pump(const Duration(seconds: 16));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('submit')), findsOneWidget);
      expect(
        find.text(
          'Sesi lokal ditutup. Pencabutan token server belum terkonfirmasi.',
        ),
        findsOneWidget,
      );
      expect(api.isAuthenticated, isFalse);
      logout.complete(http.Response('', 204));
      await tester.pumpAndSettle();
      expect(find.byType(DashboardPage, skipOffstage: false), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'late logout failure after disposal cannot affect a later login',
    (tester) async {
      final logout = Completer<http.Response>();
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) return loginResponse();
          if (request.url.path.endsWith('/logout')) return logout.future;
          return _json({'data': snapshotFixture()});
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      await tester.tap(find.byTooltip('Keluar'));
      await tester.pump();
      await tester.pumpWidget(const SizedBox());
      await _enter(tester, api);
      logout.complete(_json({}, 500));
      await tester.pumpAndSettle();
      expect(api.isAuthenticated, isTrue);
      expect(find.text('31.2'), findsOneWidget);
      expect(find.textContaining('Pencabutan token server'), findsNothing);
      expect(find.byType(DashboardPage), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  for (final overlay in ['drawer', 'dialog', 'date picker', 'dropdown']) {
    testWidgets('logout clears $overlay before remote revocation completes', (
      tester,
    ) async {
      final logout = Completer<http.Response>();
      var logouts = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) return loginResponse();
          if (request.url.path.endsWith('/logout')) {
            logouts++;
            return logout.future;
          }
          if (request.url.path.endsWith('/me')) {
            return _json({
              'data': {...testUser, 'email': 'private-profile@example.test'},
            });
          }
          if (request.url.path.endsWith('/history')) {
            return _json(historyResponse());
          }
          return _json({'data': snapshotFixture()});
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      final close = tester
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.logout_rounded),
          )
          .onPressed!;
      if (overlay == 'drawer') {
        await _navigate(tester, 'account');
        expect(find.text('private-profile@example.test'), findsOneWidget);
        await tester.tap(find.byTooltip('Buka navigasi'));
      } else if (overlay == 'dialog') {
        await tester.tap(find.byTooltip('Tentang status data'));
      } else {
        await _navigate(tester, 'history');
        final control = find.byKey(
          Key(overlay == 'date picker' ? 'history-from' : 'history-node'),
        );
        await tester.ensureVisible(control);
        await tester.tap(control);
      }
      await tester.pumpAndSettle();
      // Deliver an already-captured logout action while the overlay is active.
      close();
      await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump();
      expect(api.isAuthenticated, isFalse);
      expect(find.text('31.2', skipOffstage: false), findsNothing);
      expect(
        find.text('private-profile@example.test', skipOffstage: false),
        findsNothing,
      );
      expect(find.text('28.5 °C', skipOffstage: false), findsNothing);
      expect(find.byType(AlertDialog, skipOffstage: false), findsNothing);
      expect(find.byType(DatePickerDialog, skipOffstage: false), findsNothing);
      expect(find.byType(Drawer, skipOffstage: false), findsNothing);
      expect(
        find.byType(DropdownButtonFormField<String>, skipOffstage: false),
        findsNothing,
      );
      expect(find.byTooltip('Tentang status data'), findsNothing);
      expect(logouts, 1);
      logout.complete(http.Response('', 204));
      await tester.pumpAndSettle();
      expect(find.byType(DashboardPage, skipOffstage: false), findsNothing);
      expect(find.byKey(const Key('submit')), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }

  for (final overlay in ['drawer', 'date picker', 'dropdown']) {
    testWidgets('401 closes $overlay and discards private account/history', (
      tester,
    ) async {
      var reads = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) return loginResponse();
          if (request.url.path.endsWith('/me')) {
            return _json({
              'data': {...testUser, 'email': 'private-profile@example.test'},
            });
          }
          if (request.url.path.endsWith('/history')) {
            return _json(historyResponse());
          }
          return ++reads == 1
              ? _json({'data': snapshotFixture()})
              : _json({}, 401);
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      if (overlay == 'drawer') {
        await _navigate(tester, 'account');
        expect(find.text('private-profile@example.test'), findsOneWidget);
        await tester.tap(find.byTooltip('Buka navigasi'));
      } else {
        await _navigate(tester, 'history');
        expect(find.text('28.5 °C'), findsOneWidget);
        final control = find.byKey(
          Key(overlay == 'date picker' ? 'history-from' : 'history-node'),
        );
        await tester.ensureVisible(control);
        await tester.tap(control);
      }
      await tester.pumpAndSettle();
      if (overlay == 'date picker') {
        expect(find.byType(DatePickerDialog), findsOneWidget);
      }
      await tester.pump(const Duration(seconds: 16));
      await tester.pumpAndSettle();
      expect(api.isAuthenticated, isFalse);
      expect(find.byType(DashboardPage, skipOffstage: false), findsNothing);
      expect(find.byType(DatePickerDialog, skipOffstage: false), findsNothing);
      expect(find.byType(Drawer, skipOffstage: false), findsNothing);
      expect(
        find.text('private-profile@example.test', skipOffstage: false),
        findsNothing,
      );
      expect(find.text('28.5 °C', skipOffstage: false), findsNothing);
      expect(find.byKey(const Key('submit')), findsOneWidget);
      await tester.binding.handlePopRoute();
      await tester.pump();
      expect(find.byType(DashboardPage, skipOffstage: false), findsNothing);
      await tester.pump(const Duration(minutes: 2));
      expect(reads, 2);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }

  testWidgets(
    'failed revocation is reported on login but not carried into a new session',
    (tester) async {
      final logout = Completer<http.Response>();
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) return loginResponse();
          if (request.url.path.endsWith('/logout')) return logout.future;
          return _json({'data': snapshotFixture()});
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      await tester.tap(find.byTooltip('Keluar'));
      await tester.pump();
      expect(find.text('31.2', skipOffstage: false), findsNothing);
      logout.complete(_json({}, 500));
      await tester.pumpAndSettle();
      const message =
          'Sesi lokal ditutup. Pencabutan token server belum terkonfirmasi.';
      expect(find.text(message), findsOneWidget);
      expect(find.byKey(const Key('submit')), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('password')),
        'fixture-password',
      );
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(api.isAuthenticated, isTrue);
      expect(find.text('31.2'), findsOneWidget);
      expect(find.text(message), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'pending logout immediately removes private content and controls',
    (tester) async {
      final logout = Completer<http.Response>();
      var logouts = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) return loginResponse();
          if (request.url.path.endsWith('/logout')) {
            logouts++;
            expect(request.headers['Authorization'], 'Bearer test-only-token');
            return logout.future;
          }
          return _json({'data': snapshotFixture()});
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      expect(find.text('31.2'), findsOneWidget);
      await tester.tap(find.byTooltip('Keluar'));
      await tester.pump();

      expect(api.isAuthenticated, isFalse);
      expect(find.text('31.2', skipOffstage: false), findsNothing);
      expect(find.byTooltip('Tentang status data'), findsNothing);
      expect(find.byTooltip('Buka navigasi'), findsNothing);
      await tester.binding.handlePopRoute();
      await tester.pump();
      expect(logouts, 1);
      logout.complete(http.Response('', 204));
      await tester.pumpAndSettle();
      expect(find.byType(DashboardPage, skipOffstage: false), findsNothing);
      expect(find.byKey(const Key('submit')), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    '401 with an open dialog removes the private route and stops polling',
    (tester) async {
      var reads = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) {
            final data =
                jsonDecode(loginResponse().body) as Map<String, dynamic>;
            data['expires_at'] = '2099-01-01T00:00:00Z';
            return _json(data);
          }
          return ++reads == 1
              ? _json({'data': snapshotFixture()})
              : _json({}, 401);
        }),
      );
      addTearDown(api.dispose);
      await _enter(tester, api);
      expect(find.text('31.2'), findsOneWidget);
      await tester.tap(find.byTooltip('Tentang status data'));
      await tester.pumpAndSettle();
      expect(find.byType(AlertDialog), findsOneWidget);

      await tester.pump(const Duration(seconds: 16));
      await tester.pumpAndSettle();

      expect(api.isAuthenticated, isFalse);
      expect(find.byType(AlertDialog, skipOffstage: false), findsNothing);
      expect(find.byType(DashboardPage, skipOffstage: false), findsNothing);
      expect(find.text('31.2', skipOffstage: false), findsNothing);
      expect(find.byKey(const Key('submit')), findsOneWidget);
      expect(
        find.text('Sesi berakhir. Silakan masuk kembali.'),
        findsOneWidget,
      );
      await tester.pump(const Duration(minutes: 2));
      expect(reads, 2);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
}
