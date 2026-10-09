import http from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../preview/', import.meta.url));
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.jpg': 'image/jpeg', '.woff2': 'font/woff2' };
export function previewServer() {
    return http.createServer(async (req, res) => {
        try {
            const pathname = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
            const relative = pathname === '/' ? 'index.html' : pathname.replace(/^\//, '');
            let file = path.resolve(root, relative);
            if (!file.startsWith(path.resolve(root) + path.sep)) throw new Error('Invalid path');
            if (!path.extname(file)) file += '.html';
            if (!(await stat(file)).isFile()) throw new Error('Not a file');
            res.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
            res.end(await readFile(file));
        } catch {
            res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(await readFile(path.join(root, '404.html')));
        }
    });
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const port = Number(process.env.PORT || 8123);
    previewServer().listen(port, '127.0.0.1', () => console.log('Static preview: http://127.0.0.1:' + port));
}
