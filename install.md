# Magento Docker & Vue Storefront Installation Guide

## Prerequisites

- Docker & Docker Compose
- Node.js 16+ and npm/yarn
- Git
- Free ports: 80, 3306, 9200, 9300 (for Elasticsearch), 6379 (for Redis)

## Part 1: Magento Docker Setup

### 1. Clone Magento Docker Repository

bash
git clone https://github.com/markshust/docker-magento
cd docker-magento

### 2. Create Magento Project

bash
bin/download 2.4.6 # Replace with your desired Magento version
bin/setup

### 3. Docker Environment Configuration

- Default URLs:
  - Magento: http://localhost
  - phpMyAdmin: http://localhost:8080
- Default credentials:
  - Magento Admin: admin/magento2
  - Database: magento/magento/magento

### 4. Useful Docker Commands

bash
bin/start # Start containers
bin/stop # Stop containers
bin/restart # Restart containers
bin/bash # SSH into PHP container
bin/cli # Run Magento CLI commands

## Part 2: Vue Storefront Setup

### 1. Install Vue Storefront

bash
git clone https://github.com/vuestorefront/vue-storefront
cd vue-storefront
yarn install

### 2. Configure Vue Storefront

bash
cp config/default.json config/local.json
Edit `config/local.json`:

json
{
"magento2": {
"url": "http://localhost",
"imgUrl": "http://localhost/media/catalog/product",
"assetPath": "http://localhost",
"magentoUserName": "admin",
"magentoUserPassword": "magento2",
"httpUserName": "",
"httpUserPassword": ""
}
}

### 3. Configure Magento API

In Magento Admin:

1. Navigate to Stores → Configuration → Services → Magento Web API
2. Set OAuth security settings
3. Create Integration:
   - System → Integrations
   - Add New Integration
   - Grant required permissions

### 4. Install Magento 2 Integration Module

bash
cd magento-root
composer require vue-storefront/magento2-vsbridge-indexer
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

### 5. Start Vue Storefront

bash
yarn dev

## Configuration Steps

### 1. Magento 2 API Configuration

- Enable all required APIs in Magento Admin
- Configure CORS in `app/etc/env.php`:

php
'http_cache_hosts' => [
[
'host' => 'varnish',
'port' => '80'
]
],
'system' => [
'full_page_cache' => [
'varnish_hosts' => 'varnish:80'
]
]

### 2. Elasticsearch Configuration

bash
bin/magento config:set catalog/search/engine elasticsearch7
bin/magento config:set catalog/search/elasticsearch7_server_hostname elasticsearch
bin/magento config:set catalog/search/elasticsearch7_server_port 9200

### 3. Redis Configuration

bash
bin/magento setup:config:set --cache-backend=redis --cache-backend-redis-server=redis

## Common Tasks

### Reindex Magento Data to Vue Storefront

bash
bin/cli vsbridge:reindex

### Clear Caches

bash
Magento
bin/cli cache:flush
Vue Storefront
yarn cache:clear

### Update Dependencies

bash
Magento
bin/composer update
Vue Storefront
yarn upgrade

## Troubleshooting

### Common Issues

1. Docker Sync Issues

   - Solution: `bin/docker-sync-restart`

2. Elasticsearch Connection Issues

   - Check elasticsearch container: `docker-compose ps`
   - Verify elasticsearch configuration in Magento

3. Vue Storefront API Connection
   - Verify API credentials
   - Check CORS settings
   - Validate integration tokens

## Development Workflow

### Local Development

1. Start environment:

bash
bin/start
cd vue-storefront
yarn dev

2. Access URLs:

- Magento Admin: http://localhost/admin
- Vue Storefront: http://localhost:3000
- Vue Storefront API: http://localhost:8080

### Building for Production

bash
Vue Storefront
yarn build

## Additional Resources

- [Docker Magento Documentation](https://github.com/markshust/docker-magento)
- [Vue Storefront Documentation](https://docs.vuestorefront.io/v1/)
- [Magento 2 Integration Module](https://github.com/vuestorefront/magento2/tree/main)
- [Vue Storefront Middleware](https://github.com/vuestorefront/middleware)
- [Magento 2 Installation Setup](https://docs.alokai.com/magento/installation-setup/installation.html)
- [Magento 2 API Documentation](https://devdocs.magento.com/guides/v2.4/get-started/bk-get-started-api.html)
