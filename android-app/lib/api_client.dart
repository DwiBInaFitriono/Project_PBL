import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

/// Safe to display: never includes a server body, credentials, or transport URL.
/// HTTP failures retain their status; network/timeout/payload failures use null.
class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => 'ApiException: $message';
}

/// Read-only Laravel REST client, except for login and token revocation.
///
/// Tokens stay private and in memory. No storage, MQTT, polling, automatic
/// retries, or redirects are performed. HTTPS is required by default; local
/// HTTP requires explicit opt-in and a loopback/emulator host.
class ApiClient {
  ApiClient({
    required String baseUrl,
    http.Client? client,
    Duration timeout = const Duration(seconds: 15),
    bool allowInsecureLocal = false,
  }) : _baseUrl = _validateBaseUrl(baseUrl, allowInsecureLocal),
       _timeout = timeout,
       _client = client ?? http.Client() {
    if (timeout <= Duration.zero) {
      throw ArgumentError('Batas waktu harus lebih dari nol.');
    }
  }

  /// Fails closed when API_BASE_URL is absent or invalid.
  factory ApiClient.fromEnvironment({
    http.Client? client,
    Duration timeout = const Duration(seconds: 15),
  }) => ApiClient(
    baseUrl: const String.fromEnvironment('API_BASE_URL'),
    allowInsecureLocal: const bool.fromEnvironment('ALLOW_INSECURE_LOCAL_API'),
    client: client,
    timeout: timeout,
  );

  static Uri _validateBaseUrl(String value, bool allowInsecureLocal) {
    final uri = Uri.tryParse(value.trim());
    if (uri == null ||
        !uri.hasAuthority ||
        uri.host.isEmpty ||
        uri.userInfo.isNotEmpty ||
        uri.hasQuery ||
        uri.hasFragment ||
        (uri.scheme != 'https' && uri.scheme != 'http')) {
      throw ArgumentError('Alamat API tidak valid.');
    }
    final path = uri.path.replaceFirst(RegExp(r'/+$'), '');
    if (!path.endsWith('/api/v1')) {
      throw ArgumentError('Alamat API harus berakhir dengan /api/v1.');
    }
    final local =
        uri.host == 'localhost' ||
        uri.host == '127.0.0.1' ||
        uri.host == '10.0.2.2';
    if (uri.scheme == 'http' && (!allowInsecureLocal || !local)) {
      throw ArgumentError(
        'Gunakan HTTPS atau izinkan HTTP lokal secara eksplisit.',
      );
    }
    return uri.replace(path: path);
  }

  final Uri _baseUrl;
  final Duration _timeout;
  final http.Client _client;
  String? _token;
  DateTime? _expiresAt;
  Map<String, dynamic>? _user;
  bool _disposed = false;
  int _sessionVersion = 0;

  bool get isAuthenticated {
    if (_expiresAt != null && !_expiresAt!.isAfter(DateTime.now())) {
      _clearSession();
    }
    return _token != null;
  }

  Map<String, dynamic>? get user => isAuthenticated ? _user : null;

  void _clearSession() {
    _sessionVersion++;
    _token = null;
    _expiresAt = null;
    _user = null;
  }

  static const _invalidResponse = ApiException('Respons server tidak valid.');

  static Map<String, dynamic> _object(Object? value) {
    if (value is! Map<String, dynamic>) throw _invalidResponse;
    return value;
  }

  static Map<String, dynamic> _parseUser(Object? value) {
    final data = _object(value);
    if (data['id'] is! int ||
        data['name'] is! String ||
        data['email'] is! String ||
        data['role'] is! String) {
      throw _invalidResponse;
    }
    return Map<String, dynamic>.unmodifiable({
      for (final key in ['id', 'name', 'email', 'role']) key: data[key],
    });
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    _clearSession();
    final data = await _request(
      'POST',
      '/auth/login',
      authenticated: false,
      body: {'email': email, 'password': password, 'device_name': deviceName},
    );
    final token = data['token'];
    final expiresAt = _parseExpiry(data['expires_at']);
    if (token is! String ||
        token.isEmpty ||
        RegExp(r'[\s\x00-\x1f\x7f]').hasMatch(token) ||
        data['token_type'] != 'Bearer' ||
        expiresAt == null ||
        !expiresAt.isAfter(DateTime.now())) {
      throw _invalidResponse;
    }
    final parsedUser = _parseUser(data['user']);
    _token = token;
    _expiresAt = expiresAt;
    _user = parsedUser;
    return _user!;
  }

  Future<Map<String, dynamic>> me() async {
    final data = await _request('GET', '/me');
    _user = _parseUser(data['data']);
    return _user!;
  }

  /// Returns the unwrapped snapshot, keeping null distinct from numeric zero.
  Future<Map<String, dynamic>> monitoring({String? node}) async {
    final response = await _request(
      'GET',
      '/monitoring',
      query: {'node': ?node},
    );
    final data = _object(response['data']);
    if (!_date(data['generatedAt']) ||
        data['staleAfterSeconds'] is! int ||
        data['pollIntervalSeconds'] is! int) {
      throw _invalidResponse;
    }
    for (final value in _list(data['sensors'])) {
      _sensorDefinition(value);
    }
    final nodeIds = <String>{};
    for (final value in _list(data['nodes'])) {
      final node = _object(value);
      if ((node['id'] != '1' && node['id'] != '2') ||
          !nodeIds.add(node['id'] as String) ||
          node['name'] is! String ||
          node['status'] is! String ||
          node['freshness'] is! String ||
          (node['age_seconds'] != null && node['age_seconds'] is! num) ||
          !_nullableDate(node['last_reading'])) {
        throw _invalidResponse;
      }
      for (final value in _list(node['sensors'])) {
        final sensor = _sensorDefinition(value);
        if (!_nullableNumber(sensor['value']) ||
            !_nullableDate(sensor['last_reading'])) {
          throw _invalidResponse;
        }
        for (final value in _list(sensor['readings'])) {
          final reading = _object(value);
          if (!_number(reading['value']) || !_date(reading['recorded_at'])) {
            throw _invalidResponse;
          }
        }
      }
    }
    return data;
  }

  /// Returns the complete data/meta/filters envelope. Dates are YYYY-MM-DD.
  Future<Map<String, dynamic>> history({
    String? node,
    String? sensor,
    String? from,
    String? to,
    String timezone = 'Asia/Jakarta',
    int page = 1,
  }) async {
    final response = await _request(
      'GET',
      '/history',
      query: {
        'node': ?node,
        'sensor': ?sensor,
        'from': ?from,
        'to': ?to,
        'timezone': timezone,
        'page': '$page',
      },
    );
    final meta = _object(response['meta']);
    for (final key in ['current_page', 'last_page', 'per_page', 'total']) {
      if (meta[key] is! int) throw _invalidResponse;
    }
    final filters = _object(response['filters']);
    if (filters['timezone'] is! String ||
        filters['from'] is! String ||
        filters['to'] is! String ||
        (filters['node'] != null && filters['node'] is! String) ||
        (filters['sensor'] != null && filters['sensor'] is! String)) {
      throw _invalidResponse;
    }
    for (final value in _list(response['data'])) {
      final row = _object(value);
      if (row['id'] is! int ||
          !_number(row['value']) ||
          !_date(row['recorded_at'])) {
        throw _invalidResponse;
      }
      for (final key in [
        'node_id',
        'sensor_id',
        'node_name',
        'sensor_name',
        'unit',
      ]) {
        if (row[key] is! String) throw _invalidResponse;
      }
    }
    return response;
  }

  /// Returns unwrapped read-only settings; authorization remains server-side.
  Future<Map<String, dynamic>> espSettings() async {
    final response = await _request('GET', '/settings/esp');
    final data = _object(response['data']);
    for (final value in _list(data['nodes'])) {
      final node = _object(value);
      if (node['id'] is! String || node['name'] is! String) {
        throw _invalidResponse;
      }
    }
    for (final value in _list(data['sensors'])) {
      _sensorDefinition(value);
    }
    final integration = _object(data['integration']);
    if (integration['mqtt_enabled'] is! bool ||
        integration['hardware_connected'] is! bool) {
      throw _invalidResponse;
    }
    return data;
  }

  /// Always drops local credentials, even if server revocation fails.
  Future<void> logout() async {
    final token = _token;
    _clearSession();
    if (token != null) {
      await _request(
        'POST',
        '/auth/logout',
        authenticated: false,
        revokingToken: token,
        expectedStatus: 204,
      );
    }
  }

  /// Closes even an injected client; do not share it with another owner.
  void dispose() {
    if (_disposed) return;
    _disposed = true;
    _clearSession();
    _client.close();
  }

  static List<dynamic> _list(Object? value) {
    if (value is! List) throw _invalidResponse;
    return value;
  }

  static bool _date(Object? value) =>
      value is String && DateTime.tryParse(value) != null;
  static DateTime? _parseExpiry(Object? value) {
    if (value is! String) return null;
    final match = RegExp(
      r'^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,6})?(Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$',
    ).firstMatch(value);
    if (match == null) return null;
    final year = int.parse(match[1]!);
    final month = int.parse(match[2]!);
    final day = int.parse(match[3]!);
    final calendar = DateTime.utc(year, month, day);
    if (calendar.year != year ||
        calendar.month != month ||
        calendar.day != day) {
      return null;
    }
    return DateTime.tryParse(value)?.toUtc();
  }

  static bool _nullableDate(Object? value) => value == null || _date(value);
  static bool _number(Object? value) => value is num && value.isFinite;
  static bool _nullableNumber(Object? value) => value == null || _number(value);

  static Map<String, dynamic> _sensorDefinition(Object? value) {
    final sensor = _object(value);
    if (sensor['id'] is! String ||
        sensor['name'] is! String ||
        sensor['unit'] is! String ||
        sensor['decimals'] is! int) {
      throw _invalidResponse;
    }
    return sensor;
  }

  static const _maxResponseBytes = 2 * 1024 * 1024;

  static Future<http.Response> _readResponse(
    http.StreamedResponse response,
    StreamIterator<List<int>> chunks,
  ) async {
    final bytes = <int>[];
    try {
      while (await chunks.moveNext()) {
        final chunk = chunks.current;
        if (chunk.length > _maxResponseBytes - bytes.length) {
          throw const ApiException('Respons server terlalu besar.');
        }
        bytes.addAll(chunk);
      }
      return http.Response.bytes(bytes, response.statusCode);
    } finally {
      unawaited(chunks.cancel().catchError((Object _) {}));
    }
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    bool authenticated = true,
    String? revokingToken,
    Map<String, dynamic>? body,
    Map<String, String>? query,
    int expectedStatus = 200,
  }) async {
    if (_disposed) throw const ApiException('Klien API telah ditutup.');
    if (authenticated && !isAuthenticated) {
      throw const ApiException('Silakan masuk kembali.', statusCode: 401);
    }
    final sessionVersion = _sessionVersion;
    final abort = Completer<void>();
    final request =
        http.AbortableRequest(
            method,
            _baseUrl.replace(
              path: '${_baseUrl.path}$path',
              queryParameters: query == null || query.isEmpty ? null : query,
            ),
            abortTrigger: abort.future,
          )
          ..followRedirects = false
          ..headers['Accept'] = 'application/json';
    final token = revokingToken ?? (authenticated ? _token : null);
    if (token != null) {
      request.headers['Authorization'] = 'Bearer $token';
    }
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }
    final http.Response response;
    StreamIterator<List<int>>? chunks;
    try {
      response = await _client
          .send(request)
          .then<http.Response>((response) {
            if (abort.isCompleted ||
                response.statusCode != expectedStatus ||
                expectedStatus == 204) {
              unawaited(
                response.stream.listen(null).cancel().catchError((Object _) {}),
              );
              return http.Response.bytes(const [], response.statusCode);
            }
            chunks = StreamIterator(response.stream);
            return _readResponse(response, chunks!);
          })
          .timeout(_timeout);
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw const ApiException('Permintaan kehabisan waktu. Coba lagi.');
    } on Exception {
      throw const ApiException('Tidak dapat terhubung ke server.');
    } finally {
      abort.complete();
      if (chunks != null) {
        unawaited(chunks!.cancel().catchError((Object _) {}));
      }
    }
    if (_disposed ||
        (revokingToken == null && sessionVersion != _sessionVersion)) {
      throw const ApiException('Sesi telah berubah. Silakan masuk kembali.');
    }
    if (response.statusCode != expectedStatus) {
      if (response.statusCode == 401 && revokingToken == null) _clearSession();
      final message = switch (response.statusCode) {
        401 => 'Sesi berakhir. Silakan masuk kembali.',
        403 => 'Anda tidak memiliki akses.',
        422 => 'Periksa kembali data yang dikirim.',
        429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
        _ => 'Permintaan API gagal. Coba lagi nanti.',
      };
      throw ApiException(message, statusCode: response.statusCode);
    }
    if (expectedStatus == 204) return {};
    try {
      return _object(jsonDecode(utf8.decode(response.bodyBytes)));
    } on FormatException {
      throw _invalidResponse;
    }
  }
}
