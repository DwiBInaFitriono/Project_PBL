import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rebung_pintar/api_client.dart';

import 'api_client_test.dart' show loginResponse;

Future<Map<String, dynamic>> signIn(ApiClient api) => api.login(
  email: 'viewer@example.test',
  password: 'test-only-password',
  deviceName: 'flutter-test',
);

void main() {
  for (final status in [204, 401, 500]) {
    test(
      'late logout $status cannot clear a newly authenticated session',
      () async {
        final pending = Completer<http.Response>();
        final started = Completer<void>();
        var loginCount = 0;
        final api = ApiClient(
          baseUrl: 'https://example.test/api/v1',
          client: MockClient((request) async {
            if (request.url.path.endsWith('/login')) {
              final payload =
                  jsonDecode(loginResponse().body) as Map<String, dynamic>;
              payload['token'] = 'test-token-${++loginCount}';
              return http.Response(jsonEncode(payload), 200);
            }
            if (request.url.path.endsWith('/logout')) {
              expect(request.headers['authorization'], 'Bearer test-token-1');
              started.complete();
              return pending.future;
            }
            expect(request.headers['authorization'], 'Bearer test-token-2');
            return http.Response(
              jsonEncode({'data': jsonDecode(loginResponse().body)['user']}),
              200,
            );
          }),
        );
        addTearDown(api.dispose);
        await signIn(api);
        final logout = api.logout();
        final checked = status == 204
            ? logout
            : expectLater(logout, throwsA(isA<ApiException>()));
        await started.future;
        await signIn(api);
        pending.complete(http.Response('', status));
        await checked;
        expect(api.isAuthenticated, isTrue);
        expect((await api.me())['id'], 1);
      },
    );
  }

  test('late 401 from an old identity does not clear a new login', () async {
    final pending = Completer<http.Response>();
    final started = Completer<void>();
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        if (request.url.path.endsWith('/login')) return loginResponse();
        started.complete();
        return pending.future;
      }),
    );
    addTearDown(api.dispose);
    await signIn(api);
    final me = api.me();
    final checked = expectLater(
      me,
      throwsA(
        isA<ApiException>().having(
          (e) => e.statusCode,
          'not the new session',
          isNull,
        ),
      ),
    );
    await started.future;
    await signIn(api);
    pending.complete(http.Response('secret', 401));
    await checked;
    expect(api.isAuthenticated, isTrue);
  });

  test(
    'expired in-memory session stops requests before sending its token',
    () async {
      var requests = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          requests++;
          final payload =
              jsonDecode(loginResponse().body) as Map<String, dynamic>;
          payload['expires_at'] = DateTime.now()
              .toUtc()
              .add(const Duration(milliseconds: 150))
              .toIso8601String();
          return http.Response(jsonEncode(payload), 200);
        }),
      );
      addTearDown(api.dispose);
      await signIn(api);
      await Future<void>.delayed(const Duration(milliseconds: 200));
      await expectLater(
        api.me(),
        throwsA(
          isA<ApiException>().having(
            (e) => e.statusCode,
            'expired session',
            401,
          ),
        ),
      );
      expect(api.isAuthenticated, isFalse);
      expect(api.user, isNull);
      expect(requests, 1);
    },
  );

  for (final host in ['192.168.1.10', '10.1.2.3', '172.16.0.1']) {
    test('HTTP opt-in does not authorize LAN credentials to $host', () {
      expect(
        () => ApiClient(
          baseUrl: 'http://$host:8000/api/v1',
          allowInsecureLocal: true,
        ),
        throwsArgumentError,
      );
    });
  }

  for (final expiry in [
    '2000-01-01T00:00:00Z',
    '2099-01-01',
    '2099-01-01T00:00:00',
    '2099-02-31T00:00:00Z',
  ]) {
    test('login rejects invalid or expired token lifetime $expiry', () async {
      final payload = jsonDecode(loginResponse().body) as Map<String, dynamic>;
      payload['expires_at'] = expiry;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient(
          (_) async => http.Response(jsonEncode(payload), 200),
        ),
      );
      addTearDown(api.dispose);
      await expectLater(signIn(api), throwsA(isA<ApiException>()));
      expect(api.isAuthenticated, isFalse);
    });
  }

  test(
    'logout clears credentials before waiting for server revocation',
    () async {
      final pending = Completer<http.Response>();
      final started = Completer<void>();
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) {
          if (request.url.path.endsWith('/login')) {
            return Future.value(loginResponse());
          }
          expect(request.headers['authorization'], 'Bearer test-only-token');
          started.complete();
          return pending.future;
        }),
      );
      addTearDown(api.dispose);
      await signIn(api);
      final logout = api.logout();
      await started.future;
      final retained = api.isAuthenticated;
      final retainedUser = api.user;
      pending.complete(http.Response('', 204));
      await logout;
      expect(retained, isFalse);
      expect(retainedUser, isNull);
    },
  );

  test('401 headers clear the session even when the body never ends', () async {
    var cancelled = false;
    final stream = StreamController<List<int>>(
      onCancel: () => cancelled = true,
    );
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      timeout: const Duration(milliseconds: 50),
      client: MockClient.streaming((request, body) async {
        if (request.url.path.endsWith('/login')) {
          return http.StreamedResponse(
            Stream.value(loginResponse().bodyBytes),
            200,
          );
        }
        return http.StreamedResponse(stream.stream, 401);
      }),
    );
    addTearDown(() {
      api.dispose();
      unawaited(stream.close());
    });
    await signIn(api);
    await expectLater(
      api.me(),
      throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 401)),
    );
    expect(api.isAuthenticated, isFalse);
    expect(api.user, isNull);
    expect(cancelled, isTrue);
  });

  test('body timeout cancels the stream subscription', () async {
    var cancelled = false;
    final stream = StreamController<List<int>>(
      onCancel: () => cancelled = true,
    );
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      timeout: const Duration(milliseconds: 30),
      client: MockClient.streaming(
        (request, body) async => http.StreamedResponse(stream.stream, 200),
      ),
    );
    addTearDown(() {
      api.dispose();
      unawaited(stream.close());
    });
    await expectLater(signIn(api), throwsA(isA<ApiException>()));
    expect(cancelled, isTrue);
  });

  test(
    'oversized streamed response is cancelled before buffering its tail',
    () async {
      var cancelled = false;
      final stream = StreamController<List<int>>(
        onCancel: () => cancelled = true,
      );
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        timeout: const Duration(milliseconds: 200),
        client: MockClient.streaming((request, body) async {
          stream.add(List.filled(2 * 1024 * 1024 + 1, 32));
          return http.StreamedResponse(stream.stream, 200);
        }),
      );
      addTearDown(() {
        api.dispose();
        unawaited(stream.close());
      });
      await expectLater(
        signIn(api),
        throwsA(
          isA<ApiException>().having(
            (e) => e.message,
            'bounded response',
            'Respons server terlalu besar.',
          ),
        ),
      );
      expect(cancelled, isTrue);
      expect(api.isAuthenticated, isFalse);
    },
  );
}
