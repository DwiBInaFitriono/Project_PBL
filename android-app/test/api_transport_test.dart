import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/api_client.dart';

import 'api_client_test.dart' show loginResponse;
import 'api_security_test.dart' show signIn;

void main() {
  for (final redirectLogin in [true, false]) {
    test(
      'native transport refuses redirects for login=$redirectLogin',
      () async {
        final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
        var redirectedRequests = 0;
        var originalRequests = 0;
        server.listen((request) async {
          await utf8.decoder.bind(request).join();
          if (request.uri.path == '/redirect-target') {
            redirectedRequests++;
            request.response.write('{}');
          } else {
            originalRequests++;
            if (!redirectLogin && request.uri.path.endsWith('/login')) {
              request.response.write(loginResponse().body);
            } else {
              if (!redirectLogin) {
                expect(
                  request.headers.value('authorization'),
                  'Bearer test-only-token',
                );
              }
              request.response.statusCode = redirectLogin ? 307 : 302;
              request.response.headers.set('location', '/redirect-target');
            }
          }
          await request.response.close();
        });
        final api = ApiClient(
          baseUrl: 'http://127.0.0.1:${server.port}/api/v1',
          allowInsecureLocal: true,
        );
        try {
          if (!redirectLogin) await signIn(api);
          await expectLater(
            redirectLogin ? signIn(api) : api.me(),
            throwsA(
              isA<ApiException>().having(
                (e) => e.statusCode,
                'redirect status',
                redirectLogin ? 307 : 302,
              ),
            ),
          );
          expect(originalRequests, redirectLogin ? 1 : 2);
          expect(redirectedRequests, 0);
        } finally {
          api.dispose();
          await server.close(force: true);
        }
      },
    );
  }
}
