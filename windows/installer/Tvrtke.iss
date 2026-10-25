; TVRTKE Windows per-user installer (Inno Setup 6).
; Values supplied by CI via /DAppVersion, /DSourceDir, /DOutputDir.
#ifndef AppVersion
  #define AppVersion "0.4.0"
#endif
#ifndef SourceDir
  #error SourceDir must be supplied by the build workflow.
#endif
#ifndef OutputDir
  #error OutputDir must be supplied by the build workflow.
#endif

[Setup]
AppId={{7B0C5CD7-8381-454C-A212-A1BD359F28AF}
AppName=TVRTKE
AppVersion={#AppVersion}
AppVerName=TVRTKE {#AppVersion}
AppPublisher=Brendigo
AppPublisherURL=https://tvrtke.com.hr
AppSupportURL=https://github.com/bren-wp/Tvrtke/issues
DefaultDirName={autopf}\TVRTKE
DefaultGroupName=TVRTKE
PrivilegesRequired=lowest
OutputDir={#OutputDir}
OutputBaseFilename=TVRTKE-Setup-v{#AppVersion}-win-x64
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
DisableProgramGroupPage=yes
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayIcon={app}\Tvrtke.Desktop.exe
SetupLogging=yes
CloseApplications=yes
RestartApplications=no

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Stvori prečac na radnoj površini"; GroupDescription: "Prečaci:"; Flags: unchecked

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\TVRTKE"; Filename: "{app}\Tvrtke.Desktop.exe"
Name: "{autodesktop}\TVRTKE"; Filename: "{app}\Tvrtke.Desktop.exe"; Tasks: desktopicon

[Run]
Filename: "{app}\Tvrtke.Desktop.exe"; Description: "Pokreni TVRTKE"; Flags: nowait postinstall skipifsilent
