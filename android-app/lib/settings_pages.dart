import 'package:flutter/material.dart';

import 'api_client.dart';
import 'dashboard_widgets.dart';

class EspSettingsPage extends StatelessWidget {
  const EspSettingsPage({super.key, this.api, this.onSessionExpired});
  final ApiClient? api;
  final VoidCallback? onSessionExpired;

  @override
  Widget build(BuildContext context) => api == null
      ? _preview(context)
      : _LiveSettings(api: api!, onSessionExpired: onSessionExpired, esp: true);

  Widget _preview(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SectionTitle('Konfigurasi perangkat', ''),
      const InfoNotice(
        'PRATINJAU · Ingest perangkat melalui MQTT di server belum aktif.',
      ),
      const SizedBox(height: 24),
      TwoColumns(
        children: [
          for (final node in [1, 2])
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(Icons.memory_rounded, size: 30),
                  const SizedBox(height: 12),
                  Text(
                    'Node $node',
                    style: const TextStyle(
                      fontSize: 21,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text('Belum terhubung', style: TextStyle(fontSize: 13)),
                  const Divider(height: 28),
                  for (final sensor in sensorsForNode(node))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        children: [
                          Icon(sensor.icon, size: 20),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text('${sensor.name} (${sensor.unit})'),
                          ),
                        ],
                      ),
                    ),
                  const Text(
                    '3 sensor usulan',
                    style: TextStyle(fontSize: 12, height: 1.6),
                  ),
                ],
              ),
            ),
        ],
      ),
      const SizedBox(height: 24),
      const SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Koneksi',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            SizedBox(height: 12),
            Text(
              'Perangkat → MQTT → Laravel\nFlutter → REST API\nBroker dan protokol belum diaktifkan.',
              style: TextStyle(fontSize: 14, height: 1.8),
            ),
          ],
        ),
      ),
    ],
  );
}

class AccountSettingsPage extends StatelessWidget {
  const AccountSettingsPage({super.key, this.api, this.onSessionExpired});
  final ApiClient? api;
  final VoidCallback? onSessionExpired;

  @override
  Widget build(BuildContext context) => api == null
      ? _preview(context)
      : _LiveSettings(api: api!, onSessionExpired: onSessionExpired);

  Widget _preview(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SectionTitle('Setting Akun', ''),
      const InfoNotice('PRATINJAU · Belum masuk. Form nonaktif.'),
      const SizedBox(height: 24),
      SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Informasi akun',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            const Text('Peran: —', style: TextStyle(fontSize: 13)),
            const SizedBox(height: 20),
            _field('Nama lengkap'),
            _field('Alamat email'),
            _field('Kata sandi saat ini', password: true),
            const Text(
              'Konfirmasi dengan sandi saat ini.',
              style: TextStyle(fontSize: 12, height: 1.6),
            ),
            const SizedBox(height: 16),
            const Align(
              alignment: Alignment.centerLeft,
              child: FilledButton(
                onPressed: null,
                child: Text('Simpan perubahan'),
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 24),
      SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Ganti kata sandi',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 16),
            _field('Kata sandi saat ini', password: true),
            _field('Kata sandi baru', password: true),
            _field('Konfirmasi kata sandi baru', password: true),
            const Text(
              'Minimal 8 karakter.',
              style: TextStyle(fontSize: 12, height: 1.6),
            ),
            const SizedBox(height: 16),
            const Align(
              alignment: Alignment.centerLeft,
              child: FilledButton(
                onPressed: null,
                child: Text('Perbarui kata sandi'),
              ),
            ),
          ],
        ),
      ),
    ],
  );

  Widget _field(String label, {bool password = false}) => Padding(
    padding: const EdgeInsets.only(bottom: 18),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          label,
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        TextField(
          enabled: false,
          obscureText: password,
          decoration: const InputDecoration(hintText: '—'),
        ),
      ],
    ),
  );
}

class _LiveSettings extends StatefulWidget {
  const _LiveSettings({
    required this.api,
    this.onSessionExpired,
    this.esp = false,
  });
  final ApiClient api;
  final VoidCallback? onSessionExpired;
  final bool esp;

  @override
  State<_LiveSettings> createState() => _LiveSettingsState();
}

class _LiveSettingsState extends State<_LiveSettings> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _expired = false;
  int _requestVersion = 0;

  @override
  void didUpdateWidget(covariant _LiveSettings oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.api != widget.api || oldWidget.esp != widget.esp) {
      _expired = false;
      _load();
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (_expired) return;
    final version = ++_requestVersion;
    final api = widget.api;
    setState(() {
      _data = null;
      _error = null;
    });
    try {
      final data = await (widget.esp ? api.espSettings() : api.me());
      if (!mounted || version != _requestVersion || _expired) return;
      setState(() => _data = data);
    } on ApiException catch (error) {
      if (!mounted || api != widget.api || _expired) return;
      if (error.statusCode == 401) {
        setState(() {
          _expired = true;
          _data = null;
          _error = 'Sesi berakhir. Silakan masuk kembali.';
          _requestVersion++;
        });
        widget.onSessionExpired?.call();
      } else if (version == _requestVersion) {
        setState(() => _error = error.message);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionTitle(widget.esp ? 'Konfigurasi perangkat' : 'Setting Akun', ''),
        if (_error != null) ...[
          InfoNotice(_error!),
          if (!_expired)
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton(
                key: const Key('settings-retry'),
                onPressed: _load,
                child: const Text('Muat ulang'),
              ),
            ),
        ] else if (data == null)
          Text(widget.esp ? 'Memuat konfigurasi…' : 'Memuat akun…')
        else if (widget.esp)
          _esp(data)
        else ...[
          const InfoNotice('Profil hanya baca'),
          const SizedBox(height: 20),
          SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _value('Nama lengkap', data['name'] as String),
                _value('Alamat email', data['email'] as String),
                _value('Peran', switch (data['role']) {
                  'operator' => 'Operator (operator)',
                  'viewer' => 'Pemantau (viewer)',
                  _ => data['role'] as String,
                }),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _value(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(label, style: const TextStyle(fontSize: 13)),
        const SizedBox(height: 8),
        Semantics(
          label: '$label: $value',
          excludeSemantics: true,
          child: SelectableText(value, style: const TextStyle(fontSize: 16)),
        ),
      ],
    ),
  );

  Widget _esp(Map<String, dynamic> data) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const InfoNotice('Konfigurasi hanya baca'),
      const SizedBox(height: 20),
      Text(
        data['integration']['mqtt_enabled'] == true
            ? 'Ingest MQTT aktif di server'
            : 'MQTT nonaktif di server',
      ),
      const SizedBox(height: 8),
      Text(
        data['integration']['hardware_connected'] == true
            ? 'Perangkat terhubung menurut server'
            : 'Koneksi perangkat belum terverifikasi',
      ),
      const SizedBox(height: 20),
      TwoColumns(
        children: [
          for (final node in data['nodes'] as List)
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(Icons.memory_rounded, size: 30),
                  const SizedBox(height: 12),
                  Text(
                    node['name'] as String,
                    style: const TextStyle(
                      fontSize: 21,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text('Node ${node['id']}'),
                  const Divider(height: 28),
                  for (final sensor in data['sensors'] as List)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text('${sensor['name']} (${sensor['unit']})'),
                    ),
                ],
              ),
            ),
        ],
      ),
      const SizedBox(height: 20),
    ],
  );
}
