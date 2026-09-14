**[Zpět](../Readme.md)**

# Aktualizace projektů s Gitlab automatickým nasazením [deploy]

## Projekty vycházející z Fire CMS

1. `git remote add fire-cms git@github.com:Fire-man-x/Fire-CMS.git` - stačí do projektu přidat vždy pouze 1×
2. `git checkout master`
3. `git pull`
4. `git fetch fire-cms`
5. `git merge fire-cms/master`
6. provést vlastní úpravy a změny, pak udělat `git commit`
7. `git push`


# Vytvoření projektu

## Projekty vycházející z Fire CMS

1. `git clone [ssh nově založeného webu]`
2. `git remote add fire-cms git@github.com:Fire-man-x/Fire-CMS.git` - stačí do projektu přidat vždy pouze 1×
3. `git checkout master`
4. `git fetch fire-cms`
5. `git rebase fire-cms/master`
6. `git push --force`
