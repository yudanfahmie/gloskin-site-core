#!/usr/bin/env python3
"""Optional Chromium smoke for the canonical Graphik WOFF runtime."""
from pathlib import Path
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
import shutil
import threading
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
EXPECTED = {
    "Graphik-Light.woff",
    "Graphik-Regular.woff",
    "Graphik-Medium.woff",
    "Graphik-Semibold.woff",
    "Graphik-Bold.woff",
}
HTML = b'''<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="/plugin/gloskin-site-core/assets/css/gloskin-ui1-fonts.css">
<style>body,.sample{font-family:"Graphik",sans-serif}</style></head><body>
<span class="sample" style="font-weight:300">Light</span>
<span class="sample" style="font-weight:400">Regular</span>
<span class="sample" style="font-weight:500">Medium</span>
<span class="sample" style="font-weight:600">Semibold</span>
<span class="sample" style="font-weight:700">Bold</span>
</body></html>'''


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def log_message(self, fmt, *args):
        pass

    def do_GET(self):
        if self.path == "/__graphik_font_fixture__.html":
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(HTML)))
            self.end_headers()
            self.wfile.write(HTML)
            return
        super().do_GET()


server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
thread = threading.Thread(target=server.serve_forever, daemon=True)
thread.start()
url = f"http://127.0.0.1:{server.server_port}/__graphik_font_fixture__.html"

failures = []
console_errors = []
font_responses = {}
result = None
try:
    with sync_playwright() as playwright:
        launch_args = {"headless": True}
        system_chromium = shutil.which("chromium") or shutil.which("chromium-browser")
        if system_chromium:
            launch_args["executable_path"] = system_chromium
        browser = playwright.chromium.launch(**launch_args)
        page = browser.new_page()
        page.on("requestfailed", lambda req: failures.append((req.url, req.failure)))
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

        def capture(response):
            if response.url.lower().endswith((".woff", ".woff2")):
                font_responses[response.url.rsplit("/", 1)[-1]] = response.status

        page.on("response", capture)
        page.goto(url, wait_until="networkidle")
        result = page.evaluate("""async () => {
          const weights=[300,400,500,600,700];
          for (const w of weights) await document.fonts.load(`${w} 18px Graphik`, 'Graphik');
          await document.fonts.ready;
          return {
            status: document.fonts.status,
            checks: Object.fromEntries(weights.map(w => [w, document.fonts.check(`${w} 18px Graphik`, 'Graphik')])),
            family: getComputedStyle(document.body).fontFamily.split(',')[0].replace(/[\"']/g,'').trim()
          };
        }""")
        page.wait_for_timeout(250)
        browser.close()
finally:
    server.shutdown()
    server.server_close()

if result is None or result["status"] != "loaded" or not all(result["checks"].values()):
    raise SystemExit(f"Graphik FontFaceSet did not load all target weights: {result}")
if result["family"] != "Graphik":
    raise SystemExit(f"computed body font-family is not Graphik: {result['family']}")
missing = EXPECTED.difference(font_responses)
bad = {name: status for name, status in font_responses.items() if name in EXPECTED and status != 200}
if missing or bad:
    raise SystemExit(f"Graphik network contract failed: missing={sorted(missing)} bad={bad}")
font_failures = [item for item in failures if item[0].lower().endswith((".woff", ".woff2"))]
if font_failures:
    raise SystemExit(f"font request failures: {font_failures}")
decode_errors = [msg for msg in console_errors if any(term in msg.lower() for term in ("failed to decode downloaded font", "ots parsing error", "font decode", "font parsing"))]
if decode_errors:
    raise SystemExit(f"Chromium font decode/OTS errors: {decode_errors}")
if any(name.startswith("Graphik") and name.endswith(".woff2") for name in font_responses):
    raise SystemExit(f"superseded Graphik WOFF2 requested: {font_responses}")

print("font-browser-smoke: OK")
