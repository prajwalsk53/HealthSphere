import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/auth_provider.dart';

class ChatScreen extends StatefulWidget {
  final int userId;
  final String name;
  const ChatScreen({super.key, required this.userId, required this.name});
  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _msgCtrl   = TextEditingController();
  final _scroll    = ScrollController();
  List<dynamic> _messages = [];
  bool _sending = false;
  Timer? _poller;
  int? _myId;

  @override
  void initState() {
    super.initState();
    _myId = context.read<AuthProvider>().user?['id'] as int?;
    _load();
    _poller = Timer.periodic(const Duration(seconds: 5), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _poller?.cancel();
    _msgCtrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    try {
      final msgs = await ApiService.getThread(widget.userId);
      if (mounted) setState(() => _messages = msgs);
      if (!silent) _scrollBottom();
    } catch (_) {}
  }

  void _scrollBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
    });
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending) return;
    _msgCtrl.clear();
    setState(() => _sending = true);
    try {
      await ApiService.sendMessage(widget.userId, text);
      await _load();
      _scrollBottom();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(children: [
          CircleAvatar(
            radius: 16,
            backgroundColor: AppTheme.primary.withOpacity(0.12),
            child: Text(widget.name.isNotEmpty ? widget.name[0] : '?', style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 12)),
          ),
          const SizedBox(width: 10),
          Text(widget.name),
        ]),
      ),
      body: Column(children: [
        Expanded(
          child: _messages.isEmpty
              ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.chat_bubble_outline, size: 48, color: Color(0xFFCCCCCC)),
                  const SizedBox(height: 8),
                  Text('Start a conversation with ${widget.name}', style: const TextStyle(color: Color(0xFF9E9E9E))),
                ]))
              : ListView.builder(
                  controller: _scroll,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  itemCount: _messages.length,
                  itemBuilder: (_, i) {
                    final m = _messages[i] as Map<String, dynamic>;
                    final mine = (m['sender_id'] as num?)?.toInt() == _myId;
                    return _bubble(m, mine);
                  },
                ),
        ),
        Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            boxShadow: [BoxShadow(color: Color(0x0A000000), blurRadius: 10, offset: Offset(0, -2))],
          ),
          child: Row(children: [
            Expanded(
              child: TextField(
                controller: _msgCtrl,
                maxLines: null,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(
                  hintText: 'Type a message...',
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                  filled: true, fillColor: const Color(0xFFF0F4FF),
                ),
                onSubmitted: (_) => _send(),
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: _sending ? null : _send,
              child: Container(
                width: 44, height: 44,
                decoration: BoxDecoration(
                  color: _sending ? const Color(0xFFCCCCCC) : AppTheme.primary,
                  shape: BoxShape.circle,
                ),
                child: _sending
                    ? const Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
              ),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _bubble(Map<String, dynamic> m, bool mine) {
    final time = m['created_at'] != null ? DateFormat('HH:mm').format(DateTime.parse(m['created_at'] as String)) : '';
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
        decoration: BoxDecoration(
          color: mine ? AppTheme.primary : Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: mine ? const Radius.circular(16) : const Radius.circular(4),
            bottomRight: mine ? const Radius.circular(4) : const Radius.circular(16),
          ),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 6)],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.end, mainAxisSize: MainAxisSize.min, children: [
          Text(m['message'] as String? ?? '', style: TextStyle(color: mine ? Colors.white : const Color(0xFF1A1A2E), fontSize: 14)),
          const SizedBox(height: 2),
          Text(time, style: TextStyle(color: mine ? Colors.white60 : const Color(0xFF9E9E9E), fontSize: 10)),
        ]),
      ),
    );
  }
}
