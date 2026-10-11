/* B01: serve this synthetic reference only, on loopback. */
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = fs.realpathSync(__dirname);
const port = Number(process.env.CVORTEX_B01_PORT || 8768);
const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json', '.md': 'text/plain', '.png': 'image/png' };
http.createServer((req, res) => {
  if (!['GET', 'HEAD'].includes(req.method)) { res.writeHead(405); return res.end(); }
  let file;
  try {
    const route = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
    file = path.resolve(root, `.${route}`);
    if (fs.existsSync(file) && fs.statSync(file).isDirectory()) file = path.join(file, 'index.html');
    file = fs.realpathSync(file);
    if (!file.startsWith(root + path.sep)) { res.writeHead(403); return res.end(); }
    if (!types[path.extname(file)]) { res.writeHead(404); return res.end(); }
  } catch { res.writeHead(404); return res.end('Not found'); }
  res.setHeader('Content-Type', types[path.extname(file)] + (path.extname(file) === '.png' ? '' : '; charset=utf-8'));
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Content-Security-Policy', "default-src 'self'; connect-src 'none'; font-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'");
  fs.readFile(file, (error, data) => { if (error) { res.writeHead(404); return res.end(); } res.end(req.method === 'HEAD' ? undefined : data); });
}).listen(port, '127.0.0.1', () => console.log(`B01 local reference: http://127.0.0.1:${port}`));
