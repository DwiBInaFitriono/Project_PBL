import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rebung_pintar/api_client.dart';

const testUser = <String, dynamic>{
  'id': 1,
  'name': 'Pemantau Uji',
  'email': 'viewer@example.test',
  'role': 'viewer',
};

http.Response loginResponse() => http.Response(
  jsonEncode({
    'token': 'test-only-token',
    'token_type': 'Bearer',
    'expires_at': DateTime.now()
        .toUtc()
        .add(const Duration(hours: 24))
        .toIso8601String(),
    'user': testUser,
  }),
  200,
);

class ClosingMockClient extends MockClient {
  ClosingMockClient() : super((request) async => loginResponse());
  int closeCount = 0;
  int requests = 0;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) {
    requests++;
    return super.send(request);
  }

  @override
  void close() {
    closeCount++;
    super.close();
  }
}

void main() {
  for (final action in ['logout', 'dispose']) {
    test('late login cannot restore credentials after $action', () async {
      final pending = Completer<http.Response>();
      final started = Completer<void>();
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) {
          started.complete();
          return pending.future;
        }),
      );
      final login = api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      await started.future;
      if (action == 'dispose') {
        api.dispose();
      } else {
        await api.logout();
      }
      final rejected = expectLater(login, throwsA(isA<ApiException>()));
      pending.complete(loginResponse());
      await rejected;
      expect(api.isAuthenticated, isFalse);
      expect(api.user, isNull);
    });
  }

  test('failed re-login cannot keep the previous user identity', () async {
    var attempt = 0;
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient(
        (request) async =>
            ++attempt == 1 ? loginResponse() : http.Response('{}', 422),
      ),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    await expectLater(
      api.login(
        email: 'other@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      ),
      throwsA(isA<ApiException>()),
    );
    expect(api.isAuthenticated, isFalse);
    expect(api.user, isNull);
  });

  test(
    'dispose closes its HTTP client once and prevents further requests',
    () async {
      final transport = ClosingMockClient();
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: transport,
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      api.dispose();
      api.dispose();
      expect(transport.closeCount, 1);
      expect(api.isAuthenticated, isFalse);
      expect(api.user, isNull);
      await expectLater(
        api.login(
          email: 'viewer@example.test',
          password: 'test-only-password',
          deviceName: 'flutter-test',
        ),
        throwsA(isA<ApiException>()),
      );
      expect(transport.requests, 1);
    },
  );

  test(
    'environment factory uses compile-time URL with secure defaults',
    () async {
      const url = String.fromEnvironment('API_BASE_URL');
      const insecure = bool.fromEnvironment('ALLOW_INSECURE_LOCAL_API');
      if (url.isEmpty || (url.startsWith('http:') && !insecure)) {
        expect(() => ApiClient.fromEnvironment(), throwsArgumentError);
        return;
      }
      final api = ApiClient.fromEnvironment(
        client: MockClient((request) async {
          expect(
            request.url.toString(),
            '${url.replaceFirst(RegExp(r"/+$"), "")}/auth/login',
          );
          return loginResponse();
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      expect(api.isAuthenticated, isTrue);
    },
  );

  for (final outcome in ['success', '401', '500', 'network', 'timeout']) {
    test('logout clears the session for $outcome', () async {
      var requests = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        timeout: const Duration(milliseconds: 20),
        client: MockClient((request) async {
          requests++;
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          expect(request.method, 'POST');
          expect(request.url.path, '/api/v1/auth/logout');
          expect(request.followRedirects, isFalse);
          expect(request.headers['authorization'], 'Bearer test-only-token');
          if (outcome == 'network') {
            throw http.ClientException('test-only-token');
          }
          if (outcome == 'timeout') return Completer<http.Response>().future;
          return http.Response(
            '',
            outcome == 'success' ? 204 : int.parse(outcome),
          );
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      if (outcome == 'success') {
        await api.logout();
      } else {
        await expectLater(api.logout(), throwsA(isA<ApiException>()));
      }
      expect(api.isAuthenticated, isFalse);
      expect(api.user, isNull);
      await api.logout();
      expect(requests, 2, reason: 'Logout without a token is local only');
      await expectLater(
        api.me(),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 401)),
      );
    });
  }

  test(
    'ESP settings returns server integration flags without inventing hardware',
    () async {
      final settings = {
        'nodes': [
          {'id': '1', 'name': 'Node 1'},
        ],
        'sensors': [
          {'id': 'temperature', 'name': 'Suhu', 'unit': '°C', 'decimals': 1},
        ],
        'integration': {'mqtt_enabled': false, 'hardware_connected': false},
      };
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          expect(request.method, 'GET');
          expect(request.url.path, '/api/v1/settings/esp');
          return http.Response(
            jsonEncode({'data': settings}),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      expect(await api.espSettings(), settings);
    },
  );

  test('ESP permission errors preserve the session', () async {
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient(
        (request) async => request.url.path.endsWith('/auth/login')
            ? loginResponse()
            : http.Response('{}', 403),
      ),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    await expectLater(
      api.espSettings(),
      throwsA(
        isA<ApiException>().having((e) => e.statusCode, 'forbidden', 403),
      ),
    );
    expect(api.isAuthenticated, isTrue);
  });

  test('ESP settings rejects malformed integration metadata', () async {
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient(
        (request) async => request.url.path.endsWith('/auth/login')
            ? loginResponse()
            : http.Response(
                '{"data":{"nodes":[],"sensors":[],"integration":{"mqtt_enabled":"false"}}}',
                200,
              ),
      ),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    await expectLater(api.espSettings(), throwsA(isA<ApiException>()));
  });

  test(
    'history encodes exact filters and returns rows plus pagination',
    () async {
      final result = <String, dynamic>{
        'data': [
          {
            'id': 12,
            'node_id': '1',
            'sensor_id': 'temperature',
            'value': 0,
            'recorded_at': '2026-09-16T00:00:00Z',
            'node_name': 'Node 1',
            'sensor_name': 'Suhu',
            'unit': '°C',
          },
        ],
        'meta': {
          'current_page': 2,
          'last_page': 3,
          'per_page': 25,
          'total': 51,
        },
        'filters': {
          'node': '1',
          'sensor': 'temperature',
          'from': '2026-09-01',
          'to': '2026-09-16',
          'timezone': 'Asia/Jakarta',
        },
      };
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          expect(request.method, 'GET');
          expect(request.url.path, '/api/v1/history');
          expect(request.url.queryParameters, {
            'node': '1',
            'sensor': 'temperature',
            'from': '2026-09-01',
            'to': '2026-09-16',
            'timezone': 'Asia/Jakarta',
            'page': '2',
          });
          return http.Response(
            jsonEncode(result),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      expect(
        await api.history(
          node: '1',
          sensor: 'temperature',
          from: '2026-09-01',
          to: '2026-09-16',
          page: 2,
        ),
        result,
      );
    },
  );

  test(
    'history defaults to WIB page one and preserves empty results',
    () async {
      final result = {
        'data': [],
        'meta': {'current_page': 1, 'last_page': 1, 'per_page': 25, 'total': 0},
        'filters': {
          'node': null,
          'sensor': null,
          'from': '2026-09-10',
          'to': '2026-09-16',
          'timezone': 'Asia/Jakarta',
        },
      };
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          expect(request.url.queryParameters, {
            'timezone': 'Asia/Jakarta',
            'page': '1',
          });
          return http.Response(jsonEncode(result), 200);
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      expect(await api.history(), result);
    },
  );

  test('history rejects corrupt pagination safely', () async {
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient(
        (request) async => request.url.path.endsWith('/auth/login')
            ? loginResponse()
            : http.Response(
                '{"data":[],"meta":{"total":"broken"},"filters":{}}',
                200,
              ),
      ),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    await expectLater(api.history(), throwsA(isA<ApiException>()));
  });

  test(
    'monitoring preserves null sensors and real zero with node filter',
    () async {
      final snapshot = <String, dynamic>{
        'nodes': [
          {
            'id': '1',
            'name': 'Node 1',
            'status': 'waiting',
            'freshness': 'never',
            'age_seconds': null,
            'last_reading': null,
            'sensors': [
              {
                'id': 'temperature',
                'name': 'Suhu',
                'unit': '°C',
                'decimals': 1,
                'value': null,
                'last_reading': null,
                'readings': [],
              },
              {
                'id': 'humidity',
                'name': 'Kelembapan',
                'unit': '%',
                'decimals': 1,
                'value': 0,
                'last_reading': '2026-09-16T00:00:00Z',
                'readings': [
                  {'recorded_at': '2026-09-16T00:00:00Z', 'value': 0},
                ],
              },
            ],
          },
        ],
        'sensors': [
          {'id': 'temperature', 'name': 'Suhu', 'unit': '°C', 'decimals': 1},
        ],
        'generatedAt': '2026-09-16T00:00:00Z',
        'staleAfterSeconds': 300,
        'pollIntervalSeconds': 15,
      };
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          expect(request.method, 'GET');
          expect(request.url.path, '/api/v1/monitoring');
          expect(request.headers['authorization'], 'Bearer test-only-token');
          expect(request.url.queryParameters, {'node': '1'});
          return http.Response(
            jsonEncode({'data': snapshot}),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      expect(await api.monitoring(node: '1'), snapshot);
    },
  );

  test(
    'monitoring omits an absent node and rejects invalid nested data',
    () async {
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          expect(request.url.hasQuery, isFalse);
          return http.Response(
            jsonEncode({
              'data': {
                'nodes': [null],
                'sensors': [],
                'generatedAt': '2026-09-16T00:00:00Z',
                'staleAfterSeconds': 300,
                'pollIntervalSeconds': 15,
              },
            }),
            200,
          );
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      await expectLater(api.monitoring(), throwsA(isA<ApiException>()));
    },
  );

  test('timeout covers response body consumption with a safe error', () async {
    final stream = StreamController<List<int>>();
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      timeout: const Duration(milliseconds: 20),
      client: MockClient.streaming(
        (request, body) async => http.StreamedResponse(stream.stream, 200),
      ),
    );
    await expectLater(
      api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      ),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'timeout',
          'Permintaan kehabisan waktu. Coba lagi.',
        ),
      ),
    );
    await stream.close();
    expect(api.isAuthenticated, isFalse);
  });

  test('network exceptions do not expose transport secrets', () async {
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        throw http.ClientException(
          'test-only-password test-only-token',
          request.url,
        );
      }),
    );
    await expectLater(
      api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      ),
      throwsA(
        isA<ApiException>()
            .having((e) => e.statusCode, 'no HTTP status', isNull)
            .having(
              (e) => e.message,
              'safe message',
              'Tidak dapat terhubung ke server.',
            ),
      ),
    );
  });

  for (final body in [
    'not-json test-only-token',
    '[]',
    'null',
    '{}',
    jsonEncode({
      'token': '',
      'token_type': 'Bearer',
      'expires_at': '2026-09-17T00:00:00Z',
      'user': testUser,
    }),
    jsonEncode({
      'token': 'test-only-token',
      'token_type': 'Basic',
      'expires_at': '2026-09-17T00:00:00Z',
      'user': testUser,
    }),
    jsonEncode({
      'token': 'test-only-token',
      'token_type': 'Bearer',
      'expires_at': 'wrong',
      'user': testUser,
    }),
    jsonEncode({
      'token': 'test-only-token\r\nX-Secret: value',
      'token_type': 'Bearer',
      'expires_at': '2026-09-17T00:00:00Z',
      'user': testUser,
    }),
    jsonEncode({
      'token': 'test-only-token',
      'token_type': 'Bearer',
      'expires_at': '2026-09-17T00:00:00Z',
      'user': {'id': 'wrong'},
    }),
  ].indexed) {
    test(
      'corrupt login payload ${body.$1} cannot establish a session',
      () async {
        final api = ApiClient(
          baseUrl: 'https://example.test/api/v1',
          client: MockClient((request) async => http.Response(body.$2, 200)),
        );
        await expectLater(
          api.login(
            email: 'viewer@example.test',
            password: 'test-only-password',
            deviceName: 'flutter-test',
          ),
          throwsA(
            isA<ApiException>()
                .having(
                  (e) => e.message,
                  'message',
                  'Respons server tidak valid.',
                )
                .having(
                  (e) => e.toString(),
                  'no secret',
                  isNot(contains('test-only-token')),
                ),
          ),
        );
        expect(api.isAuthenticated, isFalse);
        expect(api.user, isNull);
      },
    );
  }

  test('malformed me data does not replace the last valid user', () async {
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient(
        (request) async => request.url.path.endsWith('/auth/login')
            ? loginResponse()
            : http.Response('{"data":{"id":false}}', 200),
      ),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    await expectLater(api.me(), throwsA(isA<ApiException>()));
    expect(api.user, testUser);
  });

  for (final status in [401, 403, 422, 429, 500, 302, 307]) {
    test('HTTP $status has a safe error and 401 clears the session', () async {
      var requests = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          requests++;
          if (request.url.path.endsWith('/auth/login')) return loginResponse();
          return http.Response(
            'secret-password test-only-token <html>',
            status,
            headers: {
              'location': 'https://other.test/steal',
              'retry-after': '60',
            },
          );
        }),
      );
      await api.login(
        email: 'viewer@example.test',
        password: 'test-only-password',
        deviceName: 'flutter-test',
      );
      await expectLater(
        api.me(),
        throwsA(
          isA<ApiException>()
              .having((e) => e.statusCode, 'statusCode', status)
              .having(
                (e) => e.toString(),
                'safe text',
                isNot(contains('secret-password')),
              )
              .having(
                (e) => e.toString(),
                'no token',
                isNot(contains('test-only-token')),
              ),
        ),
      );
      expect(api.isAuthenticated, status != 401);
      expect(api.user, status == 401 ? isNull : testUser);
      expect(requests, 2, reason: 'No redirect or automatic retry');
    });
  }

  test(
    'private endpoints reject a missing in-memory session locally',
    () async {
      var requests = 0;
      final api = ApiClient(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((request) async {
          requests++;
          return http.Response('{}', 200);
        }),
      );
      await expectLater(
        api.me(),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 401)),
      );
      expect(requests, 0);
    },
  );

  test('me uses bearer headers without redirects or URL secrets', () async {
    final requests = <http.Request>[];
    final refreshedUser = {...testUser, 'name': 'Nama Baru'};
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1/',
      client: MockClient((request) async {
        requests.add(request);
        if (request.url.path.endsWith('/auth/login')) return loginResponse();
        expect(request.method, 'GET');
        expect(request.url.toString(), 'https://example.test/api/v1/me');
        expect(request.headers['authorization'], 'Bearer test-only-token');
        expect(request.headers['accept'], 'application/json');
        return http.Response(jsonEncode({'data': refreshedUser}), 200);
      }),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    expect(await api.me(), refreshedUser);
    expect(api.user, refreshedUser);
    for (final request in requests) {
      expect(request.followRedirects, isFalse);
      expect(request.url.hasQuery, isFalse);
    }
  });

  test('base URL validates the API prefix and explicit HTTP opt-in', () {
    for (final url in [
      '',
      ' ',
      'example.test/api/v1',
      'ftp://example.test/api/v1',
      'https://example.test',
      'https://example.test/api/v2',
      'https://user:password@example.test/api/v1',
      'https://example.test/api/v1?token=secret',
      'https://example.test/api/v1#fragment',
      'https://example.test/api/v1?',
      'https://example.test/api/v1#',
      'http://localhost:8000/api/v1',
      'http://127.0.0.1:8000/api/v1',
      'http://10.0.2.2:8000/api/v1',
    ]) {
      expect(() => ApiClient(baseUrl: url), throwsArgumentError, reason: url);
    }
    for (final url in [
      'http://localhost:8000/api/v1',
      'http://127.0.0.1:8000/api/v1',
      'http://10.0.2.2:8000/api/v1',
    ]) {
      expect(
        () => ApiClient(baseUrl: url, allowInsecureLocal: true),
        returnsNormally,
      );
    }
    expect(
      () => ApiClient(
        baseUrl: 'http://example.test/api/v1',
        allowInsecureLocal: true,
      ),
      throwsArgumentError,
    );
    expect(
      () => ApiClient(
        baseUrl: 'https://example.test/api/v1',
        timeout: Duration.zero,
      ),
      throwsArgumentError,
    );
  });

  test('trailing slash normalization preserves a deployment prefix', () async {
    final api = ApiClient(
      baseUrl: ' https://example.test/rebung/api/v1/// ',
      client: MockClient((request) async {
        expect(
          request.url.toString(),
          'https://example.test/rebung/api/v1/auth/login',
        );
        return loginResponse();
      }),
    );
    await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
  });

  test('login sends JSON and exposes only the authenticated user', () async {
    final api = ApiClient(
      baseUrl: 'https://example.test/api/v1',
      client: MockClient((request) async {
        expect(request.method, 'POST');
        expect(
          request.url.toString(),
          'https://example.test/api/v1/auth/login',
        );
        expect(request.headers['accept'], 'application/json');
        expect(request.headers['content-type'], contains('application/json'));
        expect(request.headers.containsKey('authorization'), isFalse);
        expect(jsonDecode(request.body), {
          'email': 'viewer@example.test',
          'password': 'test-only-password',
          'device_name': 'flutter-test',
        });
        return loginResponse();
      }),
    );
    expect(api.isAuthenticated, isFalse);
    expect(api.user, isNull);
    final user = await api.login(
      email: 'viewer@example.test',
      password: 'test-only-password',
      deviceName: 'flutter-test',
    );
    expect(user, testUser);
    expect(user.containsKey('token'), isFalse);
    expect(api.user, testUser);
    expect(api.isAuthenticated, isTrue);
  });
}
