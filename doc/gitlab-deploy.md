**[Zpět](../Readme.md)**

# Gitlab automatické nasazení [deploy]

## Konfigurace repozitáře
Nastavte větev určenou k automatickému nasazení:
- chráněná `Settings -> Repository -> Branch defaults`
- výchozí `Settings -> Repository -> Protected branches`

Nastavte v proměnné projektu `Settings -> CI/CD -> Variables`:
- `COMPOSER_TOKEN`
	- Typ: `Variable`
	- Přístupový token pro Gitlab repozitáře `xx`
- `DEPLOY_KEY`
	- Typ: `File`
	- SSH privátní klíč. Musí být ukončeno prázdným řádkem.
- `DEPLOY_HOST`
	- Typ: `Variable`
	- SSH host `demo.cz@server.cz`
- `DEPLOY_PATH`
	- Typ: `Variable`
	- Cesta do root složky projektu relativní `www/demo.cz/` nebo absolutní `/var/www/demo.cz/data/www/demo.cz/`
- `DEPLOY_RESET`
	- Typ: `Variable`
	- Pokud tato proměnná existuje spustí se reset databáze

## Konfigurace SSH
1. Vytvořte dvojici SSH klíčů bez hesla
	```shell
	ssh-keygen -t rsa -b 2048 -C "Demo Deploy"
	```
1. Zkopírujte veřejný klíč na server
	```shell
	ssh-copy-id -i .ssh/id_rsa demo.cz@server.cz

	# případně s portem
	ssh-copy-id -i .ssh/id_rsa -p 22 demo.cz@server.cz
	```
1. Otestujte připojení
	```shell
	ssh -o "IdentitiesOnly=yes" -o "PreferredAuthentications=publickey" -i .ssh/id_rsa.pub demo.cz@server.cz

	# případně s portem
	ssh -p 22 -o "IdentitiesOnly=yes" -o "PreferredAuthentications=publickey" -i .ssh/id_rsa.pub demo.cz@server.cz
	```

## PHP v prostředí SSH
V případě, že se v SSH spouští jiná verze PHP než je vyžadována aplikací.
1. Zjistěte cesty k dostupným verzím PHP
	```shell
	whereis php
	# php: /usr/bin/php /usr/lib64/php /usr/share/php /opt/php83/bin/php /usr/share/man/man1/php.1.gz
	```
1. V domovském adresáři vytvořte soubory `.bashrc` a `.bash_profile` s definicí cesty k PHP
	```shell
	echo 'export PATH=/opt/php83/bin:$PATH' >> $HOME/.bashrc
	echo 'export PATH=/opt/php83/bin:$PATH' >> $HOME/.bash_profile
	```