import http.client
import importlib.util
from pathlib import Path
import tempfile
import threading
import unittest
from unittest.mock import patch


class LocalServerTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        spec = importlib.util.spec_from_file_location('serve_web', Path(__file__).with_name('serve_web.py'))
        cls.module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(cls.module)

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name).resolve()
        (self.root / 'index.html').write_text('<!doctype html><title>Fixture</title>')
        (self.root / '.env').write_text('TEST_SECRET=must-not-be-served')
        (self.root / 'assets').mkdir()
        (self.root / 'assets' / 'fixture.txt').write_text('public fixture')
        self.server = self.module.create_server(self.root, port=0)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join()
        self.temp.cleanup()

    def request(self, path, method='GET', headers=None):
        client = http.client.HTTPConnection('127.0.0.1', self.server.server_port, timeout=3)
        client.request(method, path, headers=headers or {})
        response = client.getresponse()
        result = response.status, dict(response.getheaders()), response.read()
        client.close()
        return result

    def test_security_headers_on_success_and_error(self):
        for path, expected in [('/', 200), ('/missing', 404), ('/.env', 404), ('/%2eenv', 404)]:
            with self.subTest(path=path):
                status, headers, body = self.request(path)
                self.assertEqual(status, expected)
                self.assertEqual(headers.get('X-Frame-Options'), 'DENY')
                self.assertEqual(headers.get('X-Content-Type-Options'), 'nosniff')
                self.assertIn("frame-ancestors 'none'", headers.get('Content-Security-Policy', ''))
                self.assertIn('no-store', headers.get('Cache-Control', ''))
                self.assertNotIn(b'must-not-be-served', body)
                self.assertNotIn('Python', headers.get('Server', ''))
                self.assertNotIn('Strict-Transport-Security', headers)

    def test_no_directory_listing_or_encoded_traversal(self):
        for path in ['/assets/', '/../.env', '/%2e%2e/.env', '/assets/%2e%2e/.env']:
            self.assertEqual(self.request(path)[0], 404)
        self.assertEqual(self.request('/assets/fixture.txt')[0], 200)

    def test_directory_index_cannot_escape_public_root(self):
        with tempfile.TemporaryDirectory() as private_directory:
            private = Path(private_directory) / 'private.txt'
            private.write_text('outside-root-fixture')
            nested = self.root / 'nested'
            nested.mkdir()
            try:
                (nested / 'index.html').symlink_to(private)
            except OSError as error:
                self.skipTest(f'File symlinks unavailable: {error}')
            for path in ['/nested/', '/nested/index.html']:
                with self.subTest(path=path):
                    status, headers, body = self.request(path)
                    self.assertEqual(status, 404)
                    self.assertNotIn(b'outside-root-fixture', body)
                    self.assertEqual(headers.get('X-Content-Type-Options'), 'nosniff')

    def test_root_index_cannot_escape_public_root(self):
        with tempfile.TemporaryDirectory() as private_directory:
            private = Path(private_directory) / 'private.txt'
            private.write_text('outside-root-fixture')
            index = self.root / 'index.html'
            index.unlink()
            try:
                index.symlink_to(private)
            except OSError as error:
                self.skipTest(f'File symlinks unavailable: {error}')
            for path in ['/', '/index.html']:
                with self.subTest(path=path):
                    status, _, body = self.request(path)
                    self.assertEqual(status, 404)
                    self.assertNotIn(b'outside-root-fixture', body)

    def test_index_resolution_is_checked_for_root_and_nested_requests(self):
        # Model symlink resolution only; exercise the actual HTTP server/file serving.
        original_resolve = Path.resolve
        nested = self.root / 'nested'
        nested.mkdir()
        (nested / 'index.html').write_text('must-not-be-served-via-index')
        for request_path, index in [('/', self.root / 'index.html'), ('/nested/', nested / 'index.html')]:
            outside = self.root.parent / 'outside-root-index-fixture.html'

            def resolve(path, *args, **kwargs):
                if path == index:
                    return outside
                return original_resolve(path, *args, **kwargs)

            with self.subTest(path=request_path), patch.object(Path, 'resolve', resolve):
                status, _, body = self.request(request_path)
                self.assertEqual(status, 404)
                self.assertNotIn(b'must-not-be-served-via-index', body)

    def test_nested_public_index_and_asset_still_work(self):
        nested = self.root / 'nested'
        nested.mkdir()
        (nested / 'index.html').write_text('public nested index')
        self.assertEqual(self.request('/nested/')[2], b'public nested index')
        self.assertEqual(self.request('/assets/fixture.txt')[2], b'public fixture')

    def test_foreign_and_ambiguous_hosts_are_rejected(self):
        for host in ['audit.invalid', '127.0.0.1.audit.invalid', 'localhost@audit.invalid', 'localhost:invalid', 'localhost:65536', 'localhost, audit.invalid']:
            with self.subTest(host=host):
                status, headers, _ = self.request('/', headers={'Host': host})
                self.assertEqual(status, 400)
                self.assertEqual(headers.get('X-Content-Type-Options'), 'nosniff')
        self.assertEqual(self.request('/', headers={'Host': f'localhost:{self.server.server_port}'})[0], 200)
        self.assertEqual(self.request('/', headers={'Host': f'127.0.0.1:{self.server.server_port}'})[0], 200)

    def test_head_and_mutations(self):
        self.assertEqual(self.request('/', 'HEAD')[2], b'')
        for method in ['POST', 'PUT', 'DELETE', 'PATCH']:
            status, headers, body = self.request('/', method)
            self.assertEqual(status, 405)
            self.assertEqual(headers.get('X-Content-Type-Options'), 'nosniff')


if __name__ == '__main__':
    unittest.main()
