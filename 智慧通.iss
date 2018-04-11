; 脚本由 Inno Setup 脚本向导 生成！
; 有关创建 Inno Setup 脚本文件的详细资料请查阅帮助文档！

#define MyAppName "智慧通打印"
#define MyAppVersion "1.0"
#define MyAppPublisher "智慧通打印"
#define MyAppExeName "智慧通打印.bat"

[Setup]
; 注: AppId的值为单独标识该应用程序。
; 不要为其他安装程序使用相同的AppId值。
; (生成新的GUID，点击 工具|在IDE中生成GUID。)
AppId={{B08D166C-FAA1-49DE-86B9-0BAA95E5D3DD}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
;AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={pf}\{#MyAppName}
DisableProgramGroupPage=yes
OutputDir=C:\智慧通打印
OutputBaseFilename=setup
SetupIconFile=C:\Users\lei\Desktop\print_exe\printer_72px.ico
Compression=lzma
SolidCompression=yes

[Languages]
Name: "chinesesimp"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: checkablealone
Name: "quicklaunchicon"; Description: "{cm:CreateQuickLaunchIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: checkablealone
Name: "startupicon"; Description: "开机启动"; GroupDescription: "{cm:AdditionalIcons}"; 

[Files]
Source: "C:\Users\lei\Desktop\print_exe\智慧通打印.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "C:\Users\lei\Desktop\print_exe\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "C:\Users\lei\Desktop\print_exe\printer_72px.ico"; DestDir: "{app}"; Flags: ignoreversion
; 注意: 不要在任何共享系统文件上使用“Flags: ignoreversion”
; VC Redistribute  
Source: "C:\Users\lei\Desktop\print_exe\vc_redist.x86.exe"; DestDir: "{app}"; Check: NeedInstallVC8SP1

[Icons]
Name: "{commonprograms}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{commondesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon; IconFilename: "{app}\printer_72px.ico"

[Code]  
var  
  vc8SP1Missing: Boolean;  
  
function NeedInstallVC8SP1(): Boolean;  
begin  
  Result := vc8SP1Missing;  
end;  
  
function InitializeSetup(): Boolean;  
begin  
// 这里，不同版本运行环境对应的GUID不同  
  if RegValueExists(HKLM, 'SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\{710f4c1c-cc18-4c49-8cbf-51240c89a1a2}', 'Version') // Microsoft Visual C++ 2005 Redistributable X86 [XP/Win7 32位 V8.0.61001]  
  or RegValueExists(HKLM, 'SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\{ad8a2fa1-06e7-4b0d-927d-6e54b3d31028}', 'Version') // Microsoft Visual C++ 2005 Redistributable X64 [Win7 64位 V8.0.61000]  
  or RegValueExists(HKLM, 'SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\{071C9B48-7C32-4621-A0AC-3F809523288F}', 'Version') // Microsoft Visual C++ 2005 SP1 Redistributable X64 [Win7 64位 V8.0.56336]  
    then  
      begin  
        vc8SP1Missing := false;  
      end  
    else  
      begin  
        vc8SP1Missing := true;  
      end;  
  result := true;  
end;  

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent
Filename: "{tmp}\vc_redist.x86.exe"; Parameters: /q; WorkingDir: {tmp}; Flags: skipifdoesntexist; StatusMsg: "Install Microsoft Visual C++ 2014 Runtime ..."; Check: NeedInstallVC8SP1  
