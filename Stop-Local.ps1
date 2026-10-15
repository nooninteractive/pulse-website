param([ValidateRange(1024,65535)][int]$Port=4191)
$pidFile=Join-Path $PSScriptRoot ".local-data\$Port\server.pid"
if(Test-Path -LiteralPath $pidFile){$serverProcessId=[int](Get-Content -LiteralPath $pidFile);$process=Get-CimInstance Win32_Process -Filter "ProcessId=$serverProcessId" -ErrorAction SilentlyContinue;if($process -and $process.Name -eq 'php.exe' -and $process.CommandLine.Contains("127.0.0.1:$Port") -and $process.CommandLine.Contains((Join-Path $PSScriptRoot 'public'))){Stop-Process -Id $serverProcessId};Remove-Item -LiteralPath $pidFile}
