# Publish to GitHub (origin) and optionally ICDSoft (icdsoft).
#
# Live site: https://chuckneedham.com
# ICDSoft work tree (after server hook setup): /home/chuckneedham/www
# Bare repo: ~/private/chuckneeedham-deploy.git
#
# Usage: .\deploy.ps1
#        .\deploy.ps1 -Message "Update gallery albums"
#        .\deploy.ps1 -SkipCommit          # push only
#        .\deploy.ps1 -GitHubOnly          # do not push to ICDSoft yet
#
# Do NOT push to icdsoft until DEPLOY-ICDSOFT.txt one-time SSH setup is done.

param(
  [string]$Message = "Update site",
  [switch]$SkipCommit,
  [switch]$GitHubOnly
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

function Ensure-Remote([string]$Name, [string]$Url) {
  $existing = git remote get-url $Name 2>$null
  if (-not $existing) {
    git remote add $Name $Url
    Write-Host "Added remote $Name -> $Url"
  }
}

Ensure-Remote "origin" "https://github.com/Quilbilly/chuckneeedham.git"
Ensure-Remote "icdsoft" "chuckneedham@s414.sureserver.com:/home/chuckneedham/private/chuckneeedham-deploy.git"

if (-not $SkipCommit) {
  git add -A
  $status = git status --porcelain
  if ($status) {
    git commit -m $Message
  } else {
    Write-Host "No local changes to commit."
  }
}

git push -u origin HEAD:main

if ($GitHubOnly) {
  Write-Host "Published to GitHub only. Skipping icdsoft (use without -GitHubOnly after server setup)."
  exit 0
}

Write-Host "Pushing to ICDSoft (checks out into /home/chuckneedham/www)..."
git push icdsoft HEAD:main
Write-Host "Published to GitHub and ICDSoft."
