param(
    [Parameter(Mandatory = $true)]
    [string] $Version,

    [string] $Modules = "env,php,composer,git,docker",

    [string] $Format = "table",

    [string] $Path = ".",

    [string] $ExtraArgs = ""
)

$ErrorActionPreference = "Stop"

if ($Version -notmatch '^v\d+\.\d+\.\d+$') {
    throw "DevDoctor version must be an explicit tag such as v0.12.0."
}

$releaseBase = "https://github.com/rtcoder/devdoctor/releases/download/$Version"
$destination = Join-Path $env:RUNNER_TEMP "devdoctor.phar"
$checksumFile = Join-Path $env:RUNNER_TEMP "devdoctor.phar.sha256"

Invoke-WebRequest -Uri "$releaseBase/devdoctor.phar" -OutFile $destination
Invoke-WebRequest -Uri "$releaseBase/devdoctor.phar.sha256" -OutFile $checksumFile

$expected = ((Get-Content $checksumFile -Raw).Trim() -split '\s+')[0].ToLowerInvariant()
$actual = (Get-FileHash -Path $destination -Algorithm SHA256).Hash.ToLowerInvariant()

if ($expected -ne $actual) {
    throw "DevDoctor PHAR checksum verification failed."
}

$arguments = @(
    $destination
    "ci"
    "--path=$Path"
    "--format=$Format"
    "--modules=$Modules"
)

if ($ExtraArgs.Trim() -ne "") {
    $arguments += $ExtraArgs -split '\s+'
}

& php @arguments
exit $LASTEXITCODE
