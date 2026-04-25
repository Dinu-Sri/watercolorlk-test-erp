param(
    [Parameter(Mandatory = $true)]
    [string]$Server,

    [Parameter(Mandatory = $true)]
    [string]$Username,

    [Parameter(Mandatory = $true)]
    [string]$RemoteProjectPath,

    [int]$Port = 22,
    [string]$PrivateKeyPath = "",
    [string]$LocalJson = "tmp-google-reviews-scraper-pro/google_reviews.json",
    [string]$LocalImagesDir = "tmp-google-reviews-scraper-pro/review_images"
)

$ErrorActionPreference = "Stop"

function Join-SshTarget {
    param([string]$User, [string]$Host)
    return "$User@$Host"
}

function Get-SshArgs {
    param([int]$PortNumber, [string]$KeyPath)
    $args = @("-P", "$PortNumber")
    if ($KeyPath -ne "") {
        $args += @("-i", $KeyPath)
    }
    return $args
}

$workspace = Split-Path -Parent $PSScriptRoot
$jsonPath = Join-Path $workspace $LocalJson
$imagesPath = Join-Path $workspace $LocalImagesDir

if (-not (Test-Path $jsonPath)) {
    throw "JSON file not found: $jsonPath"
}

if (-not (Test-Path $imagesPath)) {
    throw "Images folder not found: $imagesPath"
}

$target = Join-SshTarget -User $Username -Host $Server
$remoteTmpPath = "$RemoteProjectPath/tmp-google-reviews-scraper-pro"
$remoteImagesPath = "$remoteTmpPath/review_images"

$sshArgs = Get-SshArgs -PortNumber $Port -KeyPath $PrivateKeyPath

Write-Host "Creating remote directories..."
$mkdirCmd = "mkdir -p '$remoteTmpPath' '$remoteImagesPath'"
& ssh @sshArgs $target $mkdirCmd

Write-Host "Uploading google_reviews.json..."
$scpArgsJson = @("-P", "$Port")
if ($PrivateKeyPath -ne "") {
    $scpArgsJson += @("-i", $PrivateKeyPath)
}
$scpArgsJson += @($jsonPath, "$target:$remoteTmpPath/google_reviews.json")
& scp @scpArgsJson

Write-Host "Uploading review_images folder..."
$scpArgsImages = @("-P", "$Port", "-r")
if ($PrivateKeyPath -ne "") {
    $scpArgsImages += @("-i", $PrivateKeyPath)
}
$scpArgsImages += @((Join-Path $imagesPath "*"), "$target:$remoteImagesPath/")
& scp @scpArgsImages

Write-Host "Upload complete."
Write-Host "Next on server:"
Write-Host "  cd $RemoteProjectPath"
Write-Host "  php importer/push-uploaded-reviews.php"
