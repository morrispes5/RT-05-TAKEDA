#!/usr/bin/env node
// Validasi konfigurasi build mobile RT05 TAKEDA.
// --self-test memeriksa input di proses ini. --plan menulis matriks CI.
// --build-debug hanya untuk CI, dari direktori mobile, dan tidak pernah --release.
// --build-release (M15) hanya workflow rilis: flavor staging/prod HTTPS, build number dari CI,
// kunci dari key.properties yang ditulis workflow; sidik jari sertifikat diverifikasi langkah terpisah.
import { spawnSync } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { appendFileSync, copyFileSync, existsSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

const FLAVORS = Object.freeze(['dev', 'staging', 'prod']);
const RELEASE_FLAVORS = Object.freeze(['staging', 'prod']);
// Sama dengan AppConfig.devHttpHosts dan network security config debug Android.
const DEV_HTTP_HOSTS = new Set(['10.0.2.2', '127.0.0.1']);
const DEFAULTS = Object.freeze({
    dev: 'http://10.0.2.2:8077',
    staging: 'https://takeda-staging.morriz.tech',
    prod: 'https://takeda.morriz.tech',
});

function hasWhitespaceOrControl(value) {
    return /[\u0000-\u001F\u007F]/.test(value) || /\s/u.test(value);
}

function canonicalPort(text) {
    if (!/^[0-9]+$/.test(text)) return null;
    const stripped = text.replace(/^0+/, '') || '0';
    if (stripped.length > 5) return null;
    const port = Number(stripped);
    if (!Number.isInteger(port) || port < 1 || port > 65535) return null;
    return port;
}

function splitAuthority(authority) {
    if (authority.startsWith('[')) {
        const match = /^\[([^\]]+)\](?::([0-9]+))?$/.exec(authority);
        if (!match || match[1].length === 0) return null;
        return { portText: match[2] ?? null };
    }
    const colon = authority.lastIndexOf(':');
    if (colon === -1) return authority.length === 0 ? null : { portText: null };
    const host = authority.slice(0, colon);
    const portText = authority.slice(colon + 1);
    if (host.length === 0 || !/^[0-9]+$/.test(portText)) return { portText: portText.length === 0 ? '' : portText, invalid: true };
    return { portText };
}

// Bentuk mentah wajib http(s)://authority atau dengan satu garis miring akhir.
// Pemeriksaan ini terjadi sebelum URL parser, karena WHATWG menormalisasi
// https:host, userinfo kosong, ?, #, backslash, dan /../ menjadi origin.
function validateBuild(flavor, apiBaseUrl) {
    if (typeof flavor !== 'string' || !FLAVORS.includes(flavor)) return { ok: false, error: 'flavor-invalid' };
    if (typeof apiBaseUrl !== 'string' || apiBaseUrl.length === 0 || apiBaseUrl.trim().length === 0) {
        return { ok: false, error: 'url-empty' };
    }
    if (hasWhitespaceOrControl(apiBaseUrl)) return { ok: false, error: 'url-whitespace' };
    if (apiBaseUrl.includes('\\')) return { ok: false, error: 'url-backslash' };
    if (apiBaseUrl.includes('@')) return { ok: false, error: 'url-userinfo' };
    if (apiBaseUrl.includes('?') || apiBaseUrl.includes('#')) return { ok: false, error: 'url-query-or-fragment' };
    if (!/^https?:\/\//i.test(apiBaseUrl)) {
        if (/^https?:/.test(apiBaseUrl)) return { ok: false, error: 'url-not-origin' };
        if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(apiBaseUrl)) return { ok: false, error: 'url-scheme' };
        return { ok: false, error: 'url-invalid' };
    }
    const raw = /^https?:\/\/([^/?#\\@]+)\/?$/i.exec(apiBaseUrl);
    if (!raw) return { ok: false, error: 'url-not-origin' };
    const authority = splitAuthority(raw[1]);
    if (!authority) return { ok: false, error: 'url-invalid' };
    if (authority.invalid || authority.portText === '') return { ok: false, error: 'url-port' };
    if (authority.portText !== null && canonicalPort(authority.portText) === null) return { ok: false, error: 'url-port' };
    let url;
    try {
        url = new URL(apiBaseUrl);
    } catch {
        return { ok: false, error: 'url-invalid' };
    }
    if (url.username !== '' || url.password !== '') return { ok: false, error: 'url-userinfo' };
    if (url.search !== '' || url.hash !== '') return { ok: false, error: 'url-query-or-fragment' };
    if (url.pathname !== '/') return { ok: false, error: 'url-not-origin' };
    if (url.protocol === 'http:') {
        // WHATWG menormalisasi alias numerik (127.1, 0x7f000001) menjadi localhost;
        // host dev mentah tetap harus sama dengan allowlist Android/Dart.
        if (flavor !== 'dev' || !DEV_HTTP_HOSTS.has(raw[1].split(':')[0])) return { ok: false, error: 'url-http-not-allowed' };
    } else if (url.protocol !== 'https:') {
        return { ok: false, error: 'url-scheme' };
    }
    if (url.hostname.length === 0 || url.origin === 'null') return { ok: false, error: 'url-invalid' };
    return { ok: true, flavor, apiBaseUrl: url.origin };
}

function planBuilds(env) {
    const event = env.RT05_EVENT || '';
    if (event === 'pull_request' || event === 'push') {
        const builds = [
            { flavor: 'dev', apiBaseUrl: DEFAULTS.dev },
            { flavor: 'staging', apiBaseUrl: DEFAULTS.staging },
        ];
        for (const build of builds) {
            const checked = validateBuild(build.flavor, build.apiBaseUrl);
            if (!checked.ok) return checked;
        }
        return { ok: true, builds };
    }
    if (event !== 'workflow_dispatch') return { ok: false, error: 'event-invalid' };
    const flavor = env.RT05_FLAVOR ?? '';
    const raw = env.RT05_API_BASE_URL ?? '';
    if (!FLAVORS.includes(flavor)) return { ok: false, error: 'flavor-invalid' };
    return validatePlan(flavor, raw === '' ? DEFAULTS[flavor] : raw);
}

function validatePlan(flavor, apiBaseUrl) {
    const checked = validateBuild(flavor, apiBaseUrl);
    if (!checked.ok) return checked;
    return { ok: true, builds: [{ flavor: checked.flavor, apiBaseUrl: checked.apiBaseUrl }] };
}

// Firebase M10: nilai klien Android (bukan secret server) dari GitHub repository variables.
// Semua atau tidak sama sekali; format diperiksa agar nilai salah tempel tidak masuk APK.
const FIREBASE = {
    FIREBASE_API_KEY: /^AIza[0-9A-Za-z_-]{35}$/,
    FIREBASE_APP_ID: /^1:[0-9]{6,15}:android:[0-9a-f]{8,40}$/,
    FIREBASE_SENDER_ID: /^[0-9]{6,15}$/,
    FIREBASE_PROJECT_ID: /^[a-z][a-z0-9-]{4,28}[a-z0-9]$/,
};

function firebaseDefines(env) {
    const values = Object.keys(FIREBASE).map((k) => [k, env[`RT05_${k}`] ?? '']);
    const filled = values.filter(([, v]) => v !== '');
    if (filled.length === 0) return { ok: true, defines: [] };
    if (filled.length !== values.length) return { ok: false, error: 'firebase-incomplete' };
    const bad = values.find(([k, v]) => !FIREBASE[k].test(v));
    if (bad) return { ok: false, error: `firebase-invalid:${bad[0]}` };
    return { ok: true, defines: values.map(([k, v]) => `--dart-define=${k}=${v}`) };
}

function debugArgs(checked, firebase = []) {
    return [
        'build',
        'apk',
        '--debug',
        `--dart-define=API_BASE_URL=${checked.apiBaseUrl}`,
        `--dart-define=FLAVOR=${checked.flavor}`,
        ...firebase,
    ];
}

// Build number Android (versionCode) harus naik tiap rilis agar APK baru bisa memperbarui yang lama.
function validateRelease(flavor, apiBaseUrl, buildNumber) {
    if (!RELEASE_FLAVORS.includes(flavor)) return { ok: false, error: 'release-flavor' };
    const checked = validateBuild(flavor, apiBaseUrl);
    if (!checked.ok) return checked;
    if (!/^[1-9][0-9]{0,8}$/.test(buildNumber ?? '') || Number(buildNumber) > 2100000000) return { ok: false, error: 'release-build-number' };
    return { ...checked, buildNumber };
}

function releaseArgs(checked, firebase = []) {
    return [
        'build',
        'apk',
        '--release',
        // APK per arsitektur: arm64 (hampir semua HP warga) dan armeabi-v7a (HP lama); x86_64 hanya emulator.
        // Flutter menambah 1000×ABI pada versionCode (arm64 = 2000 + build), tetap naik tiap rilis.
        '--split-per-abi',
        '--target-platform=android-arm,android-arm64',
        `--build-number=${checked.buildNumber}`,
        `--dart-define=API_BASE_URL=${checked.apiBaseUrl}`,
        `--dart-define=FLAVOR=${checked.flavor}`,
        ...firebase,
    ];
}

// Sidik jari SHA-256 sertifikat penanda tangan (format apksigner/keytool, dengan atau tanpa titik dua).
function normalizeCertDigest(value) {
    const hex = (value ?? '').replace(/:/g, '').trim().toLowerCase();
    return /^[0-9a-f]{64}$/.test(hex) ? hex : null;
}

function emitBuilds(builds, outputFile = process.env.GITHUB_OUTPUT) {
    const json = JSON.stringify(builds);
    if (!outputFile) {
        process.stdout.write(`${json}\n`);
        return json;
    }
    const delim = `RT05_${randomBytes(8).toString('hex')}`;
    appendFileSync(outputFile, `builds<<${delim}\n${json}\n${delim}\n`);
    return json;
}

function expect(failures, condition, label) {
    if (!condition) failures.push(label);
}

function selfTest() {
    const failures = [];
    const check = (flavor, url, error, normalized) => {
        const result = validateBuild(flavor, url);
        const id = failures.length;
        if (error) {
            expect(failures, !result.ok && result.error === error, `case-${id}-${error}-got-${result.ok ? 'ok' : result.error}`);
            return;
        }
        expect(
            failures,
            result.ok && result.flavor === flavor && result.apiBaseUrl === (normalized ?? url),
            `case-${id}-normalize`,
        );
    };

    check('dev', DEFAULTS.dev);
    check('dev', 'http://127.0.0.1:8077');
    check('dev', 'http://127.1:8077', 'url-http-not-allowed');
    check('dev', 'http://0x7f000001:8077', 'url-http-not-allowed');
    check('staging', 'HTTPS://takeda-staging.morriz.tech:443/', null, DEFAULTS.staging);
    check('staging', DEFAULTS.staging);
    check('prod', DEFAULTS.prod);
    check('dev', DEFAULTS.staging);
    check('staging', 'http://takeda-staging.morriz.tech', 'url-http-not-allowed');
    check('dev', 'http://example.com', 'url-http-not-allowed');
    check('dev', 'http://localhost:8077', 'url-http-not-allowed');
    check('dev', 'http://[::1]:8077', 'url-http-not-allowed');
    check('nope', DEFAULTS.staging, 'flavor-invalid');
    check('DEV', DEFAULTS.dev, 'flavor-invalid');
    check(' dev', DEFAULTS.dev, 'flavor-invalid');
    check('staging', 'https://sample:sample@takeda-staging.morriz.tech', 'url-userinfo');
    check('staging', 'https://takeda-staging.morriz.tech?x=1', 'url-query-or-fragment');
    check('staging', 'https://takeda-staging.morriz.tech#f', 'url-query-or-fragment');
    check('dev', '', 'url-empty');
    check('dev', '   ', 'url-empty');
    check('dev', 'http://10.0.2.2:8077\n', 'url-whitespace');
    check('staging', 'https://takeda-staging.morriz.tech /', 'url-whitespace');
    check('staging', 'https://takeda-staging.morriz.tech\u0001', 'url-whitespace');
    check('staging', 'https://takeda-staging.morriz.tech/api', 'url-not-origin');
    check('staging', 'https://takeda-staging.morriz.tech/', null, DEFAULTS.staging);
    check('staging', 'https://takeda-staging.morriz.tech:443', null, DEFAULTS.staging);
    check('staging', 'https://takeda-staging.morriz.tech:443/', null, DEFAULTS.staging);
    check('staging', 'https://takeda-staging.morriz.tech:8443', null, 'https://takeda-staging.morriz.tech:8443');
    check('staging', 'https://takeda-staging.morriz.tech:8443/', null, 'https://takeda-staging.morriz.tech:8443');
    check('staging', 'https://takeda-staging.morriz.tech:65535', null, 'https://takeda-staging.morriz.tech:65535');
    check('dev', 'http://10.0.2.2:8077/', null, DEFAULTS.dev);
    check('dev', 'http://127.0.0.1:80', null, 'http://127.0.0.1');
    check('prod', 'https://takeda.morriz.tech:443', null, DEFAULTS.prod);
    check('staging', 'https://takeda-staging.morriz.tech:0', 'url-port');
    check('staging', 'https://takeda-staging.morriz.tech:65536', 'url-port');
    check('staging', 'https://takeda-staging.morriz.tech:99999', 'url-port');
    check('staging', 'https://takeda-staging.morriz.tech?', 'url-query-or-fragment');
    check('staging', 'https://takeda-staging.morriz.tech#', 'url-query-or-fragment');
    check('staging', 'https://@takeda-staging.morriz.tech', 'url-userinfo');
    check('staging', 'https://:@takeda-staging.morriz.tech', 'url-userinfo');
    check('staging', 'https://takeda-staging.morriz.tech\\a', 'url-backslash');
    check('staging', 'https:\\\\takeda-staging.morriz.tech', 'url-backslash');
    check('staging', 'https://takeda-staging.morriz.tech/../', 'url-not-origin');
    check('staging', 'https://takeda-staging.morriz.tech/..', 'url-not-origin');
    check('staging', 'https:takeda-staging.morriz.tech', 'url-not-origin');
    check('staging', 'https:/takeda-staging.morriz.tech', 'url-not-origin');
    check('prod', 'http://10.0.2.2:8077', 'url-http-not-allowed');
    check('dev', 'ftp://10.0.2.2:8077', 'url-scheme');

    const ignored = planBuilds({ RT05_EVENT: 'pull_request', RT05_FLAVOR: 'prod', RT05_API_BASE_URL: 'http://example.com' });
    expect(failures, ignored.ok && ignored.builds.length === 2, 'pr-count');
    expect(failures, ignored.builds?.[0]?.flavor === 'dev' && ignored.builds?.[0]?.apiBaseUrl === DEFAULTS.dev, 'pr-dev');
    expect(failures, ignored.builds?.[1]?.flavor === 'staging' && ignored.builds?.[1]?.apiBaseUrl === DEFAULTS.staging, 'pr-staging');
    const pushed = planBuilds({ RT05_EVENT: 'push', RT05_FLAVOR: 'nope', RT05_API_BASE_URL: '' });
    expect(failures, JSON.stringify(pushed.builds) === JSON.stringify(ignored.builds), 'push-same');

    const stagingDefault = planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'staging', RT05_API_BASE_URL: '' });
    expect(failures, stagingDefault.ok && stagingDefault.builds.length === 1 && stagingDefault.builds[0].apiBaseUrl === DEFAULTS.staging, 'dispatch-staging');
    const prodDefault = planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'prod', RT05_API_BASE_URL: '' });
    expect(failures, prodDefault.ok && prodDefault.builds[0].flavor === 'prod' && prodDefault.builds[0].apiBaseUrl === DEFAULTS.prod, 'dispatch-prod');
    const slashDispatch = planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'staging', RT05_API_BASE_URL: `${DEFAULTS.staging}/` });
    expect(failures, slashDispatch.ok && slashDispatch.builds[0].apiBaseUrl === DEFAULTS.staging, 'dispatch-slash');
    const portDispatch = planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'staging', RT05_API_BASE_URL: `${DEFAULTS.staging}:443` });
    expect(failures, portDispatch.ok && portDispatch.builds[0].apiBaseUrl === DEFAULTS.staging, 'dispatch-port-443');
    expect(failures, planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'staging', RT05_API_BASE_URL: `${DEFAULTS.staging}:0` }).error === 'url-port', 'dispatch-port-0');
    expect(failures, planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'staging', RT05_API_BASE_URL: 'https:takeda-staging.morriz.tech' }).error === 'url-not-origin', 'dispatch-scheme-slash');
    expect(failures, planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'staging', RT05_API_BASE_URL: 'http://takeda-staging.morriz.tech' }).error === 'url-http-not-allowed', 'dispatch-http');
    expect(failures, planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'nope', RT05_API_BASE_URL: DEFAULTS.staging }).error === 'flavor-invalid', 'dispatch-flavor');
    expect(failures, planBuilds({ RT05_EVENT: 'workflow_dispatch', RT05_FLAVOR: 'dev', RT05_API_BASE_URL: 'http://example.com' }).error === 'url-http-not-allowed', 'dispatch-dev-public-http');
    const userinfo = planBuilds({
        RT05_EVENT: 'workflow_dispatch',
        RT05_FLAVOR: 'staging',
        RT05_API_BASE_URL: 'https://sample:sample@takeda-staging.morriz.tech',
    });
    expect(failures, userinfo.error === 'url-userinfo' && !JSON.stringify(userinfo).includes('sample:sample'), 'dispatch-userinfo');
    expect(failures, planBuilds({ RT05_EVENT: 'schedule' }).error === 'event-invalid', 'event');

    const stagingArgs = debugArgs(validateBuild('staging', `${DEFAULTS.staging}:443/`));
    expect(failures, stagingArgs.includes('--debug') && !stagingArgs.includes('--release'), 'args-debug');
    expect(failures, stagingArgs.includes(`--dart-define=FLAVOR=staging`) && stagingArgs.includes(`--dart-define=API_BASE_URL=${DEFAULTS.staging}`), 'args-flavor');
    expect(failures, validateBuild('staging', 'http://takeda-staging.morriz.tech').ok === false, 'args-withheld');
    const fb = {
        RT05_FIREBASE_API_KEY: `AIza${'A'.repeat(35)}`,
        RT05_FIREBASE_APP_ID: '1:123456789012:android:0123456789abcdef',
        RT05_FIREBASE_SENDER_ID: '123456789012',
        RT05_FIREBASE_PROJECT_ID: 'rt05-takeda-uji',
    };
    expect(failures, firebaseDefines({}).ok && firebaseDefines({}).defines.length === 0, 'firebase-none');
    expect(failures, firebaseDefines(fb).defines?.length === 4 && debugArgs(validateBuild('staging', DEFAULTS.staging), firebaseDefines(fb).defines).includes('--dart-define=FIREBASE_PROJECT_ID=rt05-takeda-uji'), 'firebase-all');
    expect(failures, firebaseDefines({ RT05_FIREBASE_API_KEY: fb.RT05_FIREBASE_API_KEY }).error === 'firebase-incomplete', 'firebase-partial');
    expect(failures, firebaseDefines({ ...fb, RT05_FIREBASE_PROJECT_ID: 'Bad Project; rm' }).error === 'firebase-invalid:FIREBASE_PROJECT_ID', 'firebase-invalid');

    expect(failures, validateRelease('dev', DEFAULTS.dev, '5').error === 'release-flavor', 'release-dev');
    expect(failures, validateRelease('staging', 'http://takeda-staging.morriz.tech', '5').error === 'url-http-not-allowed', 'release-http');
    expect(failures, validateRelease('staging', DEFAULTS.staging, '0').error === 'release-build-number', 'release-build-0');
    expect(failures, validateRelease('staging', DEFAULTS.staging, '12a').error === 'release-build-number', 'release-build-text');
    expect(failures, validateRelease('staging', DEFAULTS.staging, '').error === 'release-build-number', 'release-build-empty');
    const rel = validateRelease('prod', `${DEFAULTS.prod}/`, '42');
    const relArgs = rel.ok ? releaseArgs(rel, firebaseDefines(fb).defines) : [];
    expect(failures, relArgs.includes('--release') && !relArgs.includes('--debug') && relArgs.includes('--build-number=42') && relArgs.includes(`--dart-define=API_BASE_URL=${DEFAULTS.prod}`), 'release-args');
    expect(failures, relArgs.includes('--split-per-abi') && relArgs.includes('--target-platform=android-arm,android-arm64'), 'release-split');
    const digest = 'AB:'.repeat(31) + 'AB';
    expect(failures, normalizeCertDigest(digest) === 'ab'.repeat(32) && normalizeCertDigest('ab'.repeat(32)) === 'ab'.repeat(32), 'cert-normalize');
    expect(failures, normalizeCertDigest('ab'.repeat(31)) === null && normalizeCertDigest('') === null, 'cert-invalid');

    const output = path.join(tmpdir(), `rt05-mobile-plan-${process.pid}.txt`);
    try {
        emitBuilds(ignored.builds, output);
        const text = readFileSync(output, 'utf8');
        expect(failures, text.startsWith('builds<<RT05_') && text.includes(JSON.stringify(ignored.builds)), 'github-output');
    } finally {
        if (existsSync(output)) unlinkSync(output);
    }

    if (failures.length > 0) {
        console.error(`self-test gagal: ${failures.join(', ')}`);
        return false;
    }
    console.log('self-test OK: pemeriksaan input build debug dan rilis');
    return true;
}

function runPlan() {
    const result = planBuilds(process.env);
    if (!result.ok) {
        console.error(`konfigurasi build ditolak: ${result.error}`);
        process.exit(1);
    }
    emitBuilds(result.builds);
}

function buildDebug() {
    if (!existsSync('pubspec.yaml')) {
        console.error('--build-debug dijalankan dari direktori mobile');
        process.exit(1);
    }
    const result = validateBuild(process.env.RT05_FLAVOR ?? '', process.env.RT05_API_BASE_URL ?? '');
    if (!result.ok) {
        console.error(`konfigurasi build ditolak: ${result.error}`);
        process.exit(1);
    }
    const firebase = firebaseDefines(process.env);
    if (!firebase.ok) {
        console.error(`konfigurasi Firebase ditolak: ${firebase.error}`);
        process.exit(1);
    }
    if (firebase.defines.length === 0) console.log('Firebase tidak dikonfigurasi: APK berjalan tanpa push (inbox saja).');
    const args = debugArgs(result, firebase.defines);
    if (args.includes('--release') || !args.includes(`--dart-define=FLAVOR=${result.flavor}`)) {
        console.error('argumen build tidak konsisten');
        process.exit(1);
    }
    const proc = spawnSync('flutter', args, { stdio: 'inherit', shell: false });
    if (proc.error || proc.status !== 0) {
        if (proc.error) console.error('flutter tidak dapat dijalankan');
        process.exit(proc.status || 1);
    }
    const outDir = path.join('build', 'app', 'outputs', 'flutter-apk');
    const named = path.join(outDir, `rt05-takeda-${result.flavor}-debug.apk`);
    const apk = path.join(outDir, 'app-debug.apk');
    if (!existsSync(apk)) {
        console.error('apk debug tidak ditemukan');
        process.exit(1);
    }
    copyFileSync(apk, named);
    const hash = createHash('sha256').update(readFileSync(named)).digest('hex');
    writeFileSync(`${named}.sha256`, `${hash}  ${path.basename(named)}\n`);
}

function buildRelease() {
    if (!existsSync('pubspec.yaml')) {
        console.error('--build-release dijalankan dari direktori mobile');
        process.exit(1);
    }
    const result = validateRelease(process.env.RT05_FLAVOR ?? '', process.env.RT05_API_BASE_URL ?? '', process.env.RT05_BUILD_NUMBER ?? '');
    if (!result.ok) {
        console.error(`konfigurasi rilis ditolak: ${result.error}`);
        process.exit(1);
    }
    if (!existsSync(path.join('android', 'key.properties'))) {
        console.error('key.properties tidak ada: rilis tanpa kunci tetap ditolak');
        process.exit(1);
    }
    const firebase = firebaseDefines(process.env);
    if (!firebase.ok || firebase.defines.length === 0) {
        console.error(`rilis memerlukan konfigurasi Firebase lengkap: ${firebase.error ?? 'kosong'}`);
        process.exit(1);
    }
    const proc = spawnSync('flutter', releaseArgs(result, firebase.defines), { stdio: 'inherit', shell: false });
    if (proc.error || proc.status !== 0) {
        if (proc.error) console.error('flutter tidak dapat dijalankan');
        process.exit(proc.status || 1);
    }
    const outDir = path.join('build', 'app', 'outputs', 'flutter-apk');
    for (const abi of ['arm64-v8a', 'armeabi-v7a']) {
        const apk = path.join(outDir, `app-${abi}-release.apk`);
        if (!existsSync(apk)) {
            console.error(`apk rilis ${abi} tidak ditemukan`);
            process.exit(1);
        }
        const named = path.join(outDir, `rt05-takeda-${result.flavor}-${result.buildNumber}-${abi}.apk`);
        copyFileSync(apk, named);
        const hash = createHash('sha256').update(readFileSync(named)).digest('hex');
        writeFileSync(`${named}.sha256`, `${hash}  ${path.basename(named)}\n`);
        console.log(`APK rilis: ${path.basename(named)} sha256 ${hash}`);
    }
}

// Bandingkan sertifikat APK (keluaran `apksigner verify --print-certs`, via stdin) dengan sidik jari yang
// disimpan sebagai repository variable. Satu penanda tangan, harus sama persis.
function verifyCert() {
    const expected = normalizeCertDigest(process.env.RT05_RELEASE_CERT_SHA256);
    if (!expected) {
        console.error('RT05_RELEASE_CERT_SHA256 kosong atau salah format');
        process.exit(1);
    }
    const text = readFileSync(0, 'utf8');
    const found = [...text.matchAll(/certificate SHA-256 digest:\s*([0-9a-fA-F:]+)/g)].map((m) => normalizeCertDigest(m[1]));
    if (found.length !== 1 || found[0] !== expected) {
        console.error(`sertifikat APK tidak sesuai kunci rilis (ditemukan ${found.length} penanda tangan)`);
        process.exit(1);
    }
    console.log('sertifikat APK = kunci rilis tetap');
}

const command = process.argv[2];
if (command === '--self-test') process.exit(selfTest() ? 0 : 1);
if (command === '--plan') runPlan();
else if (command === '--build-debug') buildDebug();
else if (command === '--build-release') buildRelease();
else if (command === '--verify-cert') verifyCert();
else {
    console.error('pemakaian: --self-test | --plan | --build-debug | --build-release | --verify-cert');
    process.exit(1);
}
