# TVRTKE icon rasterizer for Windows builds. No network, fonts or personal data.
# The bar chart and ascending graph share the WordPress vector visual identity.
param(
    [string]$OutputPath = 'windows/Tvrtke.Desktop/Assets/Tvrtke.ico'
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

function New-IconFrame([int]$size) {
    $bmp = [System.Drawing.Bitmap]::new($size, $size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.ScaleTransform(($size / 256.0), ($size / 256.0))
    $bg = [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml('#092E48'))
    $teal = [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml('#06BDB5'))
    $aqua = [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml('#25E0CE'))
    $blue = [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml('#14587E'))
    $whitePen = [System.Drawing.Pen]::new([System.Drawing.Color]::White, 12)
    $whitePen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $whitePen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $whitePen.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $ring = [System.Drawing.Pen]::new([System.Drawing.Color]::White, 8)
    try {
        $round = [System.Drawing.Drawing2D.GraphicsPath]::new()
        try {
            $round.AddArc(6, 6, 96, 96, 180, 90)
            $round.AddArc(154, 6, 96, 96, 270, 90)
            $round.AddArc(154, 154, 96, 96, 0, 90)
            $round.AddArc(6, 154, 96, 96, 90, 90)
            $round.CloseFigure()
            $g.FillPath($bg, $round)
        } finally { $round.Dispose() }
        $g.FillPolygon($teal, [System.Drawing.PointF[]]@(
            [System.Drawing.PointF]::new(41,200),
            [System.Drawing.PointF]::new(41,133),
            [System.Drawing.PointF]::new(85,103),
            [System.Drawing.PointF]::new(85,200)))
        $g.FillPolygon($aqua, [System.Drawing.PointF[]]@(
            [System.Drawing.PointF]::new(91,200),
            [System.Drawing.PointF]::new(91,94),
            [System.Drawing.PointF]::new(141,54),
            [System.Drawing.PointF]::new(141,200)))
        $g.FillPolygon($blue, [System.Drawing.PointF[]]@(
            [System.Drawing.PointF]::new(147,200),
            [System.Drawing.PointF]::new(147,53),
            [System.Drawing.PointF]::new(191,88),
            [System.Drawing.PointF]::new(191,200)))
        $g.FillPolygon($teal, [System.Drawing.PointF[]]@(
            [System.Drawing.PointF]::new(196,200),
            [System.Drawing.PointF]::new(196,135),
            [System.Drawing.PointF]::new(221,153),
            [System.Drawing.PointF]::new(221,200)))

        $points = [System.Drawing.PointF[]]@(
            [System.Drawing.PointF]::new(51,179),
            [System.Drawing.PointF]::new(101,154),
            [System.Drawing.PointF]::new(142,160),
            [System.Drawing.PointF]::new(199,108))
        $g.DrawLines($whitePen, $points)
        foreach ($p in $points) {
            $g.FillEllipse($teal, ($p.X - 13), ($p.Y - 13), 26, 26)
            $g.DrawEllipse($ring, ($p.X - 13), ($p.Y - 13), 26, 26)
        }
        $bytes = [System.IO.MemoryStream]::new()
        try {
            $bmp.Save($bytes, [System.Drawing.Imaging.ImageFormat]::Png)
            return ,$bytes.ToArray()
        } finally { $bytes.Dispose() }
    } finally {
        $ring.Dispose()
        $whitePen.Dispose()
        $bg.Dispose()
        $teal.Dispose()
        $aqua.Dispose()
        $blue.Dispose()
        $g.Dispose()
        $bmp.Dispose()
    }
}

$sizes = @(16, 24, 32, 48, 64, 128, 256)
$frames = @()
foreach ($size in $sizes) {
    $bytes = [byte[]](New-IconFrame $size)
    if ($bytes.Length -lt 50) { throw "Invalid PNG frame for $size" }
    $frames += ,@{ Width = $size; Bytes = $bytes }
}
$path = [System.IO.Path]::GetFullPath($OutputPath)
[System.IO.Directory]::CreateDirectory([System.IO.Path]::GetDirectoryName($path)) | Out-Null
$stream = [System.IO.File]::Create($path)
$writer = [System.IO.BinaryWriter]::new($stream)
try {
    $writer.Write([uint16]0)
    $writer.Write([uint16]1)
    $writer.Write([uint16]$frames.Count)
    $offset = 6 + 16 * $frames.Count
    foreach ($frame in $frames) {
        $side = if ($frame.Width -eq 256) { 0 } else { $frame.Width }
        $writer.Write([byte]$side)
        $writer.Write([byte]$side)
        $writer.Write([byte]0)
        $writer.Write([byte]0)
        $writer.Write([uint16]1)
        $writer.Write([uint16]32)
        $writer.Write([uint32]$frame.Bytes.Length)
        $writer.Write([uint32]$offset)
        $offset += $frame.Bytes.Length
    }
    foreach ($frame in $frames) { $writer.Write([byte[]]$frame.Bytes) }
} finally {
    $writer.Dispose()
    $stream.Dispose()
}
Write-Host "TVRTKE icon: $path ($((Get-Item $path).Length) bytes; $($sizes.Count) frames)"
