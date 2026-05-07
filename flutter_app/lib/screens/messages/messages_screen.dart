import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../widgets/hs_widgets.dart';
import 'chat_screen.dart';

class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key});
  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  List<dynamic> _convos = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final c = await ApiService.getConversations();
      if (mounted) setState(() { _convos = c; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _newChat() async {
    final contacts = await ApiService.getContacts();
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => Column(children: [
        const Padding(
          padding: EdgeInsets.all(16),
          child: Text('New Message', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        ),
        Expanded(child: ListView.builder(
          itemCount: contacts.length,
          itemBuilder: (_, i) {
            final c = contacts[i] as Map<String, dynamic>;
            return ListTile(
              leading: CircleAvatar(
                backgroundColor: AppTheme.primary.withOpacity(0.12),
                child: Text('${c['first_name']?[0] ?? '?'}', style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
              ),
              title: Text('${c['first_name']} ${c['last_name']}'),
              subtitle: Text(c['specialization'] ?? c['role'] ?? ''),
              onTap: () {
                Navigator.pop(context);
                Navigator.push(context, MaterialPageRoute(
                  builder: (_) => ChatScreen(userId: c['id'] as int, name: '${c['first_name']} ${c['last_name']}'),
                )).then((_) => _load());
              },
            );
          },
        )),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      floatingActionButton: FloatingActionButton(
        onPressed: _newChat,
        backgroundColor: AppTheme.primary,
        child: const Icon(Icons.edit_rounded),
      ),
      body: _loading
          ? const LoadingOverlay()
          : _convos.isEmpty
              ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.chat_bubble_outline_rounded, size: 56, color: Color(0xFFCCCCCC)),
                  const SizedBox(height: 12),
                  const Text('No messages yet', style: TextStyle(color: Color(0xFF9E9E9E))),
                  const SizedBox(height: 16),
                  ElevatedButton(onPressed: _newChat, child: const Text('Start a Conversation')),
                ]))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    itemCount: _convos.length,
                    itemBuilder: (_, i) {
                      final c = _convos[i] as Map<String, dynamic>;
                      final unread = (c['unread'] as num? ?? 0).toInt();
                      return ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                        leading: Stack(children: [
                          CircleAvatar(
                            radius: 24,
                            backgroundColor: AppTheme.primary.withOpacity(0.12),
                            child: Text(
                              '${(c['first_name'] as String? ?? '?')[0]}${(c['last_name'] as String? ?? '')[0]}',
                              style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary),
                            ),
                          ),
                          if (unread > 0)
                            Positioned(right: 0, top: 0, child: Container(
                              width: 16, height: 16,
                              decoration: const BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle),
                              child: Center(child: Text('$unread', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold))),
                            )),
                        ]),
                        title: Text(
                          '${c['first_name']} ${c['last_name']}',
                          style: TextStyle(fontWeight: unread > 0 ? FontWeight.bold : FontWeight.normal),
                        ),
                        subtitle: Text(
                          c['last_message'] ?? '',
                          maxLines: 1, overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: unread > 0 ? const Color(0xFF333333) : const Color(0xFF9E9E9E)),
                        ),
                        trailing: c['last_time'] != null
                            ? Text(_formatTime(c['last_time'] as String), style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E)))
                            : null,
                        onTap: () => Navigator.push(context, MaterialPageRoute(
                          builder: (_) => ChatScreen(userId: c['other_id'] as int, name: '${c['first_name']} ${c['last_name']}'),
                        )).then((_) => _load()),
                      );
                    },
                  ),
                ),
    );
  }

  String _formatTime(String t) {
    try {
      final dt = DateTime.parse(t);
      final now = DateTime.now();
      if (dt.day == now.day) return DateFormat('HH:mm').format(dt);
      return DateFormat('dd/MM').format(dt);
    } catch (_) {
      return '';
    }
  }
}
