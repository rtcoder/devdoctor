#!/usr/bin/env bash

set -euo pipefail

version="${1:?version is required}"
sha256="${2:?sha256 is required}"
tap_dir="${RUNNER_TEMP}/homebrew-tap"

git clone "https://x-access-token:${HOMEBREW_TAP_TOKEN}@github.com/rtcoder/homebrew-tap.git" "${tap_dir}"
mkdir -p "${tap_dir}/Formula"

cat > "${tap_dir}/Formula/devdoctor.rb" <<EOF
class Devdoctor < Formula
  desc "Developer diagnostics for humans"
  homepage "https://github.com/rtcoder/devdoctor"
  url "https://github.com/rtcoder/devdoctor/releases/download/v${version}/devdoctor.phar"
  sha256 "${sha256}"
  license "MIT"
  version "${version}"

  depends_on "php"

  def install
    bin.install "devdoctor.phar" => "devdoctor"
  end

  test do
    assert_match version.to_s, shell_output("#{bin}/devdoctor --version")
  end
end
EOF

cd "${tap_dir}"
git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
git add Formula/devdoctor.rb

if git diff --cached --quiet; then
  exit 0
fi

git commit -m "Update DevDoctor to ${version}"
git push origin main
