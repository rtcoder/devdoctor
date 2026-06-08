#!/usr/bin/env bash

set -euo pipefail

version="${1:?version is required}"
checksum_file="${2:?checksum file is required}"
tap_dir="${RUNNER_TEMP}/homebrew-tap"

sha_for() {
  local asset="${1}"
  local sha

  sha="$(awk -v asset="${asset}" '$2 == asset || $2 == "*" asset { print $1; exit }' "${checksum_file}")"

  if [[ -z "${sha}" ]]; then
    echo "Missing checksum for ${asset}" >&2
    exit 1
  fi

  printf '%s' "${sha}"
}

linux_x64_sha="$(sha_for devdoctor-linux-x64)"
linux_arm64_sha="$(sha_for devdoctor-linux-arm64)"
macos_x64_sha="$(sha_for devdoctor-macos-x64)"
macos_arm64_sha="$(sha_for devdoctor-macos-arm64)"

git clone "https://x-access-token:${HOMEBREW_TAP_TOKEN}@github.com/rtcoder/homebrew-tap.git" "${tap_dir}"
mkdir -p "${tap_dir}/Formula"

cat > "${tap_dir}/Formula/devdoctor.rb" <<EOF
class Devdoctor < Formula
  desc "Developer diagnostics for humans"
  homepage "https://github.com/rtcoder/devdoctor"
  license "MIT"
  version "${version}"

  on_macos do
    if Hardware::CPU.arm?
      url "https://github.com/rtcoder/devdoctor/releases/download/v${version}/devdoctor-macos-arm64"
      sha256 "${macos_arm64_sha}"
    else
      url "https://github.com/rtcoder/devdoctor/releases/download/v${version}/devdoctor-macos-x64"
      sha256 "${macos_x64_sha}"
    end
  end

  on_linux do
    if Hardware::CPU.arm?
      url "https://github.com/rtcoder/devdoctor/releases/download/v${version}/devdoctor-linux-arm64"
      sha256 "${linux_arm64_sha}"
    else
      url "https://github.com/rtcoder/devdoctor/releases/download/v${version}/devdoctor-linux-x64"
      sha256 "${linux_x64_sha}"
    end
  end

  def install
    binary = Dir["devdoctor-*"].first
    chmod 0755, binary
    bin.install binary => "devdoctor"
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
