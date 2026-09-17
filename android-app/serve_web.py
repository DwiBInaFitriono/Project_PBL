"""Serve only Flutter build/web on loopback; not a production web server."""
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit


class PreviewHandler(SimpleHTTPRequestHandler):
    server_version = 'LocalPreview'
    sys_version = ''

    def parse_request(self):
        if not super().parse_request():
            return False
        hosts = self.headers.get_all('Host', [])
        match = re.fullmatch(r'(?:localhost|127\.0\.0\.1)(?::([0-9]{1,5}))?', hosts[0], re.IGNORECASE) if len(hosts) == 1 else None
        if match is None or (match[1] is not None and not 1 <= int(match[1]) <= 65535):
            self.send_error(400)
            return False
        return True

    def version_string(self):
        return self.server_version

    def log_message(self, format, *args):
        # Do not persist request URLs, query strings, or credentials.
        return

    def end_headers(self):
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('X-Frame-Options', 'DENY')
        self.send_header('Referrer-Policy', 'no-referrer')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
        self.send_header('Content-Security-Policy',
                         "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; "
                         "style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
                         "font-src 'self' data:; connect-src 'self' http://127.0.0.1:8000; "
                         "worker-src 'self' blob:; object-src 'none'; base-uri 'self'; "
                         "frame-ancestors 'none'; form-action 'none'")
        super().end_headers()

    def send_error(self, code, message=None, explain=None):
        body = b'Request unavailable.\n'
        self.send_response(code)
        self.send_header('Content-Type', 'text/plain; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        if code == 405:
            self.send_header('Allow', 'GET, HEAD')
        self.end_headers()
        if self.command != 'HEAD':
            self.wfile.write(body)

    def send_head(self):
        path = unquote(urlsplit(self.path).path)
        if '\\' in path or '\x00' in path or ':' in path or any(part.startswith('.') for part in path.split('/') if part):
            self.send_error(404)
            return None
        root = Path(self.directory).resolve()
        target = (root / path.lstrip('/')).resolve()
        if not target.is_relative_to(root):
            self.send_error(404)
            return None
        if target.is_dir():
            index = (target / 'index.html').resolve()
            if not index.is_relative_to(root) or not index.is_file():
                self.send_error(404)
                return None
        return super().send_head()

    def do_POST(self):
        self.send_error(405)

    do_PUT = do_POST
    do_DELETE = do_POST
    do_PATCH = do_POST
    do_OPTIONS = do_POST


def create_server(root, port=8091):
    root = Path(root).resolve()
    if not (root / 'index.html').is_file():
        raise ValueError('Build Flutter web first; index.html is missing.')
    return ThreadingHTTPServer(('127.0.0.1', port), partial(PreviewHandler, directory=str(root)))


if __name__ == '__main__':
    with create_server(Path(__file__).parent / 'build' / 'web') as server:
        print('Flutter local: http://127.0.0.1:8091 (loopback only)', flush=True)
        try:
            server.serve_forever()
        except KeyboardInterrupt:
            pass
