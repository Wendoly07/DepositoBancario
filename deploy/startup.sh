#!/bin/bash
# Comando de inicio de Azure App Service:
#   bash /home/site/wwwroot/deploy/startup.sh

# Carpetas persistentes FUERA de wwwroot (no se borran al redesplegar)
mkdir -p /home/data/transferencias/data \
         /home/data/transferencias/uploads \
         /home/data/transferencias/sessions
chmod 700 /home/data/transferencias/sessions

# Configuración de Nginx del proyecto
cp /home/site/wwwroot/deploy/nginx/default /etc/nginx/sites-available/default
service nginx reload
