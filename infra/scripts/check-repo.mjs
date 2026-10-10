// Pemeriksaan kebersihan monorepo tanpa dependency: file terlarang, pola secret,
// gitlink/.git bersarang, tautan relatif Markdown, dan paritas konfigurasi Vercel.
// Jalankan dari mana saja: node infra/scripts/check-repo.mjs
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
// Berkas terlacak ditambah berkas baru yang belum di-add (tetap menghormati .gitignore).
const files = git('ls-files', '-z', '--cached', '--others', '--exclude-standard').split('\0')
    .filter(f => f && existsSync(path.join(root, f)));
const tracked = new Set(files);
const trackedDirs = new Set(files.flatMap(f => f.split('/').slice(0, -1).map((_, i, a) => a.slice(0, i + 1).join('/'))));
const failures = [];
const fail = (check, detail) => failures.push(`[${check}] ${detail}`);

// 1. File yang tidak boleh masuk Git.
const forbidden = [
    [/(^|\/)\.env(\.[^/]+)?$/, f => !f.endsWith('.env.example')],
    [/(^|\/)(node_modules|vendor)\//],
    [/\.(pem|p12|pfx|jks|keystore|sqlite|sqlite3|db|dump)$/i],
    [/(^|\/)storage\/[^/]*\.key$/],
    [/(^|\/)(key\.properties|auth\.json|google-services\.json|GoogleService-Info\.plist)$/],
    [/service[-_]?account[^/]*\.json$/i],
    // Dump/backup SQL dilarang; SQL skema/role sumber di infra/sql dan init Postgres lokal diizinkan.
    [/\.sql(\.gz)?$/i, f => !/^infra\/(sql|docker\/postgres\/init)\/[^/]+\.sql$/.test(f)],
];
for (const f of files) {
    for (const [pattern, extra] of forbidden) {
        if (pattern.test(f) && (!extra || extra(f))) fail('forbidden-file', f);
    }
}

// 2. Pola secret pada file teks.
const secretPatterns = [
    ['private-key', /-----BEGIN (RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/],
    // DSN berpassword ke host mana pun selain Postgres lokal/CI (127.0.0.1, localhost, service "postgres").
    ['postgres-dsn-with-password', /postgres(ql)?:\/\/[^\s:@/]+:[^\s@/]{6,}@(?!(127\.0\.0\.1|localhost|postgres)[:/])[^\s]+/i],
    ['neon-password', /\bnpg_[A-Za-z0-9]{12,}/],
    ['github-token', /\b(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{30,}/],
    ['aws-access-key', /\bAKIA[0-9A-Z]{16}\b/],
    ['openai-like-key', /\bsk-[A-Za-z0-9_-]{32,}/],
    ['laravel-app-key', /APP_KEY=base64:[A-Za-z0-9+/=]{20,}/],
    ['vercel-token', /\bvercel_[A-Za-z0-9]{20,}/i],
];
const binary = /\.(png|jpe?g|webp|gif|ico|woff2?|ttf|otf|zip|gz|pdf|mp4|apk|aab)$/i;
for (const f of files) {
    if (binary.test(f)) continue;
    const full = path.join(root, f);
    if (!existsSync(full) || statSync(full).size > 2 * 1024 * 1024) continue;
    const text = readFileSync(full, 'utf8');
    for (const [name, pattern] of secretPatterns) {
        if (pattern.test(text)) fail('secret-pattern', `${f}: ${name}`);
    }
}

// 3. Tidak ada submodule/gitlink atau .git bersarang.
for (const line of git('ls-files', '-s').split('\n')) {
    if (line.startsWith('160000 ')) fail('nested-git', `gitlink ${line.split('\t')[1]}`);
}
const skipDirs = new Set(['.git', 'node_modules', 'vendor', 'build', '.dart_tool', 'artifacts']);
const walk = dir => {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        if (!entry.isDirectory()) continue;
        const full = path.join(dir, entry.name);
        if (entry.name === '.git' && dir !== root) fail('nested-git', path.relative(root, full));
        if (!skipDirs.has(entry.name)) walk(full);
    }
};
walk(root);

// 4. Tautan relatif Markdown menunjuk berkas/folder yang dilacak Git.
const linkPattern = /\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g;
for (const f of files.filter(f => f.endsWith('.md'))) {
    const text = readFileSync(path.join(root, f), 'utf8').replace(/```[\s\S]*?```/g, '');
    for (const [, raw] of text.matchAll(linkPattern)) {
        if (/^([a-z][a-z0-9+.-]*:|#|\/)/i.test(raw)) continue;
        const target = decodeURIComponent(raw.split('#')[0]);
        if (!target) continue;
        const resolved = path.posix.normalize(path.posix.join(path.posix.dirname(f), target)).replace(/\/$/, '');
        if (!tracked.has(resolved) && !trackedDirs.has(resolved)) fail('broken-link', `${f} -> ${raw}`);
    }
}

// 5. vercel.json root (jembatan transisi) harus setara dengan website/vercel.json.
const rootVercel = path.join(root, 'vercel.json');
const webVercel = path.join(root, 'website/vercel.json');
if (existsSync(rootVercel) && existsSync(webVercel)) {
    const a = JSON.parse(readFileSync(rootVercel, 'utf8'));
    const b = JSON.parse(readFileSync(webVercel, 'utf8'));
    if (a.outputDirectory !== 'website/preview') fail('vercel', 'root vercel.json outputDirectory harus website/preview');
    if (b.outputDirectory !== 'preview') fail('vercel', 'website/vercel.json outputDirectory harus preview');
    const strip = ({ outputDirectory, ...rest }) => JSON.stringify(rest);
    if (strip(a) !== strip(b)) fail('vercel', 'vercel.json root dan website/ berbeda selain outputDirectory');
} else if (!existsSync(webVercel)) {
    fail('vercel', 'website/vercel.json tidak ditemukan');
}

if (failures.length) {
    console.error(failures.join('\n'));
    console.error(`\n${failures.length} masalah ditemukan pada ${files.length} berkas.`);
    process.exit(1);
}
console.log(`OK: ${files.length} berkas dilacak; tanpa file terlarang, pola secret, .git bersarang, atau tautan relatif rusak; vercel.json setara.`);
