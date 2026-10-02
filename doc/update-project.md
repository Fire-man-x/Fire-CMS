**[Zpět](../Readme.md)**

# Aktualizace projektů z jádra Fire CMS

## Projekty vycházející z Fire CMS

1. `git remote add fire-cms git@github.com:Fire-man-x/Fire-CMS.git` - stačí do projektu přidat vždy pouze 1×
2. `git checkout master`
3. `git pull`
4. `git fetch fire-cms`
5. `git merge fire-cms/master`
6. provést vlastní úpravy a změny, pak udělat `git commit`
7. `git push` - push do nasazovací větve projekt rovnou nasadí (viz [GitHub nasazení](github-deploy.md))

Vlastní `.github/workflows/deploy.yml` projektu merge jádra nemění. Pokud se v jádru změnil vzor
`.github/workflows/deploy.yml.dist`, porovnejte ho s `deploy.yml` projektu ručně.


# Vytvoření projektu

## Projekty vycházející z Fire CMS

1. `git clone [ssh nově založeného webu]`
2. `git remote add fire-cms git@github.com:Fire-man-x/Fire-CMS.git` - stačí do projektu přidat vždy pouze 1×
3. `git checkout master`
4. `git fetch fire-cms`
5. `git rebase fire-cms/master`
6. `git push --force`
7. nasazení: `cp .github/workflows/deploy.yml.dist .github/workflows/deploy.yml` a nastavení podle
   [GitHub nasazení](github-deploy.md)
8. dokumentace projektu, pokud je potřeba: `theme/docs/` s hlavním souborem `theme/docs/README.md`
   (`docs/` a `doc/` patří jádru)
