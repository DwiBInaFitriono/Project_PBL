import 'package:flutter/material.dart';

import 'api_client.dart';
import 'dashboard_widgets.dart';

class HistoryDraft {
  HistoryDraft({this.node = '', this.sensor = ''}) {
    final today = DateTime.now().toUtc().add(const Duration(hours: 7));
    to = DateTime.utc(today.year, today.month, today.day);
    from = to.subtract(const Duration(days: 6));
  }
  String node;
  String sensor;
  late DateTime from;
  late DateTime to;
  String? applied;
  String? error;
  _HistoryFilters? _filters;
  int _page = 1;
}

class _HistoryFilters {
  const _HistoryFilters(this.node, this.sensor, this.from, this.to);
  final String node;
  final String sensor;
  final DateTime from;
  final DateTime to;
}

class HistoryPage extends StatefulWidget {
  const HistoryPage({
    super.key,
    this.initialNode = '',
    this.initialSensor = '',
    this.draft,
    this.api,
    this.onSessionExpired,
  });
  final ApiClient? api;
  final VoidCallback? onSessionExpired;
  final HistoryDraft? draft;
  final String initialNode;
  final String initialSensor;
  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  Map<String, dynamic>? _response;
  String? _loadError;
  bool _loading = false;
  bool _expired = false;
  int _requestVersion = 0;

  @override
  void didUpdateWidget(covariant HistoryPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.api != widget.api ||
        oldWidget.draft != widget.draft ||
        oldWidget.initialNode != widget.initialNode ||
        oldWidget.initialSensor != widget.initialSensor) {
      _requestVersion++;
      if (oldWidget.api != widget.api) _expired = false;
      _response = null;
      if (!_expired) _loadError = null;
      _loading = false;
      _draft =
          widget.draft ??
          HistoryDraft(node: widget.initialNode, sensor: widget.initialSensor);
      _draft._filters ??= _captureFilters();
      _draft.applied ??= _describe(_draft._filters!);
      _load();
    }
  }

  void _edit(VoidCallback change) => setState(() {
    change();
    _requestVersion++;
    _loading = false;
  });

  @override
  void initState() {
    super.initState();
    _draft._filters ??= _captureFilters();
    _draft.applied ??= _describe(_draft._filters!);
    _load();
  }

  _HistoryFilters _captureFilters() =>
      _HistoryFilters(_node, _sensor, _from, _to);

  String _queryDate(DateTime date) =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  Future<void> _load() async {
    final api = widget.api;
    if (api == null || _expired) return;
    final version = ++_requestVersion;
    final filters = _draft._filters!;
    setState(() {
      _loading = true;
      _loadError = null;
      _response = null;
    });
    try {
      final response = await api.history(
        node: filters.node.isEmpty ? null : filters.node,
        sensor: filters.sensor.isEmpty ? null : filters.sensor,
        from: _queryDate(filters.from),
        to: _queryDate(filters.to),
        timezone: 'Asia/Jakarta',
        page: _draft._page,
      );
      if (!mounted || _expired || version != _requestVersion) return;
      setState(() {
        _loading = false;
        _response = response;
        _draft._page = response['meta']['current_page'] as int;
      });
    } on ApiException catch (error) {
      if (!mounted || api != widget.api || _expired) return;
      // A superseded filter request can still invalidate the current session.
      if (error.statusCode == 401) {
        setState(() {
          _expired = true;
          _loading = false;
          _response = null;
          _loadError = 'Sesi berakhir. Silakan masuk kembali.';
          _requestVersion++;
        });
        widget.onSessionExpired?.call();
      } else if (version == _requestVersion) {
        setState(() {
          _loading = false;
          _loadError = error.message;
        });
      }
    }
  }

  String _timestamp(String value) {
    final time = DateTime.parse(value).toUtc().add(const Duration(hours: 7));
    String part(int value) => value.toString().padLeft(2, '0');
    return '${_date(time)} ${part(time.hour)}:${part(time.minute)}:${part(time.second)} WIB';
  }

  Widget _results() {
    final response = _response;
    if (_loading) return const Text('Memuat riwayat…');
    if (_loadError != null || response == null) {
      return SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_loadError != null && !_expired)
              const Text('Gagal memuat riwayat'),
            Text(_loadError ?? 'Filter diubah. Terapkan atau muat ulang.'),
            if (!_expired)
              Align(
                alignment: Alignment.centerLeft,
                child: OutlinedButton(
                  key: const Key('history-retry'),
                  onPressed: _load,
                  child: const Text('Muat ulang'),
                ),
              ),
          ],
        ),
      );
    }
    final rows = response['data'] as List;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('${response['meta']['total']} pembacaan'),
        const SizedBox(height: 16),
        if (rows.isEmpty)
          const EmptyHistory()
        else
          for (final row in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: SurfaceCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('${row['node_name']} · ${row['sensor_name']}'),
                    const SizedBox(height: 8),
                    Text(
                      '${row['value']} ${row['unit']}',
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(_timestamp(row['recorded_at'] as String)),
                  ],
                ),
              ),
            ),
        const SizedBox(height: 12),
        Text(
          'Halaman ${response['meta']['current_page']} dari ${response['meta']['last_page']}',
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 16,
          runSpacing: 12,
          children: [
            OutlinedButton(
              key: const Key('history-previous'),
              onPressed: _draft._page > 1
                  ? () => _goToPage(_draft._page - 1)
                  : null,
              child: const Text('Sebelumnya'),
            ),
            OutlinedButton(
              key: const Key('history-next'),
              onPressed: _draft._page < (response['meta']['last_page'] as int)
                  ? () => _goToPage(_draft._page + 1)
                  : null,
              child: const Text('Berikutnya'),
            ),
          ],
        ),
      ],
    );
  }

  void _goToPage(int page) {
    setState(() => _draft._page = page);
    _load();
  }

  late HistoryDraft _draft =
      widget.draft ??
      HistoryDraft(node: widget.initialNode, sensor: widget.initialSensor);
  String get _node => _draft.node;
  set _node(String value) => _draft.node = value;
  String get _sensor => _draft.sensor;
  set _sensor(String value) => _draft.sensor = value;
  DateTime get _to => _draft.to;
  set _to(DateTime value) => _draft.to = value;
  DateTime get _from => _draft.from;
  set _from(DateTime value) => _draft.from = value;
  String get _applied => _draft.applied ??= _description();
  set _applied(String value) => _draft.applied = value;
  String? get _error => _draft.error;
  set _error(String? value) => _draft.error = value;

  String _date(DateTime date) =>
      '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  String _description() => _describe(_captureFilters());
  String _describe(_HistoryFilters filters) =>
      '${filters.node.isEmpty ? 'Semua node' : 'Node ${filters.node}'} · ${filters.sensor.isEmpty ? 'Semua parameter' : sensors.firstWhere((s) => s.id == filters.sensor).name}\n${_date(filters.from)} — ${_date(filters.to)} WIB';

  Future<void> _pickDate(bool from) async {
    final chosen = await showDatePicker(
      context: context,
      initialDate: from ? _from : _to,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
      helpText: from ? 'Dari tanggal (WIB)' : 'Sampai tanggal (WIB)',
      cancelText: 'Batal',
      confirmText: 'Pilih',
    );
    if (chosen != null && mounted) {
      final date = DateTime.utc(chosen.year, chosen.month, chosen.day);
      _edit(() {
        if (from) {
          _from = date;
        } else {
          _to = date;
        }
      });
    }
  }

  void _apply() => setState(() {
    if (_to.isBefore(_from)) {
      _error = 'Sampai tanggal tidak boleh sebelum Dari tanggal.';
      return;
    }
    if (_to.difference(_from).inDays > 365) {
      _error = 'Rentang tanggal maksimal 366 hari.';
      return;
    }
    _error = null;
    _applied = _description();
    _draft._filters = _captureFilters();
    _draft._page = 1;
    _load();
  });

  void _reset() => setState(() {
    _node = '';
    _sensor = '';
    final today = DateTime.now().toUtc().add(const Duration(hours: 7));
    _to = DateTime.utc(today.year, today.month, today.day);
    _from = _to.subtract(const Duration(days: 6));
    _error = null;
    _applied = _description();
    _draft._filters = _captureFilters();
    _draft._page = 1;
    _load();
  });

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SectionTitle('Riwayat pembacaan', 'WIB · Maks. 366 hari'),
      if (widget.api == null) const InfoNotice('PRATINJAU · Filter lokal'),
      const SizedBox(height: 20),
      SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TwoColumns(
              children: [
                _select('Node', 'history-node', _node, {
                  '': 'Semua node',
                  '1': 'Node 1',
                  '2': 'Node 2',
                }, (value) => _edit(() => _node = value)),
                _select('Parameter', 'history-sensor', _sensor, {
                  '': 'Semua parameter',
                  for (final sensor in sensors) sensor.id: sensor.name,
                }, (value) => _edit(() => _sensor = value)),
              ],
            ),
            const SizedBox(height: 20),
            TwoColumns(
              children: [
                _dateButton('Dari tanggal', _from, true),
                _dateButton('Sampai tanggal', _to, false),
              ],
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 20),
            Wrap(
              spacing: 16,
              runSpacing: 12,
              children: [
                FilledButton(
                  key: const Key('apply-history'),
                  onPressed: _expired ? null : _apply,
                  child: const Text('Terapkan filter'),
                ),
                OutlinedButton(
                  key: const Key('reset-history'),
                  onPressed: _expired ? null : _reset,
                  child: const Text('Reset filter'),
                ),
              ],
            ),
          ],
        ),
      ),
      const SizedBox(height: 20),
      Text(_applied, style: const TextStyle(fontSize: 13, height: 1.7)),
      const SizedBox(height: 8),
      if (widget.api == null) ...[
        const Text('0 pembacaan', style: TextStyle(fontSize: 13)),
        const SizedBox(height: 16),
        const EmptyHistory(),
      ] else
        _results(),
      const SizedBox(height: 16),
      const Text(
        'PDF belum tersedia.',
        style: TextStyle(fontSize: 13, height: 1.6),
      ),
      const SizedBox(height: 8),
      Wrap(
        spacing: 12,
        runSpacing: 8,
        children: [
          OutlinedButton.icon(
            key: const Key('export-csv-button'),
            onPressed: _exportCsv,
            icon: const Icon(Icons.table_chart_outlined, size: 18),
            label: const Text('Ekspor CSV'),
          ),
          OutlinedButton.icon(
            onPressed: null,
            icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
            label: const Text('Ekspor PDF (segera)'),
          ),
        ],
      ),
    ],
  );

  void _exportCsv() {
    final rows = _response?['data'] as List<dynamic>? ?? [];
    if (rows.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tidak ada data pembacaan untuk diekspor.')),
      );
      return;
    }

    final buffer = StringBuffer();
    buffer.writeln('Node,Sensor,Nilai,Satuan,Waktu_WIB');
    for (final r in rows) {
      final node = r['node_name'] ?? '';
      final sensor = r['sensor_name'] ?? '';
      final val = r['value'] ?? '';
      final unit = r['unit'] ?? '';
      final time = r['recorded_at'] ?? '';
      buffer.writeln('"$node","$sensor","$val","$unit","$time"');
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Berhasil mengekspor ${rows.length} baris data CSV!'),
        backgroundColor: const Color(0xFF2E7D32),
      ),
    );
  }


  Widget _select(
    String label,
    String keyName,
    String value,
    Map<String, String> options,
    ValueChanged<String> change,
  ) => Column(
    key: Key(keyName),
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(
        label,
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
      ),
      const SizedBox(height: 8),
      DropdownButtonFormField<String>(
        key: ValueKey('$keyName-$value'),
        initialValue: value,
        isExpanded: true,
        decoration: const InputDecoration(
          contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        ),
        items: [
          for (final entry in options.entries)
            DropdownMenuItem(
              value: entry.key,
              child: Text(entry.value, style: const TextStyle(fontSize: 14)),
            ),
        ],
        onChanged: (value) {
          if (value != null) change(value);
        },
      ),
    ],
  );

  Widget _dateButton(String label, DateTime date, bool from) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(
        label,
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
      ),
      const SizedBox(height: 8),
      Semantics(
        label: '$label ${_date(date)} WIB',
        button: true,
        excludeSemantics: true,
        onTap: () => _pickDate(from),
        child: OutlinedButton.icon(
          key: Key(from ? 'history-from' : 'history-to'),
          onPressed: () => _pickDate(from),
          icon: const Icon(Icons.calendar_today_outlined, size: 18),
          label: Text(_date(date)),
        ),
      ),
    ],
  );
}
