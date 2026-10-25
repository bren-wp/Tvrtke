# UI and metadata regression checks. Structural, not a claim of pixel equality.
$ErrorActionPreference = 'Stop'
function Require([bool]$ok, [string]$why) {
    if (-not $ok) { throw "TVRTKE UI contract: $why" }
}
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$mainPath = Join-Path $root 'windows/Tvrtke.Desktop/MainWindow.xaml'
$favPath = Join-Path $root 'windows/Tvrtke.Desktop/FavoritesWindow.xaml'
$apiPath = Join-Path $root 'windows/Tvrtke.Desktop/ApiSettingsWindow.xaml'
[xml]$main = Get-Content -Raw $mainPath
[xml]$favorites = Get-Content -Raw $favPath
[xml]$settings = Get-Content -Raw $apiPath
foreach ($screen in @($main.Window, $favorites.Window, $settings.Window)) {
    Require ($screen.WindowStartupLocation -in @('CenterScreen','CenterOwner')) "Window must start centered"
    Require ($screen.ResizeMode -match '^CanResize') "All windows must remain resizable"
    Require ([double]$screen.Width -lt 1450) "Never start full-screen at reference 1448px width"
    Require ([double]$screen.MinWidth -le 520) "Small-screen width must be supported"
}
$paths = @($main.SelectNodes("//*[local-name()='Button']//*[local-name()='Path']"))
Require ($paths.Count -ge 9) "Vector icons missing from Windows navigation"
Require ((Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/DisplaySizing.cs')) -match 'MonitorFromWindow') "Per-monitor DPI layout missing"
Require ((Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/MainWindow.xaml.cs')) -match 'DisplaySizing.WorkArea') "Main window must clamp to monitor"
Require ((Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/FavoritesWindow.xaml.cs')) -match 'DisplaySizing.WorkArea') "Favorites must clamp to monitor"
Require ((Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/ApiSettingsWindow.xaml.cs')) -match 'DisplaySizing.WorkArea') "Settings must clamp to monitor"
$project = Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/Tvrtke.Desktop.csproj')
Require ($project.Contains('<Company>Brendigo</Company>')) "Windows Company casing"
Require ($project.Contains('<Authors>Brendigo</Authors>')) "Windows Author casing"
$php = Get-Content -Raw (Join-Path $root 'wordpress/tvrtke-registar/tvrtke-registar.php')
$readme = Get-Content -Raw (Join-Path $root 'wordpress/tvrtke-registar/readme.txt')
Require ($php.Contains(' * Author: Brendigo')) "WordPress Author casing"
Require ($readme.Contains('Contributors: brendigo')) "WordPress Contributors casing"
foreach ($cssFile in @('wordpress/tvrtke-registar/assets/search.css','wordpress/tvrtke-registar/assets/admin.css')) {
    $css = Get-Content -Raw (Join-Path $root $cssFile)
    Require ($css.Contains('@container')) "$cssFile missing embedded-width responsive rules"
    Require ($css.Contains('@media')) "$cssFile missing viewport/accessibility rules"
}
Write-Host 'TVRTKE responsive layout and brand metadata contracts: OK'
