// Membuat versi web foto dokumentasi: WebP 3 lebar, metadata EXIF/GPS dibuang,
// plus og-image.jpg (1200x630) dari foto gapura untuk pratinjau tautan.
// Pakai: node scripts/optimize-images.mjs [folder-foto-asli]
// Default folder asli: storage/app/dokumentasi-src (tidak ikut git).
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const root = path.resolve(import.meta.dirname, '..');
const dataFile = path.join(root, 'resources/data/dokumentasi.json');
const srcDir = path.resolve(process.argv[2] ?? path.join(root, 'storage/app/dokumentasi-src'));
const outDir = path.join(root, 'public/images/dokumentasi');
const widths = [480, 960, 1600];

const data = JSON.parse(await readFile(dataFile, 'utf8'));
await mkdir(outDir, { recursive: true });

for (const [slug, foto] of Object.entries(data.foto)) {
    const input = path.join(srcDir, foto.sumber);
    if (!existsSync(input)) {
        throw new Error(`Foto asli tidak ditemukan: ${input}`);
    }

    // rotate() menerapkan orientasi EXIF sebelum metadata dibuang.
    const base = sharp(input).rotate();
    const { width, height } = await base.metadata();
    foto.lebar = width;
    foto.tinggi = height;
    foto.ukuran = widths.filter((w) => w <= width);

    for (const w of foto.ukuran) {
        await base.clone().resize({ width: w }).webp({ quality: 76 }).toFile(path.join(outDir, `${slug}-${w}.webp`));
    }
    console.log(`${slug}: ${width}x${height} -> ${foto.ukuran.join(', ')}`);
}

// Pratinjau tautan (WhatsApp, Telegram) masih paling aman memakai JPEG.
await sharp(path.join(srcDir, data.foto.gapura.sumber))
    .rotate()
    .resize({ width: 1200, height: 630, fit: 'cover', position: 'centre' })
    .jpeg({ quality: 80, mozjpeg: true })
    .toFile(path.join(root, 'public/images/og-image.jpg'));

await writeFile(dataFile, JSON.stringify(data, null, 4) + '\n');
