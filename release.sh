#!/usr/bin/env bash
# Publie une nouvelle version : vérifie les numéros, crée le commit et le tag, pousse sur GitHub.
# L'action « Publication » construit alors le zip et crée la Release.
#
#   ./release.sh            -> utilise la version de composer.json
set -euo pipefail

VERSION=$(php -r 'echo json_decode(file_get_contents("composer.json"))->version;')
VJSON=$(php -r '$j = json_decode(file_get_contents("version.json"), true); $b = reset($j["stable"]); echo $b["current"];')

if [ "$VERSION" != "$VJSON" ]; then
	echo "composer.json ($VERSION) et version.json ($VJSON) ne correspondent pas." >&2
	exit 1
fi
if ! grep -q "^### $VERSION " CHANGELOG.md; then
	echo "CHANGELOG.md ne contient pas d'entrée « ### $VERSION »." >&2
	exit 1
fi
if git rev-parse "v$VERSION" >/dev/null 2>&1; then
	echo "Le tag v$VERSION existe déjà." >&2
	exit 1
fi

find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l > /dev/null
echo "Syntaxe PHP : OK"

git add -A
git commit -m "Portfolio $VERSION" || echo "(rien à valider)"
git tag -a "v$VERSION" -m "Portfolio $VERSION"
git push origin HEAD
git push origin "v$VERSION"
echo "v$VERSION poussée : la Release GitHub va être créée par l'action « Publication »."
