$ErrorActionPreference = "Stop"

Set-Location "c:\Users\User\Desktop\watercolorlK test ERP"

$src = "tmp-google-reviews-scraper-pro/google_reviews.json"
$dst = "tmp-google-reviews-scraper-pro/google_reviews_import.sql"

if (!(Test-Path $src)) { throw "Missing $src" }

function SqlEscape([string]$s) {
  if ($null -eq $s) { return "" }
  $s = $s -replace "\\", "\\\\"
  $s = $s -replace "'", "''"
  return $s
}

function ToDateTimeSql([object]$v) {
  if ($null -eq $v) { return "NULL" }
  $s = [string]$v
  if ([string]::IsNullOrWhiteSpace($s)) { return "NULL" }
  try {
    $dt = [datetimeoffset]::Parse($s)
    return "'" + $dt.ToString("yyyy-MM-dd HH:mm:ss") + "'"
  } catch {
    return "NULL"
  }
}

$json = Get-Content $src -Raw | ConvertFrom-Json
$lines = New-Object System.Collections.Generic.List[string]

$lines.Add("SET NAMES utf8mb4;")
$lines.Add("SET FOREIGN_KEY_CHECKS=0;")
$lines.Add("")
$lines.Add("-- Optional: uncomment next line to replace all rows")
$lines.Add("-- TRUNCATE TABLE google_reviews;")
$lines.Add("")

foreach ($r in $json) {
  $reviewId = SqlEscape([string]$r.review_id)
  if ([string]::IsNullOrWhiteSpace($reviewId)) { continue }

  $placeId = SqlEscape([string]$r.place_id)
  $author = SqlEscape([string]$r.author)

  $rating = 0.0
  try { $rating = [double]$r.rating } catch { $rating = 0.0 }

  $reviewText = ""
  if ($r.PSObject.Properties.Name -contains "description") {
    if ($r.description -is [string]) {
      $reviewText = [string]$r.description
    } elseif ($r.description -and $r.description.PSObject.Properties.Name -contains "en") {
      $reviewText = [string]$r.description.en
    } else {
      $parts = @()
      if ($r.description) {
        foreach ($p in $r.description.PSObject.Properties) {
          if ($p.Value) { $parts += [string]$p.Value }
        }
      }
      $reviewText = ($parts -join " ")
    }
  }
  $reviewText = SqlEscape($reviewText)

  $reviewDateSql = ToDateTimeSql($r.review_date)

  $likes = 0
  try { $likes = [int]$r.likes } catch { $likes = 0 }

  $authorProfileUrl = SqlEscape([string]$r.author_profile_url)
  $profilePictureRemote = SqlEscape([string]$r.profile_picture)

  $localPic = ""
  if ($r.PSObject.Properties.Name -contains "local_profile_picture" -and $r.local_profile_picture) {
    $localPic = "importer/reviews_images/profiles/" + [string]$r.local_profile_picture
  }
  $localPic = SqlEscape($localPic)

  $ownerResponse = ""
  if ($r.PSObject.Properties.Name -contains "owner_responses" -and $r.owner_responses) {
    if ($r.owner_responses.PSObject.Properties.Name -contains "en") {
      if ($r.owner_responses.en -and $r.owner_responses.en.PSObject.Properties.Name -contains "text") {
        $ownerResponse = [string]$r.owner_responses.en.text
      }
    }
  }
  $ownerResponse = SqlEscape($ownerResponse)

  $language = "en"
  if ($r.PSObject.Properties.Name -contains "language" -and $r.language) {
    $language = [string]$r.language
  }
  $language = SqlEscape($language)

  $lines.Add("INSERT INTO google_reviews (")
  $lines.Add("  review_id, place_id, author, rating, review_text, review_date, likes,")
  $lines.Add("  author_profile_url, profile_picture_local_path, profile_picture_remote_url,")
  $lines.Add("  owner_response, language, is_active, imported_at, updated_at")
  $lines.Add(") VALUES (")
  $lines.Add("  '$reviewId', '$placeId', '$author', $rating, '$reviewText', $reviewDateSql, $likes,")
  $lines.Add("  '$authorProfileUrl', '$localPic', '$profilePictureRemote',")
  $lines.Add("  '$ownerResponse', '$language', 1, NOW(), NOW()")
  $lines.Add(") ON DUPLICATE KEY UPDATE")
  $lines.Add("  place_id=VALUES(place_id),")
  $lines.Add("  author=VALUES(author),")
  $lines.Add("  rating=VALUES(rating),")
  $lines.Add("  review_text=VALUES(review_text),")
  $lines.Add("  review_date=VALUES(review_date),")
  $lines.Add("  likes=VALUES(likes),")
  $lines.Add("  author_profile_url=VALUES(author_profile_url),")
  $lines.Add("  profile_picture_local_path=VALUES(profile_picture_local_path),")
  $lines.Add("  profile_picture_remote_url=VALUES(profile_picture_remote_url),")
  $lines.Add("  owner_response=VALUES(owner_response),")
  $lines.Add("  language=VALUES(language),")
  $lines.Add("  is_active=1,")
  $lines.Add("  updated_at=NOW();")
  $lines.Add("")
}

$lines.Add("SET FOREIGN_KEY_CHECKS=1;")

$dstAbs = Join-Path (Get-Location) $dst
[System.IO.File]::WriteAllLines($dstAbs, $lines, [System.Text.UTF8Encoding]::new($false))

$count = ($lines | Where-Object { $_ -like 'INSERT INTO google_reviews*' }).Count
Write-Output "Created $dst"
Write-Output "Rows scripted: $count"
