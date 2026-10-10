param([string]$Revision = 'HEAD')
# Mengarsipkan subtree website/ dari commit monorepo menjadi ZIP berawalan web/.

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Push-Location $repoRoot
try {
    $dirty = @(git status --porcelain --untracked-files=all)
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat memeriksa status Git.' }
    if ($dirty.Count) { throw 'Commit atau rapikan perubahan terlebih dahulu; paket harus berasal dari checkout bersih.' }
    $commit = (git rev-parse --verify "${Revision}^{commit}").Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Commit sumber tidak ditemukan.' }
    $tree = (git rev-parse "${commit}:website").Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Folder website/ tidak ada pada commit sumber.' }
    $date = (git show -s --format=%cs $commit).Trim()
    $shortCommit = $commit.Substring(0, 8)
    $name = "RT05_TAKEDA_WEB_FRONTEND_${date}_${shortCommit}"
    $outputDir = Join-Path $repoRoot 'artifacts/releases'
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
    $zipPath = Join-Path $outputDir "$name.zip"
    $manifestPath = Join-Path $outputDir "$name.manifest.json"
    $checksumPath = Join-Path $outputDir "$name.sha256"

    $blobs = @{}
    foreach ($line in @(git ls-tree -r $tree)) {
        if ($line -notmatch '^\d+ blob ([a-f0-9]+)\t(.+)$') { throw "Jenis entry Git tidak didukung: $line" }
        $blobId = $Matches[1]
        $filePath = $Matches[2]
        if ($filePath -match '(^|/)(\.git|node_modules|vendor|artifacts)(/|$)' -or
            ($filePath -match '(^|/)\.env($|\.)' -and $filePath -ne '.env.example') -or
            $filePath -match '\.(sqlite|sqlite3|db)$' -or $filePath -eq 'auth.json') {
            throw "Berkas lokal/rahasia tidak boleh masuk paket: $filePath"
        }
        $blobs[$filePath] = $blobId
    }
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membaca inventaris Git.' }
    git archive --format=zip --prefix=web/ --output=$zipPath $tree
    if ($LASTEXITCODE -ne 0) { throw 'Git archive gagal.' }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    $sha1 = [System.Security.Cryptography.SHA1]::Create()
    $sha256 = [System.Security.Cryptography.SHA256]::Create()
    $files = @()
    try {
        $entries = @($zip.Entries | Where-Object { $_.Name } | Sort-Object FullName)
        if ($entries.Count -ne $blobs.Count) { throw 'Jumlah file arsip berbeda dari Git; periksa export-ignore.' }
        foreach ($entry in $entries) {
            if (-not $entry.FullName.StartsWith('web/')) { throw 'Awalan folder arsip harus web/.' }
            $relative = $entry.FullName.Substring(4)
            if (-not $blobs.ContainsKey($relative)) { throw "Berkas arsip tidak ditemukan di Git: $relative" }
            $stream = $entry.Open()
            $buffer = New-Object System.IO.MemoryStream
            try { $stream.CopyTo($buffer); $bytes = $buffer.ToArray() }
            finally { $stream.Dispose(); $buffer.Dispose() }
            $header = [System.Text.Encoding]::UTF8.GetBytes("blob $($bytes.Length)`0")
            $gitBytes = New-Object byte[] ($header.Length + $bytes.Length)
            [System.Buffer]::BlockCopy($header, 0, $gitBytes, 0, $header.Length)
            [System.Buffer]::BlockCopy($bytes, 0, $gitBytes, $header.Length, $bytes.Length)
            $blobHash = [System.BitConverter]::ToString($sha1.ComputeHash($gitBytes)).Replace('-', '').ToLowerInvariant()
            if ($blobHash -ne $blobs[$relative]) { throw "Isi arsip berbeda dari blob Git: $relative" }
            $fileHash = [System.BitConverter]::ToString($sha256.ComputeHash($bytes)).Replace('-', '').ToLowerInvariant()
            $files += [ordered]@{ path = $entry.FullName; bytes = $bytes.Length; gitBlob = $blobHash; sha256 = $fileHash }
        }
    } finally { $zip.Dispose(); $sha1.Dispose(); $sha256.Dispose() }

    $archiveHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $manifest = [ordered]@{
        project = 'RT 05 Takeda - web frontend'
        repository = 'https://github.com/morrispes5/RT-05-TAKEDA'
        designAccepted = '2026-10-10'
        designBaseline = '0658d5d4351eb6d1409baab5835cdc5e137533db'
        sourceCommit = $commit
        sourcePath = 'website'
        sourceTree = $tree
        archive = "$name.zip"
        archiveSha256 = $archiveHash
        fileCount = $files.Count
        files = $files
    }
    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 6) + "`n", $utf8)
    $manifestHash = (Get-FileHash -LiteralPath $manifestPath -Algorithm SHA256).Hash.ToLowerInvariant()
    [System.IO.File]::WriteAllText($checksumPath, "$archiveHash  $name.zip`n$manifestHash  $name.manifest.json`n", $utf8)
    [ordered]@{ sourceCommit = $commit; filesVerified = $files.Count; zip = $zipPath; manifest = $manifestPath; checksum = $checksumPath; sha256 = $archiveHash } | ConvertTo-Json
} finally { Pop-Location }
