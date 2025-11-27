<script>
    // global socket server url (override by setting WINDOW.SOCKET_URL before this include)
    window.SOCKET_URL = window.SOCKET_URL || '{{ env('SOCKET_URL', 'http://localhost:3000') }}';
</script>
