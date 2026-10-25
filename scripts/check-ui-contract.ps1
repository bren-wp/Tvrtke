# UI and metadata regression checks. Structural, not a claim of pixel equality.
$ErrorActionPreference = 'Stop'
function Require([bool]$ok, [string]$why) {
    if (-not $ok) { throw "TVRTKE UI contract: $why" }
}
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$mainPath = Join-Path $root 'windows/Tvrtke.Desktop/MainWindow.xaml'
$favPath = Join-Path $root 'windows/Tvrtke.Desktop/FavoritesWindow.xaml'
$apiPath = Join-Path $root 'windows/Tvrtke.Desktop/ApiSettingsWindow.xaml'
$profilePath = Join-Path $root 'windows/Tvrtke.Desktop/SubjectWindow.xaml'
[xml]$main = Get-Content -Raw $mainPath
[xml]$favorites = Get-Content -Raw $favPath
[xml]$settings = Get-Content -Raw $apiPath
[xml]$profile = Get-Content -Raw $profilePath
foreach ($screen in @($main.Window, $favorites.Window, $settings.Window, $profile.Window)) {
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
Require ((Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/SubjectWindow.xaml.cs')) -match 'DisplaySizing.WorkArea') "Profile must clamp to monitor"
$mainCode = Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/MainWindow.xaml.cs')
Require ($mainCode.Contains('WindowState = WindowState.Normal')) "Must open normally, never maximized"
Require ($mainCode.Contains('Math.Clamp')) "Main window must fit actual monitor work area"
Require ($mainCode.Contains('SearchFilterGrid')) "Responsive search filters must be repositioned"
Require ($mainCode.Contains('ProfileButton.IsEnabled')) "Profile details action is not connected"
Require ($mainCode.Contains('ResultsGrid_MouseDoubleClick')) "Double-click profile action is missing"
$project = Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/Tvrtke.Desktop.csproj')
Require ($project.Contains('<Company>Brendigo</Company>')) "Windows Company casing"
Require ($project.Contains('<Authors>Brendigo</Authors>')) "Windows Author casing"
$php = Get-Content -Raw (Join-Path $root 'wordpress/tvrtke-registar/tvrtke-registar.php')
$readme = Get-Content -Raw (Join-Path $root 'wordpress/tvrtke-registar/readme.txt')
Require ($php.Contains(' * Author: Brendigo')) "WordPress Author casing"
Require ($readme.Contains('Contributors: brendigo')) "WordPress Contributors casing"
Require ((Get-Content -Raw (Join-Path $root 'windows/Tvrtke.Desktop/AssemblyBranding.cs')) -match 'Contributors", "brendigo"') "Windows contributor casing"
Require ((Get-Content -Raw (Join-Path $root 'windows/installer/Tvrtke.iss')) -match 'AppPublisher=Brendigo') "Installer publisher casing"
Require ($php.Contains('data-tvrtke-copy')) "WordPress copy-shortcode action missing"
Require ((Get-Content -Raw (Join-Path $root 'wordpress/tvrtke-registar/assets/admin.js')) -match 'clipboard.writeText') "WordPress copy-shortcode handler missing"
foreach ($cssFile in @('wordpress/tvrtke-registar/assets/search.css','wordpress/tvrtke-registar/assets/admin.css')) {
    $css = Get-Content -Raw (Join-Path $root $cssFile)
    Require ($css.Contains('@container')) "$cssFile missing embedded-width responsive rules"
    Require ($css.Contains('@media')) "$cssFile missing viewport/accessibility rules"
}
Write-Host 'TVRTKE responsive layout and brand metadata contracts: OK'
