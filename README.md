<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>


## Install
```
git clone https://github.com/piccard21/lakshmi.git
cd lakshmi

docker-compose up -d --build
docker-compose exec php composer install
```
docker-compose run --rm node yarn install
docker-compose run --rm node npm run prod
docker-compose down -v
docker-compose up -d --build


## Bootstrap
[see](https://www.positronx.io/how-to-properly-install-and-use-bootstrap-in-laravel/)
```
docker-compose exec php composer require laravel/ui
docker-compose exec php php artisan ui bootstrap --auth
(docker-compose exec php php artisan ui vue)
docker-compose run --rm node npm install
docker-compose run --rm node npm run dev
docker-compose run --rm node npm run watch
docker-compose exec php php artisan migrate
```


## Bootstrap-Vue
[see](https://www.solmediaco.com/blog/how-to-include-bootstrapvue-in-a-laravel-project)
```
docker-compose exec php composer require laravel/ui
docker-compose exec php php artisan ui vue
docker-compose run --rm node npm install
docker-compose run --rm node npm run dev
docker-compose run --rm node npm install bootstrap-vue
```


## Laravel Cmds
```
# create command 
docker-compose exec php php artisan make:command CronTest

# what crons do I have
docker-compose exec php php artisan schedule:list

# db mgration
docker-compose exec php php artisan migrate

# generate a  migration when you generate the model
docker-compose exec php php artisan make:model Flight --migration

# provider
docker-compose exec php php artisan make:provider WhateverProvider

# api-controller
docker-compose exec php php artisan make:controller API/CategoryController --api

# log into container 
docker-compose exec db sh
```



