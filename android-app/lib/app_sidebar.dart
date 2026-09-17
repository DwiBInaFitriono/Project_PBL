import 'package:flutter/material.dart';

import 'dashboard_widgets.dart';
import 'login_page.dart';
import 'app_palette.dart';

class AppSidebar extends StatelessWidget {
  const AppSidebar({
    super.key,
    required this.compact,
    required this.destination,
    required this.expandedNode,
    required this.onToggleNode,
    required this.onNavigate,
    this.onClose,
    this.activeSensor,
    this.showSettings = false,
    this.operatorPreview = true,
    this.preview = true,
  });
  final bool compact;
  final String destination;
  final String? activeSensor;
  final int? expandedNode;
  final ValueChanged<int> onToggleNode;
  final void Function(String destination, String? sensor) onNavigate;
  final VoidCallback? onClose;
  final bool showSettings;
  final bool operatorPreview;
  final bool preview;

  @override
  Widget build(BuildContext context) => Theme(
    data: Theme.of(context),
    child: Builder(
      builder: (context) => ColoredBox(
        color: Theme.of(context).brightness == Brightness.dark
            ? AppPalette.darkCard
            : AppPalette.card,
        child: SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              compact ? 8 : 16,
              20,
              compact ? 8 : 16,
              16,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (!compact)
                  Row(
                    children: [
                      const Expanded(
                        child: SizedBox(
                          height: 48,
                          child: FittedBox(
                            alignment: Alignment.centerLeft,
                            fit: BoxFit.scaleDown,
                            child: SizedBox(width: 176, child: RebungBrand()),
                          ),
                        ),
                      ),
                      if (onClose != null)
                        IconButton(
                          tooltip: 'Tutup navigasi',
                          onPressed: onClose,
                          icon: const Icon(Icons.close_rounded),
                        ),
                    ],
                  )
                else
                  const Tooltip(
                    message: 'Rebung Pintar',
                    child: Icon(Icons.grass_rounded, size: 30),
                  ),
                const SizedBox(height: 20),
                Expanded(
                  child: SingleChildScrollView(
                    key: const Key('sidebar-scroll'),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _link(
                          context,
                          'Dashboard',
                          Icons.dashboard_outlined,
                          'dashboard',
                        ),
                        if (!compact)
                          const Padding(
                            padding: EdgeInsets.fromLTRB(12, 20, 0, 8),
                            child: Text(
                              'PERANGKAT',
                              style: TextStyle(
                                fontSize: 10,
                                letterSpacing: 1.5,
                              ),
                            ),
                          ),
                        for (final node in [1, 2]) ...[
                          Tooltip(
                            message: 'Menu Node $node',
                            child: TextButton(
                              key: Key('menu-node-$node'),
                              style: TextButton.styleFrom(
                                foregroundColor: Theme.of(context)
                                    .colorScheme
                                    .onSurface,
                                minimumSize: const Size(48, 48),
                                padding: const EdgeInsets.all(12),
                                alignment: Alignment.centerLeft,
                              ),
                              onPressed: () => onToggleNode(node),
                              child: Row(
                                children: [
                                  Badge(
                                    label: Text('$node'),
                                    backgroundColor: AppPalette.yellow,
                                    textColor: const Color(0xFF011E60),
                                    child: const Icon(
                                      Icons.memory_rounded,
                                      size: 21,
                                    ),
                                  ),
                                  if (!compact) ...[
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: Text(
                                        'Node $node',
                                        style: const TextStyle(fontSize: 15),
                                      ),
                                    ),
                                    Icon(
                                      expandedNode == node
                                          ? Icons.expand_less
                                          : Icons.expand_more,
                                      size: 18,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ),
                          if (!compact && expandedNode == node)
                            Padding(
                              padding: const EdgeInsets.only(left: 12),
                              child: Column(
                                children: [
                                  _link(
                                    context,
                                    'Ringkasan Node $node',
                                    Icons.grid_view_rounded,
                                    'node-$node',
                                    keyName: 'nav-node-$node',
                                    small: true,
                                  ),
                                  for (final sensor in sensors)
                                    _link(
                                      context,
                                      sensor.name,
                                      sensor.icon,
                                      'node-$node',
                                      sensor: sensor.id,
                                      keyName: 'nav-node-$node-${sensor.id}',
                                      small: true,
                                    ),
                                  if (showSettings)
                                    _link(
                                      context,
                                      'Riwayat pembacaan',
                                      Icons.history,
                                      'history-$node',
                                      small: true,
                                    ),
                                ],
                              ),
                            ),
                        ],
                        if (showSettings) ...[
                          _link(
                            context,
                            'Riwayat data',
                            Icons.history_rounded,
                            'history',
                          ),
                          if (operatorPreview) ...[
                            if (!compact)
                              const Padding(
                                padding: EdgeInsets.fromLTRB(12, 20, 0, 8),
                                child: Text(
                                  'PENGATURAN',
                                  style: TextStyle(
                                    fontSize: 10,
                                    letterSpacing: 1.5,
                                  ),
                                ),
                              ),
                            _link(
                              context,
                              'Setting ESP',
                              Icons.settings_input_component_outlined,
                              'esp',
                            ),
                          ],
                        ],
                      ],
                    ),
                  ),
                ),
                if (showSettings) ...[
                  const Divider(),
                  if (!compact && preview)
                    const Padding(
                      padding: EdgeInsets.symmetric(
                        vertical: 8,
                        horizontal: 12,
                      ),
                      child: Text('PRATINJAU', style: TextStyle(fontSize: 11)),
                    ),
                  _link(
                    context,
                    'Setting Akun',
                    Icons.manage_accounts_outlined,
                    'account',
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    ),
  );

  Widget _link(
    BuildContext context,
    String title,
    IconData icon,
    String target, {
    String? sensor,
    String? keyName,
    bool small = false,
  }) {
    final selected =
        destination == target &&
        (target.startsWith('node-') ? sensor == activeSensor : sensor == null);
    return Semantics(
      selected: selected,
      child: Tooltip(
        message: title,
        child: TextButton(
          key: Key(keyName ?? 'nav-$target'),
          onPressed: () => onNavigate(target, sensor),
          style: TextButton.styleFrom(
            minimumSize: const Size(48, 48),
            alignment: Alignment.centerLeft,
            padding: const EdgeInsets.all(12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            backgroundColor: selected ? AppPalette.yellow : null,
            foregroundColor: selected
                ? const Color(0xFF011E60)
                : Theme.of(context).colorScheme.onSurface,
          ),
          child: Row(
            children: [
              Icon(icon, size: small ? 18 : 21),
              if (!compact) ...[
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(fontSize: small ? 12 : 15),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
