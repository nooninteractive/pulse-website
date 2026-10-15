param([ValidateRange(1024,65535)][int]$Port=4191)
$ErrorActionPreference='Stop'
$php = if ($env:PULSE_PHP) { $env:PULSE_PHP } else { 'php' }
if (-not (Test-Path -LiteralPath $php)) { $command=Get-Command php -ErrorAction SilentlyContinue; if(-not $command){throw 'Instala PHP 8.2+ con pdo_sqlite, mbstring, fileinfo y zip o ajusta la ruta PHP.'};$php=$command.Source }
$extensions=Join-Path (Split-Path $php) 'ext'
$data=Join-Path $PSScriptRoot ".local-data\$Port"; New-Item -ItemType Directory -Force -Path $data | Out-Null
$pidFile=Join-Path $data 'server.pid'
if(Test-Path -LiteralPath $pidFile) {
    $serverProcessId=[int](Get-Content -LiteralPath $pidFile)
    $existing=Get-CimInstance Win32_Process -Filter "ProcessId=$serverProcessId" -ErrorAction SilentlyContinue
    if($existing -and $existing.Name -eq 'php.exe' -and $existing.CommandLine.Contains("127.0.0.1:$Port") -and $existing.CommandLine.Contains((Join-Path $PSScriptRoot 'public'))) { Write-Output "Pulse ya está activo: http://127.0.0.1:$Port/"; return }
}
$env:PULSE_DATA_DIR=$data.Replace('\','/'); $env:PULSE_LOCAL_PORT="$Port"
$options=@('-n','-d',"extension_dir=$extensions",'-d','extension=pdo_sqlite','-d','extension=mbstring','-d','extension=fileinfo','-d','extension=zip','-d',"session.save_path=$data\sessions",'-d','upload_max_filesize=50M','-d','post_max_size=55M')
& $php @options (Join-Path $PSScriptRoot 'local-setup.php')
if($LASTEXITCODE -ne 0){throw 'No se pudo inicializar PHP.'}
$env:PULSE_TEST_CONFIG=(Join-Path $data 'pulse-config.php').Replace('\','/')
$listener=[System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback,$Port)
try{$listener.Start()}finally{$listener.Stop()}
$arguments=@($options)+@('-S',"127.0.0.1:$Port",'-t',(Join-Path $PSScriptRoot 'public'))
$quoted=$arguments|ForEach-Object{'"'+$_.Replace('"','\"')+'"'}
$process=Start-Process -FilePath $php -ArgumentList $quoted -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $data 'server.stdout.log') -RedirectStandardError (Join-Path $data 'server.stderr.log')
Set-Content (Join-Path $data 'server.pid') $process.Id
Write-Output "Pulse local: http://127.0.0.1:$Port/ (PID $($process.Id))"
