"""Generator 15 avatar keluarga (M17, permintaan pemilik 25 Okt 2026) -> assets/avatars/<kode>.svg.

Gaya datar, warna tema RT05 (navy/kuning/sky). Jalankan dari folder mobile/: `python tool/avatars.py`. Hasil SVG di-commit;
daftar kode harus sama dengan `familyAvatars` (lib/features/household/family_avatar.dart) dan Rules::FAMILY_AVATARS di API.
"""
from pathlib import Path

INK = "#183B50"
NAVY = "#123E65"
YELLOW = "#F2CD5A"
SKIN = ["#F6D2B0", "#E8B98F", "#D39A6A", "#B97A4E"]
GREY = "#C9CED3"
DARK = "#2B2B33"
BROWN = "#5A3A25"


def figure(kind, skin, shirt, hair=DARK, accent=YELLOW):
    """Satu tokoh di koordinat dasar: kepala (60,54) r20, badan sampai y=120."""
    cx, cy = 60, 54
    back, front, extra = [], [], []
    body = f'<path d="M24 124 Q24 86 60 84 Q96 86 96 124 Z" fill="{shirt}"/>'
    neck = f'<rect x="53" y="68" width="14" height="18" rx="6" fill="{skin}"/>'
    ears = f'<circle cx="40" cy="{cy + 2}" r="5" fill="{skin}"/><circle cx="80" cy="{cy + 2}" r="5" fill="{skin}"/>'
    head = f'<circle cx="{cx}" cy="{cy}" r="20" fill="{skin}"/>'
    eyes = f'<circle cx="52.5" cy="{cy + 1}" r="2.4" fill="{INK}"/><circle cx="67.5" cy="{cy + 1}" r="2.4" fill="{INK}"/>'
    smile = f'<path d="M53 {cy + 9} Q60 {cy + 15} 67 {cy + 9}" stroke="{INK}" stroke-width="2.4" fill="none" stroke-linecap="round"/>'
    cheeks = f'<circle cx="47" cy="{cy + 8}" r="3.2" fill="#F08A7E" opacity=".45"/><circle cx="73" cy="{cy + 8}" r="3.2" fill="#F08A7E" opacity=".45"/>'
    collar = f'<path d="M50 86 L60 98 L70 86" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linejoin="round" opacity=".85"/>'
    show_ears = True

    if kind == "ayah":
        front.append(f'<path d="M40 52 Q38 30 60 30 Q82 30 80 52 Q76 40 64 39 Q52 42 40 52 Z" fill="{hair}"/>')
        extra.append(f'<path d="M54 {cy + 4} Q60 {cy + 7} 66 {cy + 4}" stroke="{hair}" stroke-width="3" fill="none" stroke-linecap="round"/>')
    elif kind == "ayah_peci":
        front.append(f'<path d="M41 44 Q41 30 60 30 Q79 30 79 44 Z" fill="{DARK}"/><rect x="40" y="40" width="40" height="5" rx="2" fill="{accent}" opacity=".9"/>')
        extra.append(collar)
    elif kind == "ibu":
        back.append(f'<path d="M36 56 Q34 28 60 28 Q86 28 84 56 L88 86 Q60 96 32 86 Z" fill="{hair}"/>')
        front.append(f'<path d="M40 50 Q42 32 60 32 Q74 32 80 46 Q68 36 56 40 Q46 44 40 50 Z" fill="{hair}"/>')
        extra.append(f'<circle cx="40" cy="{cy + 9}" r="2.2" fill="{accent}"/><circle cx="80" cy="{cy + 9}" r="2.2" fill="{accent}"/>')
    elif kind in ("ibu_hijab", "remaja_hijab", "nenek_hijab"):
        show_ears = False
        back.append(f'<path d="M30 58 Q30 26 60 26 Q90 26 90 58 L96 124 L24 124 Z" fill="{hair}"/>')
        extra.append(f'<path d="M40 60 Q40 34 60 34 Q80 34 80 60" fill="none" stroke="#FFFFFF" stroke-width="2" opacity=".35"/>')
        if kind == "nenek_hijab":
            extra.append(glasses(cy))
    elif kind == "kakek":
        front.append(f'<path d="M40 54 Q38 40 44 36 L46 50 Z M80 54 Q82 40 76 36 L74 50 Z" fill="{GREY}"/>')
        front.append(f'<path d="M46 36 Q60 30 74 36" stroke="{GREY}" stroke-width="3" fill="none" stroke-linecap="round"/>')
        extra.append(glasses(cy))
        extra.append(f'<path d="M51 {cy + 7} Q60 {cy + 3} 69 {cy + 7} Q60 {cy + 10} 51 {cy + 7} Z" fill="{GREY}"/>')
    elif kind == "nenek":
        back.append(f'<circle cx="60" cy="28" r="10" fill="{GREY}"/>')
        front.append(f'<path d="M40 52 Q40 32 60 32 Q80 32 80 52 Q74 40 60 40 Q46 40 40 52 Z" fill="{GREY}"/>')
        extra.append(glasses(cy))
    elif kind == "kakak_laki":
        front.append(f'<path d="M40 50 Q36 28 58 28 Q86 26 80 50 Q78 38 70 36 L72 42 Q60 36 50 40 Q44 42 40 50 Z" fill="{hair}"/>')
        extra.append(collar)
    elif kind == "kakak_perempuan":
        back.append(f'<path d="M78 40 Q100 44 92 76 Q88 60 80 54 Z" fill="{hair}"/>')
        front.append(f'<path d="M40 52 Q38 30 60 30 Q82 30 80 52 Q72 38 58 38 Q46 40 40 52 Z" fill="{hair}"/>')
        extra.append(f'<circle cx="80" cy="42" r="4" fill="{accent}"/>')
    elif kind == "adik_laki":
        front.append(f'<path d="M40 50 Q38 30 60 30 Q82 30 80 50 Q70 40 60 41 Q50 40 40 50 Z" fill="{hair}"/><path d="M60 30 Q64 20 70 24" stroke="{hair}" stroke-width="3" fill="none" stroke-linecap="round"/>')
        extra.append(f'<rect x="38" y="98" width="44" height="6" rx="3" fill="#FFFFFF" opacity=".7"/>')
    elif kind == "adik_perempuan":
        back.append(f'<circle cx="35" cy="48" r="9" fill="{hair}"/><circle cx="85" cy="48" r="9" fill="{hair}"/>')
        front.append(f'<path d="M40 50 Q40 30 60 30 Q80 30 80 50 Q72 40 60 40 Q48 40 40 50 Z" fill="{hair}"/>')
        extra.append(f'<circle cx="38" cy="40" r="3.4" fill="{accent}"/><circle cx="82" cy="40" r="3.4" fill="{accent}"/>')
    elif kind == "bayi":
        front.append(f'<path d="M58 34 Q60 26 66 30 Q62 30 62 35" stroke="{hair}" stroke-width="2.6" fill="none" stroke-linecap="round"/>')
        smile = f'<circle cx="60" cy="{cy + 11}" r="3.6" fill="{accent}"/><circle cx="60" cy="{cy + 11}" r="1.6" fill="#FFFFFF"/>'
        body = f'<path d="M26 124 Q26 88 60 86 Q94 88 94 124 Z" fill="{shirt}"/><circle cx="48" cy="104" r="4" fill="#FFFFFF" opacity=".6"/><circle cx="72" cy="110" r="4" fill="#FFFFFF" opacity=".6"/>'
    parts = [*back, body, neck]
    if show_ears:
        parts.append(ears)
    parts += [head, *front, eyes, smile, cheeks, *extra]
    return "".join(parts)


def glasses(cy):
    return (f'<circle cx="52.5" cy="{cy + 1}" r="5.5" fill="none" stroke="{INK}" stroke-width="1.8"/>'
            f'<circle cx="67.5" cy="{cy + 1}" r="5.5" fill="none" stroke="{INK}" stroke-width="1.8"/>'
            f'<path d="M58 {cy + 1} L62 {cy + 1}" stroke="{INK}" stroke-width="1.8"/>')


def place(svg, x, s, lift=0):
    """Tempatkan tokoh dasar (pusat x=60, dasar y=120) di x dengan skala s."""
    return f'<g transform="translate({x - 60 * s:.1f} {120 - 120 * s - lift:.1f}) scale({s})">{svg}</g>'


def avatar(bg, inner, label):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" role="img" aria-label="{label}">'
            f'<defs><clipPath id="c"><circle cx="60" cy="60" r="60"/></clipPath></defs>'
            f'<g clip-path="url(#c)"><rect width="120" height="120" fill="{bg}"/>'
            f'<circle cx="96" cy="22" r="18" fill="#FFFFFF" opacity=".35"/>{inner}</g></svg>\n')


S = SKIN
AVATARS = [
    ("ayah", "Ayah", "#D9EAF6", figure("ayah", S[1], NAVY)),
    ("ibu", "Ibu", "#FDE9B0", figure("ibu", S[0], "#E07A5F", hair=BROWN)),
    ("ayah_peci", "Ayah berpeci", "#E3F4EA", figure("ayah_peci", S[2], "#1E7A4C")),
    ("ibu_hijab", "Ibu berhijab", "#D9EAF6", figure("ibu_hijab", S[1], NAVY, hair="#2E8B8B")),
    ("kakek", "Kakek", "#FDE9B0", figure("kakek", S[2], "#6B7F8E")),
    ("nenek", "Nenek", "#F7DCD6", figure("nenek", S[0], "#8E5A9B")),
    ("nenek_hijab", "Nenek berhijab", "#E3F4EA", figure("nenek_hijab", S[1], "#6B7F8E", hair="#E8E2D6")),
    ("kakak_laki", "Kakak laki-laki", "#D9EAF6", figure("kakak_laki", S[1], "#E07A5F")),
    ("kakak_perempuan", "Kakak perempuan", "#FDE9B0", figure("kakak_perempuan", S[0], "#2E8B8B", hair=BROWN)),
    ("remaja_hijab", "Kakak berhijab", "#F7DCD6", figure("remaja_hijab", S[2], YELLOW, hair=NAVY)),
    ("adik_laki", "Adik laki-laki", "#E3F4EA", figure("adik_laki", S[3], "#C0392B")),
    ("adik_perempuan", "Adik perempuan", "#D9EAF6", figure("adik_perempuan", S[1], "#E07A5F", hair=DARK, accent="#FFFFFF")),
    ("bayi", "Bayi", "#FDE9B0", figure("bayi", S[0], "#7FB7E0", hair=BROWN, accent="#E07A5F")),
    ("pasangan", "Ayah dan ibu", "#F7DCD6",
     place(figure("ibu_hijab", S[1], "#E07A5F", hair=NAVY), 82, .72) + place(figure("ayah", S[2], NAVY), 38, .76)),
    ("keluarga", "Keluarga", "#D9EAF6",
     place(figure("ayah", S[1], NAVY), 30, .66, lift=4) + place(figure("ibu", S[0], "#E07A5F", hair=BROWN), 90, .64, lift=4)
     + place(figure("adik_perempuan", S[0], YELLOW, accent="#FFFFFF"), 60, .5, lift=-2)),
]

if __name__ == "__main__":
    out = Path(__file__).resolve().parent.parent / "assets" / "avatars"
    out.mkdir(parents=True, exist_ok=True)
    for kode, label, bg, inner in AVATARS:
        (out / f"{kode}.svg").write_text(avatar(bg, inner, label), encoding="utf-8", newline="\n")
    print(len(AVATARS), "avatar ->", out)
