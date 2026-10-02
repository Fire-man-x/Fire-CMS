Jak spustit:

```bash
docker exec -it garage_s3 /garage status
```

Přiřaďte uzlu roli a kapacitu (např. kapacitu 100GB a zónu lokal):
(Nahraďte VASE_ID_UZLU kódem z předchozího příkazu)bash
```bash
docker exec -it garage_s3 /garage layout assign VASE_ID_UZLU --capacity 100M --zone lokal
docker exec -it garage_s3 /garage layout apply --version 1
```

Vytvoření nového klíče (uživatele):
```bash
docker exec -it garage_s3 /garage key create muj-novy-klic
```
Tento příkaz vám v terminálu vypíše access_key_id a secret_access_key.
Vytvoření nového bucketu (úložiště):
```bash
docker exec -it garage_s3 /garage bucket create muj-bucket
```

Přiřazení oprávnění ke čtení i zápisu klíče k bucketu:
```bash
docker exec -it garage_s3 /garage bucket allow muj-bucket --read --write --key muj-novy-klic
docker exec -it garage_s3 /garage bucket website --allow muj-bucket
```