#!/bin/bash

# Script pour installer la tâche cron de génération des vignettes
# Usage: ./bin/install-cron.sh [fréquence]
# Fréquences disponibles: hourly, daily, 6hours
# Par défaut: daily

FREQUENCY=${1:-daily}
SCRIPT_PATH="/var/www/ebest.pro/web/bin/generate-screenshots.php"
LOG_PATH="/var/www/ebest.pro/web/data/cron-screenshots.log"
PHP_PATH=$(which php)

if [ -z "$PHP_PATH" ]; then
    echo "Erreur: PHP CLI n'est pas trouvé dans le PATH"
    exit 1
fi

# Créer le répertoire de logs si nécessaire
mkdir -p "$(dirname "$LOG_PATH")"

# Définir la fréquence cron
case $FREQUENCY in
    hourly)
        CRON_SCHEDULE="0 * * * *"
        DESCRIPTION="toutes les heures"
        ;;
    6hours)
        CRON_SCHEDULE="0 */6 * * *"
        DESCRIPTION="toutes les 6 heures"
        ;;
    daily|*)
        CRON_SCHEDULE="0 2 * * *"
        DESCRIPTION="tous les jours à 2h du matin"
        ;;
esac

CRON_LINE="$CRON_SCHEDULE $PHP_PATH $SCRIPT_PATH >> $LOG_PATH 2>&1"

echo "=== Installation de la tâche cron ==="
echo "Fréquence: $DESCRIPTION"
echo "Commande: $CRON_LINE"
echo ""
echo "Pour installer, exécutez:"
echo "  crontab -e"
echo ""
echo "Puis ajoutez cette ligne:"
echo "$CRON_LINE"
echo ""
echo "Ou exécutez cette commande pour l'ajouter automatiquement:"
echo "(crontab -l 2>/dev/null; echo \"$CRON_LINE\") | crontab -"
