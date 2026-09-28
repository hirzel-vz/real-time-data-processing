param(
    [string]$TargetDir = "C:\Users\thirz\Documents\projects\wp-h5p-generator",
    [string]$RepoUrl = "https://github.com/Learning4tech/wp-h5p-generator.git"
)

$ErrorActionPreference = "Stop"

$tmp = Join-Path $env:TEMP "wp-h5p-migrate"
if (Test-Path $tmp) { Remove-Item -Recurse -Force $tmp }

Write-Host "Cloning source branch..."
git clone --depth 1 -b vibe/cfa-quiz-json-fix-5fe363 https://github.com/hirzel-vz/real-time-data-processing.git $tmp
if ($LASTEXITCODE -ne 0) { throw "Clone failed" }

$src = Join-Path $tmp "quiz"
New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null

Write-Host "Restructuring files..."
$rootFiles = @(
    "README.md",
    "README_fork.md",
    "AGENTS.md",
    "json2h5p_question_set.py",
    "json2h5p_branching.py",
    "h5p_generation_service.py"
)
foreach ($f in $rootFiles) {
    Copy-Item (Join-Path $src $f) $TargetDir
}

Copy-Item (Join-Path $src "wp-h5p-generator") $TargetDir -Recurse
Copy-Item (Join-Path $src "wp-talim") $TargetDir -Recurse

New-Item -ItemType Directory -Force -Path (Join-Path $TargetDir "examples") | Out-Null
Copy-Item (Join-Path $src "cfa_mock_exam_2_session_1.json") (Join-Path $TargetDir "examples")
Copy-Item (Join-Path $src "Week1_CivicLab_Philadelphia1787.json") (Join-Path $TargetDir "examples")

Write-Host "Initializing git and pushing to $RepoUrl ..."
Set-Location $TargetDir
git init
git add -A
git commit -m "Initial import: JSON-to-H5P converters, Mistral generation service, WordPress plugin, Docker stack"
git branch -M main
git remote add origin $RepoUrl
git push -u origin main
if ($LASTEXITCODE -ne 0) { throw "Push failed - check that you have write access to $RepoUrl" }

Write-Host "Cleaning up..."
Remove-Item -Recurse -Force $tmp

Write-Host ""
Write-Host "Done. Project pushed to $RepoUrl" -ForegroundColor Green
Write-Host "Local copy: $TargetDir"
