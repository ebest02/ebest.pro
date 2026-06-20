# Génération automatique des vignettes

Ce script permet de générer automatiquement toutes les vignettes des sites listés dans `data/sites.json`.

## Utilisation manuelle

```bash
# Générer les vignettes (respecte le cache de 24h)
php bin/generate-screenshots.php

# Forcer la régénération de toutes les vignettes
php bin/generate-screenshots.php --force
```

## Installation de la tâche cron

### Méthode 1 : Script d'installation automatique

```bash
# Installation avec fréquence quotidienne (par défaut)
./bin/install-cron.sh daily

# Installation avec fréquence horaire
./bin/install-cron.sh hourly

# Installation avec fréquence toutes les 6 heures
./bin/install-cron.sh 6hours
```

Le script vous donnera la commande à exécuter pour ajouter la tâche cron.

### Méthode 2 : Installation manuelle

1. Ouvrir le crontab :
   ```bash
   crontab -e
   ```

2. Ajouter une des lignes suivantes selon la fréquence souhaitée :

   **Tous les jours à 2h du matin (recommandé) :**
   ```
   0 2 * * * /usr/bin/php /var/www/ebest.pro/web/bin/generate-screenshots.php >> /var/www/ebest.pro/web/data/cron-screenshots.log 2>&1
   ```

   **Toutes les 6 heures :**
   ```
   0 */6 * * * /usr/bin/php /var/www/ebest.pro/web/bin/generate-screenshots.php >> /var/www/ebest.pro/web/data/cron-screenshots.log 2>&1
   ```

   **Toutes les heures :**
   ```
   0 * * * * /usr/bin/php /var/www/ebest.pro/web/bin/generate-screenshots.php >> /var/www/ebest.pro/web/data/cron-screenshots.log 2>&1
   ```

   **Tous les jours à minuit (avec force) :**
   ```
   0 0 * * * /usr/bin/php /var/www/ebest.pro/web/bin/generate-screenshots.php --force >> /var/www/ebest.pro/web/data/cron-screenshots.log 2>&1
   ```

3. Sauvegarder et quitter l'éditeur

## Vérification

Pour vérifier que la tâche cron est bien installée :

```bash
crontab -l | grep generate-screenshots
```

Pour voir les logs de la dernière exécution :

```bash
tail -f /var/www/ebest.pro/web/data/cron-screenshots.log
```

## Fonctionnement

- Le script lit la liste des sites depuis `data/sites.json`
- Pour chaque site, il génère une vignette si elle n'existe pas ou si elle a plus de 24h
- Les vignettes sont sauvegardées dans `data/screenshots/`
- Le script affiche un résumé avec le nombre de succès, d'erreurs et de vignettes en cache
- Les logs sont écrits dans `data/cron-screenshots.log`

## Options

- `--force` : Force la régénération de toutes les vignettes, même si elles existent déjà
